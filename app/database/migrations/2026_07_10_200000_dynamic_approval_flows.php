<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flow_areas', function (Blueprint $table) {
            $table->string('area', 50)->primary();
            $table->string('label', 120);
            $table->timestamps();
        });

        Schema::table('approval_trails', function (Blueprint $table) {
            $table->string('approver_type', 30)->nullable()->after('level_name');
            $table->foreignId('approver_department_id')->nullable()->after('default_user_id')
                ->constrained('departments')->nullOnDelete();
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->string('role_label', 120)->nullable()->after('level_name');
            $table->string('approver_type', 30)->nullable()->after('role_label');
            $table->unsignedBigInteger('approver_department_id')->nullable()->after('approver_type');
        });

        $this->backfillApproverTypes();
        $this->seedAreaLabels();
        $this->stripNamesFromRoleLabels();
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn(['role_label', 'approver_type', 'approver_department_id']);
        });

        Schema::table('approval_trails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_department_id');
            $table->dropColumn('approver_type');
        });

        Schema::dropIfExists('approval_flow_areas');
    }

    private function backfillApproverTypes(): void
    {
        $map = [
            'departamento' => 'gestor_depto',
            'gerencia' => 'usuario',
            'diretoria' => 'diretor_depto',
            'financeiro' => 'dept_financeiro',
            'presidencia' => 'usuario',
        ];

        foreach ($map as $levelName => $type) {
            DB::table('approval_trails')
                ->where('level_name', $levelName)
                ->update(['approver_type' => $type]);
        }
    }

    private function seedAreaLabels(): void
    {
        $labels = [
            'matriz' => 'Matriz',
            'filiais' => 'Filiais',
            'compras' => 'Compras',
            'modernizacao' => 'Modernização',
            'comercial' => 'Comercial / Faturamento / Marketing',
            'licitacao' => 'Licitação',
            'dp_rh' => 'DP / RH',
            'juridico' => 'Jurídico',
            'financeiro' => 'Financeiro',
            'baluarte' => 'Baluarte (Matriz + Comercial)',
            'multi_star' => 'Multi / Star (pré-aprovação Luiz Farias)',
        ];

        $now = now();
        foreach ($labels as $area => $label) {
            if (DB::table('approval_trails')->where('area', $area)->exists()) {
                DB::table('approval_flow_areas')->insertOrIgnore([
                    'area' => $area,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function stripNamesFromRoleLabels(): void
    {
        $replacements = [
            'Gerência Operacional (Erismar)' => 'Gerência Operacional',
            'Gerência de Filiais (Cilas)' => 'Gerência de Filiais',
            'Head Modernização (Matheus Xavier)' => 'Head Modernização',
            'Gerência Comercial (Leiliane)' => 'Gerência Comercial',
            'Diretoria Comercial (Ana Paula)' => 'Diretoria Comercial',
            'Gerência Licitação (Letícia)' => 'Gerência Licitação',
            'Diretoria (Luiz Farias)' => 'Diretoria',
            'Head DP/RH (Silene)' => 'Head DP/RH',
            'Head Jurídico (Dra. Alexyxandra)' => 'Head Jurídico',
            'Diretoria (Dionei Eurich)' => 'Diretoria',
            'Presidência (Leonardo Prudente)' => 'Presidência',
            'Financeiro (auditoria)' => 'Financeiro (auditoria)',
        ];

        foreach ($replacements as $from => $to) {
            DB::table('approval_trails')->where('role_label', $from)->update(['role_label' => $to]);
        }
    }
};
