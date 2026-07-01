<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A1 — Documento obrigatório para aprovar.
 *
 * Feedback do cliente: todo lançamento deve conter documentos (NF, boleto,
 * relatório, comprovação). Nem todo tem tudo, mas NÃO pode ser aprovado "sem nada".
 * A trava age no envio para aprovação e como guarda na própria aprovação.
 */
class PayableDocumentoObrigatorioTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $keys = ['financeiro.contas_pagar.visualizar']): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    private function makePayable(string $status = 'pendente'): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1500.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    private function addDocument(Payable $payable, User $uploader): PayableDocument
    {
        return PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $uploader->id,
            'name' => 'nota-fiscal.pdf',
            'path' => 'payables/docs/nota-fiscal.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);
    }

    /** Trilha mínima da matriz (1 nível sem assigned_to) para o envio criar step. */
    private function seedMatrizTrail(): void
    {
        ApprovalTrail::create([
            'area' => 'matriz', 'order' => 1, 'level_name' => 'departamento',
            'role_label' => 'Departamento', 'default_user_id' => null,
        ]);
    }

    private function sendUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/enviar-aprovacao";
    }

    private function approveUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/aprovar";
    }

    // ─── Envio para aprovação ────────────────────────────────────────────

    public function test_nao_envia_para_aprovacao_sem_documento(): void
    {
        $user = $this->activeUser(['*']);
        $payable = $this->makePayable('pendente');

        $this->actingAs($user)
            ->post($this->sendUrl($payable))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Não avançou: continua pendente e sem steps criados.
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pendente']);
        $this->assertDatabaseMissing('approval_steps', ['payable_id' => $payable->id]);
    }

    public function test_envia_para_aprovacao_com_documento(): void
    {
        $this->seedMatrizTrail();
        $user = $this->activeUser(['*']);
        $payable = $this->makePayable('pendente');
        $this->addDocument($payable, $user);

        $this->actingAs($user)
            ->post($this->sendUrl($payable))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aguardando_aprovacao']);
    }

    // ─── Aprovação ───────────────────────────────────────────────────────

    public function test_nao_aprova_sem_documento(): void
    {
        $user = $this->activeUser(['*']);
        $payable = $this->makePayable('aguardando_aprovacao');

        $this->actingAs($user)
            ->post($this->approveUrl($payable))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aguardando_aprovacao']);
    }

    public function test_aprova_com_documento(): void
    {
        $this->seedMatrizTrail();
        $user = $this->activeUser(['*']);
        $payable = $this->makePayable('pendente');
        $this->addDocument($payable, $user);

        // Envia (com doc) → cria o step 'departamento' (assigned_to null).
        $this->actingAs($user)->post($this->sendUrl($payable))->assertRedirect();
        $this->assertEquals('aguardando_aprovacao', $payable->fresh()->status);

        // Aprova com wildcard: a trava de documento NÃO deve bloquear (tem anexo).
        $this->actingAs($user)
            ->post($this->approveUrl($payable))
            ->assertRedirect()
            ->assertSessionMissing('error');

        // Único nível → título fica aprovado.
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aprovado']);
    }
}
