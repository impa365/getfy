<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL: alinha colunas product_id ao products.id (varchar/character varying 36).
 * Em installs anteriores, checkout_sessions.product_id ficou bigint e tabelas de
 * afiliados/tracking usaram uuid nativo — quebrando joins (SQLSTATE 42883) no dashboard.
 */
return new class extends Migration
{
    /** @var array<int, array{table: string, column: string, nullable: bool}> */
    private const COLUMNS = [
        ['table' => 'checkout_sessions', 'column' => 'product_id', 'nullable' => false],
        ['table' => 'product_affiliate_programs', 'column' => 'product_id', 'nullable' => false],
        ['table' => 'product_coproducers', 'column' => 'product_id', 'nullable' => false],
        ['table' => 'product_affiliates', 'column' => 'product_id', 'nullable' => false],
        ['table' => 'checkout_field_events', 'column' => 'product_id', 'nullable' => true],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $spec) {
            $this->alignProductIdColumn($spec['table'], $spec['column'], $spec['nullable']);
        }
    }

    public function down(): void
    {
        // Irreversível com segurança (dados já podem ser UUIDs em texto).
    }

    private function alignProductIdColumn(string $table, string $column, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $row = DB::selectOne(
            'SELECT data_type, character_maximum_length
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = ?
               AND column_name = ?',
            [$table, $column]
        );

        if (! $row) {
            return;
        }

        $dataType = strtolower((string) ($row->data_type ?? ''));
        $length = (int) ($row->character_maximum_length ?? 0);
        if (in_array($dataType, ['character varying', 'varchar', 'character', 'text'], true)
            && ($length === 0 || $length >= 36)) {
            return;
        }

        $this->dropForeignKeysOnColumn($table, $column);

        $nullSql = $nullable ? 'NULL' : 'NOT NULL';
        // bigint/uuid → varchar(36); USING cast cobre ambos.
        DB::statement(
            "ALTER TABLE {$table} ALTER COLUMN {$column} TYPE character varying(36) USING {$column}::text"
        );
        if ($nullable) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
        } else {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        $fks = DB::select(
            'SELECT tc.constraint_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             WHERE tc.table_schema = current_schema()
               AND tc.table_name = ?
               AND tc.constraint_type = ?
               AND kcu.column_name = ?',
            [$table, 'FOREIGN KEY', $column]
        );

        foreach ($fks as $fk) {
            $name = (string) ($fk->constraint_name ?? '');
            if ($name === '') {
                continue;
            }
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
    }
};
