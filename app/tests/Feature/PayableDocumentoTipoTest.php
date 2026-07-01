<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documentos separados por tipo (feedback do cliente): Nota Fiscal, Boleto,
 * Relatório, Comprovação, Outro. O anexo carrega o tipo escolhido.
 */
class PayableDocumentoTipoTest extends TestCase
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
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => $status,
        ]);
    }

    private function docUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/documentos";
    }

    public function test_anexa_documento_com_tipo(): void
    {
        Storage::fake('public');
        $user = $this->activeUser();
        $payable = $this->makePayable();

        $this->actingAs($user)
            ->post($this->docUrl($payable), [
                'file' => UploadedFile::fake()->create('nf.pdf', 100, 'application/pdf'),
                'type' => 'nota_fiscal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payable_documents', [
            'payable_id' => $payable->id,
            'name' => 'nf.pdf',
            'doc_type' => 'nota_fiscal',
        ]);
    }

    public function test_tipo_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user = $this->activeUser();
        $payable = $this->makePayable();

        $this->actingAs($user)
            ->postJson($this->docUrl($payable), [
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                'type' => 'tipo_inexistente',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_sem_tipo_usa_outro(): void
    {
        Storage::fake('public');
        $user = $this->activeUser();
        $payable = $this->makePayable();

        $this->actingAs($user)
            ->post($this->docUrl($payable), [
                'file' => UploadedFile::fake()->create('sem-tipo.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payable_documents', [
            'payable_id' => $payable->id,
            'name' => 'sem-tipo.pdf',
            'doc_type' => 'outro',
        ]);
    }

    public function test_comprovante_do_pagamento_e_classificado_como_comprovacao(): void
    {
        Storage::fake('public');
        $pagador = $this->activeUser();
        PayableRole::create(['role' => 'pagador', 'user_id' => $pagador->id]);
        $payable = $this->makePayable('aprovado');

        $this->actingAs($pagador)
            ->post("/financeiro/contas-pagar/{$payable->id}/registrar-pagamento", [
                'paid_at' => now()->toDateString(),
                'file' => UploadedFile::fake()->create('comprovante.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payable_documents', [
            'payable_id' => $payable->id,
            'name' => 'comprovante.pdf',
            'doc_type' => 'comprovacao',
        ]);
    }
}
