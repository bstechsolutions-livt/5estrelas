<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('senior_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('cod_emp');
            $table->unsignedInteger('cod_for');
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->json('senior_raw')->nullable();
            $table->timestamp('senior_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['cod_emp', 'cod_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senior_suppliers');
    }
};
