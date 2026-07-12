<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('senior_cod_usu')->nullable()->after('department_id');
            $table->index('senior_cod_usu');
        });

        Schema::table('payables', function (Blueprint $table) {
            $table->unsignedInteger('senior_cod_usu')->nullable()->after('department_id');
            $table->index('senior_cod_usu');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropIndex(['senior_cod_usu']);
            $table->dropColumn('senior_cod_usu');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['senior_cod_usu']);
            $table->dropColumn('senior_cod_usu');
        });
    }
};
