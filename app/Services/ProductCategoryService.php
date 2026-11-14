<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Categorias de produto. Plugins usam ensureSystemCategory() + syncSystemCategory() para manter uma categoria própria
 * (ex.: todos os produtos do catálogo de um fornecedor), que o usuário vê e filtra mas não edita.
 */
class ProductCategoryService
{
    public function uniqueSlug(?int $tenantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $n = 2;
        while (ProductCategory::forTenant($tenantId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function ensureSystemCategory(?int $tenantId, string $key, string $name, ?string $color = null): ProductCategory
    {
        $category = ProductCategory::forTenant($tenantId)->where('system_key', $key)->first();
        if ($category) {
            $changes = array_filter(['name' => $category->name !== $name ? $name : null, 'color' => $color && $category->color !== $color ? $color : null]);
            if ($changes) {
                $category->update($changes);
            }

            return $category;
        }

        return ProductCategory::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $this->uniqueSlug($tenantId, $name),
            'color' => $color,
            'system_key' => $key,
            'position' => 0,
        ]);
    }

    /**
     * Deixa na categoria do sistema exatamente estes produtos (do mesmo tenant). Retorna quantos ficaram.
     *
     * @param  iterable<string>  $productIds
     */
    public function syncSystemCategory(?int $tenantId, string $key, string $name, iterable $productIds, ?string $color = null): int
    {
        $category = $this->ensureSystemCategory($tenantId, $key, $name, $color);
        $ids = Product::forTenant($tenantId)
            ->whereIn('id', collect($productIds)->map(fn ($id) => (string) $id)->unique()->values()->all() ?: ['__none__'])
            ->pluck('id')->all();
        $category->products()->sync($ids);

        return count($ids);
    }

    /**
     * Troca as categorias do usuário de um produto, preservando as categorias de sistema (que são dos plugins).
     *
     * @param  array<int|string>  $categoryIds
     */
    public function syncUserCategories(Product $product, array $categoryIds): void
    {
        $userIds = ProductCategory::forTenant($product->tenant_id)
            ->whereNull('system_key')
            ->whereIn('id', array_map('intval', $categoryIds) ?: [0])
            ->pluck('id')->all();
        $systemIds = $product->categories()->whereNotNull('product_categories.system_key')->pluck('product_categories.id')->all();
        $product->categories()->sync(array_merge($systemIds, $userIds));
    }

    /**
     * Categorias do tenant com a contagem de produtos (respeitando a lista de produtos visíveis, quando informada).
     *
     * @param  array<string>|null  $visibleProductIds
     * @return list<array{id: int, name: string, slug: string, color: string|null, system: bool, products_count: int}>
     */
    public function listWithCounts(?int $tenantId, ?array $visibleProductIds = null): array
    {
        $counts = DB::table('category_product')
            ->join('product_categories', 'product_categories.id', '=', 'category_product.product_category_id')
            ->when($tenantId === null, fn ($q) => $q->whereNull('product_categories.tenant_id'), fn ($q) => $q->where('product_categories.tenant_id', $tenantId))
            ->when($visibleProductIds !== null, fn ($q) => $q->whereIn('category_product.product_id', $visibleProductIds ?: ['__none__']))
            ->groupBy('category_product.product_category_id')
            ->selectRaw('category_product.product_category_id as cid, count(*) as total')
            ->pluck('total', 'cid');

        return ProductCategory::forTenant($tenantId)->orderBy('position')->orderBy('name')->get()
            ->map(fn (ProductCategory $c) => $c->toBadge() + ['products_count' => (int) ($counts[$c->id] ?? 0)])
            ->values()->all();
    }
}
