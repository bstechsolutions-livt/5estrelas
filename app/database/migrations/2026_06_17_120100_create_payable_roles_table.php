<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alçada do Contas a Pagar (Alcada_CP) — Spec contas-pagar-alcada-pagamento.
 *
 * Pivot papel -> usuário (estilo user_permission). É a fonte de verdade de quem
 * executa cada ação do fluxo de pagamento. Nesta spec só o papel `pagador` é
 * consumido por uma ação; `conciliador`/`assinante` nascem aqui para o cadastro
 * ser coeso e passam a ser usados nas Specs 2/3. A tabela cresce na Fase 1 com
 * novos papéis (gerência/diretoria por trilha).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // pagador | conciliador | assinante
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'user_id']); // sem duplicar o mesmo usuário no mesmo papel
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_roles');
    }
};
