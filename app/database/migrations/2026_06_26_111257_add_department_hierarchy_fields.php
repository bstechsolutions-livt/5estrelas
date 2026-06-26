<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura organizacional para fluxo de aprovação multinível e @menções.
 *
 * - departments ganha: gestor, diretor, área (pra trilha de aprovação)
 * - users ganha: department_id (a qual departamento pertence)
 * - payables ganha: department_id (área de origem do título)
 * - mentions: tabela pra @menções em comentários (notificação + visibilidade)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Campos de hierarquia no departamento
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'area_key')) {
                $table->string('area_key', 30)->nullable()->after('is_active'); // matriz, comercial, licitacao, dp_rh, juridico
            }
            if (!Schema::hasColumn('departments', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->after('area_key')->constrained('users')->nullOnDelete(); // gestor/head
            }
            if (!Schema::hasColumn('departments', 'director_id')) {
                $table->foreignId('director_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete(); // diretor
            }
        });

        // Departamento do usuário
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('is_active')->constrained('departments')->nullOnDelete();
            }
        });

        // Departamento de origem do título (pra determinar trilha de aprovação)
        Schema::table('payables', function (Blueprint $table) {
            if (!Schema::hasColumn('payables', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('branch_id')->constrained('departments')->nullOnDelete();
            }
        });

        // @Menções em comentários (notificação + visibilidade)
        Schema::create('comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_comment_id')->constrained('payable_comments')->cascadeOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('read')->default(false); // se o mencionado já viu
            $table->timestamps();

            $table->unique(['payable_comment_id', 'mentioned_user_id']);
            $table->index(['mentioned_user_id', 'read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');

        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('director_id');
            $table->dropColumn('area_key');
        });
    }
};
