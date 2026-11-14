<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name', 80);
                $table->string('slug', 100);
                $table->string('color', 20)->nullable();
                // Preenchido quando a categoria pertence a um plugin (ex.: "impa-provider"): o plugin mantém os produtos dela.
                $table->string('system_key', 80)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug'], 'product_categories_tenant_slug_unique');
                $table->index(['tenant_id', 'system_key'], 'product_categories_tenant_system_idx');
            });
        }

        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_category_id');
                $table->string('product_id', 36);
                $table->timestamps();

                $table->foreign('product_category_id', 'category_product_category_fk')
                    ->references('id')->on('product_categories')->cascadeOnDelete();
                $table->foreign('product_id', 'category_product_product_fk')
                    ->references('id')->on('products')->cascadeOnDelete();
                $table->unique(['product_category_id', 'product_id'], 'category_product_unique');
                $table->index('product_id', 'category_product_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('product_categories');
    }
};
