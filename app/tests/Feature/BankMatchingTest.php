<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\User;
use App\Services\BankMatchingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankMatchingTest extends TestCase
{
    use RefreshDatabase;

    private BankMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BankMatchingService();
    }

    private function createImport(): BankStatementImport
    {
        $user = User::factory()->create(['is_active' => true]);

        return BankStatementImport::create([
            'user_id' => $user->id,
            'bank_name' => 'Banco do Brasil',
            'bank_id' => '001',
            'account_number' => '12345-6',
            'file_name' => 'extrato.ofx',
            'file_path' => 'ofx/extrato.ofx',
            'status' => 'processing',
            'transaction_count' => 0,
            'matched_count' => 0,
        ]);
    }

    private function createTransaction(BankStatementImport $import, array $attrs = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'import_id' => $import->id,
            'fitid' => uniqid(),
            'date' => now(),
            'amount' => -1500.00,
            'type' => 'debit',
            'description' => 'Pagamento fornecedor',
            'match_status' => 'pending',
            'match_confidence' => 'none',
        ], $attrs));
    }

    private function createPayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->toDateString(),
        ], $attrs));
    }

    // ─── 1. Match exact (same amount, ±2 days) → confidence high ─────────

    public function test_match_exact_amount_within_2_days_gives_high_confidence(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
        ]);

        $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->copy()->subDay()->toDateString(), // 1 day difference
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('pending', $tx->match_status);
        $this->assertEquals('high', $tx->match_confidence);
        $this->assertNotNull($tx->matched_payable_id);
    }

    // ─── 2. Match medium (same amount, 3-5 days) → confidence medium ─────

    public function test_match_amount_within_3_to_5_days_gives_medium_confidence(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -2000.00,
            'type' => 'debit',
        ]);

        $this->createPayable([
            'amount' => 2000.00,
            'paid_at' => $txDate->copy()->subDays(4)->toDateString(), // 4 days difference
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('medium', $tx->match_confidence);
    }

    // ─── 3. Match low (same amount, >5 days) → confidence low ────────────

    public function test_match_amount_beyond_5_days_gives_low_confidence(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -3000.00,
            'type' => 'debit',
        ]);

        $this->createPayable([
            'amount' => 3000.00,
            'paid_at' => $txDate->copy()->subDays(10)->toDateString(), // 10 days difference
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('low', $tx->match_confidence);
    }

    // ─── 4. No match (different amount) → unmatched ──────────────────────

    public function test_no_match_when_amount_differs(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
        ]);

        // Payable with a different amount (not within R$ 0.01 tolerance)
        $this->createPayable([
            'amount' => 1600.00,
            'paid_at' => $txDate->toDateString(),
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertEquals('none', $tx->match_confidence);
        $this->assertNull($tx->matched_payable_id);
    }

    // ─── 5. Credit transaction → unmatched (no matching) ─────────────────

    public function test_credit_transaction_is_always_unmatched(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => 1500.00,
            'type' => 'credit',
        ]);

        // Even if there's a payable with the exact amount and date
        $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->toDateString(),
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertEquals('none', $tx->match_confidence);
    }

    // ─── 6. Payable already conciliado → not a candidate ─────────────────

    public function test_payable_already_conciliado_is_not_a_candidate(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
        ]);

        // Payable with status conciliado — should not be matched
        $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->toDateString(),
            'status' => 'conciliado',
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('unmatched', $tx->match_status);
    }

    // ─── 7. Multiple candidates → sorted by confidence ───────────────────

    public function test_multiple_candidates_sorted_by_confidence(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $tx = $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
        ]);

        // Low confidence candidate (10 days)
        $payableLow = $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->copy()->subDays(10)->toDateString(),
        ]);

        // High confidence candidate (1 day)
        $payableHigh = $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->copy()->subDay()->toDateString(),
        ]);

        // Medium confidence candidate (4 days)
        $payableMedium = $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->copy()->subDays(4)->toDateString(),
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);

        // The best candidate (highest confidence) should be selected
        $tx->refresh();
        $this->assertEquals($payableHigh->id, $tx->matched_payable_id);
        $this->assertEquals('high', $tx->match_confidence);

        // Verify findCandidates returns sorted list
        $candidates = $this->service->findCandidates($tx);
        $this->assertCount(3, $candidates);
        $this->assertEquals('high', $candidates[0]['confidence']);
        $this->assertEquals('medium', $candidates[1]['confidence']);
        $this->assertEquals('low', $candidates[2]['confidence']);
    }

    // ─── 8. Duplicate detection (same payable for 2 transactions) ────────

    public function test_duplicate_detection_when_same_payable_matches_multiple_transactions(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        // Two debit transactions with same amount
        $tx1 = $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
            'fitid' => 'TX001',
        ]);
        $tx2 = $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
            'fitid' => 'TX002',
        ]);

        // Only one payable matching this amount
        $payable = $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->toDateString(),
        ]);

        $result = $this->service->run($import->id);

        $this->assertEquals(2, $result['matched']);

        // Both transactions should match the same payable
        $tx1->refresh();
        $tx2->refresh();

        $this->assertEquals($payable->id, $tx1->matched_payable_id);
        $this->assertEquals($payable->id, $tx2->matched_payable_id);

        // The second transaction should have possible_duplicate flag
        $this->assertTrue($tx2->raw_data['possible_duplicate'] ?? false);
    }

    // ─── calculateConfidence unit tests ──────────────────────────────────

    public function test_calculate_confidence_null_paid_at_returns_low(): void
    {
        $this->assertEquals('low', $this->service->calculateConfidence(Carbon::now(), null));
    }

    public function test_calculate_confidence_same_day_returns_high(): void
    {
        $date = Carbon::parse('2026-06-15');
        $this->assertEquals('high', $this->service->calculateConfidence($date, $date->copy()));
    }

    public function test_calculate_confidence_2_days_returns_high(): void
    {
        $txDate = Carbon::parse('2026-06-15');
        $paidAt = Carbon::parse('2026-06-13');
        $this->assertEquals('high', $this->service->calculateConfidence($txDate, $paidAt));
    }

    public function test_calculate_confidence_3_days_returns_medium(): void
    {
        $txDate = Carbon::parse('2026-06-15');
        $paidAt = Carbon::parse('2026-06-12');
        $this->assertEquals('medium', $this->service->calculateConfidence($txDate, $paidAt));
    }

    public function test_calculate_confidence_5_days_returns_medium(): void
    {
        $txDate = Carbon::parse('2026-06-15');
        $paidAt = Carbon::parse('2026-06-10');
        $this->assertEquals('medium', $this->service->calculateConfidence($txDate, $paidAt));
    }

    public function test_calculate_confidence_6_days_returns_low(): void
    {
        $txDate = Carbon::parse('2026-06-15');
        $paidAt = Carbon::parse('2026-06-09');
        $this->assertEquals('low', $this->service->calculateConfidence($txDate, $paidAt));
    }

    // ─── Amount tolerance ────────────────────────────────────────────────

    public function test_amount_tolerance_within_one_cent_matches(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.01, // 1 cent difference
            'type' => 'debit',
        ]);

        $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->toDateString(),
        ]);

        $result = $this->service->run($import->id);
        $this->assertEquals(1, $result['matched']);
    }

    // ─── Import status update ────────────────────────────────────────────

    public function test_run_updates_import_status_to_done(): void
    {
        $import = $this->createImport();
        $txDate = Carbon::parse('2026-06-15');

        $this->createTransaction($import, [
            'date' => $txDate,
            'amount' => -1500.00,
            'type' => 'debit',
        ]);

        $this->createPayable([
            'amount' => 1500.00,
            'paid_at' => $txDate->toDateString(),
        ]);

        $this->service->run($import->id);

        $import->refresh();
        $this->assertEquals('done', $import->status);
        $this->assertEquals(1, $import->matched_count);
    }
}
