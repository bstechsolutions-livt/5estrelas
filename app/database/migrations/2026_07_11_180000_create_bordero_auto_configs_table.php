<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('bordero_auto_configs');
    }
};
