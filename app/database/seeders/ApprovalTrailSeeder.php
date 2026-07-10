<?php

namespace Database\Seeders;

use App\Models\ApprovalTrail;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Trilhas de aprovação conforme organograma v3.0 (jun/2026).
 * A etapa "gerência" da trilha é absorvida pela 1ª etapa do departamento (gestor no cadastro).
 */
class ApprovalTrailSeeder extends Seeder
{
    public function run(): void
    {
        ApprovalTrail::truncate();

        $leonardo = $this->user('Leonardo Prudente', 'leonardo@grupo5estrelas.com.br');
        $dionei = $this->user('Dionei Eurich', 'dionei@grupo5estrelas.com.br');
        $anaPaula = $this->user('Ana Paula', 'anapaula@grupo5estrelas.com.br');
        $luizFarias = $this->user('Luiz Farias', 'luiz.farias@grupo5estrelas.com.br');
        $karen = $this->user('Karen', 'karen@grupo5estrelas.com.br');
        $erismar = $this->user('Erismar', 'erismar@grupo5estrelas.com.br');
        $cilas = $this->user('Cilas', 'cilas@grupo5estrelas.com.br');
        $matheus = $this->user('Matheus Xavier', 'matheus.xavier@grupo5estrelas.com.br');
        $leiliane = $this->user('Leiliane', 'leiliane@grupo5estrelas.com.br');
        $leticia = $this->user('Letícia', 'leticia@grupo5estrelas.com.br');
        $silene = $this->user('Silene', 'silene@grupo5estrelas.com.br');
        $alexyxandra = $this->user('Dra. Alexyxandra', 'alexyxandra@grupo5estrelas.com.br');

        $finPres = [
            [4, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [5, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ];

        $this->trail('matriz', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência Operacional (Erismar)', $erismar?->id],
            [3, 'diretoria', 'Diretoria (Dionei Eurich)', $dionei?->id],
            ...$finPres,
        ]);

        $this->trail('filiais', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência de Filiais (Cilas)', $cilas?->id],
            [3, 'diretoria', 'Diretoria (Dionei Eurich)', $dionei?->id],
            ...$finPres,
        ]);

        $this->trail('compras', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência Operacional (Erismar)', $erismar?->id],
            [3, 'diretoria', 'Diretoria (Dionei Eurich)', $dionei?->id],
            ...$finPres,
        ]);

        $this->trail('modernizacao', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Head Modernização (Matheus Xavier)', $matheus?->id],
            [3, 'diretoria', 'Diretoria (Dionei Eurich)', $dionei?->id],
            ...$finPres,
        ]);

        $this->trail('comercial', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência Comercial (Leiliane)', $leiliane?->id],
            [3, 'diretoria', 'Diretoria Comercial (Ana Paula)', $anaPaula?->id],
            ...$finPres,
        ]);

        $this->trail('licitacao', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência Licitação (Letícia)', $leticia?->id],
            [3, 'diretoria', 'Diretoria (Luiz Farias)', $luizFarias?->id],
            ...$finPres,
        ]);

        $this->trail('dp_rh', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Head DP/RH (Silene)', $silene?->id],
            [3, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [4, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        $this->trail('juridico', [
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Head Jurídico (Dra. Alexyxandra)', $alexyxandra?->id],
            [3, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [4, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        $this->command?->info('✅ Trilhas de aprovação configuradas (8 áreas).');
    }

    private function trail(string $area, array $levels): void
    {
        foreach ($levels as [$order, $levelName, $roleLabel, $userId]) {
            ApprovalTrail::create([
                'area' => $area,
                'order' => $order,
                'level_name' => $levelName,
                'role_label' => $roleLabel,
                'default_user_id' => $userId,
            ]);
        }
    }

    private function user(string $name, string $email): ?User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => bcrypt('5estrelas2026'), 'is_active' => true]
        );
    }
}
