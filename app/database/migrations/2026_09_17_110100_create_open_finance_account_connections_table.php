<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_finance_account_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('open_finance_payer_id')->nullable()->constrained('open_finance_payers')->nullOnDelete();
            $table->string('environment', 16);
            $table->string('provider_account_hash', 64)->nullable();
            $table->text('openfinance_link')->nullable();
            $table->string('openfinance_id', 64)->nullable();
            $table->string('status', 32);
            $table->boolean('statement_activated')->default(false);
            $table->string('last_error_kind', 32)->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'environment'], 'of_connections_account_env_unique');
            $table->unique(['environment', 'provider_account_hash'], 'of_connections_env_hash_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_finance_account_connections');
    }
};
