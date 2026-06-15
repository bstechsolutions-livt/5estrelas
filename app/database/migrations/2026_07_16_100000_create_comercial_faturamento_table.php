<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_faturamento', function (Blueprint $table) {
            $table->id();
            $table->integer('ano');
            $table->string('local_nome');
            $table->foreignId('cliente_id')->nullable()->constrained('bs_comercial_clientes')->nullOnDelete();
            $table->decimal('jan', 14, 2)->default(0);
            $table->decimal('fev', 14, 2)->default(0);
            $table->decimal('mar', 14, 2)->default(0);
            $table->decimal('abr', 14, 2)->default(0);
            $table->decimal('mai', 14, 2)->default(0);
            $table->decimal('jun', 14, 2)->default(0);
            $table->decimal('jul', 14, 2)->default(0);
            $table->decimal('ago', 14, 2)->default(0);
            $table->decimal('setembro', 14, 2)->default(0);
            $table->decimal('out', 14, 2)->default(0);
            $table->decimal('nov', 14, 2)->default(0);
            $table->decimal('dez', 14, 2)->default(0);
            $table->timestamps();

            $table->index('ano');
            $table->unique(['ano', 'local_nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_faturamento');
    }
};
