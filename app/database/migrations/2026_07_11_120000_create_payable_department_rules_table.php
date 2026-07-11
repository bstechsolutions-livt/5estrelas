<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_department_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('codccu')->default('[]');
            $table->json('description_patterns')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_department_rules');
    }
};
