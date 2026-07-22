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
            'bank_name' => 'Banco de Brasília',
            'bank_id' => '070',
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
            'date' => Carbon::parse('2026-06-15'),
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
            'title_number' => 'TIT-'.uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'pago',
            'paid_at' => '2026-06-15',
        ], $attrs));
    }

    // ─── 1. Same day + same amount → high confidence ──────────────────────────

    public function test_same_day_same_amount_matches_with_high_confidence(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15']);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['unmatched']);
        $this->assertEquals(0, $result['ambiguous']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('pending', $tx->match_status);
        $this->assertEquals('high', $tx->match_confidence);
        $this->assertNotNull($tx->matched_payable_id);
    }

    // ─── 2. Different day → no match ─────────────────────────────────────────

    public function test_different_day_does_not_match(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        // Payable paid on a different day
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-14']);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertEquals('none', $tx->match_confidence);
        $this->assertNull($tx->matched_payable_id);
    }

    // ─── 3. Same day different amount → no match ─────────────────────────────

    public function test_same_day_different_amount_does_not_match(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        $this->createPayable(['amount' => 1600.00, 'paid_at' => '2026-06-15']);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);
    }

    // ─── 4. Amount within ±R$ 0.01 tolerance → matches ──────────────────────

    public function test_amount_within_one_cent_tolerance_matches(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.01]);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15']);

        $result = $this->service->run($import->id);

        $this->assertEquals(1, $result['matched']);
    }

    // ─── 5. Two payables same day same amount → ambiguous ────────────────────

    public function test_two_candidates_same_day_creates_ambiguous(): void
    {
        $import = $this->createImport();
        $tx = $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15', 'supplier_name' => 'Fornecedor A']);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15', 'supplier_name' => 'Fornecedor B']);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);
        $this->assertEquals(1, $result['ambiguous']);

        $tx->refresh();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertEquals('low', $tx->match_confidence);
        $this->assertNull($tx->matched_payable_id);
        $this->assertTrue($tx->raw_data['ambiguous'] ?? false);
        $this->assertCount(2, $tx->raw_data['ambiguous_candidates'] ?? []);
    }

    // ─── 6. Credit transaction → always unmatched ────────────────────────────

    public function test_credit_transaction_is_always_unmatched(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['amount' => 1500.00, 'type' => 'credit']);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15']);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        $tx = BankTransaction::where('import_id', $import->id)->first();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertEquals('none', $tx->match_confidence);
    }

    // ─── 7. Status conciliado → not a candidate ───────────────────────────────

    public function test_payable_conciliado_is_not_a_candidate(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15', 'status' => 'conciliado']);

        $result = $this->service->run($import->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);
    }

    // ─── 8. Already matched payable ID excluded → second tx gets unmatched ───

    public function test_already_matched_payable_excluded_from_second_tx(): void
    {
        $import = $this->createImport();
        $payable = $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15']);

        $tx1 = $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00, 'fitid' => 'TX001']);
        $tx2 = $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00, 'fitid' => 'TX002']);

        $result = $this->service->run($import->id);

        // tx1 matches, tx2 finds no remaining candidate
        $tx1->refresh();
        $tx2->refresh();

        $this->assertNotNull($tx1->matched_payable_id);
        $this->assertEquals('pending', $tx1->match_status);
        $this->assertNull($tx2->matched_payable_id);
        $this->assertEquals('unmatched', $tx2->match_status);
    }

    // ─── 9. findCandidatesForDay returns correct structure ───────────────────

    public function test_find_candidates_for_day_returns_structure(): void
    {
        $import = $this->createImport();
        $tx = $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -750.00]);

        $payable = $this->createPayable(['amount' => 750.00, 'paid_at' => '2026-06-15', 'title_number' => 'TIT-XYZ', 'supplier_name' => 'Loja ABC']);

        $candidates = $this->service->findCandidatesForDay($tx, '2026-06-15');

        $this->assertCount(1, $candidates);
        $this->assertEquals($payable->id, $candidates[0]['payable_id']);
        $this->assertEquals('TIT-XYZ', $candidates[0]['title_number']);
        $this->assertEquals('Loja ABC', $candidates[0]['supplier_name']);
        $this->assertEquals('2026-06-15', $candidates[0]['paid_at']);
    }

    // ─── 10. Import status updated to done after run ─────────────────────────

    public function test_run_updates_import_status_to_done(): void
    {
        $import = $this->createImport();
        $this->createTransaction($import, ['date' => Carbon::parse('2026-06-15'), 'amount' => -1500.00]);
        $this->createPayable(['amount' => 1500.00, 'paid_at' => '2026-06-15']);

        $this->service->run($import->id);

        $import->refresh();
        $this->assertEquals('done', $import->status);
        $this->assertEquals(1, $import->matched_count);
    }

    public function test_rematch_unmatched_for_date_matches_single_candidate(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $account = \App\Models\BankAccount::create([
            'name' => 'Teste',
            'bank_code' => '033',
            'bank_name' => 'Santander',
            'agency' => '1',
            'account_number' => '1',
            'is_active' => true,
        ]);
        $session = \App\Models\ConciliationSession::create([
            'bank_account_id' => $account->id,
            'reference_date' => '2026-06-16',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $import = BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'conciliation_session_id' => $session->id,
            'bank_name' => 'Santander',
            'bank_id' => '033',
            'account_number' => '1',
            'file_name' => 'x.ofx',
            'file_path' => 'ofx/x.ofx',
            'status' => 'done',
            'transaction_count' => 1,
            'matched_count' => 0,
        ]);
        $tx = $this->createTransaction($import, [
            'date' => Carbon::parse('2026-06-16'),
            'amount' => -231.00,
            'match_status' => 'unmatched',
            'description' => 'DEBITO PIX',
        ]);
        $payable = $this->createPayable([
            'amount' => 231.00,
            'paid_at' => '2026-06-16',
            'status' => 'aguardando_conciliacao',
        ]);

        $result = $this->service->rematchUnmatchedForDate(Carbon::parse('2026-06-16'));

        $this->assertEquals(1, $result['matched']);
        $tx->refresh();
        $this->assertEquals('pending', $tx->match_status);
        $this->assertEquals($payable->id, $tx->matched_payable_id);
        $this->assertEquals('high', $tx->match_confidence);
    }

    public function test_rematch_unmatched_ambiguous_when_two_same_amount(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $account = \App\Models\BankAccount::create([
            'name' => 'Teste2',
            'bank_code' => '033',
            'bank_name' => 'Santander',
            'agency' => '1',
            'account_number' => '2',
            'is_active' => true,
        ]);
        $session = \App\Models\ConciliationSession::create([
            'bank_account_id' => $account->id,
            'reference_date' => '2026-06-16',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $import = BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'conciliation_session_id' => $session->id,
            'bank_name' => 'Santander',
            'bank_id' => '033',
            'account_number' => '2',
            'file_name' => 'y.ofx',
            'file_path' => 'ofx/y.ofx',
            'status' => 'done',
            'transaction_count' => 1,
            'matched_count' => 0,
        ]);
        $tx = $this->createTransaction($import, [
            'date' => Carbon::parse('2026-06-16'),
            'amount' => -100.00,
            'match_status' => 'unmatched',
            'description' => 'DEBITO PIX',
        ]);
        $this->createPayable(['amount' => 100.00, 'paid_at' => '2026-06-16', 'status' => 'aguardando_conciliacao']);
        $this->createPayable(['amount' => 100.00, 'paid_at' => '2026-06-16', 'status' => 'aguardando_conciliacao']);

        $result = $this->service->rematchUnmatchedForDate(Carbon::parse('2026-06-16'));

        $this->assertEquals(1, $result['ambiguous']);
        $tx->refresh();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertNull($tx->matched_payable_id);
        $this->assertTrue(($tx->raw_data['ambiguous'] ?? false) === true);
    }

    // ─── calculateConfidence unit tests (kept from original) ─────────────────

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
        $this->assertEquals('high', $this->service->calculateConfidence(
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-13'),
        ));
    }

    public function test_calculate_confidence_3_days_returns_medium(): void
    {
        $this->assertEquals('medium', $this->service->calculateConfidence(
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-12'),
        ));
    }

    public function test_calculate_confidence_5_days_returns_medium(): void
    {
        $this->assertEquals('medium', $this->service->calculateConfidence(
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-10'),
        ));
    }

    public function test_calculate_confidence_6_days_returns_low(): void
    {
        $this->assertEquals('low', $this->service->calculateConfidence(
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-09'),
        ));
    }
}
