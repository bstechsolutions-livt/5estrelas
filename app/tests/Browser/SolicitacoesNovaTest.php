<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Solicitacao;
use App\Models\SolicitacaoAssunto;
use App\Models\SolicitacaoCampos;
use App\Models\SolicitacaoCom;
use App\Models\SolicitacaoMov;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela "Novo Ticket" (/solicitacoes/nova).
 *
 * Cobre: render da tela (sem travar em loading) e o fluxo de criação de ponta a
 * ponta pela UI — selecionar departamento (o assunto é auto-selecionado quando há
 * apenas um), preencher título/descrição e submeter, confirmando a persistência
 * em `intranet_solicitacao`.
 *
 * Roda contra o servidor local com o banco real (Postgres de dev). Bruno tem
 * permissão wildcard ('*').
 */
class SolicitacoesNovaTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    /**
     * Cria um departamento ativo com UM assunto ativo cujos campos "titulo" e
     * "descricao" estão habilitados (para que os inputs apareçam no formulário).
     *
     * @return array{dept: Department, assunto: SolicitacaoAssunto, sufixo: string}
     */
    private function criarCenario(): array
    {
        $sufixo = strtoupper(substr(uniqid(), -6));

        // Garante que existe pelo menos uma filial ativa (necessária para
        // props.filiais no frontend e para o campo filial_id do ticket).
        $branch = Branch::firstOrCreate(
            ['code' => 'DUSK'],
            ['name' => 'Filial Dusk QA', 'is_active' => true, 'cnpj' => '00000000000000']
        );

        $dept = Department::create([
            'name' => 'Dusk Nova QA '.$sufixo,
            'is_active' => true,
        ]);

        $assunto = new SolicitacaoAssunto;
        $assunto->department_id = $dept->id;
        $assunto->assunto = 'Assunto Nova '.$sufixo;
        $assunto->ativo = 'S';
        $assunto->prioridade = 'media';
        $assunto->save();

        // Habilita os campos "titulo" e "descricao" para este assunto (a existência da
        // linha em intranet_solicitacao_campos é o que marca o campo como ativo).
        foreach (['titulo', 'descricao'] as $descricao) {
            SolicitacaoCampos::create([
                'assunto_id' => $assunto->id,
                'descricao' => $descricao,
                'obrigatorio' => 0,
                'tipo' => 'texto',
            ]);
        }

        return ['dept' => $dept, 'assunto' => $assunto, 'sufixo' => $sufixo];
    }

    private function limpar(array $cenario): void
    {
        $tickets = Solicitacao::where('assunto_id', $cenario['assunto']->id)->pluck('id');
        foreach ($tickets as $id) {
            SolicitacaoCom::where('solicitacao_id', $id)->forceDelete();
            SolicitacaoMov::where('solicitacao_id', $id)->delete();
            \App\Models\BsFilialDeptoSelect::where('solicitacao_id', $id)->delete();
        }
        Solicitacao::where('assunto_id', $cenario['assunto']->id)->delete();
        SolicitacaoCampos::where('assunto_id', $cenario['assunto']->id)->delete();
        $cenario['assunto']->delete();
        $cenario['dept']->delete();
    }

    /** Escapa um texto para uso seguro em expressão XPath. */
    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }
        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        return "concat('".str_replace("'", "',\"'\",'", $value)."')";
    }

    public function test_tela_nova_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/nova')
                ->waitForText('Novo Ticket', 20)
                ->assertSee('Novo Ticket')
                // Painel principal do formulário
                ->assertSee('Dados do Ticket')
                ->assertSee('Departamento Responsável')
                // Sidebar (grupo Tickets)
                ->assertSee('Acompanhar');
        });
    }

    public function test_criar_ticket_ponta_a_ponta(): void
    {
        $cenario = $this->criarCenario();
        $titulo = 'Titulo Dusk Nova '.$cenario['sufixo'];
        $descricao = 'Descricao gerada pelo teste Dusk '.$cenario['sufixo'];
        $persistido = false;

        try {
            $this->browse(function (Browser $browser) use ($cenario, $titulo, $descricao, &$persistido) {
                $browser->loginAs($this->bruno())
                    ->visit('/solicitacoes/nova')
                    ->waitForText('Novo Ticket', 20)
                    ->waitFor('@nova-departamento', 20);

                // Abre o Select de Departamento Responsável e seleciona o departamento do cenário.
                $browser->click('@nova-departamento')->pause(500);
                $browser->driver->findElement(WebDriverBy::xpath(
                    "//li[contains(@class,'p-select-option')][contains(normalize-space(.), ".$this->xpathLiteral($cenario['dept']->name).")]"
                ))->click();

                // Com um único assunto no departamento, ele é auto-selecionado (watch),
                // o que faz o formulário de título/descrição aparecer.
                $browser->waitFor('@nova-titulo', 20)
                    ->type('@nova-titulo', $titulo)
                    ->waitFor('@nova-descricao', 15)
                    ->type('@nova-descricao', $descricao);

                // Submete o ticket.
                $browser->scrollIntoView('@nova-submit')
                    ->pause(500)
                    ->click('@nova-submit')
                    ->pause(2000);

                // Confirma a persistência consultando o banco diretamente (robusto contra a
                // lentidão do servidor single-threaded de dev e o redirect pós-criação).
                $deadline = microtime(true) + 45;
                while (microtime(true) < $deadline) {
                    $persistido = Solicitacao::where('titulo', $titulo)
                        ->where('assunto_id', $cenario['assunto']->id)
                        ->exists();
                    if ($persistido) {
                        break;
                    }
                    usleep(400000);
                }
            });

            $this->assertTrue($persistido, 'O ticket deveria ter sido criado em intranet_solicitacao.');

            // Confere os dados principais persistidos.
            $this->assertDatabaseHas('intranet_solicitacao', [
                'titulo' => $titulo,
                'assunto_id' => $cenario['assunto']->id,
                'departamento_responsavel' => $cenario['dept']->name,
                'usuario_solicitante' => $this->bruno()->id,
            ]);
        } finally {
            $this->limpar($cenario);
        }
    }
}
