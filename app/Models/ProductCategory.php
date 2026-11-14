<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCategory extends Model
{
    protected $fillable = ['tenant_id', 'name', 'slug', 'color', 'system_key', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product', 'product_category_id', 'product_id')->withTimestamps();
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        return $query->where('tenant_id', $tenantId);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null && $this->system_key !== '';
    }

    /**
     * @return array{id: int, name: string, slug: string, color: string|null, system: bool}
     */
    public function toBadge(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'color' => $this->color, 'system' => $this->isSystem()];
    }
}
