<?php

namespace Tests\Browser;

use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A2 — Visualizador de anexo inline (browser).
 *
 * Feedback do cliente: ao visualizar um arquivo, NÃO abrir outra aba/página;
 * abrir na mesma tela e ter opção de voltar.
 */
class PayableAnexoInlineTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function makePayable(): Payable
    {
        return Payable::create([
            'title_number' => 'DUSK-VIEW-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Viewer',
            'amount' => 900.00,
            'due_date' => now()->addDays(4)->toDateString(),
            'status' => 'pendente',
        ]);
    }

    private function addDocument(Payable $payable): PayableDocument
    {
        return PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $this->bruno()->id,
            'name' => 'comprovante.png',
            'path' => 'payables/docs/comprovante.png',
            'mime_type' => 'image/png',
            'size' => 2048,
        ]);
    }

    public function test_abre_anexo_inline_e_volta(): void
    {
        $p = $this->makePayable();
        $this->addDocument($p);

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Viewer', 10)
                ->waitFor('@doc-open', 10)
                ->click('@doc-open')
                // Abre na MESMA página (dialog inline), sem nova aba.
                ->waitFor('@doc-viewer', 10)
                ->assertVisible('@doc-viewer')
                // Botão "Voltar" fecha o visualizador.
                ->click('@doc-viewer-close')
                ->pause(600)
                ->assertMissing('@doc-viewer')
                // Continua na mesma tela do título.
                ->assertSee('Fornecedor Dusk Viewer');
        });

        PayableDocument::where('payable_id', $p->id)->delete();
        $p->delete();
    }
}
