<?php

namespace Tests\Browser;

use App\Models\Comercial\Faturamento;
use App\Models\User;
use Laravel\Dusk\Browser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
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
                ->waitFor('@btn-adicionar-local', 10)
                ->click('@btn-adicionar-local')
                ->waitForText('Adicionar Local', 5)
                ->waitFor('@input-novo-local', 5)
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

    public function test_exportar_excel_mostra_sucesso(): void
    {
        $nome = 'Local Export Fat ' . uniqid();
        Faturamento::create(['ano' => 2025, 'local_nome' => $nome, 'jan' => 1234.56]);

        $this->browse(function (Browser $browser) use ($nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/faturamento')
                ->waitForText('Faturamento', 10)
                ->waitForText($nome, 10)
                ->click('@fat-exportar')
                ->waitForText('exportada', 5);
        });

        Faturamento::where('local_nome', $nome)->delete();
    }

    public function test_importar_excel_preenche_e_salva(): void
    {
        $nome = 'Local Import Fat ' . uniqid();
        Faturamento::where('local_nome', 'like', 'Local Import Fat%')->delete();

        // Fixture: cabeçalho Local + 12 meses, uma linha de dados.
        $path = storage_path('app/dusk_faturamento_import.xlsx');
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            ['Local', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            [$nome, 1000, 2000, 0, 0, 0, 0, 0, 0, 5000, 0, 0, 0],
        ], null, 'A1');
        (new XlsxWriter($ss))->save($path);

        try {
            $this->browse(function (Browser $browser) use ($nome, $path) {
                $browser->loginAs($this->bruno())
                    ->visit('/comercial/faturamento')
                    ->waitForText('Faturamento', 10)
                    // Ano ativo = 2025 por padrão.
                    ->attach('@fat-importar', $path)
                    ->waitForText('Importado para 2025', 10)
                    // A linha importada aparece na tabela.
                    ->waitForText($nome, 10)
                    // Aguarda o toast sumir (sobrepõe o botão Salvar no canto).
                    ->pause(2800)
                    // Salva e confirma persistência.
                    ->click('@btn-salvar')
                    ->waitForText('Faturamento salvo', 10);
            });

            $this->assertDatabaseHas('bs_comercial_faturamento', [
                'ano' => 2025,
                'local_nome' => $nome,
                'jan' => 1000,
                'setembro' => 5000,
            ]);
        } finally {
            @unlink($path);
            Faturamento::where('local_nome', $nome)->delete();
        }
    }
}
