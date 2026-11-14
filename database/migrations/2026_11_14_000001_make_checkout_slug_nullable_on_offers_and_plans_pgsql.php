<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_02_26_000001 só tornou checkout_slug opcional no MySQL/MariaDB. No PostgreSQL a coluna seguia NOT NULL,
 * e criar oferta extra ou produto de assinatura (que gravam checkout_slug = null) dava erro 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE product_offers ALTER COLUMN checkout_slug DROP NOT NULL');
        DB::statement('ALTER TABLE subscription_plans ALTER COLUMN checkout_slug DROP NOT NULL');
    }

    public function down(): void
    {
    }
};
