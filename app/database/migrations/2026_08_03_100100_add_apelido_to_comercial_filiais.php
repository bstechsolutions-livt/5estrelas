<?php

use App\Models\Comercial\Filial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adiciona apelido em instalações que já tinham bs_comercial_filiais antes do campo existir na create. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_comercial_filiais') || Schema::hasColumn('bs_comercial_filiais', 'apelido')) {
            return;
        }

        Schema::table('bs_comercial_filiais', function (Blueprint $table) {
            $table->string('apelido', 100)->nullable()->after('fantasia');
        });

        Filial::query()->each(function (Filial $filial) {
            if (filled($filial->apelido)) {
                return;
            }
            $filial->apelido = Filial::gerarApelido($filial->nome, $filial->fantasia, $filial->tag);
            $filial->saveQuietly();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bs_comercial_filiais', 'apelido')) {
            Schema::table('bs_comercial_filiais', function (Blueprint $table) {
                $table->dropColumn('apelido');
            });
        }
    }
};
