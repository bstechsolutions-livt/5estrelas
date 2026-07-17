<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayablePaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($keys as $key) {
            $permission = Permission::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'module' => 'financeiro'],
            );
            $user->permissions()->attach($permission->id);
        }

        return $user;
    }

    private function financeUser(): User
    {
        return $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.preparar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ]);
    }

    private function payable(string $status): Payable
    {
        return Payable::create([
            'title_number' => 'REC-'.uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 100,
            'due_date' => now()->addDay()->toDateString(),
            'status' => $status,
        ]);
    }

    private function updateUrl(Payable $payable): string
    {
        return "/financeiro/contas-pagar/{$payable->id}/comprovante-pagamento";
    }

    public function test_financeiro_pode_adicionar_comprovante_em_qualquer_status(): void
    {
        Storage::fake('public');
        $user = $this->financeUser();

        foreach (array_keys(Payable::STATUS_LABELS) as $status) {
            $payable = $this->payable($status);
            $name = "comprovante-{$status}.pdf";

            $this->actingAs($user)
                ->post($this->updateUrl($payable), [
                    'file' => UploadedFile::fake()->create($name, 50, 'application/pdf'),
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('payable_documents', [
                'payable_id' => $payable->id,
                'doc_type' => 'comprovacao',
                'name' => $name,
            ]);
            $this->assertSame($status, $payable->fresh()->status);
        }
    }

    public function test_novo_comprovante_substitui_o_anterior_e_registra_auditoria(): void
    {
        Storage::fake('public');
        $user = $this->financeUser();
        $payable = $this->payable('conciliado');
        Storage::disk('public')->put('payables/docs/antigo.pdf', 'antigo');

        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'antigo.pdf',
            'doc_type' => 'comprovacao',
            'path' => 'payables/docs/antigo.pdf',
            'mime_type' => 'application/pdf',
            'size' => 6,
        ]);

        $this->actingAs($user)
            ->post($this->updateUrl($payable), [
                'file' => UploadedFile::fake()->create('novo.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('payable_documents', [
            'payable_id' => $payable->id,
            'name' => 'antigo.pdf',
        ]);
        $this->assertDatabaseHas('payable_documents', [
            'payable_id' => $payable->id,
            'name' => 'novo.pdf',
            'doc_type' => 'comprovacao',
        ]);
        $this->assertSame(1, $payable->documents()->where('doc_type', 'comprovacao')->count());
        Storage::disk('public')->assertMissing('payables/docs/antigo.pdf');

        $newPath = $payable->documents()->where('doc_type', 'comprovacao')->value('path');
        Storage::disk('public')->assertExists($newPath);
        $this->assertDatabaseHas('payable_comments', [
            'payable_id' => $payable->id,
            'type' => 'payment',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.comprovante_atualizado',
            'auditable_id' => $payable->id,
        ]);
    }

    public function test_financeiro_pode_remover_comprovante_sem_alterar_status(): void
    {
        Storage::fake('public');
        $user = $this->financeUser();
        $payable = $this->payable('encerrado');

        $this->actingAs($user)->post($this->updateUrl($payable), [
            'file' => UploadedFile::fake()->create('comprovante.pdf', 40, 'application/pdf'),
        ]);
        $path = $payable->documents()->where('doc_type', 'comprovacao')->value('path');

        $this->actingAs($user)
            ->delete($this->updateUrl($payable))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('payable_documents', [
            'payable_id' => $payable->id,
            'doc_type' => 'comprovacao',
        ]);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('encerrado', $payable->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.comprovante_removido',
            'auditable_id' => $payable->id,
        ]);
    }

    public function test_usuario_sem_permissao_de_preparar_nao_gerencia_comprovante(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ]);
        $payable = $this->payable('aguardando_conciliacao');

        $this->actingAs($user)
            ->post($this->updateUrl($payable), [
                'file' => UploadedFile::fake()->create('negado.pdf', 40, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete($this->updateUrl($payable))
            ->assertForbidden();

        $this->assertDatabaseMissing('payable_documents', [
            'payable_id' => $payable->id,
            'doc_type' => 'comprovacao',
        ]);
    }

    public function test_endpoint_generico_nao_permite_burlar_gestao_do_comprovante(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ]);
        $payable = $this->payable('pendente');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/documentos", [
                'file' => UploadedFile::fake()->create('negado-generico.pdf', 40, 'application/pdf'),
                'type' => 'comprovacao',
            ])
            ->assertForbidden();

        $doc = PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'existente.pdf',
            'doc_type' => 'comprovacao',
            'path' => 'payables/docs/existente.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
        ]);

        $this->actingAs($user)
            ->delete("/financeiro/contas-pagar/{$payable->id}/documentos/{$doc->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('payable_documents', ['id' => $doc->id]);
    }
}
