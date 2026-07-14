<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['approval_steps', 'approval_trails'] as $table) {
            DB::table($table)
                ->where('level_name', 'presid_ncia')
                ->update(['level_name' => 'presidencia']);
        }
    }

    public function down(): void
    {
        // Não reverte — presidencia é o nome canônico.
    }
};
