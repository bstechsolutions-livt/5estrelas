<?php

namespace Database\Seeders;

use App\Models\ApprovalFlowArea;
use App\Models\ApprovalTrail;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Trilhas de aprovação conforme organograma v3.0.
 * Rótulos sem nomes de pessoas — aprovadores via approver_type + default_user_id.
 */
class ApprovalTrailSeeder extends Seeder
{
    public function run(): void
    {
        ApprovalTrail::truncate();
        ApprovalFlowArea::query()->delete();

        $leonardo = $this->user('Leonardo Prudente', 'leonardo@grupo5estrelas.com.br');
        $dionei = $this->user('Dionei Eurich', 'dionei@grupo5estrelas.com.br');
        $anaPaula = $this->user('Ana Paula', 'anapaula@grupo5estrelas.com.br');
        $luizFarias = $this->user('Luiz Farias', 'farias@grupo5estrelas.com.br', ['luiz.farias@grupo5estrelas.com.br']);
        $karen = $this->user('Karen', 'karen@grupo5estrelas.com.br');
        $erismar = $this->user('Erismar', 'erismar@grupo5estrelas.com.br');
        $cilas = $this->user('Cilas', 'cilas@grupo5estrelas.com.br');
        $matheus = $this->user('Matheus Xavier', 'matheus.xavier@grupo5estrelas.com.br');
        $leiliane = $this->user('Leiliane', 'leiliane@grupo5estrelas.com.br');
        $leticia = $this->user('Letícia', 'leticia@grupo5estrelas.com.br');
        $silene = $this->user('Silene', 'silene@grupo5estrelas.com.br');
        $alexyxandra = $this->user('Dra. Alexyxandra', 'alexyxandra@grupo5estrelas.com.br');

        $finPres = [
            [4, 'financeiro', 'Financeiro (auditoria)', ApprovalTrail::TYPE_DEPT_FINANCEIRO, null, null],
            [5, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $leonardo?->id, null],
        ];

        $this->trail('matriz', 'Matriz', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Gerência Operacional', ApprovalTrail::TYPE_USUARIO, $erismar?->id, null],
            [3, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $dionei?->id, null],
            ...$finPres,
        ]);

        $this->trail('filiais', 'Filiais', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Gerência de Filiais', ApprovalTrail::TYPE_USUARIO, $cilas?->id, null],
            [3, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $dionei?->id, null],
            ...$finPres,
        ]);

        $this->trail('compras', 'Compras', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Gerência Operacional', ApprovalTrail::TYPE_USUARIO, $erismar?->id, null],
            [3, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $dionei?->id, null],
            ...$finPres,
        ]);

        $this->trail('modernizacao', 'Modernização', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Head Modernização', ApprovalTrail::TYPE_USUARIO, $matheus?->id, null],
            [3, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $dionei?->id, null],
            ...$finPres,
        ]);

        $this->trail('comercial', 'Comercial / Faturamento / Marketing', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Gerência Comercial', ApprovalTrail::TYPE_USUARIO, $leiliane?->id, null],
            [3, 'diretoria', 'Diretoria Comercial', ApprovalTrail::TYPE_DIRETOR_DEPTO, $anaPaula?->id, null],
            ...$finPres,
        ]);

        $this->trail('licitacao', 'Licitação', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Gerência Licitação', ApprovalTrail::TYPE_USUARIO, $leticia?->id, null],
            [3, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $luizFarias?->id, null],
            ...$finPres,
        ]);

        $this->trail('dp_rh', 'DP / RH', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Head DP/RH', ApprovalTrail::TYPE_USUARIO, $silene?->id, null],
            [3, 'financeiro', 'Financeiro (auditoria)', ApprovalTrail::TYPE_DEPT_FINANCEIRO, null, null],
            [4, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $leonardo?->id, null],
        ]);

        $this->trail('juridico', 'Jurídico', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'gerencia', 'Head Jurídico', ApprovalTrail::TYPE_USUARIO, $alexyxandra?->id, null],
            [3, 'financeiro', 'Financeiro (auditoria)', ApprovalTrail::TYPE_DEPT_FINANCEIRO, null, null],
            [4, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $leonardo?->id, null],
        ]);

        $this->trail('financeiro', 'Financeiro', [
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null, null],
            [2, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $dionei?->id, null],
            [3, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $leonardo?->id, null],
        ]);

        $this->command?->info('✅ Trilhas de aprovação configuradas (9 áreas).');
    }

    private function trail(string $area, string $label, array $levels): void
    {
        ApprovalFlowArea::create(['area' => $area, 'label' => $label]);

        foreach ($levels as [$order, $levelName, $roleLabel, $type, $userId, $deptId]) {
            ApprovalTrail::create([
                'area' => $area,
                'order' => $order,
                'level_name' => $levelName,
                'role_label' => $roleLabel,
                'approver_type' => $type,
                'default_user_id' => $userId,
                'approver_department_id' => $deptId,
            ]);
        }
    }

    private function user(string $name, string $email, array $altEmails = []): ?User
    {
        $existing = User::whereIn('email', array_merge([$email], $altEmails))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => bcrypt('5estrelas2026'), 'is_active' => true]
        );
    }
}
