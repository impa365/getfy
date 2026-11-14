<?php

namespace App\Support;

use App\Models\MemberLesson;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Mascara a URL real de mídia (S3/rclone/Drive) da área de membros.
 *
 * O aluno nunca deve receber o endereço do storage upstream no HTML/Inertia/DOM.
 * Em vez disso, entregamos um endereço interno do próprio Getfy
 * (/m/{slug}/aula/{lesson}/media) que só responde para a sessão autenticada
 * com acesso ao produto — servido com suporte a HTTP Range para seek.
 */
class MemberLessonMediaUrl
{
    /** Extensões tratadas como mídia reproduzível (vídeo/áudio) e, portanto, mascaradas. */
    private const MASKABLE_EXTENSIONS = [
        'mp4', 'm4v', 'mov', 'webm', 'mkv', 'ogv', 'avi', 'mpg', 'mpeg',
        'mp3', 'm4a', 'wav', 'ogg', 'oga', 'aac', 'flac',
    ];

    /**
     * A URL aponta para um arquivo de mídia direto (por extensão) que deve ser mascarado?
     */
    public static function isMaskableMediaUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::MASKABLE_EXTENSIONS, true);
    }

    /**
     * É um player/embed de terceiros (YouTube, Vimeo, Drive, etc.) que NÃO deve
     * ser proxiado — o player usa o embed diretamente.
     */
    public static function isEmbedProviderUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ([
            'youtube.com', 'youtu.be', 'youtube-nocookie.com',
            'vimeo.com', 'player.vimeo.com',
            'drive.google.com', 'docs.google.com', 'googleusercontent.com',
            'twitch.tv', 'streamable.com', 'loom.com', 'wistia.com', 'wistia.net',
        ] as $needle) {
            if ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A URL pertence ao nosso próprio storage (allowlist: R2/S3/app) e portanto
     * pode/Deve ser proxiada para esconder a origem.
     */
    public static function isSelfHostedUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        return SafeRemoteUrl::isAllowedHttpUrl($url);
    }

    /**
     * Endereço interno (mesma origem) que o player deve usar no lugar da URL do storage.
     */
    public static function streamUrl(Request $request, Product $product, MemberLesson $lesson): string
    {
        return self::innerUrl($request, $product, 'aula/'.$lesson->id.'/media');
    }

    /**
     * Endereço interno do PDF (proxy já existente) para esconder a origem do arquivo.
     */
    public static function pdfUrl(Request $request, Product $product, MemberLesson $lesson, int $fileIndex): string
    {
        return self::innerUrl($request, $product, 'aula/'.$lesson->id.'/pdf/'.$fileIndex);
    }

    /**
     * Monta uma URL interna da área de membros respeitando o modo de acesso
     * (path `/m/{slug}/...` ou host subdomínio/domínio próprio).
     */
    public static function innerUrl(Request $request, Product $product, string $suffix): string
    {
        $suffix = ltrim($suffix, '/');
        // Host atual (respeita proxies): funciona no modo path, subdomínio e domínio próprio.
        $origin = rtrim($request->getSchemeAndHttpHost(), '/');
        $routeName = $request->route()?->getName() ?? '';

        // Modo host: conteúdo na raiz do host (subdomínio/domínio próprio).
        if (str_ends_with($routeName, '.host')) {
            return $origin.'/'.$suffix;
        }

        $slug = (string) ($request->route('slug') ?? $product->checkout_slug ?? '');

        return $origin.'/m/'.trim($slug, '/').'/'.$suffix;
    }
}
