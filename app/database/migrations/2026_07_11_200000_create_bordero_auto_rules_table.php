<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bordero_auto_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('min_titles_per_group')->default(2);
            $table->string('due_grouping', 20)->default('none');
            $table->unsignedSmallInteger('max_due_span_days')->default(7);
            $table->string('eligibility_mode', 30)->default('all_pending');
            $table->unsignedSmallInteger('eligibility_due_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_applied_at')->nullable();
            $table->unsignedInteger('last_applied_count')->nullable();
            $table->timestamp('last_cron_at')->nullable();
            $table->unsignedInteger('last_cron_count')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('bordero_auto_configs')) {
            $config = DB::table('bordero_auto_configs')->orderBy('id')->first();
            if ($config) {
                DB::table('bordero_auto_rules')->insert([
                    'name' => 'Regra padrão',
                    'is_active' => (bool) ($config->cron_enabled ?? true),
                    'min_titles_per_group' => $config->min_titles_per_group ?? 2,
                    'due_grouping' => $config->due_grouping ?? 'none',
                    'max_due_span_days' => $config->max_due_span_days ?? 7,
                    'eligibility_mode' => $config->eligibility_mode ?? 'all_pending',
                    'eligibility_due_days' => $config->eligibility_due_days,
                    'last_cron_at' => $config->last_cron_run_at ?? null,
                    'last_cron_count' => $config->last_cron_created_count ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::drop('bordero_auto_configs');
        }

        if (Schema::hasTable('borderos') && ! Schema::hasColumn('borderos', 'auto_rule_id')) {
            Schema::table('borderos', function (Blueprint $table) {
                $table->foreignId('auto_rule_id')->nullable()->after('created_by')
                    ->constrained('bordero_auto_rules')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('borderos') && Schema::hasColumn('borderos', 'auto_rule_id')) {
            Schema::table('borderos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('auto_rule_id');
            });
        }

        Schema::dropIfExists('bordero_auto_rules');

        if (! Schema::hasTable('bordero_auto_configs')) {
            Schema::create('bordero_auto_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('min_titles_per_group')->default(2);
                $table->string('due_grouping', 20)->default('none');
                $table->unsignedSmallInteger('max_due_span_days')->default(7);
                $table->string('eligibility_mode', 30)->default('all_pending');
                $table->unsignedSmallInteger('eligibility_due_days')->nullable();
                $table->boolean('cron_enabled')->default(true);
                $table->timestamp('last_cron_run_at')->nullable();
                $table->unsignedInteger('last_cron_created_count')->nullable();
                $table->timestamps();
            });
        }
    }
};
