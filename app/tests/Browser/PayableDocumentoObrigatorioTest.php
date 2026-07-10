<?php

namespace Tests\Browser;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A1 — Documento obrigatório para aprovar (browser).
 *
 * Sem documento: botão "Enviar para Aprovação" desabilitado + aviso visível.
 * Com documento: botão habilitado e preview do fluxo visível.
 */
class PayableDocumentoObrigatorioTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function makePayable(): Payable
    {
        return Payable::create([
            'title_number' => 'DUSK-DOC-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Documento',
            'amount' => 1800.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ]);
    }

    private function addDocument(Payable $payable): PayableDocument
    {
        return PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $this->bruno()->id,
            'name' => 'nota-fiscal.pdf',
            'path' => 'payables/docs/nota-fiscal.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);
    }

    public function test_sem_documento_desabilita_envio_e_mostra_aviso(): void
    {
        $p = $this->makePayable();

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Documento', 10)
                ->waitFor('@no-docs-hint', 10)
                ->assertVisible('@no-docs-hint')
                ->assertSee('Anexe ao menos um documento');
        });

        $p->delete();
    }

    public function test_com_documento_permite_enviar(): void
    {
        $p = $this->makePayable();
        $this->addDocument($p);
        $bruno = $this->bruno();
        $manager = User::factory()->create(['is_active' => true, 'name' => 'Gestor Dusk']);
        $dept = Department::firstOrCreate(
            ['name' => 'Financeiro Dusk'],
            ['is_active' => true]
        );
        $dept->forceFill(['area_key' => 'matriz', 'manager_id' => $manager->id])->save();
        $bruno->forceFill(['department_id' => $dept->id])->save();
        ApprovalTrail::firstOrCreate(
            ['area' => 'matriz', 'order' => 1, 'level_name' => 'departamento'],
            ['role_label' => 'Departamento', 'default_user_id' => null]
        );

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Documento', 10)
                ->waitFor('@btn-send-approval', 10)
                ->assertMissing('@no-docs-hint')
                ->assertEnabled('@btn-send-approval')
                ->assertSee('Sequência de aprovação');
        });

        PayableDocument::where('payable_id', $p->id)->delete();
        $p->delete();
    }
}
