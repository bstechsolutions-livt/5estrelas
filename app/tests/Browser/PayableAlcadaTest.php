<?php

namespace Tests\Browser;

use App\Models\PayableRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Alçada — Contas a Pagar.
 * Renderização dos papéis + adicionar/remover responsável (clicando de verdade).
 * Bruno tem permissão wildcard no ambiente local.
 */
class PayableAlcadaTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_renderiza_os_papeis(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar/alcada')
                ->waitFor('@alcada-page', 10)
                ->assertPresent('@alcada-role-pagador')
                ->assertPresent('@alcada-role-conciliador')
                ->assertPresent('@alcada-role-assinante')
                ->assertSee('Pagador')
                ->assertSee('Conciliador')
                ->assertSee('Assinante')
                // Descrições dos papéis
                ->assertSee('Registra o pagamento dos títulos aprovados.')
                ->assertSee('Concilia os pagamentos com o extrato bancário (Spec 2).')
                ->assertSee('Assina e encerra a conciliação');
        });
    }

    public function test_adicionar_usuario_a_um_papel(): void
    {
        $email = 'alcada.add.' . uniqid() . '@5estrelas.test';
        $alvo = User::create([
            'name' => 'Teste Add Alcada',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Garante que começa fora do papel pagador
        PayableRole::where('role', 'pagador')->where('user_id', $alvo->id)->delete();

        $this->browse(function (Browser $browser) use ($alvo) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar/alcada')
                ->waitFor('@alcada-page', 10)
                // PrimeVue Select: clicar para abrir overlay, filtrar, selecionar opção
                ->click('@alcada-select-pagador')
                ->waitFor('.p-select-overlay', 5)
                ->type('.p-select-overlay input', $alvo->name)
                ->pause(500)
                ->click('.p-select-overlay .p-select-option')
                ->pause(300)
                // Clicar no botão Adicionar
                ->click('@alcada-add-pagador')
                // Esperar toast de sucesso e usuário na lista
                ->waitForText('Pronto', 10)
                ->waitFor("@alcada-remove-pagador-{$alvo->id}", 10)
                ->assertSee($alvo->name);
        });

        $this->assertDatabaseHas('payable_roles', ['role' => 'pagador', 'user_id' => $alvo->id]);

        // Cleanup
        PayableRole::where('role', 'pagador')->where('user_id', $alvo->id)->delete();
        $alvo->delete();
    }

    public function test_remover_usuario_de_um_papel(): void
    {
        $email = 'alcada.rem.' . uniqid() . '@5estrelas.test';
        $alvo = User::create([
            'name' => 'Teste Rem Alcada',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Associar ao papel conciliador diretamente no banco
        PayableRole::firstOrCreate(['role' => 'conciliador', 'user_id' => $alvo->id]);

        $this->browse(function (Browser $browser) use ($alvo) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar/alcada')
                ->waitFor('@alcada-page', 10)
                // Verifica que o usuário está na lista
                ->assertSee($alvo->name)
                // Clica no botão remover
                ->click("@alcada-remove-conciliador-{$alvo->id}")
                // Espera toast de sucesso
                ->waitForText('Pronto', 10)
                // Verifica que o usuário sumiu
                ->assertDontSee($alvo->name);
        });

        $this->assertDatabaseMissing('payable_roles', ['role' => 'conciliador', 'user_id' => $alvo->id]);

        // Cleanup
        $alvo->delete();
    }
}
