<?php

namespace Tests\Browser;

use App\Models\Comercial\Faturamento;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Faturamento.
 * Rodam contra o servidor local com o banco real.
 *
 * Observação: rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialFaturamentoTest).
 */
class ComercialFaturamentoTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_faturamento_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/faturamento')
                ->waitForText('Faturamento', 10)
                ->assertSee('Faturamento')
                // Ações do cabeçalho
                ->assertSee('Adicionar local')
                ->assertSee('Salvar')
                ->assertSee('Importar Excel')
                // Tabs de ano
                ->assertSee('2025')
                ->assertSee('2026')
                ->assertSee('Comparativo');
        });
    }

    public function test_adicionar_local_e_salvar(): void
    {
        $nome = 'Dusk Faturamento QA '.uniqid();

        // Limpa qualquer remanescente para a assertiva ser estável.
        Faturamento::where('local_nome', 'like', 'Dusk Faturamento QA%')->delete();

        $this->browse(function (Browser $browser) use ($nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/faturamento')
                ->waitForText('Faturamento', 10)
                // Abre o modal "Adicionar Local"
                ->click('@btn-adicionar-local')
                ->waitForText('Adicionar Local', 5)
                ->type('@input-novo-local', $nome)
                ->press('Adicionar')
                // A linha nova aparece na tabela
                ->waitForText($nome, 5)
                ->assertSee($nome)
                // Salva
                ->click('@btn-salvar')
                ->waitForText('Faturamento salvo', 5)
                ->assertSee('Faturamento salvo');
        });

        // Confirma persistência (salvar faz upsert do local recém-criado).
        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'ano' => 2025,
            'local_nome' => $nome,
        ]);

        Faturamento::where('local_nome', $nome)->delete();
    }

    public function test_troca_aba_comparativo(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/faturamento')
                ->waitForText('Faturamento', 10)
                ->click('@tab-comp')
                // Tabela comparativa 2025 vs 2026 aparece.
                // O título tem text-transform:uppercase via CSS, então o Selenium
                // "vê" o texto em MAIÚSCULAS (mesmo gotcha das telas Cotação/Propostas).
                ->waitForText('FATURAMENTO MENSAL — 2025 VS 2026', 5)
                ->assertSee('FATURAMENTO MENSAL — 2025 VS 2026');
        });
    }
}
