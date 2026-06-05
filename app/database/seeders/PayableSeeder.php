<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Bordero;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayableSeeder extends Seeder
{
    private array $suppliers = [
        ['name' => 'Energisa S.A.', 'cnpj' => '12345678000190', 'cat' => 'Energia'],
        ['name' => 'CAESB Água e Esgoto', 'cnpj' => '09812345000188', 'cat' => 'Água'],
        ['name' => 'Vivo Telefonia', 'cnpj' => '02558157000162', 'cat' => 'Telecomunicações'],
        ['name' => 'Uniforme Express Ltda', 'cnpj' => '33456789000155', 'cat' => 'Uniformes'],
        ['name' => 'Auto Peças Central', 'cnpj' => '44567890000122', 'cat' => 'Manutenção Veicular'],
        ['name' => 'Seguro Mais Corretora', 'cnpj' => '55678901000199', 'cat' => 'Seguros'],
        ['name' => 'Contabilidade Souza & Filhos', 'cnpj' => '66789012000166', 'cat' => 'Contabilidade'],
        ['name' => 'Papelaria Boa Vista', 'cnpj' => '77890123000133', 'cat' => 'Material de Escritório'],
        ['name' => 'TechSoft Sistemas', 'cnpj' => '88901234000100', 'cat' => 'Software'],
        ['name' => 'Aluguel Imóvel Comercial', 'cnpj' => '99012345000177', 'cat' => 'Aluguel'],
        ['name' => 'Posto Combustível Norte', 'cnpj' => '11223344000155', 'cat' => 'Combustível'],
        ['name' => 'Refeitório Popular', 'cnpj' => '22334455000122', 'cat' => 'Alimentação'],
        ['name' => 'Transporte Rápido Ltda', 'cnpj' => '33445566000199', 'cat' => 'Transporte'],
        ['name' => 'Gráfica Modelo', 'cnpj' => '44556677000166', 'cat' => 'Gráfica'],
        ['name' => 'Advocacia Campos & Assoc.', 'cnpj' => '55667788000133', 'cat' => 'Jurídico'],
    ];

    public function run(): void
    {
        $branches = Branch::pluck('id')->toArray();
        $statuses = ['pendente', 'pendente', 'pendente', 'em_preparacao', 'aguardando_aprovacao', 'aprovado', 'reprovado'];

        for ($i = 0; $i < 35; $i++) {
            $supplier = $this->suppliers[array_rand($this->suppliers)];
            $daysFromNow = random_int(-10, 45);
            $amount = random_int(150, 85000) + random_int(0, 99) / 100;

            Payable::create([
                'title_number' => 'TIT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'supplier_name' => $supplier['name'],
                'supplier_cnpj' => $supplier['cnpj'],
                'amount' => $amount,
                'due_date' => now()->addDays($daysFromNow)->toDateString(),
                'issue_date' => now()->subDays(random_int(5, 30))->toDateString(),
                'description' => "Pagamento referente a {$supplier['cat']} - competência " . now()->subMonth()->format('m/Y'),
                'category' => $supplier['cat'],
                'status' => $statuses[array_rand($statuses)],
                'branch_id' => $branches ? $branches[array_rand($branches)] : null,
            ]);
        }

        $this->command->info('✅ 35 títulos fake criados para contas a pagar.');

        $this->seedBorderos();
    }

    /**
     * Agrupa alguns títulos selecionáveis em borderôs fake, com status variados.
     */
    private function seedBorderos(): void
    {
        $creator = User::where('email', 'bruno@bstechsolutions.com')->first()
            ?? User::where('email', 'admin@5estrelas.com.br')->first()
            ?? User::first();

        if (!$creator) {
            $this->command->warn('  ! Nenhum usuário encontrado para criar borderôs.');
            return;
        }

        // Distribuição de status dos borderôs a criar
        $borderoStatuses = ['rascunho', 'aguardando_aprovacao', 'aguardando_aprovacao', 'aprovado', 'reprovado'];
        $count = 0;

        foreach ($borderoStatuses as $status) {
            // Pega títulos livres (sem borderô) em status agrupável
            $payables = Payable::whereNull('bordero_id')
                ->whereIn('status', ['pendente', 'em_preparacao', 'reprovado'])
                ->inRandomOrder()
                ->limit(random_int(2, 4))
                ->get();

            if ($payables->count() < 2) {
                break; // acabaram os títulos livres
            }

            $bordero = Bordero::create([
                'number' => Bordero::generateNumber(),
                'description' => 'Borderô de pagamentos - lote ' . ($count + 1),
                'status' => $status,
                'created_by' => $creator->id,
                'sent_for_approval_at' => $status !== 'rascunho' ? now()->subDays(random_int(1, 10)) : null,
                'approved_by' => in_array($status, ['aprovado', 'reprovado']) ? $creator->id : null,
                'approved_at' => $status === 'aprovado' ? now()->subDays(random_int(0, 5)) : null,
                'rejection_reason' => $status === 'reprovado' ? 'Divergência nos valores apresentados.' : null,
            ]);

            // Vincula títulos e alinha o status deles ao do borderô
            $payableStatus = match ($status) {
                'rascunho' => 'em_preparacao',
                'aguardando_aprovacao' => 'aguardando_aprovacao',
                'aprovado' => 'aprovado',
                'reprovado' => 'reprovado',
                default => 'em_preparacao',
            };

            Payable::whereIn('id', $payables->pluck('id'))->update([
                'bordero_id' => $bordero->id,
                'prepared_by' => $creator->id,
                'status' => $payableStatus,
            ]);

            $bordero->recalculate();
            $count++;
        }

        $this->command->info("✅ {$count} borderôs fake criados.");
    }
}
