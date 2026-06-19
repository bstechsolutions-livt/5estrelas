<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayablePagamentoTest extends TestCase
{
    use RefreshDatabase;

    /** Usuário ativo com as permissões dadas (default: ver contas a pagar). */
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

    /** Pagador: usuário com permissão de ver o módulo + associado ao papel `pagador`. */
    private function pagador(bool $active = true): User
    {
        $user = $this->activeUser();
        if (! $active) {
            $user->update(['is_active' => false]);
        }
        PayableRole::create(['role' => 'pagador', 'user_id' => $user->id]);

        return $user;
    }

    private function makePayable(string $status = 'aprovado'): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1500.50,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    private function payUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/registrar-pagamento";
    }

    public function test_pagador_registra_pagamento(): void
    {
        $pagador = $this->pagador();
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->post($this->payUrl($payable), ['paid_at' => now()->toDateString(), 'payment_method' => 'PIX'])
            ->assertRedirect();

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'status' => 'pago',
            'paid_by' => $pagador->id,
            'payment_method' => 'PIX',
        ]);
        $this->assertDatabaseHas('payable_comments', ['payable_id' => $payable->id, 'type' => 'payment']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.pago', 'auditable_id' => $payable->id]);
    }

    public function test_nao_pagador_com_curinga_recebe_403(): void
    {
        // Tem '*' (acesso total), mas NÃO está na alçada como pagador.
        $admin = $this->activeUser(['*']);
        $payable = $this->makePayable('aprovado');

        $this->actingAs($admin)
            ->post($this->payUrl($payable), ['paid_at' => now()->toDateString()])
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aprovado']);
    }

    public function test_pagador_inativo_recebe_403(): void
    {
        $pagador = $this->pagador(active: false);
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->post($this->payUrl($payable), ['paid_at' => now()->toDateString()])
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aprovado']);
    }

    public function test_titulo_nao_aprovado_nao_pode_ser_pago(): void
    {
        $pagador = $this->pagador();
        $payable = $this->makePayable('pendente');

        $this->actingAs($pagador)
            ->post($this->payUrl($payable), ['paid_at' => now()->toDateString()])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pendente']);
        $this->assertDatabaseMissing('payable_comments', ['payable_id' => $payable->id, 'type' => 'payment']);
    }

    public function test_data_de_pagamento_futura_e_invalida(): void
    {
        $pagador = $this->pagador();
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->postJson($this->payUrl($payable), ['paid_at' => now()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_at']);
    }

    public function test_forma_de_pagamento_fora_da_lista_e_invalida(): void
    {
        $pagador = $this->pagador();
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->postJson($this->payUrl($payable), [
                'paid_at' => now()->toDateString(),
                'payment_method' => 'BITCOIN',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_comprovante_e_armazenado_como_documento(): void
    {
        Storage::fake('public');
        $pagador = $this->pagador();
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->post($this->payUrl($payable), [
                'paid_at' => now()->toDateString(),
                'file' => UploadedFile::fake()->create('comprovante.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payable_documents', ['payable_id' => $payable->id, 'name' => 'comprovante.pdf']);
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    public function test_pagamento_e_idempotente(): void
    {
        $pagador = $this->pagador();
        $payable = $this->makePayable('aprovado');
        $url = $this->payUrl($payable);

        $this->actingAs($pagador)->post($url, ['paid_at' => now()->toDateString()])->assertRedirect();
        $this->actingAs($pagador)->post($url, ['paid_at' => now()->toDateString()])->assertRedirect();

        $this->assertEquals('pago', $payable->fresh()->status);
        $this->assertEquals(1, PayableComment::where('payable_id', $payable->id)->where('type', 'payment')->count());
        $this->assertEquals(1, AuditLog::where('event', 'contas_pagar.pago')->where('auditable_id', $payable->id)->count());
    }

    public function test_sem_pagador_configurado_ninguem_paga(): void
    {
        // Ninguém na alçada como pagador; mesmo com '*', 403.
        $admin = $this->activeUser(['*']);
        $payable = $this->makePayable('aprovado');

        $this->actingAs($admin)
            ->post($this->payUrl($payable), ['paid_at' => now()->toDateString()])
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'aprovado']);
    }
}
