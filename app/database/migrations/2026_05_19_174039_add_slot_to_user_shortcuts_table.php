<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não suporta DROP UNIQUE com índice composto direto, então recriamos a tabela
        Schema::table('user_shortcuts', function (Blueprint $table) {
            $table->string('slot', 20)->default('dashboard')->after('user_id');
        });

        // Drop antiga unique e cria nova com slot
        Schema::table('user_shortcuts', function (Blueprint $table) {
            try {
                $table->dropUnique(['user_id', 'menu_key']);
            } catch (\Throwable $e) {
                // index pode não existir em SQLite com mesmo nome
            }
        });

        Schema::table('user_shortcuts', function (Blueprint $table) {
            $table->unique(['user_id', 'slot', 'menu_key']);
        });
    }

    public function down(): void
    {
        Schema::table('user_shortcuts', function (Blueprint $table) {
            try {
                $table->dropUnique(['user_id', 'slot', 'menu_key']);
            } catch (\Throwable $e) {
            }
            $table->dropColumn('slot');
        });

        Schema::table('user_shortcuts', function (Blueprint $table) {
            $table->unique(['user_id', 'menu_key']);
        });
    }
};
