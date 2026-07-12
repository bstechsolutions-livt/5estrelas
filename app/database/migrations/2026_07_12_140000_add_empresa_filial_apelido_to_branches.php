<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedSmallInteger('cod_emp')->nullable()->after('code');
            $table->unsignedSmallInteger('cod_fil')->nullable()->after('cod_emp');
            $table->string('apelido', 100)->nullable()->after('name');

            $table->index('cod_emp');
            $table->index(['cod_emp', 'cod_fil']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['cod_emp', 'cod_fil']);
            $table->dropIndex(['cod_emp']);
            $table->dropColumn(['cod_emp', 'cod_fil', 'apelido']);
        });
    }
};
