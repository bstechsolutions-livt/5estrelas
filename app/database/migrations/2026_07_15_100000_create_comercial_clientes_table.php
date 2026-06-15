<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Clientes. Entidade que agrega propostas, contratos, postos e valor mensal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');                                    // razão social ou nome fantasia
            $table->string('contato_nome')->nullable();                // pessoa de contato
            $table->string('contato_email')->nullable();
            $table->string('contato_telefone')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('situacao')->default('ativo');              // ativo | inativo | prospecto
            $table->decimal('valor_mensal', 15, 2)->default(0);       // totalizador
            $table->integer('total_colaboradores')->default(0);
            $table->integer('total_postos')->default(0);
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_clientes');
    }
};
