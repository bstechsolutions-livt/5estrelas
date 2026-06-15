<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Views de compatibilidade em CAIXA-BAIXA.
 *
 * O PostgreSQL trata identificadores citados como case-sensitive. O código
 * portado da Biglar (Solicitacoes) referencia algumas tabelas/views ora em
 * MAIÚSCULO ("INTRANET_USUARIO"), ora em minúsculo ("intranet_usuario"),
 * herança do Oracle (case-insensitive). Já temos as versões MAIÚSCULAS; aqui
 * criamos as equivalentes minúsculas apontando para a mesma fonte, para que
 * ambas as grafias funcionem sem reescrever o código.
 *
 * Extra: expõe `telefone` (alias de `fone`) usado pelo ResolveTelefoneWhatsapp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE OR REPLACE VIEW intranet_usuario AS
            SELECT *, fone AS telefone FROM "INTRANET_USUARIO"');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS intranet_usuario');
    }
};
