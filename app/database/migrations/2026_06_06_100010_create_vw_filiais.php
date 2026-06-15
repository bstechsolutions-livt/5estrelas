<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View de compatibilidade para o adaptador App\Models\Filial.
 *
 * O código portado (contratos + solicitacoes) referencia colunas no estilo
 * legado: codigo, razaosocial, fantasia, cgc. No 5 Estrelas a tabela real é
 * `branches` (code, name, cnpj). Antes esses nomes eram apenas accessors, o que
 * quebrava queries que faziam SELECT de colunas especificas ou orderBy nelas
 * (ex.: Filial::orderBy('fantasia')->get(['codigo','fantasia'])).
 *
 * Esta view expõe TODAS as colunas reais de branches MAIS os aliases legados
 * como colunas de verdade, permitindo SELECT/ORDER BY/WHERE diretos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // View de compatibilidade Postgres-only (usa cast ::text).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE OR REPLACE VIEW vw_filiais AS
            SELECT
                b.*,
                COALESCE(b.code, b.id::text) AS codigo,
                b.name  AS razaosocial,
                b.name  AS fantasia,
                b.cnpj  AS cgc
            FROM branches b');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_filiais');
    }
};
