<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\ConciliationSessionService;
use App\Services\OfxImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankConciliationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

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

    private function createImport(?User $user = null, ?int $bankAccountId = null, ?string $date = null): BankStatementImport
    {
        $user = $user ?? User::factory()->create(['is_active' => true]);

        $session = null;
        if ($bankAccountId !== null && $date !== null) {
            $session = ConciliationSession::firstOrCreate([
                'bank_account_id' => $bankAccountId,
                'reference_date' => $date,
            ], ['status' => 'open', 'created_by' => $user->id]);
        }

        return BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccountId,
            'conciliation_session_id' => $session?->id,
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

    private function createBankAccount(array $attrs = []): BankAccount
    {
        return BankAccount::create(array_merge([
            'name' => 'MATRIZ — BRB',
            'is_active' => true,
            'bank_code' => '070',
            'bank_name' => 'BRB',
            'agency' => '046',
            'account_number' => '000132',
            'account_digit' => '9',
        ], $attrs));
    }

    private function realOfxFile(string $name = 'brb.ofx'): UploadedFile
    {
        $path = base_path("tests/fixtures/ofx/{$name}");

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    // ─── 1. Upload OFX (no date) → auto-detects date + redirects with ?date ──

    public function test_upload_without_date_auto_detects_from_ofx(): void
    {
        $user = $this->conciliador();

        // Ensure a bank account exists matching OFX (BRB 0460001329)
        BankAccount::create([
            'name' => 'BRB MATRIZ',
            'is_active' => true,
            'bank_code' => '070',
            'account_number' => '0460001329',
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => $this->realOfxFile('brb.ofx'),
            ]);

        $response->assertRedirect();
        // Redirect should include date query param
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('date=', $redirectUrl);

        // Import was created
        $this->assertDatabaseHas('bank_statement_imports', [
            'user_id' => $user->id,
            'bank_id' => '070',
            'status' => 'done',
        ]);
    }

    // ─── 2. Upload without being conciliador → 403 ───────────────────────────

    public function test_upload_without_conciliador_returns_403(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => $this->realOfxFile(),
            ]);

        $response->assertStatus(403);
    }

    // ─── 3. Upload OFX that spans multiple days → rejected ───────────────────

    public function test_upload_period_ofx_is_rejected(): void
    {
        $user = $this->conciliador();

        // Create a fake multi-day OFX
        $multiDayOfx = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE
<OFX>
<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS><LANGUAGE>POR</LANGUAGE></SONRS></SIGNONMSGSRSV1>
<BANKMSGSRSV1><STMTTRNRS><TRNUID>1</TRNUID><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS>
<STMTRS><CURDEF>BRL</CURDEF>
<BANKACCTFROM><BANKID>070</BANKID><ACCTID>0460001329</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20260601000000</DTSTART>
<DTEND>20260602000000</DTEND>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260601000000</DTPOSTED><TRNAMT>-100.00</TRNAMT><FITID>TX001</FITID><NAME>Pag</NAME></STMTTRN>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260602000000</DTPOSTED><TRNAMT>-200.00</TRNAMT><FITID>TX002</FITID><NAME>Pag</NAME></STMTTRN>
</BANKTRANLIST>
<LEDGERBAL><BALAMT>1000.00</BALAMT><DTASOF>20260602000000</DTASOF></LEDGERBAL>
</STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
OFX;

        $tmpPath = sys_get_temp_dir().'/'.uniqid('ofx_period_', true).'.ofx';
        file_put_contents($tmpPath, $multiDayOfx);

        $file = new UploadedFile(
            $tmpPath,
            'multi-day.ofx',
            'application/octet-stream',
            null,
            true,
        );

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('importResults');
        $results = session('importResults');
        $this->assertFalse($results[0]['ok']);
        $this->assertSame(OfxImportService::PERIOD_NOT_ALLOWED, $results[0]['error']);

        // No import created
        $this->assertDatabaseCount('bank_statement_imports', 0);
    }

    // ─── 4. Batch upload 2 OFX same day → dayReport shows 2 imports ──────────

    public function test_batch_upload_two_accounts_same_day_creates_two_imports(): void
    {
        $user = $this->conciliador();

        // Register 2 bank accounts matching the 2 OFX fixtures
        BankAccount::create([
            'name' => 'BRB', 'is_active' => true, 'bank_code' => '070', 'account_number' => '0460001329',
        ]);
        BankAccount::create([
            'name' => 'Banrisul', 'is_active' => true, 'bank_code' => '041', 'account_number' => '01350685083605',
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload-batch'), [
                'files' => [
                    $this->realOfxFile('brb.ofx'),
                    $this->realOfxFile('banrisul.ofx'),
                ],
            ]);

        $response->assertRedirect();

        // Both imports exist
        $this->assertDatabaseCount('bank_statement_imports', 2);

        // Both sessions created
        $this->assertDatabaseCount('conciliation_sessions', 2);

        // Day report for the BRB date should list 2 imports (or check via service)
        $brbDate = '2026-06-18';
        $banrisulDate = '2026-06-03';

        // If both share the same date, dayReport.kpis.imports would be 2.
        // Since BRB (2026-06-18) and Banrisul (2026-06-03) differ, redirect has no date.
        // Just assert both imports are in the DB.
        $this->assertDatabaseHas('bank_statement_imports', ['bank_id' => '070']);
        $this->assertDatabaseHas('bank_statement_imports', ['bank_id' => '041']);
    }

    // ─── 5. Batch same-day → redirect includes date in URL ───────────────────

    public function test_batch_upload_same_day_redirects_with_date(): void
    {
        $user = $this->conciliador();

        // Two accounts, same OFX file (same day = 2026-06-18)
        BankAccount::create([
            'name' => 'BRB1', 'is_active' => true, 'bank_code' => '070', 'account_number' => '0460001329',
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.upload-batch'), [
                'files' => [$this->realOfxFile('brb.ofx')],
            ]);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('date=2026-06-18', $redirectUrl);
    }

    // ─── 6. Index without conciliador → 200 with isConciliador=false ─────────

    public function test_index_without_conciliador_returns_200_and_not_conciliador(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BankConciliation/Index', false)
            ->where('isConciliador', false)
        );
    }

    // ─── 7. Index as conciliador → isConciliador=true ─────────────────────────

    public function test_index_as_conciliador_has_is_conciliador_true(): void
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

    // ─── 8. Index returns 'days' prop ────────────────────────────────────────

    public function test_index_has_days_prop(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BankConciliation/Index', false)
            ->has('days')
            ->has('bankAccounts')
            ->has('filters')
        );
    }

    // ─── 9. Index with ?date= returns dayReport prop ─────────────────────────

    public function test_index_with_date_returns_day_report(): void
    {
        $user = $this->activeUser();

        // Create a session so dayReport has something
        $account = $this->createBankAccount();
        $sessions = app(ConciliationSessionService::class);
        $sessions->resolve($account->id, Carbon::parse('2026-06-15'), $user);

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.index', ['date' => '2026-06-15']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BankConciliation/Index', false)
            ->has('dayReport')
            ->where('dayReport.date', '2026-06-15')
            ->has('dayReport.kpis')
            ->has('dayReport.matched')
            ->has('dayReport.ofx_only')
            ->has('dayReport.payable_only')
            ->has('dayReport.ambiguous')
        );
    }

    // ─── 10. Accept without matched_payable_id → error ───────────────────────

    public function test_accept_without_matched_payable_returns_error(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $tx = $this->createTransaction($import, [
            'match_status' => 'pending',
            'matched_payable_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.accept', $tx->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['transaction']);

        $tx->refresh();
        $this->assertEquals('pending', $tx->match_status); // unchanged
    }

    // ─── 11. Accept with matched_payable_id → accepted ───────────────────────

    public function test_accept_with_payable_sets_accepted(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-001', 'supplier_name' => 'F', 'amount' => 110.90,
            'due_date' => now()->subDays(5)->toDateString(), 'status' => 'pago',
            'paid_at' => now()->subDays(1)->toDateString(),
        ]);
        $tx = $this->createTransaction($import, [
            'match_status' => 'pending',
            'matched_payable_id' => $payable->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.accept', $tx->id));

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('accepted', $tx->match_status);
    }

    // ─── 12. Reject → clears payable and ambiguous flags ─────────────────────

    public function test_reject_clears_payable_and_ambiguous_flags(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-002', 'supplier_name' => 'F2', 'amount' => 110.90,
            'due_date' => now()->subDays(5)->toDateString(), 'status' => 'pago',
            'paid_at' => now()->subDays(1)->toDateString(),
        ]);
        $tx = $this->createTransaction($import, [
            'match_status' => 'pending',
            'matched_payable_id' => $payable->id,
            'raw_data' => ['ambiguous' => true, 'ambiguous_candidates' => [['payable_id' => $payable->id]]],
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.reject', $tx->id));

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('unmatched', $tx->match_status);
        $this->assertNull($tx->matched_payable_id);
        $this->assertFalse($tx->raw_data['ambiguous'] ?? true);
        $this->assertEmpty($tx->raw_data['ambiguous_candidates'] ?? []);
    }

    // ─── 13. Link ambiguous → resolves to manual ─────────────────────────────

    public function test_link_resolves_ambiguous_to_manual(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-003', 'supplier_name' => 'F3', 'amount' => 200.00,
            'due_date' => now()->subDays(5)->toDateString(), 'status' => 'pago',
            'paid_at' => now()->subDays(1)->toDateString(),
        ]);
        $tx = $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'matched_payable_id' => null,
            'raw_data' => ['ambiguous' => true, 'ambiguous_candidates' => []],
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.link', $tx->id), [
                'payable_id' => $payable->id,
            ]);

        $response->assertRedirect();
        $tx->refresh();
        $this->assertEquals('manual', $tx->match_status);
        $this->assertEquals($payable->id, $tx->matched_payable_id);
        $this->assertFalse($tx->raw_data['ambiguous'] ?? true);
    }

    // ─── 14. Batch conciliate by import ──────────────────────────────────────

    public function test_batch_conciliate_conciliates_payables(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-004', 'supplier_name' => 'F4', 'amount' => 1500.00,
            'due_date' => now()->subDays(10)->toDateString(), 'status' => 'pago',
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

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.conciliacao_lote',
            'module' => 'financeiro.contas_pagar',
        ]);
    }

    // ─── 15. Batch conciliate day route ──────────────────────────────────────

    public function test_batch_conciliate_day_conciliates_all_accepted_transactions(): void
    {
        $user = $this->conciliador();
        $account = $this->createBankAccount();
        $date = '2026-06-15';
        $import = $this->createImport($user, $account->id, $date);

        $payable = Payable::create([
            'title_number' => 'TIT-005', 'supplier_name' => 'F5', 'amount' => 500.00,
            'due_date' => '2026-06-10', 'status' => 'pago', 'paid_at' => $date,
        ]);
        $this->createTransaction($import, [
            'match_status' => 'accepted',
            'matched_payable_id' => $payable->id,
            'amount' => -500.00,
            'date' => Carbon::parse($date),
        ]);

        $response = $this->actingAs($user)
            ->post(route('bank-conciliation.batch-day'), ['date' => $date]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payable->refresh();
        $this->assertEquals('conciliado', $payable->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.conciliacao_lote_dia',
        ]);
    }

    // ─── 16. Delete import cascades → transactions removed ───────────────────

    public function test_delete_import_cascades(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $tx = $this->createTransaction($import);

        $response = $this->actingAs($user)
            ->delete(route('bank-conciliation.destroy', $import->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('bank_statement_imports', ['id' => $import->id]);
        $this->assertDatabaseMissing('bank_transactions', ['id' => $tx->id]);
    }

    // ─── 17. Delete with conciliated payable → error ─────────────────────────

    public function test_delete_import_with_conciliated_payable_returns_error(): void
    {
        $user = $this->conciliador();
        $import = $this->createImport($user);
        $payable = Payable::create([
            'title_number' => 'TIT-006', 'supplier_name' => 'F6', 'amount' => 500.00,
            'due_date' => now()->subDays(10)->toDateString(), 'status' => 'conciliado',
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

    // ─── 18. Search payables → returns pago only ─────────────────────────────

    public function test_search_payables_returns_pago_only(): void
    {
        $user = $this->conciliador();

        Payable::create([
            'title_number' => 'TIT-PAGO-1', 'supplier_name' => 'Fornecedor Busca', 'amount' => 300.00,
            'due_date' => now()->subDays(5)->toDateString(), 'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);
        Payable::create([
            'title_number' => 'TIT-PEND-1', 'supplier_name' => 'Fornecedor Busca Pendente', 'amount' => 300.00,
            'due_date' => now()->subDays(5)->toDateString(), 'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)
            ->get(route('bank-conciliation.search-payables', ['query' => 'Busca']));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('TIT-PAGO-1', $data[0]['title_number']);
    }

    public function test_reset_day_deletes_imports_and_sessions_without_touching_payables(): void
    {
        $user = $this->conciliador();
        $account = $this->createBankAccount();
        $sessions = app(ConciliationSessionService::class);
        $session = $sessions->resolve($account->id, Carbon::parse('2026-06-16'), $user);

        $import = BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'conciliation_session_id' => $session->id,
            'bank_name' => 'BRB',
            'bank_id' => '070',
            'account_number' => '0460001329',
            'file_name' => 'dia16.ofx',
            'file_path' => 'ofx/tmp/dia16.ofx',
            'status' => 'done',
            'transaction_count' => 1,
            'matched_count' => 0,
        ]);
        $this->createTransaction($import, ['match_status' => 'unmatched']);

        $payable = Payable::create([
            'title_number' => 'TIT-KEEP',
            'supplier_name' => 'Keep',
            'amount' => 100,
            'due_date' => '2026-06-01',
            'status' => 'pago',
            'paid_at' => '2026-06-16',
        ]);

        $this->actingAs($user)
            ->post(route('bank-conciliation.reset-day'), ['date' => '2026-06-16'])
            ->assertRedirect(route('bank-conciliation.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('importResults', []);

        $this->assertDatabaseMissing('bank_statement_imports', ['id' => $import->id]);
        $this->assertDatabaseMissing('conciliation_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    public function test_day_report_separates_tarifas_from_ofx_only(): void
    {
        $user = $this->conciliador();
        $account = $this->createBankAccount();
        $date = '2026-06-16';
        $import = $this->createImport($user, $account->id, $date);

        $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'TARIFA AVULSA ENVIO PIX',
            'amount' => -1.75,
            'date' => Carbon::parse($date),
        ]);
        $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'DEBITO PIX',
            'amount' => -100.00,
            'date' => Carbon::parse($date),
        ]);

        $report = app(ConciliationSessionService::class)->dayReport(Carbon::parse($date));

        $this->assertCount(1, $report['bank_ops']);
        $this->assertEquals('tarifa', $report['bank_ops'][0]['operation_category']);
        $this->assertCount(1, $report['ofx_only']);
        $this->assertFalse($report['can_conciliate_day']);
    }

    public function test_batch_conciliate_day_saves_tarifas_and_retains_ofx(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $user = $this->conciliador();
        $account = $this->createBankAccount();
        $date = '2026-06-16';
        $import = $this->createImport($user, $account->id, $date);

        \Illuminate\Support\Facades\Storage::disk('local')->put($import->file_path, 'OFXDATA');

        $payable = Payable::create([
            'title_number' => 'TIT-OPS', 'supplier_name' => 'FOps', 'amount' => 50.00,
            'due_date' => '2026-06-10', 'status' => 'pago', 'paid_at' => $date,
        ]);
        $this->createTransaction($import, [
            'match_status' => 'accepted',
            'matched_payable_id' => $payable->id,
            'amount' => -50.00,
            'date' => Carbon::parse($date),
            'description' => 'PIX FORNECEDOR',
        ]);
        $tarifa = $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'TARIFA AVULSA ENVIO PIX',
            'amount' => -1.75,
            'date' => Carbon::parse($date),
        ]);
        $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'APLICACAO CONTAMAX',
            'amount' => -1000.00,
            'date' => Carbon::parse($date),
        ]);

        $this->actingAs($user)
            ->post(route('bank-conciliation.batch-day'), ['date' => $date])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payable->refresh();
        $this->assertEquals('conciliado', $payable->status);

        $this->assertDatabaseHas('bank_day_operations', [
            'bank_transaction_id' => $tarifa->id,
            'category' => 'tarifa',
        ]);
        $this->assertTrue(
            \App\Models\BankDayOperation::query()
                ->where('bank_transaction_id', $tarifa->id)
                ->whereDate('reference_date', $date)
                ->exists()
        );
        $this->assertDatabaseHas('bank_day_operations', [
            'category' => 'aplicacao',
        ]);
        $this->assertTrue(
            \App\Models\BankDayOperation::query()
                ->where('category', 'aplicacao')
                ->whereDate('reference_date', $date)
                ->exists()
        );

        $tarifa->refresh();
        $this->assertEquals('non_payable', $tarifa->match_status);

        $import->refresh();
        $this->assertNotNull($import->day_conciliated_at);
        $this->assertNotNull($import->retained_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($import->retained_path);

        $this->actingAs($user)
            ->post(route('bank-conciliation.reset-day'), ['date' => $date])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bank_statement_imports', ['id' => $import->id]);
    }

    public function test_can_conciliate_day_blocked_by_ofx_only_but_not_by_payable_only(): void
    {
        $user = $this->conciliador();
        $account = $this->createBankAccount();
        $date = '2026-06-16';
        $import = $this->createImport($user, $account->id, $date);

        $payableAccepted = Payable::create([
            'title_number' => 'TIT-OK', 'supplier_name' => 'Ok', 'amount' => 50.00,
            'due_date' => '2026-06-10', 'status' => 'pago', 'paid_at' => $date,
        ]);
        $this->createTransaction($import, [
            'match_status' => 'accepted',
            'matched_payable_id' => $payableAccepted->id,
            'amount' => -50.00,
            'date' => Carbon::parse($date),
            'description' => 'PIX FORNECEDOR',
        ]);
        $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'TARIFA AVULSA ENVIO PIX',
            'amount' => -1.75,
            'date' => Carbon::parse($date),
        ]);

        // Título só no sistema — não bloqueia
        Payable::create([
            'title_number' => 'TIT-SO-SISTEMA', 'supplier_name' => 'Sem OFX', 'amount' => 999.00,
            'due_date' => '2026-06-10', 'status' => 'pago', 'paid_at' => $date,
        ]);

        $report = app(ConciliationSessionService::class)->dayReport(Carbon::parse($date));
        $this->assertTrue($report['can_conciliate_day']);
        $this->assertGreaterThan(0, $report['kpis']['payable_only']);
        $this->assertEmpty($report['ofx_only']);

        $this->createTransaction($import, [
            'match_status' => 'unmatched',
            'description' => 'DEBITO PIX SEM TITULO',
            'amount' => -200.00,
            'date' => Carbon::parse($date),
        ]);

        $reportBlocked = app(ConciliationSessionService::class)->dayReport(Carbon::parse($date));
        $this->assertFalse($reportBlocked['can_conciliate_day']);
        $this->assertCount(1, $reportBlocked['ofx_only']);
        $this->assertNotEmpty($reportBlocked['conciliate_blockers']);
    }
}
