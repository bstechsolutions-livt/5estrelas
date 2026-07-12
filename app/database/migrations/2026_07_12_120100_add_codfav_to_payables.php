<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            if (!Schema::hasColumn('payables', 'codfav')) {
                $table->bigInteger('codfav')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            if (Schema::hasColumn('payables', 'codfav')) {
                $table->dropColumn('codfav');
            }
        });
    }
};
