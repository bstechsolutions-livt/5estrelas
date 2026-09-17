<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_finance_payers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('environment', 16);
            $table->string('cpf_cnpj', 14);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->string('street')->nullable();
            $table->string('address_number', 10)->nullable();
            $table->string('address_complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('provider', 32)->default('tecnospeed');
            $table->boolean('statement_activated')->default(false);
            $table->string('provider_status', 32)->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'cpf_cnpj'], 'of_payers_env_document_unique');
            $table->index(['environment', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_finance_payers');
    }
};
