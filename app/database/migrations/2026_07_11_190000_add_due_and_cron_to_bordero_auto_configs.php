<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bordero_auto_configs')) {
            return;
        }

        Schema::table('bordero_auto_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('bordero_auto_configs', 'due_grouping')) {
                $table->string('due_grouping', 20)->default('none')->after('min_titles_per_group');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'max_due_span_days')) {
                $table->unsignedSmallInteger('max_due_span_days')->default(7)->after('due_grouping');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'eligibility_mode')) {
                $table->string('eligibility_mode', 30)->default('all_pending')->after('max_due_span_days');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'eligibility_due_days')) {
                $table->unsignedSmallInteger('eligibility_due_days')->nullable()->after('eligibility_mode');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'cron_enabled')) {
                $table->boolean('cron_enabled')->default(true)->after('eligibility_due_days');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'last_cron_run_at')) {
                $table->timestamp('last_cron_run_at')->nullable()->after('cron_enabled');
            }
            if (! Schema::hasColumn('bordero_auto_configs', 'last_cron_created_count')) {
                $table->unsignedInteger('last_cron_created_count')->nullable()->after('last_cron_run_at');
            }
        });

        if (Schema::hasColumn('bordero_auto_configs', 'group_by_due_week')) {
            Schema::table('bordero_auto_configs', function (Blueprint $table) {
                $table->dropColumn('group_by_due_week');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bordero_auto_configs')) {
            return;
        }

        Schema::table('bordero_auto_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('bordero_auto_configs', 'group_by_due_week')) {
                $table->boolean('group_by_due_week')->default(false);
            }
            foreach ([
                'due_grouping',
                'max_due_span_days',
                'eligibility_mode',
                'eligibility_due_days',
                'cron_enabled',
                'last_cron_run_at',
                'last_cron_created_count',
            ] as $col) {
                if (Schema::hasColumn('bordero_auto_configs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
