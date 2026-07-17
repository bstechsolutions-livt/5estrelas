<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('senior_codemp')->nullable();
            $table->unsignedInteger('senior_codfil')->nullable();
            $table->string('senior_num_cco', 30)->nullable();
            $table->string('senior_descricao', 120)->nullable();

            $table->string('bank_code', 10)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('agency', 20)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('account_digit', 5)->nullable();

            $table->timestamp('imported_from_senior_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['senior_codemp', 'senior_num_cco'], 'bank_accounts_senior_unique');
            $table->index(['is_active', 'name']);
            $table->index(['bank_code', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
