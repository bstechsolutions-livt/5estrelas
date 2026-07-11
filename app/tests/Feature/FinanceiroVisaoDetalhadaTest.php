<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroVisaoDetalhadaTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $key, 'module' => 'financeiro'],
                )->id,
            );
        }

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

    public function test_contas_pagar_resumida_nao_carrega_documentos(): void
    {
        $payable = $this->makePayable();
        PayableDocument::create([
            'payable_id' => $payable->id,
            'name' => 'boleto.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/test/boleto.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $resp = $this->actingAs($this->userWithPermissions(['financeiro.contas_pagar.visualizar']))
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente&view=resumida')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('id', $payable->id);
        $this->assertArrayNotHasKey('documents', $row);
    }

    public function test_contas_pagar_detalhada_carrega_documentos(): void
    {
        $payable = $this->makePayable(['supplier_name' => 'Fornecedor Docs']);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'name' => 'nota.pdf',
            'doc_type' => 'nota_fiscal',
            'path' => 'payables/test/nota.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        $resp = $this->actingAs($this->userWithPermissions(['financeiro.contas_pagar.visualizar']))
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente&view=detalhada')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'Fornecedor Docs');
        $this->assertNotEmpty($row['documents']);
        $this->assertSame('nota.pdf', $row['documents'][0]['name']);
    }

    public function test_contas_pagar_inertia_expoe_view_e_tipos_documento(): void
    {
        $this->makePayable();

        $this->actingAs($this->userWithPermissions(['financeiro.contas_pagar.visualizar']))
            ->get('/financeiro/contas-pagar?status=pendente&view=detalhada')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Index', false)
                ->where('filters.view', 'detalhada')
                ->has('documentTypes.nota_fiscal')
            );
    }

    public function test_borderos_detalhada_carrega_payables_com_documentos(): void
    {
        $user = $this->userWithPermissions([
            'financeiro.borderos.visualizar',
            'financeiro.contas_pagar.visualizar',
        ]);

        $payable = $this->makePayable(['status' => 'aguardando_aprovacao']);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'name' => 'boleto.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/test/boleto2.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $bordero = Bordero::create([
            'number' => 'BOR-' . uniqid(),
            'description' => 'Teste visão',
            'status' => 'aguardando_aprovacao',
            'total_amount' => $payable->amount,
            'items_count' => 1,
            'created_by' => $user->id,
        ]);
        $payable->update(['bordero_id' => $bordero->id, 'status' => 'aguardando_aprovacao']);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/borderos?status=aguardando_aprovacao&view=detalhada')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('id', $bordero->id);
        $this->assertNotEmpty($row['payables']);
        $this->assertNotEmpty($row['payables'][0]['documents']);
    }
}
