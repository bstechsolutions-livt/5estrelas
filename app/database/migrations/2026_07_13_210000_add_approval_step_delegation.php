<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->foreignId('delegated_to')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by')->nullable()->after('delegated_to')->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable()->after('delegated_by');
            $table->timestamp('delegation_expires_at')->nullable()->after('delegated_at');
            $table->string('delegation_reason', 500)->nullable()->after('delegation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegated_to');
            $table->dropConstrainedForeignId('delegated_by');
            $table->dropColumn(['delegated_at', 'delegation_expires_at', 'delegation_reason']);
        });
    }
};
