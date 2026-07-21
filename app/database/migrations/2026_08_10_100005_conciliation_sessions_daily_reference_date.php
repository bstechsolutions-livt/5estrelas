<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliation_sessions', function (Blueprint $table) {
            $table->date('reference_date')->nullable()->after('bank_account_id');
        });

        foreach (DB::table('conciliation_sessions')->get() as $row) {
            DB::table('conciliation_sessions')
                ->where('id', $row->id)
                ->update([
                    'reference_date' => sprintf('%04d-%02d-01', $row->year, $row->month),
                ]);
        }

        Schema::table('conciliation_sessions', function (Blueprint $table) {
            $table->dropUnique('conciliation_sessions_account_period_unique');
            $table->dropColumn(['year', 'month']);
            $table->unique(['bank_account_id', 'reference_date'], 'conciliation_sessions_account_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conciliation_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
        });

        foreach (DB::table('conciliation_sessions')->get() as $row) {
            $ts = strtotime((string) $row->reference_date);
            DB::table('conciliation_sessions')
                ->where('id', $row->id)
                ->update([
                    'year' => (int) date('Y', $ts),
                    'month' => (int) date('n', $ts),
                ]);
        }

        Schema::table('conciliation_sessions', function (Blueprint $table) {
            $table->dropUnique('conciliation_sessions_account_date_unique');
            $table->dropColumn('reference_date');
            $table->unique(['bank_account_id', 'year', 'month'], 'conciliation_sessions_account_period_unique');
        });
    }
};
