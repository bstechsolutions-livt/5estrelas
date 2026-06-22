<?php

namespace Database\Seeders;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class BankConciliationSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: delete existing demo imports first
        BankStatementImport::where('file_name', 'like', 'demo-%')->each(function ($import) {
            $import->transactions()->delete();
            $import->delete();
        });

        $bruno = User::where('email', 'bruno@bstechsolutions.com')->first();
        if (!$bruno) {
            $this->command->warn('  ! Usuário bruno@bstechsolutions.com não encontrado. Pulando BankConciliationSeeder.');
            return;
        }

        // Ensure bruno is conciliador
        PayableRole::firstOrCreate(['role' => 'conciliador', 'user_id' => $bruno->id]);

        // Get paid payables to link (from PayableSeeder)
        $paidPayables = Payable::where('status', 'pago')->get();

        // Import 1: Banco do Brasil
        $importBB = BankStatementImport::create([
            'user_id' => $bruno->id,
            'bank_name' => 'Banco do Brasil',
            'bank_id' => '001',
            'account_number' => '12345-6',
            'branch_number' => '1234',
            'file_name' => 'demo-bb-extrato.ofx',
            'file_path' => '',
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
            'balance' => 45320.50,
            'status' => 'done',
            'transaction_count' => 8,
            'matched_count' => 3,
        ]);

        $this->seedBBTransactions($importBB, $paidPayables);

        // Import 2: Santander
        $importSan = BankStatementImport::create([
            'user_id' => $bruno->id,
            'bank_name' => 'SANTANDER',
            'bank_id' => '033',
            'account_number' => '98765-4',
            'branch_number' => '0567',
            'file_name' => 'demo-santander-extrato.ofx',
            'file_path' => '',
            'period_start' => now()->subDays(25)->toDateString(),
            'period_end' => now()->subDays(2)->toDateString(),
            'balance' => 128750.00,
            'status' => 'done',
            'transaction_count' => 7,
            'matched_count' => 2,
        ]);

        $this->seedSantanderTransactions($importSan, $paidPayables);

        $this->command->info('✅ BankConciliationSeeder: 2 importações demo com transações criadas.');
    }

    private function seedBBTransactions(BankStatementImport $import, $paidPayables): void
    {
        $txData = [
            // Matched high confidence — will link to paid payables
            [
                'type' => 'debit',
                'description' => 'PAG FORNECEDOR ENERGISA SA',
                'amount' => null, // will use payable amount
                'confidence' => 'high',
                'status' => 'accepted',
                'link_payable' => true,
                'days_ago' => 5,
            ],
            [
                'type' => 'debit',
                'description' => 'TED 0987 UNIFORME EXPRESS',
                'amount' => null,
                'confidence' => 'high',
                'status' => 'accepted',
                'link_payable' => true,
                'days_ago' => 8,
            ],
            // Matched medium confidence
            [
                'type' => 'debit',
                'description' => 'DEB AUTOMATICO VIVO TEL',
                'amount' => null,
                'confidence' => 'medium',
                'status' => 'pending',
                'link_payable' => true,
                'days_ago' => 12,
            ],
            // Unmatched debit (no payable match)
            [
                'type' => 'debit',
                'description' => 'TARIFA MANUTENCAO CTA',
                'amount' => 32.50,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 3,
            ],
            [
                'type' => 'debit',
                'description' => 'IOF OPERACOES CREDITO',
                'amount' => 18.75,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 7,
            ],
            // Credits
            [
                'type' => 'credit',
                'description' => 'TED RECEBIDA CLIENTE XYZ',
                'amount' => 15000.00,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 2,
            ],
            [
                'type' => 'credit',
                'description' => 'CREDITO RENDIMENTO APLIC',
                'amount' => 234.56,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 1,
            ],
            // Rejected match
            [
                'type' => 'debit',
                'description' => 'PIX ENVIADO 55667788',
                'amount' => 1250.00,
                'confidence' => 'low',
                'status' => 'rejected',
                'link_payable' => false,
                'days_ago' => 15,
            ],
        ];

        $payableIdx = 0;
        $seq = 0;

        foreach ($txData as $tx) {
            $payableId = null;
            $amount = $tx['amount'];

            if ($tx['link_payable'] && $paidPayables->count() > $payableIdx) {
                $payable = $paidPayables[$payableIdx];
                $payableId = $payable->id;
                $amount = (float) $payable->amount;
                $payableIdx++;
            }

            if ($amount === null) {
                $amount = random_int(500, 25000) + random_int(0, 99) / 100;
            }

            BankTransaction::create([
                'import_id' => $import->id,
                'fitid' => 'BB' . now()->format('Ymd') . str_pad($seq, 4, '0', STR_PAD_LEFT),
                'date' => now()->subDays($tx['days_ago'])->toDateString(),
                'amount' => $amount,
                'type' => $tx['type'],
                'description' => $tx['description'],
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payableId,
                'match_status' => $tx['status'],
                'match_confidence' => $tx['confidence'],
                'raw_data' => ['_demo' => true, 'TRNTYPE' => strtoupper($tx['type']), 'NAME' => $tx['description']],
            ]);

            $seq++;
        }
    }

    private function seedSantanderTransactions(BankStatementImport $import, $paidPayables): void
    {
        // Use remaining paid payables (skip ones already used by BB)
        $usedCount = 3; // BB used 3
        $remaining = $paidPayables->slice($usedCount);

        $txData = [
            // Matched high
            [
                'type' => 'debit',
                'description' => 'PAG TIT CAESB AGUA ESGOTO',
                'amount' => null,
                'confidence' => 'high',
                'status' => 'accepted',
                'link_payable' => true,
                'days_ago' => 4,
            ],
            // Matched medium
            [
                'type' => 'debit',
                'description' => 'BOLETO PAPELARIA BOA VISTA',
                'amount' => null,
                'confidence' => 'medium',
                'status' => 'pending',
                'link_payable' => true,
                'days_ago' => 10,
            ],
            // Unmatched
            [
                'type' => 'debit',
                'description' => 'TAR PACOTE SERVICOS',
                'amount' => 45.90,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 6,
            ],
            [
                'type' => 'debit',
                'description' => 'SEGURO PRESTAMISTA',
                'amount' => 89.00,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 9,
            ],
            // Credits
            [
                'type' => 'credit',
                'description' => 'PIX RECEBIDO FATURAMENTO',
                'amount' => 32500.00,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 3,
            ],
            [
                'type' => 'credit',
                'description' => 'CRED APLIC AUTOMATICA',
                'amount' => 567.89,
                'confidence' => 'none',
                'status' => 'unmatched',
                'link_payable' => false,
                'days_ago' => 1,
            ],
            // Low confidence rejected
            [
                'type' => 'debit',
                'description' => 'DOC TRANSF 33445566',
                'amount' => 3200.00,
                'confidence' => 'low',
                'status' => 'rejected',
                'link_payable' => false,
                'days_ago' => 18,
            ],
        ];

        $payableIdx = 0;
        $availablePayables = $remaining->values();
        $seq = 0;

        foreach ($txData as $tx) {
            $payableId = null;
            $amount = $tx['amount'];

            if ($tx['link_payable'] && $availablePayables->count() > $payableIdx) {
                $payable = $availablePayables[$payableIdx];
                $payableId = $payable->id;
                $amount = (float) $payable->amount;
                $payableIdx++;
            }

            if ($amount === null) {
                $amount = random_int(500, 25000) + random_int(0, 99) / 100;
            }

            BankTransaction::create([
                'import_id' => $import->id,
                'fitid' => 'SAN' . now()->format('Ymd') . str_pad($seq, 4, '0', STR_PAD_LEFT),
                'date' => now()->subDays($tx['days_ago'])->toDateString(),
                'amount' => $amount,
                'type' => $tx['type'],
                'description' => $tx['description'],
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payableId,
                'match_status' => $tx['status'],
                'match_confidence' => $tx['confidence'],
                'raw_data' => ['_demo' => true, 'TRNTYPE' => strtoupper($tx['type']), 'NAME' => $tx['description']],
            ]);

            $seq++;
        }
    }
}
