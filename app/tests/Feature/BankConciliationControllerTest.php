<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankConciliationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $keys = ['financeiro.contas_pagar.visualizar', 'financeiro.conciliacao.visualizar']): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    private function conciliador(): User
    {
        $user = $this->activeUser();
        PayableRole::create(['role' => 'conciliador', 'user_id' => $user->id]);

        return $user;
    }

    private function createImport(?User $user = null): BankStatementImport
    {
        $user = $user ?? User::factory()->create(['is_active' => true]);

        return BankStatementImport::create([
            'user_id' => $user->id,
            'bank_name' => 'Banco de Brasília',
            'bank_id' => '070',
            'account_number' => '0460001329',
            'file_name' => 'brb.ofx',
            'file_path' => 'ofx/1/brb.ofx',
            'status' => 'done',
            'transaction_count' => 2,
            'matched_count' => 0,
        ]);
    }

    private function createTransaction(BankStatementImport $import, array $overrides = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'import_id' => $import->id,
            'fitid' => uniqid(),
            'date' => now()->subDays(1),
            'amount' => -110.90,
            'type' => 'debit',
            'description' => 'PACOTE PJ PLUS',
            'match_status' => 'pending',
            'match_confidence' => 'none',
        ], $overrides));
    }

    private function realOfxFile(): UploadedFile
    {
        $path = base_path('tests/fixtures/ofx/brb.ofx');

        return new UploadedFile($path, 'brb.ofx', 'application/octet-stream', null, true);
    }

    // ─── 1. Upload OFX as conciliador → redirect, import + transactions created ───

    public function test_upload_ofx_as_conciliador_creates_import_and_transactions(): void
    {
        $user = $this->conciliador();

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => $this->realOfxFile(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Import created
        $this->assertDatabaseHas('bank_statement_imports', [
            'user_id' => $user->id,
            'bank_id' => '070',
            'account_number' => '0460001329',
            'status' => 'done',
        ]);

        // Transactions created (BRB file has 2 transactions)
        $import = BankStatementImport::where('user_id', $user->id)->first();
        $this->assertEquals(2, $import->transactions()->count());
    }

    // ─── 2. Upload without being conciliador → 403 ───

    public function test_upload_without_conciliador_returns_403(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => $this->realOfxFile(),
            ]);

        $response->assertStatus(403);
    }

    // ─── 3. Upload invalid file → 422 ───

    public function test_upload_invalid_file_returns_422(): void
    {
        $user = $this->conciliador();

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
    }

    // ─── 4. Accept transaction → match_status updated to accepted ───

    public function test_accept_transaction_updates_status(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $tx = $this->createTransaction($import, ['match_status' => 'pending', 'match_confidence' => 'high']);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.accept', $tx->id));

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('accepted', $tx->match_status);
    }

    // ─── 5. Reject transaction → match_status=rejected, matched_payable_id null ───

    public function test_reject_transaction_clears_match(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-001',
            'supplier_name' => 'Fornecedor',
            'amount' => 110.90,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);
        $tx = $this->createTransaction($import, [
            'match_status' => 'pending',
            'matched_payable_id' => $payable->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.reject', $tx->id));

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('rejected', $tx->match_status);
        $this->assertNull($tx->matched_payable_id);
    }

    // ─── 6. Link manual → match_status=manual, matched_payable_id set ───

    public function test_link_manual_sets_payable(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $tx = $this->createTransaction($import, ['match_status' => 'unmatched']);
        $payable = Payable::create([
            'title_number' => 'TIT-002',
            'supplier_name' => 'Fornecedor Manual',
            'amount' => 110.90,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.link', $tx->id), [
                'payable_id' => $payable->id,
            ]);

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('manual', $tx->match_status);
        $this->assertEquals($payable->id, $tx->matched_payable_id);
    }

    // ─── 7. Batch conciliate → payables become conciliado, audit log, success ───

    public function test_batch_conciliate_conciliates_payables(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-003',
            'supplier_name' => 'Fornecedor Batch',
            'amount' => 1500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);
        $this->createTransaction($import, [
            'match_status' => 'accepted',
            'match_confidence' => 'high',
            'matched_payable_id' => $payable->id,
            'amount' => -1500.00,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.batch', $import->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payable->refresh();
        $this->assertEquals('conciliado', $payable->status);
        $this->assertEquals($user->id, $payable->conciliated_by);
        $this->assertNotNull($payable->conciliated_at);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.conciliacao_lote',
            'module' => 'financeiro.contas_pagar',
        ]);
    }

    // ─── 8. Delete import → cascaded deletion ───

    public function test_delete_import_cascades(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $tx = $this->createTransaction($import);

        $response = $this->actingAs($user)
            ->delete(route('bank-conciliation.destroy', $import->id));

        $response->assertRedirect(route('bank-conciliation.index'));
        $this->assertDatabaseMissing('bank_statement_imports', ['id' => $import->id]);
        $this->assertDatabaseMissing('bank_transactions', ['id' => $tx->id]);
    }

    // ─── 9. Delete import with conciliated transactions → error ───

    public function test_delete_import_with_conciliated_payable_returns_error(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-004',
            'supplier_name' => 'Fornecedor Conc',
            'amount' => 500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'conciliado',
            'paid_at' => now()->subDays(5)->toDateString(),
            'conciliated_at' => now()->subDay()->toDateString(),
            'conciliated_by' => $user->id,
        ]);
        $this->createTransaction($import, [
            'match_status' => 'accepted',
            'matched_payable_id' => $payable->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('bank-conciliation.destroy', $import->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bank_statement_imports', ['id' => $import->id]);
    }

    // ─── 10. Search payables → returns JSON with pago payables ───

    public function test_search_payables_returns_pago_only(): void
    {
        $user = $this->conciliador();

        Payable::create([
            'title_number' => 'TIT-PAGO-1',
            'supplier_name' => 'Fornecedor Busca',
            'amount' => 300.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);
        Payable::create([
            'title_number' => 'TIT-PENDENTE-1',
            'supplier_name' => 'Fornecedor Busca Pendente',
            'amount' => 300.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.search-payables', ['query' => 'Busca']));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('TIT-PAGO-1', $data[0]['title_number']);
    }

    // ─── 11. Index without conciliador → still 200 (read-only) ───

    public function test_index_without_conciliador_returns_200(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.index'));

        $response->assertOk();
    }

    // ─── 12. Index as conciliador → 200 with isConciliador=true ───

    public function test_index_as_conciliador_has_is_conciliador_prop(): void
    {
        $user = $this->conciliador();

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BankConciliation/Index', false)
            ->where('isConciliador', true)
        );
    }
}
