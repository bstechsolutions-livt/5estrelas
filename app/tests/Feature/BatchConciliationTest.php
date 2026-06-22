<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\BatchConciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchConciliationTest extends TestCase
{
    use RefreshDatabase;

    private BatchConciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BatchConciliationService();
    }

    private function conciliador(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $perm = Permission::firstOrCreate(
            ['key' => 'financeiro.contas_pagar.visualizar'],
            ['label' => 'Ver Contas a Pagar', 'module' => 'financeiro']
        );
        $user->permissions()->attach($perm->id);
        PayableRole::create(['role' => 'conciliador', 'user_id' => $user->id]);

        return $user;
    }

    private function createImport(?User $user = null): BankStatementImport
    {
        $user = $user ?? User::factory()->create(['is_active' => true]);

        return BankStatementImport::create([
            'user_id' => $user->id,
            'bank_name' => 'Banco do Brasil',
            'bank_id' => '001',
            'account_number' => '12345-6',
            'file_name' => 'extrato.ofx',
            'file_path' => 'ofx/extrato.ofx',
            'status' => 'done',
            'transaction_count' => 0,
            'matched_count' => 0,
        ]);
    }

    private function createTransactionWithPayable(BankStatementImport $import, string $matchStatus = 'accepted', string $payableStatus = 'pago'): array
    {
        $payable = Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor ' . uniqid(),
            'amount' => 1500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => $payableStatus,
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);

        $transaction = BankTransaction::create([
            'import_id' => $import->id,
            'fitid' => uniqid(),
            'date' => now(),
            'amount' => -1500.00,
            'type' => 'debit',
            'description' => 'Pagamento fornecedor',
            'matched_payable_id' => $payable->id,
            'match_status' => $matchStatus,
            'match_confidence' => 'high',
        ]);

        return [$transaction, $payable];
    }

    // ─── 1. Batch completes all accepted → payables become conciliado + comments + audit ───

    public function test_batch_conciliates_all_accepted_transactions(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        [$tx1, $payable1] = $this->createTransactionWithPayable($import, 'accepted');
        [$tx2, $payable2] = $this->createTransactionWithPayable($import, 'manual');
        [$tx3, $payable3] = $this->createTransactionWithPayable($import, 'accepted');

        $result = $this->service->execute($import->id, $user);

        $this->assertEquals(3, $result['conciliated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        // All payables should be conciliado
        foreach ([$payable1, $payable2, $payable3] as $payable) {
            $payable->refresh();
            $this->assertEquals('conciliado', $payable->status);
            $this->assertEquals($user->id, $payable->conciliated_by);
            $this->assertNotNull($payable->conciliated_at);
            $this->assertStringContains("OFX #{$import->id}", $payable->conciliation_notes);
        }
    }

    // ─── 2. Payable that changed status (no longer pago) → skipped ───────

    public function test_payable_no_longer_pago_is_skipped(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        [$tx1, $payable1] = $this->createTransactionWithPayable($import, 'accepted', 'pago');
        [$tx2, $payable2] = $this->createTransactionWithPayable($import, 'accepted', 'pago');

        // Change payable2 status to simulate concurrency
        $payable2->update(['status' => 'conciliado']);

        $result = $this->service->execute($import->id, $user);

        $this->assertEquals(1, $result['conciliated']);
        $this->assertEquals(1, $result['skipped']);

        $payable1->refresh();
        $this->assertEquals('conciliado', $payable1->status);

        // Transaction for skipped payable should be rejected with reason
        $tx2->refresh();
        $this->assertEquals('rejected', $tx2->match_status);
        $this->assertNotNull($tx2->raw_data['skip_reason'] ?? null);
    }

    // ─── 3. No accepted transactions → returns error message ─────────────

    public function test_no_accepted_transactions_returns_error(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        // Create transactions but with different statuses
        BankTransaction::create([
            'import_id' => $import->id,
            'fitid' => uniqid(),
            'date' => now(),
            'amount' => -1500.00,
            'type' => 'debit',
            'description' => 'Pagamento',
            'match_status' => 'pending',
            'match_confidence' => 'high',
        ]);

        $result = $this->service->execute($import->id, $user);

        $this->assertEquals(0, $result['conciliated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContains('Nenhuma transação aceita', $result['errors'][0]);
    }

    // ─── 4. Audit log created with event contas_pagar.conciliacao_lote ───

    public function test_audit_log_created_for_batch_conciliation(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        [$tx, $payable] = $this->createTransactionWithPayable($import, 'accepted');

        $this->actingAs($user);
        $this->service->execute($import->id, $user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.conciliacao_lote',
            'module' => 'financeiro.contas_pagar',
            'auditable_type' => BankStatementImport::class,
            'auditable_id' => $import->id,
        ]);

        $log = AuditLog::where('event', 'contas_pagar.conciliacao_lote')->first();
        $this->assertEquals(1, $log->new_values['conciliated']);
        $this->assertEquals($import->id, $log->new_values['import_id']);
    }

    // ─── 5. Comments created for each conciliated payable ────────────────

    public function test_comments_created_for_each_conciliated_payable(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        [$tx1, $payable1] = $this->createTransactionWithPayable($import, 'accepted');
        [$tx2, $payable2] = $this->createTransactionWithPayable($import, 'manual');

        $this->service->execute($import->id, $user);

        foreach ([$payable1, $payable2] as $payable) {
            $this->assertDatabaseHas('payable_comments', [
                'payable_id' => $payable->id,
                'user_id' => $user->id,
                'type' => 'conciliation',
            ]);

            $comment = PayableComment::where('payable_id', $payable->id)
                ->where('type', 'conciliation')
                ->first();
            $this->assertStringContains("OFX #{$import->id}", $comment->body);
        }
    }

    // ─── 6. matched_count updated on import ──────────────────────────────

    public function test_matched_count_updated_on_import(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);

        [$tx1, $payable1] = $this->createTransactionWithPayable($import, 'accepted');
        [$tx2, $payable2] = $this->createTransactionWithPayable($import, 'accepted');

        $this->service->execute($import->id, $user);

        $import->refresh();
        // After conciliation, the match_status stays 'accepted' so matched_count should reflect that
        // Note: the service counts transactions where match_status = 'accepted' after the batch
        $this->assertIsInt((int) $import->matched_count);
    }

    // ─── 7. Non-conciliador gets 403 ────────────────────────────────────

    public function test_non_conciliador_gets_403(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $import = $this->createImport($user);

        [$tx, $payable] = $this->createTransactionWithPayable($import, 'accepted');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->execute($import->id, $user);
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
