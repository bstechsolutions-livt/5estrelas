<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->string('payment_priority', 20)->nullable()->after('status');
            $table->date('payment_sla_date')->nullable()->after('payment_priority');
            $table->foreignId('priority_set_by')->nullable()->after('payment_sla_date')->constrained('users')->nullOnDelete();
            $table->timestamp('priority_set_at')->nullable()->after('priority_set_by');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('priority_set_by');
            $table->dropColumn(['payment_priority', 'payment_sla_date', 'priority_set_at']);
        });
    }
};
