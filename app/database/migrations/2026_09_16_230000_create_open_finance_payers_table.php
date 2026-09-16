<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado específico da integração Open Finance (TecnoSpeed) por pagador.
 *
 * Empresas/filiais/CNPJs já existem em bs_comercial_filiais e branches. Esta
 * tabela não duplica endereço/razão social: só guarda o estado remoto
 * (existe na TecnoSpeed, Extrato/Open Finance ativo, última verificação).
 *
 * Não persiste tokensh, token do pagador, CNPJ da SH nem payload bruto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_finance_payers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_filial_id')->nullable()->constrained('bs_comercial_filiais')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('cpf_cnpj', 14);
            $table->string('name');
            $table->string('provider', 32)->default('tecnospeed');
            $table->boolean('statement_activated')->nullable();
            $table->boolean('remote_exists')->nullable();
            $table->string('last_status', 40)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'cpf_cnpj']);
            $table->index('last_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_finance_payers');
    }
};
