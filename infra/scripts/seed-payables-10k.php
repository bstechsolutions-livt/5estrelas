<?php
require '/var/www/5estrelas/app/vendor/autoload.php';
$app = require '/var/www/5estrelas/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$branches = App\Models\Branch::pluck('id')->toArray();
$suppliers = [
    ['Energisa S.A.','12345678000190','Energia'],
    ['CAESB Água e Esgoto','09812345000188','Água'],
    ['Vivo Telefonia','02558157000162','Telecom'],
    ['Uniforme Express','33456789000155','Uniformes'],
    ['Auto Peças Central','44567890000122','Veículos'],
    ['Seguro Mais','55678901000199','Seguros'],
    ['Contabilidade Souza','66789012000166','Contabilidade'],
    ['Papelaria Boa Vista','77890123000133','Material'],
    ['TechSoft Sistemas','88901234000100','Software'],
    ['Aluguel Imóvel','99012345000177','Aluguel'],
    ['Posto Norte','11223344000155','Combustível'],
    ['Refeitório Popular','22334455000122','Alimentação'],
    ['Transporte Rápido','33445566000199','Transporte'],
    ['Gráfica Modelo','44556677000166','Gráfica'],
    ['Advocacia Campos','55667788000133','Jurídico'],
];
$statuses = ['pendente','pendente','pendente','em_preparacao','aguardando_aprovacao','aprovado','reprovado','pago'];
$rows = [];
for ($i = 0; $i < 10000; $i++) {
    $s = $suppliers[array_rand($suppliers)];
    $rows[] = [
        'title_number' => 'TIT-' . str_pad($i + 100, 5, '0', STR_PAD_LEFT),
        'supplier_name' => $s[0],
        'supplier_cnpj' => $s[1],
        'amount' => random_int(100,99999) + random_int(0,99)/100,
        'due_date' => now()->addDays(random_int(-30,90))->toDateString(),
        'issue_date' => now()->subDays(random_int(5,60))->toDateString(),
        'description' => 'Pagamento ' . $s[2] . ' comp. ' . now()->subMonth()->format('m/Y'),
        'category' => $s[2],
        'status' => $statuses[array_rand($statuses)],
        'branch_id' => $branches[array_rand($branches)],
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if (count($rows) >= 1000) {
        DB::table('payables')->insert($rows);
        $rows = [];
    }
}
if ($rows) DB::table('payables')->insert($rows);
echo App\Models\Payable::count() . " títulos no banco.\n";
