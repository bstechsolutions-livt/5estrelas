<?php

namespace Tests\Browser;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da Conciliação Bancária via OFX.
 * Cobre upload, revisão de matches (aceitar/rejeitar/vincular), batch conciliate,
 * e versão mobile.
 */
class BankConciliationTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function ensureConciliador(): void
    {
        PayableRole::firstOrCreate(['role' => 'conciliador', 'user_id' => $this->bruno()->id]);
    }

    /**
     * Helper: cria um BankStatementImport + BankTransactions para testes que não envolvem upload.
     */
    private function createImportWithTransactions(array $transactionsData = []): BankStatementImport
    {
        $import = BankStatementImport::create([
            'user_id' => $this->bruno()->id,
            'bank_name' => 'Banco de Brasília',
            'bank_id' => '070',
            'account_number' => '0460001329',
            'branch_number' => null,
            'file_name' => 'brb-test.ofx',
            'file_path' => 'ofx/test/brb-test.ofx',
            'period_start' => now()->subDays(5)->toDateString(),
            'period_end' => now()->toDateString(),
            'balance' => 110544.86,
            'status' => 'done',
            'transaction_count' => count($transactionsData),
            'matched_count' => 0,
        ]);

        foreach ($transactionsData as $tx) {
            BankTransaction::create(array_merge([
                'import_id' => $import->id,
            ], $tx));
        }

        return $import;
    }

    // ─── Test 1: Page loads ───────────────────────────────────────────────

    public function test_page_loads_with_title_and_upload_area(): void
    {
        $this->ensureConciliador();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar/conciliacao')
                ->waitForText('Concilia', 10)
                ->assertPresent('@upload-ofx');
        });
    }

    // ─── Test 2: Upload OFX ──────────────────────────────────────────────

    public function test_upload_ofx_redirects_to_show_with_transactions(): void
    {
        $this->ensureConciliador();

        $fixturePath = base_path('tests/fixtures/ofx/brb.ofx');

        $this->browse(function (Browser $browser) use ($fixturePath) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar/conciliacao')
                ->waitFor('@upload-ofx', 10)
                ->pause(500); // wait for Inertia hydration

            // PrimeVue FileUpload in basic mode with customUpload + auto renders
            // a hidden input[type=file]. We attach to it and then trigger the change
            // event so that PrimeVue picks it up and fires the @uploader handler.
            $browser->script("document.querySelector('[dusk=\"upload-ofx\"] input[type=\"file\"]').style.display = 'block'");
            $browser->attach('[dusk="upload-ofx"] input[type="file"]', $fixturePath)
                ->pause(5000) // wait for upload processing + redirect
                ->waitFor('@counter-matched', 20) // show page has counter-matched dusk attr
                ->assertSee('0460001329');
        });

        // Verify 2 transactions were created (BRB fixture has 2 STMTTRN)
        $import = BankStatementImport::query()->orderByDesc('id')->first();
        $this->assertNotNull($import);
        $this->assertEquals(2, $import->transaction_count);

        // Cleanup
        $import->transactions()->delete();
        $import->delete();
    }

    // ─── Test 3: Accept match ────────────────────────────────────────────

    public function test_accept_match_changes_badge(): void
    {
        $this->ensureConciliador();

        $payable = Payable::create([
            'title_number' => 'DUSK-OFX-ACC-' . uniqid(),
            'supplier_name' => 'Forn Dusk Accept',
            'amount' => 110.90,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(1)->toDateString(),
        ]);

        $import = $this->createImportWithTransactions([
            [
                'fitid' => 'DUSK_ACCEPT_001',
                'date' => now()->subDays(1)->toDateString(),
                'amount' => -110.90,
                'type' => 'debit',
                'description' => 'PACOTE PJ PLUS',
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payable->id,
                'match_status' => 'pending',
                'match_confidence' => 'high',
                'raw_data' => [],
            ],
        ]);

        $tx = $import->transactions()->first();

        $this->browse(function (Browser $browser) use ($import, $tx) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/conciliacao/{$import->id}")
                ->waitFor("@btn-accept-{$tx->id}", 10)
                ->pause(500) // wait for Inertia hydration
                ->click("@btn-accept-{$tx->id}")
                ->waitUntilMissing("@btn-accept-{$tx->id}", 10);
        });

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $tx->id,
            'match_status' => 'accepted',
        ]);

        // Cleanup
        $import->transactions()->delete();
        $import->delete();
        $payable->delete();
    }

    // ─── Test 4: Reject match ────────────────────────────────────────────

    public function test_reject_match_changes_badge(): void
    {
        $this->ensureConciliador();

        $payable = Payable::create([
            'title_number' => 'DUSK-OFX-REJ-' . uniqid(),
            'supplier_name' => 'Forn Dusk Reject',
            'amount' => 200.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);

        $import = $this->createImportWithTransactions([
            [
                'fitid' => 'DUSK_REJECT_001',
                'date' => now()->subDays(2)->toDateString(),
                'amount' => -200.00,
                'type' => 'debit',
                'description' => 'PAGAMENTO TED',
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payable->id,
                'match_status' => 'pending',
                'match_confidence' => 'high',
                'raw_data' => [],
            ],
        ]);

        $tx = $import->transactions()->first();

        $this->browse(function (Browser $browser) use ($import, $tx) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/conciliacao/{$import->id}")
                ->waitFor("@btn-reject-{$tx->id}", 10)
                ->pause(500) // wait for Inertia hydration
                ->click("@btn-reject-{$tx->id}")
                ->waitUntilMissing("@btn-reject-{$tx->id}", 10);
        });

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $tx->id,
            'match_status' => 'unmatched',
            'matched_payable_id' => null,
        ]);

        // Cleanup
        $import->transactions()->delete();
        $import->delete();
        $payable->delete();
    }

    // ─── Test 5: Link manual ─────────────────────────────────────────────

    public function test_link_manual_opens_dialog_and_links_payable(): void
    {
        $this->ensureConciliador();

        $payable = Payable::create([
            'title_number' => 'DUSK-OFX-LINK-' . uniqid(),
            'supplier_name' => 'Fornecedor Vincular Dusk',
            'amount' => 500.00,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(8)->toDateString(),
        ]);

        $import = $this->createImportWithTransactions([
            [
                'fitid' => 'DUSK_LINK_001',
                'date' => now()->subDays(8)->toDateString(),
                'amount' => -500.00,
                'type' => 'debit',
                'description' => 'TED PAGAMENTO',
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => null,
                'match_status' => 'unmatched',
                'match_confidence' => 'none',
                'raw_data' => [],
            ],
        ]);

        $tx = $import->transactions()->first();

        $this->browse(function (Browser $browser) use ($import, $tx, $payable) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/conciliacao/{$import->id}")
                ->waitFor("@btn-link-{$tx->id}", 10)
                ->pause(500) // wait for Inertia hydration
                ->click("@btn-link-{$tx->id}")
                ->waitFor('@search-payable-dialog', 10)
                ->pause(500) // wait for dialog animation
                ->assertPresent('@search-payable-input')
                ->type('@search-payable-input', 'Vincular Dusk')
                ->keys('@search-payable-input', '{enter}')
                ->waitFor("@search-payable-result-{$payable->id}", 10)
                ->click("@search-payable-result-{$payable->id} button")
                ->pause(1000) // let Inertia process the link
                ->waitForText('vinculado', 10);
        });

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $tx->id,
            'match_status' => 'manual',
            'matched_payable_id' => $payable->id,
        ]);

        // Cleanup
        $import->transactions()->delete();
        $import->delete();
        $payable->delete();
    }

    // ─── Test 6: Batch conciliate ────────────────────────────────────────

    public function test_batch_conciliate_accepted_transactions(): void
    {
        $this->ensureConciliador();

        $payable1 = Payable::create([
            'title_number' => 'DUSK-OFX-BATCH1-' . uniqid(),
            'supplier_name' => 'Forn Batch 1',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);

        $payable2 = Payable::create([
            'title_number' => 'DUSK-OFX-BATCH2-' . uniqid(),
            'supplier_name' => 'Forn Batch 2',
            'amount' => 2000.00,
            'due_date' => now()->subDays(4)->toDateString(),
            'status' => 'pago',
            'paid_at' => now()->subDays(1)->toDateString(),
        ]);

        $import = $this->createImportWithTransactions([
            [
                'fitid' => 'DUSK_BATCH_001',
                'date' => now()->subDays(2)->toDateString(),
                'amount' => -1000.00,
                'type' => 'debit',
                'description' => 'TED BATCH 1',
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payable1->id,
                'match_status' => 'accepted',
                'match_confidence' => 'high',
                'raw_data' => [],
            ],
            [
                'fitid' => 'DUSK_BATCH_002',
                'date' => now()->subDays(1)->toDateString(),
                'amount' => -2000.00,
                'type' => 'debit',
                'description' => 'TED BATCH 2',
                'memo' => null,
                'check_number' => null,
                'matched_payable_id' => $payable2->id,
                'match_status' => 'accepted',
                'match_confidence' => 'high',
                'raw_data' => [],
            ],
        ]);

        $this->browse(function (Browser $browser) use ($import) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/conciliacao/{$import->id}")
                ->waitFor('@btn-batch-conciliate', 10)
                ->pause(500) // wait for Inertia hydration
                ->click('@btn-batch-conciliate')
                ->waitForText('conciliado', 15);
        });

        $this->assertDatabaseHas('payables', ['id' => $payable1->id, 'status' => 'conciliado']);
        $this->assertDatabaseHas('payables', ['id' => $payable2->id, 'status' => 'conciliado']);

        // Cleanup
        $import->transactions()->delete();
        $import->delete();
        $payable1->delete();
        $payable2->delete();
    }

    // ─── Test 8: Mobile page loads ───────────────────────────────────────

    public function test_mobile_page_loads_with_cards_layout(): void
    {
        $this->ensureConciliador();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(375, 800)
                ->visit('/financeiro/contas-pagar/conciliacao')
                ->waitForText('Concilia', 10)
                ->assertPresent('@upload-ofx');
        });
    }
}
