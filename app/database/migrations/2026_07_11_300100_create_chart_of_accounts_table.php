<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('account_type', 30); // conta_financeira | centro_custo
            $table->unsignedInteger('codemp')->nullable();
            $table->string('source', 30)->default('derived'); // derived | senior
            $table->json('senior_raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'account_type', 'codemp'], 'chart_of_accounts_unique');
            $table->index('account_type');
            $table->index('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
