<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — detalhamento dos Encargos Sociais (IN 05), grupos A/B/C/D.
 * O total (somatório) é o percentual aplicado na composição de custos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_encargos', function (Blueprint $table) {
            $table->id();
            $table->string('grupo', 1);              // A, B, C, D
            $table->string('codigo')->nullable();    // a01, b02...
            $table->string('label');
            $table->decimal('percentual', 8, 4)->default(0);
            $table->integer('ordem')->default(0);
            $table->timestamps();
            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_encargos');
    }
};
