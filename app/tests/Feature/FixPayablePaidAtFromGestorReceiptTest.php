<?php

namespace Tests\Feature;

use App\Models\Payable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FixPayablePaidAtFromGestorReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_detects_approval_fallback_and_execute_updates_only_paid_at(): void
    {
        $payable = Payable::create([
            'title_number' => '231',
            'supplier_name' => 'Teste',
            'amount' => 231,
            'due_date' => '2026-06-16',
            'status' => 'aguardando_conciliacao',
            'paid_at' => '2026-06-12',
        ]);

        $approvedMs = (int) (Carbon::parse('2026-06-12 18:22:18', 'UTC')->timestamp * 1000);
        $paymentMs = (int) (Carbon::parse('2026-06-16 00:00:00', 'America/Sao_Paulo')->timestamp * 1000);
        $annexedAt = (int) (Carbon::parse('2026-07-07 20:12:23', 'UTC')->timestamp * 1000);

        $dump = [
            'results' => [[
                'gestor_id' => 'g1',
                'payable_id' => $payable->id,
                'status' => 'awaiting-inclusion',
                'history' => [
                    ['type' => 'approved', 'at' => $approvedMs, 'by' => 'u1'],
                    [
                        'type' => 'receipt-annexed',
                        'at' => $annexedAt,
                        'by' => 'u2',
                        'receipt' => ['paymentDate' => $paymentMs, 'file' => 'x'],
                    ],
                ],
            ]],
        ];

        $path = storage_path('app/testing-gestor-paid-at.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($dump));

        Artisan::call('gestor:fix-paid-at-from-receipt', [
            '--source' => $path,
        ]);
        $payable->refresh();
        $this->assertEquals('2026-06-12', $payable->paid_at?->toDateString());
        $this->assertEquals('aguardando_conciliacao', $payable->status);

        Artisan::call('gestor:fix-paid-at-from-receipt', [
            '--source' => $path,
            '--execute' => true,
            '--force' => true,
        ]);
        $payable->refresh();
        $this->assertEquals('2026-06-16', $payable->paid_at?->toDateString());
        $this->assertEquals('aguardando_conciliacao', $payable->status);
    }
}
