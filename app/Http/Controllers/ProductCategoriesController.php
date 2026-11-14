<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use App\Services\TeamAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoriesController extends Controller
{
    public function index(ProductCategoryService $service): Response
    {
        $user = auth()->user();
        $visible = $user->isTeam() ? app(TeamAccessService::class)->allowedProductIdsFor($user) : null;

        return Inertia::render('Produtos/Categorias', [
            'categorias' => $service->listWithCounts($user->tenant_id, $visible),
        ]);
    }

    public function store(Request $request, ProductCategoryService $service)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        ProductCategory::create([
            'tenant_id' => $tenantId,
            'name' => trim($validated['name']),
            'slug' => $service->uniqueSlug($tenantId, $validated['name']),
            'color' => $validated['color'] ?? null,
            'position' => (int) ProductCategory::forTenant($tenantId)->max('position') + 1,
        ]);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, ProductCategory $categoria, ProductCategoryService $service)
    {
        $this->authorizeCategory($categoria);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $categoria->update([
            'name' => trim($validated['name']),
            'slug' => $service->uniqueSlug($categoria->tenant_id, $validated['name'], $categoria->id),
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(ProductCategory $categoria)
    {
        $this->authorizeCategory($categoria);
        $categoria->delete();

        return back()->with('success', 'Categoria excluída. Os produtos continuam, só saem dela.');
    }

    private function authorizeCategory(ProductCategory $categoria): void
    {
        if ((int) $categoria->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(404);
        }
        if ($categoria->isSystem()) {
            abort(403, 'Esta categoria é mantida por um plugin e não pode ser editada.');
        }
    }
}
