<?php

namespace App\Services;

use App\Support\SafeRemoteUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega mídia (vídeo/áudio) ao aluno SEM revelar a origem real (S3/rclone/Drive).
 *
 * A URL do storage nunca sai do servidor: aqui lemos o arquivo localmente ou
 * buscamos via HTTP (range) e reescrevemos a resposta para o player, com a URL
 * interna do Getfy no cliente. Suporta HTTP Range para permitir seek/streaming.
 */
class MemberLessonMediaStreamer
{
    public function __construct(private StorageService $storage) {}

    public function respond(Request $request, string $sourceUrl): Response
    {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            abort(404, 'Mídia não encontrada.');
        }

        // 1) Arquivo no disco do tenant (storage local) — serve direto com Range.
        $path = $this->storage->pathFromUrl($sourceUrl);
        if ($path !== null) {
            $absolute = $this->resolveLocalAbsolutePath($path);
            if ($absolute !== null) {
                return $this->localFileResponse($request, $absolute);
            }
        }

        // 2) URL remota (S3/rclone/Drive) — busca server-side e repassa com Range.
        if (! SafeRemoteUrl::isSafePublicStreamUrl($sourceUrl)) {
            abort(403, 'Origem da mídia não permitida.');
        }

        return $this->remoteStreamResponse($request, $sourceUrl);
    }

    /**
     * Resolve o caminho absoluto de um arquivo do storage local, se existir.
     */
    private function resolveLocalAbsolutePath(string $path): ?string
    {
        $candidates = [
            storage_path('app/public/'.ltrim($path, '/')),
            storage_path('app/private/'.ltrim($path, '/')),
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * BinaryFileResponse já trata Range (206), ETag e Last-Modified.
     */
    private function localFileResponse(Request $request, string $absolutePath): Response
    {
        $mime = $this->mimeForPath($absolutePath);

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Inline: nunca forçar download direto.
        $response->headers->set('Content-Disposition', 'inline; filename="'.basename($absolutePath).'"');
        $response->prepare($request);

        return $response;
    }

    /**
     * Repassa a requisição (incluindo Range) para a origem remota e devolve o
     * stream ao cliente, mantendo 206 quando a origem retornar partial content.
     */
    private function remoteStreamResponse(Request $request, string $sourceUrl): StreamedResponse
    {
        $forwardHeaders = [];
        $range = $request->header('Range');
        if (is_string($range) && $range !== '') {
            $forwardHeaders['Range'] = $range;
        }
        $ifRange = $request->header('If-Range');
        if (is_string($ifRange) && $ifRange !== '') {
            $forwardHeaders['If-Range'] = $ifRange;
        }

        /** @var \Illuminate\Http\Client\Response $upstream */
        $upstream = Http::withHeaders($forwardHeaders)
            ->withOptions(['stream' => true])
            ->timeout(0)
            ->connectTimeout(30)
            ->get($sourceUrl);

        $status = $upstream->status();
        if ($status >= 400) {
            abort($status === 404 ? 404 : 502, 'Não foi possível obter a mídia.');
        }

        $contentType = (string) ($upstream->header('Content-Type') ?: $this->mimeForUrl($sourceUrl));

        $passthrough = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ];
        foreach (['Content-Length', 'Content-Range'] as $h) {
            $v = $upstream->header($h);
            if (is_string($v) && $v !== '') {
                $passthrough[$h] = $v;
            }
        }

        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(1024 * 256);
                if (function_exists('flush')) {
                    flush();
                }
            }
        }, $status, $passthrough);
    }

    private function mimeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'ogv' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg', 'oga' => 'audio/ogg',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            default => 'application/octet-stream',
        };
    }

    private function mimeForUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $this->mimeForPath($path);
    }
}
