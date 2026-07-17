<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function activeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => '*'],
                ['label' => 'Admin', 'module' => 'sistema'],
            )->id,
        );

        return $user;
    }

    private function payable(): Payable
    {
        return Payable::create([
            'title_number' => 'TIMELINE-'.uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ]);
    }

    public function test_show_exibe_apenas_atividades_automaticas_e_decisoes(): void
    {
        $user = $this->activeAdmin();
        $payable = $this->payable();

        foreach ([
            ['body' => 'Comentário manual antigo', 'type' => 'comment'],
            ['body' => 'Enviado para aprovação', 'type' => 'status_change'],
            ['body' => 'Aprovado com observação', 'type' => 'approval'],
            ['body' => 'Reprovado por divergência', 'type' => 'rejection'],
        ] as $comment) {
            PayableComment::create([
                'payable_id' => $payable->id,
                'user_id' => $user->id,
                ...$comment,
            ]);
        }

        $this->actingAs($user)
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payable.comments', 3)
                ->where('payable.comments.0.type', 'status_change')
                ->where('payable.comments.1.type', 'approval')
                ->where('payable.comments.2.type', 'rejection')
                ->missing('mentionableUsers'));
    }

    public function test_endpoint_de_comentario_manual_nao_esta_disponivel(): void
    {
        $user = $this->activeAdmin();
        $payable = $this->payable();

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/comentarios", [
                'body' => 'Tentativa de comentário avulso',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('payable_comments', [
            'payable_id' => $payable->id,
            'body' => 'Tentativa de comentário avulso',
        ]);
    }
}
