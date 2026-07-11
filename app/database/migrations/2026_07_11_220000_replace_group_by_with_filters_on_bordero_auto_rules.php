<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bordero_auto_rules')) {
            return;
        }

        if (! Schema::hasColumn('bordero_auto_rules', 'filters')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->json('filters')->nullable()->after('name');
                $table->string('filter_logic', 3)->default('and')->after('filters');
            });
        }

        if (Schema::hasColumn('bordero_auto_rules', 'group_by')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->dropColumn('group_by');
            });
        }

        DB::table('bordero_auto_rules')->whereNull('filters')->update(['filters' => '[]']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('bordero_auto_rules')) {
            return;
        }

        if (! Schema::hasColumn('bordero_auto_rules', 'group_by')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->json('group_by')->nullable();
            });
        }

        if (Schema::hasColumn('bordero_auto_rules', 'filters')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->dropColumn(['filters', 'filter_logic']);
            });
        }
    }
};
