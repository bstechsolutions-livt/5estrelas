<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona a FK payable_allocation_lines.matched_bank_transaction_id ->
 * bank_transactions.id DEPOIS que bank_transactions existe.
 *
 * Em bancos que já rodaram a migration original (2026_07_12_140000) quando ela
 * ainda criava a FK inline, a constraint já existe: esta migration detecta isso
 * e não faz nada. Em bancos novos, a coluna é criada sem FK e a constraint é
 * adicionada aqui, na ordem correta.
 *
 * O SQLite (usado nos testes) não suporta adicionar FK a tabela existente via
 * ALTER e tolera referências futuras na criação, então é ignorado aqui.
 */
return new class extends Migration
{
    private string $constraintName = 'payable_allocation_lines_matched_bank_transaction_id_foreign';

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('payable_allocation_lines') || ! Schema::hasTable('bank_transactions')) {
            return;
        }

        if ($this->constraintExists()) {
            return;
        }

        Schema::table('payable_allocation_lines', function (Blueprint $table) {
            $table->foreign('matched_bank_transaction_id')
                ->references('id')->on('bank_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('payable_allocation_lines') && $this->constraintExists()) {
            Schema::table('payable_allocation_lines', function (Blueprint $table) {
                $table->dropForeign($this->constraintName);
            });
        }
    }

    private function constraintExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?',
            [$this->constraintName]
        ) !== null;
    }
};
