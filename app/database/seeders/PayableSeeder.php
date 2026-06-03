<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Payable;
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
    }
}
