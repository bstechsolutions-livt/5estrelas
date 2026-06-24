<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow de aprovação multinível (Fluxo de Aprovação e Pagamento v3.0).
 *
 * Cada payable enviado para aprovação gera N steps (1 por nível do fluxo).
 * Os steps são resolvidos em ordem (order). Ao aprovar o último, o payable
 * avança para o próximo status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order'); // 1,2,3,4,5...
            $table->string('level_name'); // departamento, gerencia, diretoria, financeiro, presidencia
            $table->string('status', 20)->default('pendente'); // pendente, aprovado, reprovado
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // quem deve aprovar
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete(); // quem aprovou/reprovou
            $table->timestamp('resolved_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['payable_id', 'order']);
            $table->index(['assigned_to', 'status']);
        });

        // Configuração de trilhas de aprovação por área
        Schema::create('approval_trails', function (Blueprint $table) {
            $table->id();
            $table->string('area'); // matriz, comercial, licitacao, dp_rh, juridico
            $table->unsignedTinyInteger('order'); // nível dentro da trilha
            $table->string('level_name'); // departamento, gerencia, diretoria, financeiro, presidencia
            $table->string('role_label'); // "Gerência Operacional", "Diretoria Administrativa", etc.
            $table->foreignId('default_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_trails');
    }
};
