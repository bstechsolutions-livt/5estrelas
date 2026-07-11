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

        if (! Schema::hasColumn('bordero_auto_rules', 'group_by')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->json('group_by')->nullable()->after('name');
            });

            DB::table('bordero_auto_rules')->update([
                'group_by' => json_encode(['empresa', 'departamento']),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bordero_auto_rules') && Schema::hasColumn('bordero_auto_rules', 'group_by')) {
            Schema::table('bordero_auto_rules', function (Blueprint $table) {
                $table->dropColumn('group_by');
            });
        }
    }
};
