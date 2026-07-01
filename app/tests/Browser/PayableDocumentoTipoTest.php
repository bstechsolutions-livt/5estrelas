<?php

namespace Tests\Browser;

use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Documentos separados por tipo (browser).
 *
 * A tela do título exibe seções por tipo (Notas Fiscais, Boletos, Relatórios,
 * Comprovações) e agrupa cada anexo na sua seção.
 */
class PayableDocumentoTipoTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_secoes_por_tipo_aparecem_e_agrupam(): void
    {
        $p = Payable::create([
            'title_number' => 'DUSK-TIPO-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Tipo',
            'amount' => 500.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);
        PayableDocument::create([
            'payable_id' => $p->id,
            'uploaded_by' => $this->bruno()->id,
            'name' => 'boleto-teste.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/docs/boleto-teste.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Tipo', 10)
                // As 4 seções de tipo aparecem separadas.
                ->waitFor('@doc-section-nota_fiscal', 10)
                ->assertSee('Notas Fiscais')
                ->assertSee('Boletos')
                ->assertSee('Relatórios')
                ->assertSee('Comprovações')
                // O boleto anexado aparece dentro da seção de Boletos.
                ->within('@doc-section-boleto', function (Browser $section) {
                    $section->assertSee('boleto-teste.pdf');
                });
        });

        PayableDocument::where('payable_id', $p->id)->delete();
        $p->delete();
    }
}
