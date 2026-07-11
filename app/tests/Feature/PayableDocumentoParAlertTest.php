<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use App\Services\PayableDocumentPairAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableDocumentoParAlertTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    public function test_resolve_alerta_quando_so_boleto(): void
    {
        $alert = PayableDocumentPairAlert::resolve(true, false);

        $this->assertSame(PayableDocumentPairAlert::MISSING_NOTA, $alert['code']);
    }

    public function test_resolve_alerta_quando_so_nota(): void
    {
        $alert = PayableDocumentPairAlert::resolve(false, true);

        $this->assertSame(PayableDocumentPairAlert::MISSING_BOLETO, $alert['code']);
    }

    public function test_sem_alerta_quando_tem_ambos_ou_nenhum(): void
    {
        $this->assertNull(PayableDocumentPairAlert::resolve(true, true));
        $this->assertNull(PayableDocumentPairAlert::resolve(false, false));
    }

    public function test_index_exibe_alerta_falta_nota(): void
    {
        $payable = $this->makePayable(['supplier_name' => 'SoBoleto']);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $this->activeUser()->id,
            'name' => 'boleto.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/docs/boleto.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'SoBoleto');

        $this->assertNotNull($row['document_pair_alert']);
        $this->assertSame('missing_nota', $row['document_pair_alert']['code']);
    }

    public function test_index_nao_exibe_alerta_em_status_aprovado(): void
    {
        $payable = $this->makePayable([
            'supplier_name' => 'AprovadoSoBoleto',
            'status' => 'aprovado',
        ]);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $this->activeUser()->id,
            'name' => 'boleto.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/docs/boleto.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=aprovado')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'AprovadoSoBoleto');

        $this->assertArrayNotHasKey('document_pair_alert', $row);
    }

    public function test_show_exibe_alerta_falta_boleto(): void
    {
        $user = $this->activeUser();
        $payable = $this->makePayable(['status' => 'em_preparacao']);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'nf.pdf',
            'doc_type' => 'nota_fiscal',
            'path' => 'payables/docs/nf.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($user)
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payable.document_pair_alert.code', 'missing_boleto')
            );
    }
}
