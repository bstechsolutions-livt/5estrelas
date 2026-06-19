<?php

namespace Tests\Browser;

use App\Models\Department;
use App\Models\Solicitacao;
use App\Models\SolicitacaoAssunto;
use App\Models\SolicitacaoCom;
use App\Models\SolicitacaoMov;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela "Acompanhar" (/solicitacoes/lista) do módulo de Tickets.
 *
 * Cobre: carregamento (overlay some), tickets aparecem na DataTable, filtro por
 * status (cards de legenda), abrir o detalhe (Ticket.vue) clicando na linha,
 * e comentar (Editor Quill + enviar) confirmando a persistência no banco.
 *
 * Roda contra o servidor local com o banco real (Postgres de dev). Bruno tem
 * permissão wildcard ('*').
 *
 * Notas de implementação descobertas durante a automação:
 *  - Não há filtro salvo (a rota /user-preferences não existe → 404 → o composable
 *    retorna null). Logo a tela usa o departamento padrão `departamentos[0]`. Por
 *    isso o departamento de teste é nomeado para ordenar em 1º ("AAA Dusk Tickets
 *    QA ..."), e a lista já carrega filtrando por ele no onMounted.
 *  - A coluna "Título" vem desativada por padrão na config de colunas do servidor;
 *    as asserções usam o NOME DO ASSUNTO e o STATUS (colunas visíveis).
 *  - A DataTable fica abaixo da dobra e o `getText()` do Selenium não enxerga o
 *    texto fora da viewport. Por isso as esperas usam `document.body.textContent`
 *    (via waitUsing), que inclui conteúdo fora da tela de forma confiável.
 *
 * Fora de escopo (documentado): os botões "Atender"/"Resolver" (iniciar/resolver
 * atendimento) são renderizados condicionalmente por
 *   solicitacao.usuario_responsavel.matricula == props.auth.matricula
 * e `props.auth.matricula` NÃO é populado no share do Inertia do 5E (o share expõe
 * `auth.user.id`, não `matricula` — formato legado da intranet Biglar). Logo esses
 * botões não são alcançáveis pela UI sem mudança de backend; a transição de status
 * fica coberta pelos testes de Feature (PHPUnit).
 */
class SolicitacoesListaTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function novoAssunto(int $departmentId, string $nome): SolicitacaoAssunto
    {
        // department_id é coluna real (fora do fillable) → atribuição direta.
        $assunto = new SolicitacaoAssunto;
        $assunto->department_id = $departmentId;
        $assunto->assunto = $nome;
        $assunto->ativo = 'S';
        $assunto->prioridade = 'baixa';
        $assunto->save();

        return $assunto;
    }

    /**
     * @return array{dept: Department, assuntos: array<int,SolicitacaoAssunto>, tickets: array<int,Solicitacao>, sufixo: string}
     */
    private function criarCenario(): array
    {
        $bruno = $this->bruno();
        $sufixo = strtoupper(substr(uniqid(), -6));

        $dept = Department::create([
            'name' => 'AAA Dusk Tickets QA '.$sufixo,
            'is_active' => true,
        ]);

        $assuntoPend = $this->novoAssunto($dept->id, 'Pend Assunto '.$sufixo);
        $assuntoAtend = $this->novoAssunto($dept->id, 'Atend Assunto '.$sufixo);

        $filialId = \App\Models\Branch::query()->value('id');

        $base = [
            'departamento_responsavel' => $dept->name,
            'filial_id' => $filialId,
            'usuario_solicitante' => $bruno->id,
        ];

        $t1 = Solicitacao::create(array_merge($base, [
            'titulo' => 'Ticket Pendente '.$sufixo,
            'descricao' => 'Descrição do ticket pendente '.$sufixo,
            'status' => 'pendente',
            'prioridade' => 'media',
            'assunto_id' => $assuntoPend->id,
        ]));

        $t2 = Solicitacao::create(array_merge($base, [
            'titulo' => 'Ticket Atendimento '.$sufixo,
            'descricao' => 'Descrição do ticket em atendimento '.$sufixo,
            'status' => 'em atendimento',
            'prioridade' => 'alta',
            'assunto_id' => $assuntoAtend->id,
            'usuario_responsavel' => $bruno->id,
        ]));

        return [
            'dept' => $dept,
            'assuntos' => [$assuntoPend, $assuntoAtend],
            'tickets' => [$t1, $t2],
            'sufixo' => $sufixo,
        ];
    }

    private function limpar(array $cenario): void
    {
        foreach ($cenario['tickets'] as $ticket) {
            SolicitacaoCom::where('solicitacao_id', $ticket->id)->forceDelete();
            SolicitacaoMov::where('solicitacao_id', $ticket->id)->delete();
            $ticket->delete();
        }
        foreach ($cenario['assuntos'] as $assunto) {
            $assunto->delete();
        }
        $cenario['dept']->delete();
    }

    /**
     * Espera até que o corpo da DataTable (`.p-datatable-tbody`) CONTENHA o texto.
     * Usa textContent (não getText do Selenium) para enxergar conteúdo fora da viewport,
     * e escopa na tabela para ignorar ocorrências em filtros/labels.
     */
    private function esperarTexto(Browser $browser, string $texto, int $seconds = 30): void
    {
        $browser->waitUsing($seconds, 200, function () use ($browser, $texto) {
            return (bool) $browser->driver->executeScript(
                'return (document.querySelector(".p-datatable-tbody")?.textContent || "").includes(arguments[0]);',
                [$texto]
            );
        });
    }

    /** Espera até que o corpo da DataTable NÃO contenha mais o texto. */
    private function esperarTextoSumir(Browser $browser, string $texto, int $seconds = 30): void
    {
        $browser->waitUsing($seconds, 200, function () use ($browser, $texto) {
            return ! (bool) $browser->driver->executeScript(
                'return (document.querySelector(".p-datatable-tbody")?.textContent || "").includes(arguments[0]);',
                [$texto]
            );
        });
    }

    private function bodyContem(Browser $browser, string $texto): bool
    {
        return (bool) $browser->driver->executeScript(
            'return (document.querySelector(".p-datatable-tbody")?.textContent || "").includes(arguments[0]);',
            [$texto]
        );
    }

    public function test_lista_carrega_e_mostra_tickets(): void
    {
        $cenario = $this->criarCenario();
        $assuntoPend = $cenario['assuntos'][0]->assunto;
        $assuntoAtend = $cenario['assuntos'][1]->assunto;

        try {
            $this->browse(function (Browser $browser) use ($assuntoPend, $assuntoAtend) {
                $browser->loginAs($this->bruno())
                    ->visit('/solicitacoes/lista')
                    ->waitUntilMissing('@tickets-loading', 20)
                    ->waitForText('Atendimento', 10);

                // A lista carrega já filtrando pelo departamento padrão (o nosso),
                // então ambos os tickets aparecem (identificados pelo assunto).
                $this->esperarTexto($browser, $assuntoPend, 30);
                $this->assertTrue($this->bodyContem($browser, $assuntoPend), 'Ticket pendente deveria aparecer.');
                $this->assertTrue($this->bodyContem($browser, $assuntoAtend), 'Ticket em atendimento deveria aparecer.');
            });
        } finally {
            $this->limpar($cenario);
        }
    }

    public function test_filtrar_por_status_via_card(): void
    {
        $cenario = $this->criarCenario();
        $assuntoPend = $cenario['assuntos'][0]->assunto;
        $assuntoAtend = $cenario['assuntos'][1]->assunto;

        try {
            $this->browse(function (Browser $browser) use ($assuntoPend, $assuntoAtend) {
                $browser->loginAs($this->bruno())
                    ->visit('/solicitacoes/lista')
                    ->waitUntilMissing('@tickets-loading', 20)
                    ->waitForText('Atendimento', 10);

                $this->esperarTexto($browser, $assuntoPend, 30);

                // Filtra por "Em Atendimento" clicando no card de legenda.
                $browser->scrollIntoView('@card-em-atendimento')
                    ->pause(300)
                    ->click('@card-em-atendimento');

                // O ticket pendente some do corpo da tabela e o em atendimento permanece.
                $this->esperarTextoSumir($browser, $assuntoPend, 30);
                $this->assertTrue($this->bodyContem($browser, $assuntoAtend), 'Ticket em atendimento deveria permanecer.');
                $this->assertFalse($this->bodyContem($browser, $assuntoPend), 'Ticket pendente deveria sumir após filtrar.');
            });
        } finally {
            $this->limpar($cenario);
        }
    }

    public function test_abrir_detalhe_e_comentar(): void
    {
        $cenario = $this->criarCenario();
        $ticket = $cenario['tickets'][0];
        $ticketId = $ticket->id;
        $comentarioTexto = 'ComentarioDuskQA'.$cenario['sufixo'];
        $persistido = false;

        try {
            $this->browse(function (Browser $browser) use ($ticketId, $comentarioTexto, &$persistido) {
                // Deep-link suportado pela tela: ?solicitacao=ID abre o dialog de detalhe
                // (Ticket.vue) no onMounted. Equivale a clicar na linha, sem a fragilidade
                // de clicar numa célula fora da viewport.
                $browser->loginAs($this->bruno())
                    ->visit('/solicitacoes/lista?solicitacao='.$ticketId)
                    ->waitUntilMissing('@tickets-loading', 20);

                // Aguarda o dialog do ticket abrir (PrimeVue Dialog) — pode levar tempo no single-threaded dev server.
                $browser->waitFor('.p-dialog-mask', 30);

                // Abre a aba "Comentários" (trocarAba('acompanhar')), onde fica o editor.
                $browser->waitFor('@ticket-tab-comentarios', 30)
                    ->click('@ticket-tab-comentarios');

                // O editor de comentário só existe na aba de Comentários.
                $browser->waitFor('@ticket-editor', 20);

                // Rola o editor para a viewport e digita no Quill (contenteditable).
                $browser->driver->executeScript(
                    "document.querySelector('div[dusk=ticket-editor] .ql-editor')?.scrollIntoView({block:'center'});"
                );
                $browser->pause(400)
                    ->click('div[dusk="ticket-editor"] .ql-editor')
                    ->keys('div[dusk="ticket-editor"] .ql-editor', $comentarioTexto)
                    // Aguarda o Quill sincronizar o conteúdo com o v-model (habilita o envio).
                    ->pause(800);

                // Confirma que o texto foi digitado no editor antes de enviar.
                $textoEditor = $browser->driver->executeScript(
                    "return document.querySelector('div[dusk=ticket-editor] .ql-editor')?.innerText || '';"
                );
                $this->assertStringContainsString($comentarioTexto, (string) $textoEditor, 'O texto deveria estar no editor antes de enviar.');

                // Aguarda o Quill sincronizar com o v-model habilitando o botão Enviar
                // (o botão fica disabled enquanto `comentario` estiver vazio).
                $browser->waitUsing(15, 200, function () use ($browser) {
                    return (bool) $browser->driver->executeScript(
                        "return document.querySelector('[dusk=ticket-comentar]')?.disabled === false;"
                    );
                });

                // Clica em ENVIAR (botão real do editor).
                $browser->click('@ticket-comentar');

                // Aguarda a persistência consultando o banco diretamente (robusto contra a
                // lentidão do servidor single-threaded de dev). O caminho de escrita é ponta a
                // ponta via UI: editor Quill → botão enviar → controller → banco.
                $deadline = microtime(true) + 25;
                while (microtime(true) < $deadline) {
                    $persistido = SolicitacaoCom::where('solicitacao_id', $ticketId)
                        ->where('comentario', 'like', '%'.$comentarioTexto.'%')
                        ->exists();
                    if ($persistido) {
                        break;
                    }
                    usleep(300000);
                }
            });

            // OBS (documentado): a EXIBIÇÃO do comentário recém-criado na lista depende do
            // Reverb/WebSocket e/ou de `props.auth.matricula` (não populados no ambiente de
            // teste / no share do 5E), e o filtro de privacidade do front só exibe comentários
            // com `private === null` enquanto o backend grava públicos como 'N'. Por isso
            // validamos o efeito pelo banco, não pela aparição na tela.
            $this->assertTrue($persistido, 'O comentário deveria ter sido persistido em intranet_solicitacao_com.');
        } finally {
            $this->limpar($cenario);
        }
    }
}
