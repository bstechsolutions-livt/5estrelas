<?php

namespace Database\Seeders;

use App\Models\ApprovalTrail;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Configura as trilhas de aprovação conforme Fluxo de Aprovação e Pagamento v3.0.
 *
 * Trilhas por área de origem:
 * - Matriz/Filiais/Compras/Modernização: Dept → Gerência (Erismar/Cilas/Matheus) → Diretoria (Dionei) → Financeiro → Presidência
 * - Comercial/Faturamento/Marketing: Dept → Gerência (Leiliane) → Diretoria (Ana Paula) → Financeiro → Presidência
 * - Licitação: Dept → Gerência (Letícia) → Diretoria (Luiz Farias) → Financeiro → Presidência
 * - DP/RH: Dept → Gerência (Silene) → Financeiro → Presidência (sem diretoria)
 * - Jurídico: Dept → Gerência (Dra. Alexyxandra) → Financeiro → Presidência (sem diretoria)
 */
class ApprovalTrailSeeder extends Seeder
{
    public function run(): void
    {
        ApprovalTrail::truncate();

        // Encontra ou cria os usuários-chave (se não existirem, cria placeholder)
        $leonardo = $this->findOrCreateUser('Leonardo Prudente', 'leonardo@grupo5estrelas.com.br');
        $dionei = $this->findOrCreateUser('Dionei', 'dionei@grupo5estrelas.com.br');
        $anaPaula = $this->findOrCreateUser('Ana Paula', 'anapaula@grupo5estrelas.com.br');
        $luizFarias = $this->findOrCreateUser('Luiz Farias', 'luiz.farias@grupo5estrelas.com.br');
        $karen = $this->findOrCreateUser('Karen', 'karen@grupo5estrelas.com.br');
        $erismar = $this->findOrCreateUser('Erismar', 'erismar@grupo5estrelas.com.br');
        $leiliane = $this->findOrCreateUser('Leiliane', 'leiliane@grupo5estrelas.com.br');
        $leticia = $this->findOrCreateUser('Letícia', 'leticia@grupo5estrelas.com.br');
        $silene = $this->findOrCreateUser('Silene', 'silene@grupo5estrelas.com.br');
        $alexyxandra = $this->findOrCreateUser('Dra. Alexyxandra', 'alexyxandra@grupo5estrelas.com.br');

        // Trilha: Matriz / Filiais / Compras / Modernização
        $this->trail('matriz', [
            [1, 'departamento', 'Departamento (solicitante)', null],
            [2, 'gerencia', 'Gerência Operacional', $erismar?->id],
            [3, 'diretoria', 'Diretoria Administrativa (Dionei)', $dionei?->id],
            [4, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [5, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        // Trilha: Comercial / Faturamento / Marketing
        $this->trail('comercial', [
            [1, 'departamento', 'Departamento (solicitante)', null],
            [2, 'gerencia', 'Gerência Comercial (Leiliane)', $leiliane?->id],
            [3, 'diretoria', 'Diretoria Comercial (Ana Paula)', $anaPaula?->id],
            [4, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [5, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        // Trilha: Licitação
        $this->trail('licitacao', [
            [1, 'departamento', 'Departamento (solicitante)', null],
            [2, 'gerencia', 'Gerência Licitação (Letícia)', $leticia?->id],
            [3, 'diretoria', 'Diretoria (Luiz Farias)', $luizFarias?->id],
            [4, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [5, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        // Trilha: DP / RH (SEM diretoria — vai direto ao financeiro)
        $this->trail('dp_rh', [
            [1, 'departamento', 'Departamento (solicitante)', null],
            [2, 'gerencia', 'Head DP/RH (Silene)', $silene?->id],
            [3, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [4, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        // Trilha: Jurídico (SEM diretoria — vai direto ao financeiro)
        $this->trail('juridico', [
            [1, 'departamento', 'Departamento (solicitante)', null],
            [2, 'gerencia', 'Head Jurídico (Dra. Alexyxandra)', $alexyxandra?->id],
            [3, 'financeiro', 'Financeiro (auditoria)', $karen?->id],
            [4, 'presidencia', 'Presidência (Leonardo Prudente)', $leonardo?->id],
        ]);

        $this->command->info('✅ Trilhas de aprovação configuradas (5 áreas).');
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

    private function findOrCreateUser(string $name, string $email): ?User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt('5estrelas2026'),
                'is_active' => true,
            ]
        );
    }
}
