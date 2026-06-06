<?php

namespace App\Http\Controllers;

use App\Exports\SolicitacoesCamposExport;
use App\Helpers\LogHelper;
use App\Models\AgendamentoAnexos;
use App\Models\BsFilialDeptoSelect;
use App\Models\File;
use App\Models\Filial;
use App\Models\Funcionario;
use App\Models\IntranetParametro;
use App\Models\Regional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoAgendamento;
use App\Models\SolicitacaoAgendSol;
use App\Models\SolicitacaoAprovacao;
use App\Models\SolicitacaoArq;
use App\Models\SolicitacaoAssunto;
use App\Models\SolicitacaoAssuntoLiberacao;
use App\Models\SolicitacaoAssuntoModelo;
use App\Models\SolicitacaoAssuntoResponsavel;
use App\Models\SolicitacaoCAcessos;
use App\Models\SolicitacaoCampos;
use App\Models\SolicitacaoCDest;
use App\Models\SolicitacaoCEquip;
use App\Models\SolicitacaoCom;
use App\Models\SolicitacaoCRot;
use App\Models\SolicitacaoCVendas;
use App\Models\SolicitacaoEquipamentos;
use App\Models\SolicitacaoFluxo;
use App\Models\SolicitacaoFluxoDecisao;
use App\Models\SolicitacaoFluxoEtapa;
use App\Models\SolicitacaoFluxoEtapaCampo;
use App\Models\SolicitacaoFluxoEtapaCampoValor;
use App\Models\SolicitacaoFluxoEtapaResponsavel;
use App\Models\SolicitacaoFluxoExecucao;
use App\Models\SolicitacaoMov;
use App\Models\SolicitacaoSelecao;
use App\Models\SolicitacaoSelecaoItem;
use App\Models\SolicitacaoSelecaoResposta;
use App\Services\SolicitacaoReverbService;
use App\Services\WorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SolicitacoesController extends Controller
{
    public function indexConfiguracoes()
    {
        // Adaptado (5E): departamentos vêm da tabela `departments` (ativos),
        // no lugar de IntranetParametro/DEP_ATIVOS (ERP legado).
        $departamentosRaw = \App\Models\Department::where('is_active', true)
            ->orderBy('name')
            ->get();

        $departamentos = [];
        foreach ($departamentosRaw as $departamento) {
            array_push($departamentos, [
                'nome' => $departamento->name,
                'assuntos' => SolicitacaoAssunto::where('department_id', $departamento->id)
                    ->with(['modelos.arquivo'])
                    ->get(),
            ]);
        }

        return Inertia::render('Solicitacoes/Configuracoes/Index', [
            'departamentos' => $departamentos,
        ]);
    }

    public function getDepartamentos()
    {
        // Adaptado (5E): departamentos vêm da nossa tabela `departments`,
        // separados por is_active (ativos/inativos), no lugar de ERP-legado/intranet_parametros.
        $departamentos = \App\Models\Department::orderBy('name')->get();

        $ativos = [];
        $inativos = [];
        foreach ($departamentos as $departamento) {
            $item = [
                'label' => $departamento->name,
                'value' => $departamento->id,
            ];

            if ($departamento->is_active) {
                $ativos[] = $item;
            } else {
                $inativos[] = $item;
            }
        }

        return response()->json([
            'ativos' => $ativos,
            'inativos' => $inativos,
        ]);
    }

    public function storeDepartamentos(Request $request)
    {
        // Adaptado (5E): persiste a situação ativando/inativando o departamento
        // na tabela `departments` (no lugar de intranet_parametros DEP_ATIVOS).
        $inativos = $request[0];
        $ativos = $request[1];

        foreach ($inativos as $value) {
            \App\Models\Department::where('id', $value['value'])->update(['is_active' => false]);
        }

        foreach ($ativos as $value) {
            \App\Models\Department::where('id', $value['value'])->update(['is_active' => true]);
        }
    }

    public function indexNova()
    {

        // Buscar departamentos ativos para redirecionar chamados
        $departamentos = DB::table('intranet_parametros')
            ->where('menu', 'SOLICITACOES')
            ->where('submenu', 'CONFIGURACOES')
            ->where('parametro', 'DEP_ATIVOS')
            ->where('valor', 1)
            ->orderBy('condicao1')
            ->get();

        // Gera um array fixo com os campos
        $campos = [
            'titulo',
            'descricao',
            'arquivos',
            'equipamentos',
            'rotinas',
            'dados acesso',
            'usuario origem',
            'usuarios destino',
            'vendas pendentes',
            'menus intranet',
            'perfil intranet',
            'filial',
            'departamento',
        ];

        // Buscar responsáveis pelos departamentos
        foreach ($departamentos as $departamento) {
            $departamento->responsaveis = Funcionario::where('areaatuacao', $departamento->condicao1)->where('situacao', 'A')->select('matricula', 'nome')->get();

            $departamento->assuntos = SolicitacaoAssunto::where('departamento', $departamento->condicao1)
                ->where('ativo', 'S')
                ->with(['modelos.arquivo', 'liberacoes', 'responsaveis.funcionario:matricula,nome'])
                ->orderBy('assunto')
                ->get();
            foreach ($departamento->assuntos as $assunto) {
                $assunto->responsavel = $assunto->responsavel ? intval($assunto->responsavel) : null;

                // PREENCHER CAMPOS
                $camposArray = [];
                foreach ($campos as $campo) {
                    $campoTmp = SolicitacaoCampos::where('assunto_id', $assunto->id)->where('descricao', $campo)->first();

                    if ($campoTmp) {
                        $camposArray[] = [
                            'descricao' => $campo,
                            'ativo' => true,
                            'obrigatorio' => $campoTmp->obrigatorio == 0 ? false : true,
                            'observacao' => $campoTmp->observacao,
                            'tipo' => $campoTmp->tipo ?? 'texto',
                            'opcoes_titulo' => $campoTmp->opcoes_titulo ?? [],
                        ];
                    } else {

                        $camposArray[] = [
                            'descricao' => $campo,
                            'ativo' => false,
                            'obrigatorio' => false,
                            'observacao' => '',
                            'tipo' => 'texto',
                            'opcoes_titulo' => [],
                        ];
                    }
                }

                $selects = SolicitacaoSelecao::where('ASSUNTO_ID', $assunto->id)->orderBy('ordem')->get();
                foreach ($selects as $select) {
                    $itens = SolicitacaoSelecaoItem::where('selecao_id', $select->id)->orderBy('valor')->get(['id', 'valor']);
                    $select->valores = $itens->map(function ($item) {
                        return [
                            'code' => $item->id,
                            'label' => $item->valor,
                        ];
                    });
                }
                // dump($selects);

                // Atribuir os campos ao objeto $assunto
                $assunto->setAttribute('campos', $camposArray);
                $assunto->setAttribute('selects', $selects);
            }
        }

        // Buscar filiais ativas
        $filiais = UtilController::filiaisUsuarioStatic();

        // Incluir caixas da filial na fila
        foreach ($filiais as $filial) {
            // TODO: integrar caixas (ERP-legado era ERP legado) — sem equivalente no 5E
            $filial->caixas = collect();
        }

        // Filial do usuário (5E: via branch_user; fallback para a primeira filial vinculada)
        $filialUsuario = intval(optional(auth()->user()?->branches()->first())->id);

        // Se a filial do usuário for null ou 0, considerar como filial 2
        if (! $filialUsuario || $filialUsuario === 0) {
            $filialUsuario = 2;
        }

        $solicitante['nome'] = auth()->user()?->name;
        $solicitante['matricula'] = auth()->id();
        $solicitante['departamento'] = auth()->user()?->department_id;

        // Dptos (5E: tabela `departments`, no lugar de ERP-legado/ERP legado)
        $dptos = \App\Models\Department::orderBy('name')
            ->get()
            ->map(fn($d) => (object) ['codepto' => $d->id, 'descricao' => $d->name]);

        // TODO: integrar bancos/moedas/centros de custo (eram tabelas ERP legado) — sem equivalente no 5E
        $bancos = collect();
        $moedas = collect();
        $centrosCusto = collect();

        return Inertia::render('Solicitacoes/Nova/Index', [
            'departamentos' => $departamentos,
            'filialUsuario' => $filialUsuario,
            'filiais' => $filiais,
            'solicitante' => $solicitante,
            'aplicacao' => 'intranet',
            'dptos' => $dptos,
            'bancos' => $bancos,
            'moedas' => $moedas,
            'centrosCusto' => $centrosCusto,
        ]);
    }

    public function getAssuntos(Request $request)
    {
        // Obter os assuntos do departamento
        // Na configuração, incluir assuntos inativos para permitir reativação
        $query = SolicitacaoAssunto::where('departamento', $request->input('departamento'))
            ->with(['modelos.arquivo', 'liberacoes', 'responsaveis.funcionario:matricula,nome']);

        // Se não for configuração, filtrar apenas ativos
        if (! $request->input('incluir_inativos', false)) {
            $query->where('ativo', 'S');
        }

        $assuntos = $query->orderBy('assunto')->get();

        $prazoResolucao = DB::table('intranet_parametros')
            ->where('menu', 'SOLICITACOES')
            ->where('submenu', 'CONFIGURACOES')
            ->where('parametro', 'DEP_ATIVOS')
            ->where('condicao1', $request->input('departamento'))
            ->where('valor', 1)
            ->value('condicao2');

        // Lista de campos padrão
        $camposPadrao = [
            ['descricao' => 'titulo', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'descricao', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'arquivos', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'equipamentos', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'rotinas', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'dados acesso', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'usuario origem', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'usuarios destino', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'vendas pendentes', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'menus intranet', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'perfil intranet', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'filial', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
            ['descricao' => 'departamento', 'ativo' => false, 'obrigatorio' => false, 'observacao' => null, 'tipo' => 'texto', 'opcoes_titulo' => []],
        ];

        foreach ($assuntos as $assunto) {
            // Converter o responsável para inteiro ou null
            $assunto->responsavel = $assunto->responsavel ? intval($assunto->responsavel) : null;

            // Clonar o array de campos padrão
            $camposArray = $camposPadrao;

            // Buscar campos ativos do banco
            $camposAtivos = SolicitacaoCampos::where('assunto_id', $assunto->id)->pluck('obrigatorio', 'descricao');

            // Atualizar os campos ativos no array
            foreach ($camposArray as &$campo) {
                if ($camposAtivos->has($campo['descricao'])) {

                    $campoSelecionado = SolicitacaoCampos::where('ASSUNTO_ID', $assunto->id)->where('DESCRICAO', $campo['descricao'])->first();

                    $campo['ativo'] = true;
                    $campo['obrigatorio'] = $campoSelecionado->obrigatorio == 1;
                    $campo['observacao'] = $campoSelecionado->observacao;
                    // Tipo do campo: 'texto' (padrão) ou 'selecao'
                    $campo['tipo'] = $campoSelecionado->tipo ?? 'texto';
                    // Opções de seleção para o título (quando tipo = 'selecao')
                    $campo['opcoes_titulo'] = $campoSelecionado->opcoes_titulo ?? [];
                }
            }

            $selects = SolicitacaoSelecao::where('ASSUNTO_ID', $assunto->id)->orderBy('ordem')->get()->toArray();

            foreach ($selects as &$select) {
                $select['valores'] = SolicitacaoSelecaoItem::where('SELECAO_ID', $select['id'])->pluck('valor');
                $select['ativo'] = true;
                $select['obrigatorio'] = $select['obrigatorio'] == 'S' ? true : false;
            }

            // Atribuir os campos ao objeto $assunto
            $assunto->setAttribute('campos', $camposArray);
            $assunto->setAttribute('selects', $selects);
        }
        // Obter os responsáveis ativos no departamento
        $responsaveis = Funcionario::where('areaatuacao', $request->input('departamento'))
            ->where('situacao', 'A')
            ->whereNotIn('matricula', [99999999, 7801, 10000]) // excluir usuarios ficiticios
            ->select('matricula', 'nome')
            ->get();

        // Retornar os dados formatados
        return [
            'assuntos' => $assuntos,
            'responsaveis' => $responsaveis,
            'campos' => $camposPadrao,
            'prazoResolucao' => $prazoResolucao,
        ];
    }

    /**
     * Verifica se existem respostas para um campo de seleção
     * Usado para alertar o usuário antes de alterar o tipo do campo
     */
    public function verificarRespostasCampo($selecao_id)
    {
        $qtdRespostas = SolicitacaoSelecaoResposta::where('selecao_id', $selecao_id)->count();

        return response()->json([
            'existe_respostas' => $qtdRespostas > 0,
            'qtd_respostas' => $qtdRespostas,
        ]);
    }

    public function salvarAssuntos(Request $request)
    {
        // Pegar informações do request
        $assuntos = $request->input('assuntos');
        $departamento = $request->input('departamento');
        $prazoResolucao = $request->input('prazoResolucao');

        // Iniciar transição no banco
        DB::beginTransaction();
        try {

            if ($prazoResolucao) {
                DB::table('intranet_parametros')
                    ->where('menu', 'SOLICITACOES')
                    ->where('submenu', 'CONFIGURACOES')
                    ->where('parametro', 'DEP_ATIVOS')
                    ->where('condicao1', $departamento)
                    ->where('valor', 1)
                    ->update([
                        'condicao2' => $prazoResolucao,
                        'observacao' => 'Condição 2: Prazo de resolução',
                    ]);
            }

            // Obter IDs dos assuntos enviados
            $idsEnviados = array_column($assuntos, 'id');

            // Buscar assuntos existentes (todos, incluindo inativos, pois agora enviamos inativos também)
            $assuntosExistentes = SolicitacaoAssunto::where('departamento', $departamento)->pluck('id')->toArray();

            // Identificar IDs a serem excluídos (apenas os que foram removidos da lista, não os inativos)
            $idsParaExcluir = array_diff($assuntosExistentes, $idsEnviados);

            // Excluir os assuntos que não estão na lista enviada
            SolicitacaoAssunto::whereIn('id', $idsParaExcluir)->update(['ativo' => 'N']);

            // Percorrer os assuntos e criar/alterar no banco
            foreach ($assuntos as $assunto) {

                // Buscar ou criar assunto no banco
                $assuntoEncontrado = SolicitacaoAssunto::where('id', $assunto['id'])->firstOrNew();

                // Inserir novas informações
                $assuntoEncontrado->departamento = $departamento;
                $assuntoEncontrado->assunto = $assunto['assunto'];
                $assuntoEncontrado->responsavel = $assunto['responsavel'];
                $assuntoEncontrado->prioridade = $assunto['prioridade'];
                // Preservar o status ativo/inativo que vem do frontend
                $ativoValor = $assunto['ativo'] ?? 'S';
                // Converter booleano/string para 'S' ou 'N'
                if ($ativoValor === true || $ativoValor === 'S' || $ativoValor === 1 || $ativoValor === '1') {
                    $assuntoEncontrado->ativo = 'S';
                } else {
                    $assuntoEncontrado->ativo = 'N';
                }
                $assuntoEncontrado->qtd_min_anexos = $assunto['qtd_min_anexos'] ?? null;
                $assuntoEncontrado->instrucoes = $assunto['instrucoes'] ?? null;
                $assuntoEncontrado->redirect = $assunto['redirect'] ?? false;
                $assuntoEncontrado->redirect_mensagem = $assunto['redirect_mensagem'] ?? null;
                $assuntoEncontrado->redirect_mensagem_sim = $assunto['redirect_mensagem_sim'] ?? null;
                $assuntoEncontrado->redirect_nao = $assunto['redirect_nao'] ?? false;
                $assuntoEncontrado->redirect_mensagem_nao = $assunto['redirect_mensagem_nao'] ?? null;
                $assuntoEncontrado->redirect_departamento = $assunto['redirect_departamento'] ?? null;
                $assuntoEncontrado->redirect_assunto_id = $assunto['redirect_assunto_id'] ?? null;

                // Salvar assunto
                $assuntoEncontrado->save();

                // Tratar arquivos modelo
                if (isset($assunto['modelos']) && is_array($assunto['modelos'])) {
                    // Buscar modelos existentes
                    $modelosExistentes = $assuntoEncontrado->modelos()->pluck('file_id')->toArray();

                    // Obter IDs dos modelos enviados
                    $modelosEnviados = array_filter($assunto['modelos'], function ($modelo) {
                        return isset($modelo['file_id']) && ! empty($modelo['file_id']);
                    });
                    $idsModelosEnviados = array_column($modelosEnviados, 'file_id');

                    // Identificar modelos a serem removidos
                    $modelosParaRemover = array_diff($modelosExistentes, $idsModelosEnviados);

                    // Remover modelos que não estão mais na lista
                    if (! empty($modelosParaRemover)) {
                        $assuntoEncontrado->modelos()->whereIn('file_id', $modelosParaRemover)->delete();
                    }

                    // Adicionar novos modelos
                    foreach ($modelosEnviados as $modelo) {
                        // Verificar se o modelo já existe
                        $modeloExistente = $assuntoEncontrado->modelos()
                            ->where('file_id', $modelo['file_id'])
                            ->first();

                        if (! $modeloExistente) {
                            $assuntoEncontrado->modelos()->create([
                                'file_id' => $modelo['file_id'],
                            ]);
                        }
                    }
                }

                // Obter IDs dos campos existentes
                $idsCamposExistentes = SolicitacaoCampos::where('assunto_id', $assuntoEncontrado->id)->pluck('id')->toArray();

                // Obter  IDs dos campos enviados
                $idsCamposEnviados = array_column($assunto['campos'], 'id');

                // Identificar IDs a serem excluídos
                $idsCamposParaExcluir = array_diff($idsCamposExistentes, $idsCamposEnviados);

                // Excluir os campos que não estão na lista enviada
                SolicitacaoCampos::whereIn('id', $idsCamposParaExcluir)->delete();

                // Percorrer campos para criar ou alterar os campos enviados
                foreach ($assunto['campos'] as $campo) {

                    // Seguir pro proximoximo
                    if (! $campo['ativo']) {
                        continue;
                    }

                    // Buscar ou criar campo no banco
                    $campoEncontrado = SolicitacaoCampos::where('id', $campo['id'])->firstOrNew();

                    // Inserir novas informações
                    $campoEncontrado->assunto_id = $assuntoEncontrado->id;
                    $campoEncontrado->descricao = $campo['descricao'];
                    $campoEncontrado->obrigatorio = $campo['obrigatorio'];
                    $campoEncontrado->observacao = $campo['observacao'];
                    // Tipo do campo: 'texto' (padrão) ou 'selecao' (apenas para titulo por enquanto)
                    $campoEncontrado->tipo = $campo['tipo'] ?? 'texto';
                    // Opções de seleção para o título (quando tipo = 'selecao')
                    $campoEncontrado->opcoes_titulo = $campo['opcoes_titulo'] ?? null;

                    // Salvar campo
                    $campoEncontrado->save();
                }

                // LOGICA PARA SELECTS
                if (! isset($assunto['selects']) || ! is_array($assunto['selects']) || empty($assunto['selects'])) {
                    $assunto['selects'] = [];
                }

                $idsSelecoesExistentes = SolicitacaoSelecao::where('assunto_id', $assuntoEncontrado->id)->pluck('id')->toArray();

                // Obter  IDs das selecoes enviados
                $idsSelecoesEnviados = array_column($assunto['selects'], 'id');

                // Obtem os itens a serem excluidos
                $idsSelecaoExcluir = array_diff($idsSelecoesExistentes, $idsSelecoesEnviados);

                // Excluir os itens da selecao
                SolicitacaoSelecaoItem::whereIn('selecao_id', $idsSelecaoExcluir)->delete();

                // Excluir os seleções que não estão na lista enviada
                SolicitacaoSelecao::whereIn('id', $idsSelecaoExcluir)->delete();

                // #12173 - Primeiro passo: salvar todos os selects SEM campo_pai_id
                // para obter os IDs reais (evitar gravar temp IDs como "temp_0" que ERP legado converte para 0)
                $mapIdSelects = []; // Mapeia ID original (ou temp) -> novo select salvo

                foreach ($assunto['selects'] as $index => $selecao) {

                    if (! $selecao['ativo']) {
                        continue;
                    }

                    // Buscar campo existente para verificar se o tipo mudou
                    $campoExistente = $selecao['id'] ? SolicitacaoSelecao::find($selecao['id']) : null;
                    $tipoMudou = $campoExistente && $campoExistente->tipo !== $selecao['tipo'];

                    // Se o tipo mudou, verificar se existem respostas para este campo
                    $deveCriarNovo = false;
                    if ($tipoMudou) {
                        $existeResposta = SolicitacaoSelecaoResposta::where('selecao_id', $selecao['id'])->exists();
                        if ($existeResposta) {
                            // Desativar campo antigo (não excluir para manter histórico)
                            $campoExistente->update(['exibir_nova' => 'N']);
                            $deveCriarNovo = true;
                        }
                    }

                    // Se deve criar novo (tipo mudou e existem respostas), criar campo novo
                    // Caso contrário, atualizar o existente normalmente
                    if ($deveCriarNovo) {
                        $insertSelecao = new SolicitacaoSelecao;
                    } else {
                        $insertSelecao = SolicitacaoSelecao::where('id', $selecao['id'])->firstOrNew();
                    }

                    $insertSelecao->assunto_id = $assuntoEncontrado->id;
                    $insertSelecao->label = $selecao['label'];
                    $insertSelecao->obrigatorio = $selecao['obrigatorio'] ? 'S' : 'N';
                    $insertSelecao->observacao = $selecao['observacao'];
                    $insertSelecao->tipo = $selecao['tipo'];
                    $insertSelecao->tipo_data = $selecao['tipo_data'];
                    $insertSelecao->dias_minimos = $selecao['tipo'] === 'data' ? ($selecao['dias_minimos'] ?? null) : null;
                    $insertSelecao->multiplo = isset($selecao['multiplo']) && $selecao['multiplo'] ? 'S' : 'N';
                    $insertSelecao->exibir_nova = ($selecao['exibir_nova'] ?? true) ? 'S' : 'N';
                    $insertSelecao->exibir_atendimento = ($selecao['exibir_atendimento'] ?? false) ? 'S' : 'N';
                    $insertSelecao->ordem = $selecao['ordem'] ?? $index;
                    // #12173 - campo_pai_id será atualizado no segundo passo
                    $insertSelecao->campo_pai_id = null;
                    $insertSelecao->valor_condicional = $selecao['valor_condicional'] ?? null;

                    $insertSelecao->save();

                    // Mapear ID original (ou temporário) para o select salvo com ID real
                    $mapIdSelects[$selecao['id']] = $insertSelecao;

                    if ($insertSelecao->tipo != 'data' && $insertSelecao->tipo != 'texto') {

                        $valoresExistentes = SolicitacaoSelecaoItem::where('selecao_id', $insertSelecao->id)->pluck('valor')->toArray();

                        $valoresExcluir = array_diff($valoresExistentes, $selecao['valores']);

                        SolicitacaoSelecaoItem::where('selecao_id', $insertSelecao->id)->whereIn('valor', $valoresExcluir)->delete();

                        foreach ($selecao['valores'] as $valor) {
                            $insertSelecaoItem = SolicitacaoSelecaoItem::where('selecao_id', $insertSelecao->id)->where('valor', $valor)->firstOrNew();
                            $insertSelecaoItem->selecao_id = $insertSelecao->id;
                            $insertSelecaoItem->valor = $valor;
                            $insertSelecaoItem->save();
                        }
                    }
                }

                // #12173 - Segundo passo: atualizar campo_pai_id com os IDs reais
                foreach ($assunto['selects'] as $selecao) {
                    if (! $selecao['ativo']) {
                        continue;
                    }

                    $campoPaiId = $selecao['campo_pai_id'] ?? null;

                    if ($campoPaiId && isset($mapIdSelects[$selecao['id']])) {
                        $selectSalvo = $mapIdSelects[$selecao['id']];

                        // Resolver o ID real do campo pai
                        if (isset($mapIdSelects[$campoPaiId])) {
                            // O campo pai foi salvo nesta mesma operação — usar o ID real
                            $selectSalvo->campo_pai_id = $mapIdSelects[$campoPaiId]->id;
                        } else {
                            // O campo pai já existia no banco — usar o ID diretamente (se é numérico válido)
                            $paiIdNumerico = is_numeric($campoPaiId) ? intval($campoPaiId) : null;
                            if ($paiIdNumerico && SolicitacaoSelecao::where('id', $paiIdNumerico)->exists()) {
                                $selectSalvo->campo_pai_id = $paiIdNumerico;
                            }
                        }

                        $selectSalvo->save();
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            // Desfazer alterações no banco e retornar o erro
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Duplicar um assunto existente com todos os seus relacionamentos
     * #12173 - MELHORIA NA CRIAÇÃO DE PROCESSOS
     */
    public function duplicarAssunto(Request $request)
    {
        $assuntoId = $request->input('assunto_id');
        $novoNome = $request->input('novo_nome');

        // Buscar assunto original com relacionamentos
        $assuntoOriginal = SolicitacaoAssunto::with(['modelos', 'liberacoes', 'responsaveis'])->findOrFail($assuntoId);

        // Buscar selects (campos configuráveis) do assunto
        $selectsOriginais = SolicitacaoSelecao::where('assunto_id', $assuntoId)
            ->orderBy('ordem')
            ->get();

        // Buscar campos predefinidos do assunto
        $camposOriginais = SolicitacaoCampos::where('assunto_id', $assuntoId)->get();

        DB::beginTransaction();
        try {
            // 1. Criar novo assunto
            $novoAssunto = SolicitacaoAssunto::create([
                'departamento' => $assuntoOriginal->departamento,
                'assunto' => $novoNome ?? '[CÓPIA] ' . $assuntoOriginal->assunto,
                'responsavel' => $assuntoOriginal->responsavel,
                'prioridade' => $assuntoOriginal->prioridade,
                'ativo' => 'S', // Ativo para aparecer na lista
                'qtd_min_anexos' => $assuntoOriginal->qtd_min_anexos,
                'instrucoes' => $assuntoOriginal->instrucoes,
                'redirect' => $assuntoOriginal->redirect ? 'S' : 'N',
                'redirect_mensagem' => $assuntoOriginal->redirect_mensagem,
                'redirect_mensagem_sim' => $assuntoOriginal->redirect_mensagem_sim,
                'redirect_nao' => $assuntoOriginal->redirect_nao ? 'S' : 'N',
                'redirect_mensagem_nao' => $assuntoOriginal->redirect_mensagem_nao,
                'redirect_departamento' => $assuntoOriginal->redirect_departamento,
                'redirect_assunto_id' => $assuntoOriginal->redirect_assunto_id,
            ]);

            // 2. Duplicar campos predefinidos
            foreach ($camposOriginais as $campo) {
                SolicitacaoCampos::create([
                    'descricao' => $campo->descricao,
                    'assunto_id' => $novoAssunto->id,
                    'observacao' => $campo->observacao,
                    'obrigatorio' => $campo->obrigatorio ?? 'N',
                    'tipo' => $campo->tipo ?? 'texto',
                    'opcoes_titulo' => $campo->opcoes_titulo,
                ]);
            }

            // 3. Duplicar selects (campos configuráveis)
            // Primeiro passo: criar todos os selects sem campo_pai_id para obter os novos IDs
            $mapIdSelectsAntigos = []; // Mapeia ID antigo -> novo select
            foreach ($selectsOriginais as $select) {
                $novoSelect = SolicitacaoSelecao::create([
                    'assunto_id' => $novoAssunto->id,
                    'label' => $select->label,
                    'obrigatorio' => $select->obrigatorio,
                    'observacao' => $select->observacao,
                    'tipo' => $select->tipo,
                    'tipo_data' => $select->tipo_data,
                    'dias_minimos' => $select->dias_minimos,
                    'multiplo' => $select->multiplo,
                    'exibir_nova' => $select->exibir_nova,
                    'exibir_atendimento' => $select->exibir_atendimento,
                    'ordem' => $select->ordem,
                    // campo_pai_id será atualizado depois
                    'campo_pai_id' => null,
                    'valor_condicional' => $select->valor_condicional,
                ]);

                // Mapear ID antigo para novo select
                $mapIdSelectsAntigos[$select->id] = $novoSelect;

                // 4. Duplicar itens do select (opções)
                $itensOriginais = SolicitacaoSelecaoItem::where('selecao_id', $select->id)->get();
                foreach ($itensOriginais as $item) {
                    SolicitacaoSelecaoItem::create([
                        'selecao_id' => $novoSelect->id,
                        'valor' => $item->valor,
                    ]);
                }
            }

            // Segundo passo: atualizar campo_pai_id com os novos IDs
            foreach ($selectsOriginais as $select) {
                if ($select->campo_pai_id && isset($mapIdSelectsAntigos[$select->campo_pai_id])) {
                    $novoSelect = $mapIdSelectsAntigos[$select->id];
                    $novoSelect->campo_pai_id = $mapIdSelectsAntigos[$select->campo_pai_id]->id;
                    $novoSelect->save();
                }
            }

            // 5. Duplicar liberações
            foreach ($assuntoOriginal->liberacoes as $liberacao) {
                SolicitacaoAssuntoLiberacao::create([
                    'assunto_id' => $novoAssunto->id,
                    'tipo' => $liberacao->tipo,
                    'valor' => $liberacao->valor,
                ]);
            }

            // 6. Duplicar modelos (referências aos arquivos)
            foreach ($assuntoOriginal->modelos as $modelo) {
                SolicitacaoAssuntoModelo::create([
                    'solicitacao_assunto_id' => $novoAssunto->id,
                    'file_id' => $modelo->file_id,
                ]);
            }

            // 7. Duplicar responsáveis exclusivos (#22263)
            foreach ($assuntoOriginal->responsaveis as $responsavel) {
                SolicitacaoAssuntoResponsavel::create([
                    'assunto_id' => $novoAssunto->id,
                    'matricula' => $responsavel->matricula,
                ]);
            }

            DB::commit();

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Assunto duplicado com sucesso!',
                'assunto' => $novoAssunto,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao duplicar assunto: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Ativar ou desativar um assunto
     * Permite alternar o status ativo/inativo de um assunto sem removê-lo
     */
    public function toggleAtivoAssunto(Request $request)
    {
        $assuntoId = $request->input('assunto_id');

        $assunto = SolicitacaoAssunto::findOrFail($assuntoId);

        // Alternar o status ativo
        $novoStatus = $assunto->ativo === 'S' ? 'N' : 'S';
        $assunto->ativo = $novoStatus;
        $assunto->save();

        return response()->json([
            'sucesso' => true,
            'mensagem' => $novoStatus === 'S' ? 'Assunto ativado com sucesso!' : 'Assunto desativado com sucesso!',
            'ativo' => $novoStatus,
        ]);
    }

    /**
     * Exportar relatório de solicitações com campos configuráveis
     * #12173 - MELHORIA NA CRIAÇÃO DE PROCESSOS
     *
     * Ordenado por: Linha > Filial > ID Chamado
     */
    public function exportarRelatorio(Request $request)
    {
        // Validar que ao menos o departamento foi informado
        if (empty($request->input('departamento'))) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Selecione um departamento para exportar o relatório.',
            ], 422);
        }

        $filtros = [
            'departamento' => $request->input('departamento'),
            'assuntos' => $request->input('assuntos', []),
            'filiais' => $request->input('filiais', []),
            'ufs' => $request->input('ufs', []),
            'cidades' => $request->input('cidades', []),
            'situacoes' => $request->input('situacoes', []),
            'prioridades' => $request->input('prioridades', []),
            'responsavel' => $request->input('responsavel', []),
            'solicitante' => $request->input('solicitante'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
            'data_alt_inicio' => $request->input('data_alt_inicio'),
            'data_alt_fim' => $request->input('data_alt_fim'),
            'id' => $request->input('id'),
            'aba' => $request->input('aba'),
            'usuario_logado' => auth()->id(),
        ];

        $nomeArquivo = 'relatorio_solicitacoes_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new SolicitacoesCamposExport($filtros), $nomeArquivo);
    }

    public function criarSolicitacao(Request $request)
    {

        $solicitacao = $request->all();

        // Criar hash único baseado nos dados principais da solicitação para evitar duplicatas
        $hashData = [
            'titulo' => $solicitacao['titulo'] ?? '',
            'descricao' => $solicitacao['descricao'] ?? '',
            'departamento_responsavel' => $solicitacao['departamento_responsavel'],
            'assunto_id' => $solicitacao['assunto_id'],
            'usuario_solicitante' => auth()->id(),
            'filial_id' => $solicitacao['filial_id'],
            'timestamp_window' => floor(time() / 30), // Janela de 30 segundos
        ];
        $hashUnico = md5(json_encode($hashData));

        // Verificar se já existe uma solicitação com o mesmo hash nos últimos 2 minutos
        $solicitacaoExistente = Solicitacao::where('hash_duplicata', $hashUnico)
            ->where('created_at', '>', Carbon::now()->subMinutes(2))
            ->first();

        if ($solicitacaoExistente) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Uma solicitação similar já foi criada recentemente. Aguarde alguns instantes antes de tentar novamente.',
            ], 409); // 409 Conflict
        }

        DB::beginTransaction();
        try {
            // Inserir a solicitacao e pegar o ID para inserir depois nos arquivos
            $novaSolicitacao = Solicitacao::create([
                'titulo' => $solicitacao['titulo'],
                'descricao' => $solicitacao['descricao'],
                'departamento_responsavel' => $solicitacao['departamento_responsavel'],
                'prioridade' => $solicitacao['prioridade'],
                'usuario_solicitante' => auth()->id(),
                'filial_id' => $solicitacao['filial_id'],
                'assunto_id' => $solicitacao['assunto_id'],
                'usuario_origem' => $solicitacao['usuarioOrigem']['matricula'] ?? null,
                'status' => 'pendente',
                'solicitacao_pai_id' => $solicitacao['solicitacao_pai_id'] ?? null,
                'hash_duplicata' => $hashUnico,
            ]);

            // Criar movimentação referente a criação
            $usuario = auth()->user();
            $assunto = SolicitacaoAssunto::find($solicitacao['assunto_id']);
            $novaSolicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Solicitação criada',
                'descricao' => "Solicitação criada por {$usuario?->name}, para o departamento '{$solicitacao['departamento_responsavel']}', referente a '$assunto->assunto'",
                'usuario_movimentacao' => $usuario?->id,
            ]);

            // Atribuir responsável de forma automática, baseada no assunto (se houver)
            if ($solicitacao['usuario_responsavel']) {
                $novaSolicitacao->usuario_responsavel = $solicitacao['usuario_responsavel'];
                $novaSolicitacao->save();
                $novaSolicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Responsável atribuído',
                    'descricao' => "Responsável atribuído de forma automática, baseado em configurações para o assunto: '$assunto->assunto'",
                    'usuario_movimentacao' => $usuario?->id,
                ]);

                $tituloNot = 'Responsável atribuído';
                $mensagemNot = "Responsável atribuído de forma automática, baseado em configurações para o assunto: '$assunto->assunto'";
                $origem = 'solicitacoes.nova';
                $link = url('/solicitacoes/lista?solicitacao=' . $novaSolicitacao->id);
                $this->criaNotificacao($tituloNot, $mensagemNot, [$novaSolicitacao->usuario_responsavel, $novaSolicitacao->usuario_solicitante], $origem, $link);
            }

            if ($solicitacao['departamento'] || $solicitacao['filial']) {
                BsFilialDeptoSelect::create([
                    'solicitacao_id' => $novaSolicitacao->id,
                    'departamento' => $solicitacao['departamento'] ?? null,
                    'filial' => $solicitacao['filial'] ?? null,
                ]);
            }
            // Percorrer e inserir os arquivos

            $arquivos = $solicitacao['arquivos'];
            foreach ($arquivos as $arquivo) {
                SolicitacaoArq::create([
                    'solicitacao_id' => $novaSolicitacao->id,
                    'arquivo_id' => $arquivo['fileTab']['id'],
                    'usuario' => auth()->id(),
                ]);
            }

            // Inserir rotinas
            if (count($solicitacao['rotinas'])) {
                foreach ($solicitacao['rotinas'] as $rotina) {
                    SolicitacaoCRot::create([
                        'solicitacao_id' => $novaSolicitacao->id,
                        'rotina' => $rotina['codigo'],
                    ]);
                }
            }

            // Inserir dados de acesso
            if (count($solicitacao['dadosLiberacao'])) {
                foreach ($solicitacao['dadosLiberacao'] as $liberacao) {
                    foreach ($liberacao['dados'] as $dado) {

                        if ($liberacao['nome'] == 'Filiais') {
                            SolicitacaoCAcessos::create([
                                'solicitacao_id' => $novaSolicitacao->id,
                                'tipo' => $liberacao['nome'],
                                'codigo' => $dado['codigo'],
                            ]);
                        }

                        if ($liberacao['nome'] == 'Moedas') {
                            SolicitacaoCAcessos::create([
                                'solicitacao_id' => $novaSolicitacao->id,
                                'tipo' => $liberacao['nome'],
                                'codigo' => $dado['codmoeda'],
                            ]);
                        }

                        if ($liberacao['nome'] == 'Departamentos') {
                            SolicitacaoCAcessos::create([
                                'solicitacao_id' => $novaSolicitacao->id,
                                'tipo' => $liberacao['nome'],
                                'codigo' => $dado['codepto'],
                            ]);
                        }

                        if ($liberacao['nome'] == 'Bancos') {
                            SolicitacaoCAcessos::create([
                                'solicitacao_id' => $novaSolicitacao->id,
                                'tipo' => $liberacao['nome'],
                                'codigo' => $dado['codbanco'],
                            ]);
                        }

                        if ($liberacao['nome'] == 'Centros de Custo') {
                            SolicitacaoCAcessos::create([
                                'solicitacao_id' => $novaSolicitacao->id,
                                'tipo' => $liberacao['nome'],
                                'codigo' => $dado['codigocentrocusto'],
                            ]);
                        }
                    }
                }
            }

            // Inserir dados de vendas
            if (count($solicitacao['infoVendas'])) {
                foreach ($solicitacao['infoVendas'] as $liberacao) {

                    $caixas = array_map(fn($caixa) => $caixa['numcaixa'], $liberacao['caixas']);

                    SolicitacaoCVendas::create([
                        'solicitacao_id' => $novaSolicitacao->id,
                        'filial' => $liberacao['filial']['codigo'],
                        'caixas' => json_encode($caixas),
                        'valor' => $liberacao['valor'],
                        'data' => $liberacao['data'],
                        'operador' => $liberacao['operador']['matricula'],
                    ]);
                }
            }

            // Inserir dados de equipamentos
            if (count($solicitacao['equipamentos'])) {
                foreach ($solicitacao['equipamentos'] as $equipamento) {
                    SolicitacaoCEquip::create([
                        'solicitacao_id' => $novaSolicitacao->id,
                        'equipamento' => $equipamento['nome'],
                        'operacao' => $equipamento['operacao'] ?? 'TROCA',
                        'quantidade' => $equipamento['quantidade'],
                        'observacao' => $equipamento['observacao'],
                    ]);
                }
            }

            // Inserir usuários destino
            if (count($solicitacao['usuariosDestino'])) {
                foreach ($solicitacao['usuariosDestino'] as $usuario) {

                    SolicitacaoCDest::create([
                        'solicitacao_id' => $novaSolicitacao->id,
                        'matricula' => $usuario['matricula'],
                    ]);
                }
            }

            if (count($solicitacao['selects'])) {

                foreach ($solicitacao['respostas'] as $resposta) {

                    $select = collect($solicitacao['selects'])->firstWhere('id', $resposta['selecao_id']);

                    // Tipos pré-definidos ERP legado #3196
                    $tiposErp = ['depto_compras', 'depto_funcionario', 'filial_winthor', 'funcao', 'regional'];

                    if (in_array($select['tipo'], $tiposErp) || $select['tipo'] == 'selecao') {
                        if ($resposta['selecao_id'] == $select['id']) {
                            // Se for múltipla seleção, criar um registro para cada valor
                            $valores = is_array($resposta['resposta']) ? $resposta['resposta'] : [$resposta['resposta']];

                            foreach ($valores as $valor) {
                                SolicitacaoSelecaoResposta::create([
                                    'selecao_id' => $resposta['selecao_id'],
                                    'texto_resposta' => is_numeric($valor) ? null : $valor,
                                    'itens_id' => is_numeric($valor) && $select['tipo'] == 'selecao' ? $valor : null,
                                    'valor_winthor' => in_array($select['tipo'], $tiposErp) ? (string) $valor : null,
                                    'solicitacao_id' => $novaSolicitacao->id,
                                    'assunto_id' => $resposta['assunto_id'],
                                ]);
                            }
                        }
                    } elseif ($select['tipo'] == 'cnpj') {
                        if ($resposta['selecao_id'] == $select['id']) {
                            SolicitacaoSelecaoResposta::create([
                                'selecao_id' => $resposta['selecao_id'],
                                'texto_resposta' => $resposta['resposta'],
                                'solicitacao_id' => $novaSolicitacao->id,
                                'assunto_id' => $resposta['assunto_id'],
                            ]);
                        }
                    } elseif ($select['tipo'] == 'texto' || $select['tipo'] == 'numero') {
                        if ($resposta['selecao_id'] == $select['id']) {
                            SolicitacaoSelecaoResposta::create([
                                'selecao_id' => $resposta['selecao_id'],
                                'texto_resposta' => $resposta['resposta'],
                                'solicitacao_id' => $novaSolicitacao->id,
                                'assunto_id' => $resposta['assunto_id'],
                            ]);
                        }
                    } elseif ($select['tipo'] == 'data') {
                        if ($resposta['selecao_id'] == $select['id']) {

                            $data1 = null;
                            $data2 = null;

                            if (! empty($resposta['resposta']['datas'])) {
                                $data1 = $resposta['resposta']['datas'][0]
                                    ? Carbon::createFromFormat('Y-m-d', $resposta['resposta']['datas'][0])
                                    : null;

                                $data2 = isset($resposta['resposta']['datas'][1]) && $resposta['resposta']['datas'][1]
                                    ? Carbon::createFromFormat('Y-m-d', $resposta['resposta']['datas'][1])
                                    : null;
                            }

                            // Validar prazo mínimo de dias para campos de data
                            if ($select['dias_minimos'] && $data1) {
                                $dataMinima = Carbon::now()->addDays((int) $select['dias_minimos'])->startOfDay();
                                if ($data1->startOfDay()->lt($dataMinima)) {
                                    throw new \Exception(
                                        'O campo "' . $select['label'] . '" exige uma data com no mínimo ' . $select['dias_minimos'] . ' dia(s) de antecedência a partir de hoje.'
                                    );
                                }
                            }

                            SolicitacaoSelecaoResposta::create([
                                'selecao_id' => $resposta['selecao_id'],
                                'data1' => $data1,
                                'data2' => $data2,
                                'solicitacao_id' => $novaSolicitacao->id,
                                'assunto_id' => $resposta['assunto_id'],
                            ]);
                        }
                    } elseif ($select['tipo'] == 'arquivo') {

                        // Suporta múltiplos arquivos
                        $fileIds = $resposta['resposta']['file_ids'] ?? [];

                        // Criar um registro para cada arquivo
                        foreach ($fileIds as $fileId) {
                            // Verificar se o arquivo realmente existe
                            $file = File::where('id', $fileId)->first();
                            if (! $file) {
                                throw new \Exception("Arquivo não encontrado para o ID: {$fileId}");
                            }

                            SolicitacaoSelecaoResposta::create([
                                'selecao_id' => $resposta['selecao_id'],
                                'file_id' => $fileId,
                                'solicitacao_id' => $novaSolicitacao->id,
                                'assunto_id' => $resposta['assunto_id'],
                            ]);
                        }
                    } else {
                        // Tipo desconhecido, lançar erro
                        throw new \Exception("Tipo de seleção desconhecido: {$select['tipo']}");
                    }
                }
            }

            DB::commit();

            // Iniciar fluxo/workflow automaticamente se o assunto tiver fluxo ativo
            try {
                $workflowService = new WorkflowService(new SolicitacaoReverbService);
                $workflowService->iniciarFluxo($novaSolicitacao, auth()->id());
            } catch (\Throwable $e) {
                Log::warning('Workflow: Falha ao iniciar fluxo na criação', [
                    'solicitacao_id' => $novaSolicitacao->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notificar via Reverb em tempo real
            try {
                $reverbService = new SolicitacaoReverbService;
                $reverbService->notificarCriacao(
                    [
                        'id' => $novaSolicitacao->id,
                        'titulo' => $novaSolicitacao->titulo,
                        'descricao' => $novaSolicitacao->descricao,
                        'status' => $novaSolicitacao->status,
                        'prioridade' => $novaSolicitacao->prioridade,
                        'usuario_solicitante' => $novaSolicitacao->usuario_solicitante,
                        'usuario_responsavel' => $novaSolicitacao->usuario_responsavel,
                        'assunto_id' => $novaSolicitacao->assunto_id,
                        'filial_id' => $novaSolicitacao->filial_id,
                        'created_at' => $novaSolicitacao->created_at->toISOString(),
                    ],
                    $novaSolicitacao->departamento_responsavel
                );
            } catch (\Throwable $e) {
                // Log do erro, mas não impede a criação da solicitação
                Log::error('Reverb: Falha ao notificar criação de solicitação', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $novaSolicitacao->id,
                    'departamento' => $novaSolicitacao->departamento_responsavel,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Solicitação criada com sucesso!',
                'solicitacao_id' => $novaSolicitacao->id,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'sucesso' => false,
                'mensagem' => $th->getMessage(),
                'codigo' => 5001,
            ], 500);
        }
    }

    public function indexLista()
    {

        // Buscar filiais
        $filiais = UtilController::filiaisUsuarioStatic();

        $permiteVerTodos = auth()->user()?->hasPermission('solicitacoes.lista.ver-todos-depto') ?? false;

        // Departamentos ativos (5E: tabela `departments`, no lugar de intranet_parametros DEP_ATIVOS)
        $departamentosQuery = \App\Models\Department::where('is_active', true);

        if (! $permiteVerTodos) {
            $departamentosQuery->where('id', (auth()->user()?->department_id));
        }

        // Mantém a chave `condicao1` (= identificador do depto) que o fluxo/front espera
        $departamentos = $departamentosQuery->orderBy('name')->get()->map(function ($d) {
            return (object) ['id' => $d->id, 'condicao1' => $d->name, 'department_id' => $d->id];
        });

        // Buscar assuntos pelos departamentos
        foreach ($departamentos as $departamento) {

            // TODO: revisar vínculo departamento — responsáveis adicionais (intranet_parametros RESPONSAVEL_ADICIONAL) não portado
            $responsaveisAdicionais = [];

            $departamento->assuntos = SolicitacaoAssunto::with(['responsaveis.funcionario'])
                ->where('department_id', $departamento->id)
                ->where('ativo', 'S')
                ->orderBy('assunto')
                ->get();

            // Responsáveis = usuários do departamento (5E: tabela users)
            $departamento->responsaveis = \App\Models\User::where('department_id', $departamento->id)
                ->where('is_active', true)
                ->get()
                ->map(fn($u) => ['matricula' => $u->id, 'nome' => $u->name])
                ->toArray();

            // Mesclar os responsáveis adicionais com os responsáveis do departamento
            $colecaoOriginal = $departamento->responsaveis;
            $colecaoMescladas = collect(array_merge($colecaoOriginal, $responsaveisAdicionais))
                ->unique(fn($item) => is_array($item) ? $item['matricula'] : $item->matricula) // Unifica por matricula
                ->values()
                ->all();
            $departamento->responsaveis = $colecaoMescladas;
        }

        $usuarioLogado = [
            'matricula' => auth()->id(),
            'nome' => (auth()->user()?->name),
            'areaatuacao' => (auth()->user()?->department_id),
        ];

        return Inertia::render('Solicitacoes/Lista/Index', [
            'filiais' => $filiais,
            'departamentos' => $departamentos,
            'usuarioLogado' => $usuarioLogado,

        ]);
    }

    /**
     * Página "Minhas Solicitações" - Solicitações abertas pelo usuário logado
     */
    public function indexMinhas()
    {
        // Buscar filiais
        $filiais = UtilController::filiaisUsuarioStatic();

        $usuarioLogado = [
            'matricula' => auth()->id(),
            'nome' => (auth()->user()?->name),
            'areaatuacao' => (auth()->user()?->department_id),
        ];

        // Verificar se a filial do usuário está configurada para visibilidade
        $codFilialUsuario = (int) optional(auth()->user()?->branches()->first())->id;

        $modoLideranca = $codFilialUsuario > 0 && DB::table('intranet_parametros')
            ->where('menu', 'SOLICITACOES')
            ->where('submenu', 'CONFIGURACOES')
            ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
            ->where('condicao1', $codFilialUsuario)
            ->where('valor', 1)
            ->exists();

        return Inertia::render('Solicitacoes/Minhas/Index', [
            'filiais' => $filiais,
            'usuarioLogado' => $usuarioLogado,
            'modoLideranca' => $modoLideranca,
        ]);
    }

    public function salvarModelos(Request $request)
    {
        try {
            DB::beginTransaction();

            $assuntoId = $request->input('assunto_id');
            $modelos = $request->input('modelos', []);

            // Verificar se o assunto existe
            $assunto = SolicitacaoAssunto::find($assuntoId);
            if (! $assunto) {
                return response()->json(['error' => 'Assunto não encontrado'], 404);
            }

            // Buscar modelos existentes
            $modelosExistentes = $assunto->modelos()->pluck('file_id')->toArray();

            // Obter IDs dos modelos enviados
            $modelosEnviados = array_filter($modelos, function ($modelo) {
                return isset($modelo['file_id']) && ! empty($modelo['file_id']);
            });
            $idsModelosEnviados = array_column($modelosEnviados, 'file_id');

            // Identificar modelos a serem removidos
            $modelosParaRemover = array_diff($modelosExistentes, $idsModelosEnviados);

            // Remover modelos que não estão mais na lista
            if (! empty($modelosParaRemover)) {
                $assunto->modelos()->whereIn('file_id', $modelosParaRemover)->delete();
            }

            // Adicionar novos modelos
            foreach ($modelosEnviados as $modelo) {
                // Verificar se o modelo já existe
                $modeloExistente = $assunto->modelos()
                    ->where('file_id', $modelo['file_id'])
                    ->first();

                if (! $modeloExistente) {
                    $assunto->modelos()->create([
                        'file_id' => $modelo['file_id'],
                    ]);
                }
            }

            DB::commit();

            // Retornar o assunto atualizado com os modelos
            $assuntoAtualizado = SolicitacaoAssunto::with(['modelos.arquivo'])
                ->find($assuntoId);

            return response()->json([
                'success' => true,
                'message' => 'Modelos salvos com sucesso!',
                'assunto' => $assuntoAtualizado,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar modelos',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getSolicitacoes(Request $request)
    {
        $solicitacoes = Solicitacao::with([
            'usuarioSolicitante',
            'usuarioResponsavel',
            'assunto',
            'filial',
            'agendamentos',
            'etapaAtual.etapa',
        ]);

        if ($request->filled('solicitacoesMarcadas')) {
            $porPagina = 99999;
            $pagina = 1;
        } else {
            if ($request->input('porPagina', 10) == 99999) {
                $porPagina = 10;
            } else {
                $porPagina = $request->input('porPagina', 10);
            }
            $pagina = $request->input('pagina', 1);
        }

        if ($request->has('id') && ! empty($request->input('id'))) {
            $solicitacoes->where('id', $request->input('id'));

            // Verificar se usuário pode ver esta solicitação específica
            $permiteVerTodos = DB::table('INTRANET_PERMISSAO AS P')
                ->join('INTRANET_USUARIO_PERMISSAO AS IPP', 'IPP.PERMISSAO_ID', '=', 'P.IDPERMISSAO')
                ->where('IPP.MATRICULA', auth()->id())
                ->where('descricao', 'solicitacoes.lista.ver-todos-depto')
                ->exists();

            if (! $permiteVerTodos) {
                // Buscar departamentos que o usuário tem acesso
                $departamentosPermitidos = DB::table('intranet_parametros')
                    ->where('menu', 'SOLICITACOES')
                    ->where('submenu', 'CONFIGURACOES')
                    ->where('parametro', 'DEP_ATIVOS')
                    ->where('valor', 1)
                    ->where(function ($query) {
                        $query->where('condicao1', (auth()->user()?->department_id))
                            ->orWhereIn('condicao1', function ($subQuery) {
                                $subQuery->select('condicao1')
                                    ->from('intranet_parametros')
                                    ->where('menu', 'SOLICITACOES')
                                    ->where('submenu', 'CONFIGURACOES')
                                    ->where('parametro', 'RESPONSAVEL_ADICIONAL')
                                    ->where('valor', auth()->id());
                            });
                    })
                    ->pluck('condicao1');

                // Permitir apenas se: usuário é o solicitante OU departamento está na lista permitida
                $solicitacoes->where(function ($q) use ($departamentosPermitidos) {
                    $q->where('usuario_solicitante', auth()->id())
                        ->orWhereIn('departamento_responsavel', $departamentosPermitidos);
                });
            }
        } else {

            if ($request->has('prioridades') && ! empty($request->input('prioridades'))) {
                $solicitacoes->whereIn('prioridade', $request->input('prioridades'));
            }

            if ($request->has('situacoes') && ! empty($request->input('situacoes'))) {
                $situacoes = $request->input('situacoes');

                // Garantir que situacoes seja sempre um array
                if (! is_array($situacoes)) {
                    $situacoes = [$situacoes];
                }

                // Verificar se está filtrando por atrasados
                if (in_array('atrasadas', $situacoes)) {
                    // Remover 'atrasadas' do array de situações normais
                    $situacoes = array_diff($situacoes, ['atrasadas']);

                    // Aplicar filtro para solicitações atrasadas
                    $solicitacoes->where(function ($query) use ($situacoes) {
                        // Filtro para solicitações atrasadas
                        $query->where(function ($subQuery) {
                            $subQuery->whereNotNull('previsao_entrega')
                                ->whereIn('status', ['pendente', 'em atendimento', 'atendimento pausado', 'agendado'])
                                ->whereDate('previsao_entrega', '<', now()->startOfDay());
                        });

                        // Se ainda houver outras situações, incluir elas também
                        if (! empty($situacoes)) {
                            $query->orWhereIn('status', $situacoes);
                        }
                    });
                } else {
                    // Filtro normal por situações
                    $solicitacoes->whereIn('status', $situacoes);
                }
            }

            if ($request->has('filiais') && ! empty($request->input('filiais'))) {
                $solicitacoes->whereIn('filial_id', $request->input('filiais'));
            }

            if ($request->has('ufs') && ! empty($request->input('ufs'))) {
                $solicitacoes->whereHas('filial', function ($q) use ($request) {
                    $q->whereIn('uf', $request->input('ufs'));
                });
            }

            if ($request->has('cidades') && ! empty($request->input('cidades'))) {
                $cidades = $request->input('cidades');
                $solicitacoes->whereHas('filial', function ($q) use ($cidades) {
                    $bindings = implode(',', array_fill(0, count($cidades), '?'));
                    $q->whereRaw("TRIM(cidade) IN ({$bindings})", $cidades);
                });
            }

            if ($request->input('aba') != 'minhas' && $request->has('departamento') && ! empty($request->input('departamento'))) {
                $solicitacoes->where('departamento_responsavel', $request->input('departamento.condicao1'));

                // Na aba de atendimento, sempre excluir finalizadas e canceladas (é tela de trabalho, não relatório)
                $solicitacoes->whereNotIn('status', ['finalizada', 'cancelada']);

                // Filtrar por responsáveis do assunto (permissão exclusiva)
                // Se um assunto tem responsáveis configurados, apenas esses usuários podem ver as solicitações desse assunto
                $matriculaLogado = auth()->id();
                $solicitacoes->where(function ($query) use ($matriculaLogado) {
                    // Assuntos que NÃO têm responsáveis configurados (todos podem ver)
                    $query->whereHas('assunto', function ($subQuery) {
                        $subQuery->whereDoesntHave('responsaveis');
                    })
                        // OU solicitações sem assunto vinculado
                        ->orWhereNull('assunto_id')
                        // OU assuntos onde o usuário logado É um responsável
                        ->orWhereHas('assunto.responsaveis', function ($subQuery) use ($matriculaLogado) {
                            $subQuery->where('matricula', $matriculaLogado);
                        });
                });

                if ($request->input('isResponsavel')) {
                    $solicitacoes->where('usuario_responsavel', auth()->id());
                }
            } else {
                if ($request->input('aba') == 'minhas') {

                    // Verificar se a filial do usuário está configurada para visibilidade
                    $codFilialUsuario = (int) optional(auth()->user()?->branches()->first())->id;

                    $filialConfigurada = $codFilialUsuario > 0 && DB::table('intranet_parametros')
                        ->where('menu', 'SOLICITACOES')
                        ->where('submenu', 'CONFIGURACOES')
                        ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
                        ->where('condicao1', $codFilialUsuario)
                        ->where('valor', 1)
                        ->exists();

                    if ($filialConfigurada) {
                        // Ver todos os chamados da filial
                        $solicitacoes->where('filial_id', $codFilialUsuario);
                    } else {
                        // Comportamento padrão: apenas meus chamados (solicitante)
                        $solicitacoes->where('usuario_solicitante', auth()->id());
                    }
                } else {
                    $solicitacoes->where('departamento_responsavel', 'ssa5151sa51d1adqwd');
                }
            }

            if ($request->has('assuntos') && ! empty($request->input('assuntos'))) {
                $assuntos = $request->input('assuntos');
                $assuntoIds = array_map(function ($assunto) {
                    return $assunto['id'];
                }, $assuntos);

                // buscar se existe algm assunto em $assuntos com id null
                $assuntoSemId = collect($assuntos)->firstWhere('id', null);
                if ($assuntoSemId) {
                    // Se existir, buscar solicitações que não possuem assunto
                    $solicitacoes->where(function ($query) use ($assuntoIds) {
                        $query->whereNull('assunto_id')
                            ->orWhereIn('assunto_id', $assuntoIds);
                    });
                } else {
                    $solicitacoes->whereIn('assunto_id', $assuntoIds);
                }
            }

            if ($request->has('responsavel') && ! empty($request->input('responsavel'))) {
                $responsaveis = Arr::wrap($request->input('responsavel'));

                // Extrair matrículas caso venham como objetos (tratamento defensivo)
                $responsaveis = array_map(function ($r) {
                    return is_array($r) ? ($r['matricula'] ?? null) : $r;
                }, $responsaveis);
                $responsaveis = array_values(array_filter($responsaveis, fn($r) => $r !== null));

                // Se houver "nao_atribuido", separar dos demais
                $incluiNaoAtribuido = in_array('nao_atribuido', $responsaveis);
                $responsaveis = array_filter($responsaveis, fn($r) => $r !== 'nao_atribuido');

                if ($incluiNaoAtribuido && count($responsaveis)) {
                    $solicitacoes->where(function ($query) use ($responsaveis) {
                        $query->whereNull('usuario_responsavel')
                            ->orWhereIn('usuario_responsavel', $responsaveis);
                    });
                } elseif ($incluiNaoAtribuido) {
                    $solicitacoes->whereNull('usuario_responsavel');
                } else {
                    $solicitacoes->whereIn('usuario_responsavel', $responsaveis);
                }
            }

            if ($request->has('solicitante') && ! empty($request->input('solicitante'))) {
                $solicitacoes->where('usuario_solicitante', $request->input('solicitante'));
            }

            if ($request->has('dataIni') && ! empty($request->input('dataIni'))) {
                $solicitacoes->where('created_at', '>=', Carbon::parse($request->input('dataIni'))->startOfDay());
            }

            if ($request->has('dataFim') && ! empty($request->input('dataFim'))) {
                $solicitacoes->where('created_at', '<=', Carbon::parse($request->input('dataFim'))->endOfDay());
            }

            if ($request->has('dataAltIni') && ! empty($request->input('dataAltIni'))) {
                $solicitacoes->where('updated_at', '>=', Carbon::parse($request->input('dataAltIni'))->startOfDay());
            }

            if ($request->has('dataAltFim') && ! empty($request->input('dataAltFim'))) {
                $solicitacoes->where('updated_at', '<=', Carbon::parse($request->input('dataAltFim'))->endOfDay());
            }
        }

        // * ⚙️ ORDENAÇÃO
        $ordenacoes = $request->input('ordenacao', [['field' => 'id', 'order' => -1]]);

        // Garantir que ordenação não seja vazia
        if (empty($ordenacoes)) {
            $ordenacoes = [['field' => 'id', 'order' => -1]];
        }

        // Mapeamento de campos de relacionamento para ordenação
        // Campos que vêm do frontend no formato "relacionamento.campo" não funcionam diretamente
        // Precisamos usar os campos da tabela principal ou ignorar ordenação por relacionamentos
        $camposNaoOrdenaveisOuMapeados = [
            'usuario_solicitante.nome' => 'usuario_solicitante', // Ordenar pela matrícula
            'usuario_responsavel.nome' => 'usuario_responsavel', // Ordenar pela matrícula
            'filial.fantasia' => 'filial_id', // Ordenar pelo código da filial
            'filial.uf' => 'filial_id', // Ordenar pelo código da filial
            'filial.cidade' => 'filial_id', // Ordenar pelo código da filial
            'assunto.assunto' => 'assunto_id', // Ordenar pelo ID do assunto
            'usuario_origem.nome' => 'usuario_origem', // Ordenar pela matrícula de origem
        ];

        foreach ($ordenacoes as $ordenacao) {
            $fieldOriginal = $ordenacao['field'];
            $fieldLower = strtolower($fieldOriginal);

            if ($fieldLower == 'dias_aberto') {
                $field = 'created_at';
            } elseif ($fieldLower == 'prioridade') {
                $field = DB::raw("CASE prioridade WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 WHEN 'baixa' THEN 4 ELSE 5 END");
            } elseif (isset($camposNaoOrdenaveisOuMapeados[$fieldLower])) {
                // Usar o campo mapeado da tabela principal
                $field = $camposNaoOrdenaveisOuMapeados[$fieldLower];
            } else {
                $field = $fieldOriginal;
            }

            $order = $ordenacao['order'] == 1 ? 'asc' : 'desc';
            $solicitacoes->orderBy($field, $order);
        }

        $solicitacoesTotal = 0;
        $situacoesInput = $request->input('situacoes');
        $situacoesCount = is_array($situacoesInput) ? count($situacoesInput) : (empty($situacoesInput) ? 0 : 1);
        if ($situacoesCount != 1) {
            $solicitacoesTotal = $solicitacoes->count();
        }

        $solicitacoesFinal = $solicitacoes->paginate($porPagina, ['*'], 'page', $pagina);

        // Buscar fotos dos usuários (solicitantes e responsáveis)
        $matriculas = $solicitacoesFinal->pluck('usuario_solicitante')
            ->merge($solicitacoesFinal->pluck('usuario_responsavel'))
            ->filter()
            ->unique()
            ->values();

        $fotos = DB::table('INTRANET_USUARIO')
            ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
            ->whereIn('INTRANET_USUARIO.matricula', $matriculas)
            ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula');

        // ✅ Batch: carregar todos os usuarios_destino de uma vez (evita N+1)
        $solicitacaoIds = $solicitacoesFinal->pluck('id')->toArray();
        $todosDestinos = SolicitacaoCDest::whereIn('solicitacao_id', $solicitacaoIds)->get()->groupBy('solicitacao_id');

        // ✅ Batch: buscar nomes de todos os destinos de uma vez
        $matriculasDestino = $todosDestinos->flatten()->pluck('matricula')->unique()->filter()->values();
        $nomesDestino = $matriculasDestino->isNotEmpty()
            ? DB::table('users')->whereIn('id', $matriculasDestino)->pluck('name', 'id')
            : collect();

        foreach ($solicitacoesFinal as $solicitacao) {
            $solicitacao->diasAberto = Carbon::parse($solicitacao->created_at)->diffInDays(Carbon::now());

            // Adicionar fotos dos usuários
            $solicitacao->solicitante_foto = $fotos[$solicitacao->usuario_solicitante] ?? null;
            $solicitacao->responsavel_foto = $fotos[$solicitacao->usuario_responsavel] ?? null;

            // ✅ Usuarios destino do batch (sem N+1)
            $destinos = $todosDestinos->get($solicitacao->id, collect());
            foreach ($destinos as $usuario) {
                $usuario->nome = $nomesDestino[$usuario->matricula] ?? '';
            }
            $solicitacao->usuarios_destino = $destinos->values();
        }

        $colunasAtivasPorPadrao = [
            'id',
            'status',
            'prioridade',
            'usuario_solicitante',
            'created_at',
            'filial_id',
            'assunto_id',
            'previsao_entrega',
            'etapa_atual',
        ];

        // ✅ Lista estática de colunas (evita Schema::getColumnListing a cada request)
        $colunasCompletas = [
            'id',
            'titulo',
            'status',
            'prioridade',
            'usuario_solicitante',
            'usuario_responsavel',
            'departamento_responsavel',
            'created_at',
            'updated_at',
            'data_conclusao',
            'descricao',
            'filial_id',
            'assunto_id',
            'usuario_origem',
            'previsao_entrega',
            'existe_acoes',
            'solicitacao_pai_id',
            'hash_duplicata',
            'dias_aberto', // append
            'usuarios_destino',
            'etapa_atual', // custom
            'filial_cidade',
            'filial_uf',
        ];

        // 🔹 Monta estrutura final com status de ativação
        $colunasComStatus = collect($colunasCompletas)->map(fn($coluna) => [
            'coluna' => $coluna,
            'ativarColuna' => in_array($coluna, $colunasAtivasPorPadrao),
        ])->values();

        // ═══════════════════════════════════════════════════════════════════════════
        // CONTAGEM: Usar os mesmos filtros aplicados na query principal
        // ═══════════════════════════════════════════════════════════════════════════
        $queryContagem = Solicitacao::query();

        // Aplicar os mesmos filtros base
        if ($request->input('aba') != 'minhas') {
            $departamentoFiltro = $request->input('departamento.condicao1') ?? (auth()->user()?->department_id);
            $queryContagem->where('departamento_responsavel', $departamentoFiltro);
            $queryContagem->whereNotIn('status', ['finalizada', 'cancelada']);

            if ($request->input('isResponsavel')) {
                $queryContagem->where('usuario_responsavel', auth()->id());
            }
        } else {
            // Verificar se a filial do usuário está configurada para visibilidade
            $codFilialUsuario = (int) optional(auth()->user()?->branches()->first())->id;

            $filialConfigurada = $codFilialUsuario > 0 && DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
                ->where('condicao1', $codFilialUsuario)
                ->where('valor', 1)
                ->exists();

            if ($filialConfigurada) {
                $queryContagem->where('filial_id', $codFilialUsuario);
            } else {
                $queryContagem->where('usuario_solicitante', auth()->id());
            }
        }

        // Filtro por situações (para limitar a contagem apenas às situações selecionadas)
        $situacoesContagem = $request->input('situacoes', []);
        // Garantir que situacoesContagem seja sempre um array
        if (! is_array($situacoesContagem)) {
            $situacoesContagem = empty($situacoesContagem) ? [] : [$situacoesContagem];
        }
        $filtrouAtrasadas = false;
        if (! empty($situacoesContagem)) {
            // Verificar se está filtrando por atrasados
            if (in_array('atrasadas', $situacoesContagem)) {
                $filtrouAtrasadas = true;
                // Remover 'atrasadas' do array de situações normais
                $situacoesContagem = array_diff($situacoesContagem, ['atrasadas']);

                // Aplicar filtro para solicitações atrasadas
                $queryContagem->where(function ($query) use ($situacoesContagem) {
                    // Filtro para solicitações atrasadas
                    $query->where(function ($subQuery) {
                        $subQuery->whereNotNull('previsao_entrega')
                            ->whereIn('status', ['pendente', 'em atendimento', 'atendimento pausado', 'agendado'])
                            ->whereDate('previsao_entrega', '<', now()->startOfDay());
                    });

                    // Se ainda houver outras situações, incluir elas também
                    if (! empty($situacoesContagem)) {
                        $query->orWhereIn('status', $situacoesContagem);
                    }
                });
            } else {
                // Filtro normal por situações
                $queryContagem->whereIn('status', $situacoesContagem);
            }
        }

        // Filtro por prioridades
        if ($request->has('prioridades') && ! empty($request->input('prioridades'))) {
            $queryContagem->whereIn('prioridade', $request->input('prioridades'));
        }

        // Filtro por filiais
        if ($request->has('filiais') && ! empty($request->input('filiais'))) {
            $queryContagem->whereIn('filial_id', $request->input('filiais'));
        }

        // Filtro por UF
        if ($request->has('ufs') && ! empty($request->input('ufs'))) {
            $queryContagem->whereHas('filial', function ($q) use ($request) {
                $q->whereIn('uf', $request->input('ufs'));
            });
        }

        // Filtro por Cidade
        if ($request->has('cidades') && ! empty($request->input('cidades'))) {
            $cidades = $request->input('cidades');
            $queryContagem->whereHas('filial', function ($q) use ($cidades) {
                $bindings = implode(',', array_fill(0, count($cidades), '?'));
                $q->whereRaw("TRIM(cidade) IN ({$bindings})", $cidades);
            });
        }

        // Filtro por assuntos
        if ($request->has('assuntos') && ! empty($request->input('assuntos'))) {
            $assuntos = $request->input('assuntos');
            $assuntoIds = array_map(fn($assunto) => $assunto['id'], $assuntos);
            $assuntoSemId = collect($assuntos)->firstWhere('id', null);

            if ($assuntoSemId) {
                $queryContagem->where(function ($query) use ($assuntoIds) {
                    $query->whereNull('assunto_id')->orWhereIn('assunto_id', $assuntoIds);
                });
            } else {
                $queryContagem->whereIn('assunto_id', $assuntoIds);
            }
        }

        // Filtro por responsável
        if ($request->has('responsavel') && ! empty($request->input('responsavel'))) {
            $responsaveis = Arr::wrap($request->input('responsavel'));
            $incluiNaoAtribuido = in_array('nao_atribuido', $responsaveis);
            $responsaveis = array_filter($responsaveis, fn($r) => $r !== 'nao_atribuido');

            if ($incluiNaoAtribuido && count($responsaveis)) {
                $queryContagem->where(function ($query) use ($responsaveis) {
                    $query->whereNull('usuario_responsavel')->orWhereIn('usuario_responsavel', $responsaveis);
                });
            } elseif ($incluiNaoAtribuido) {
                $queryContagem->whereNull('usuario_responsavel');
            } else {
                $queryContagem->whereIn('usuario_responsavel', $responsaveis);
            }
        }

        // Filtro por solicitante
        if ($request->has('solicitante') && ! empty($request->input('solicitante'))) {
            $queryContagem->where('usuario_solicitante', $request->input('solicitante'));
        }

        // Filtro por data de criação
        if ($request->has('dataIni') && ! empty($request->input('dataIni'))) {
            $queryContagem->where('created_at', '>=', Carbon::parse($request->input('dataIni'))->startOfDay());
        }
        if ($request->has('dataFim') && ! empty($request->input('dataFim'))) {
            $queryContagem->where('created_at', '<=', Carbon::parse($request->input('dataFim'))->endOfDay());
        }

        // Filtro por data de alteração
        if ($request->has('dataAltIni') && ! empty($request->input('dataAltIni'))) {
            $queryContagem->where('updated_at', '>=', Carbon::parse($request->input('dataAltIni'))->startOfDay());
        }
        if ($request->has('dataAltFim') && ! empty($request->input('dataAltFim'))) {
            $queryContagem->where('updated_at', '<=', Carbon::parse($request->input('dataAltFim'))->endOfDay());
        }

        // ✅ CONTAGEM DE ATRASADAS: clonar ANTES de mutar $queryContagem
        $solicitacoesAtrasadas = (clone $queryContagem)
            ->whereNotNull('previsao_entrega')
            ->whereIn('status', ['pendente', 'em atendimento', 'atendimento pausado', 'agendado'])
            ->whereDate('previsao_entrega', '<', now()->startOfDay())
            ->count();

        // ✅ CONTAGEM OTIMIZADA: usar COUNT + GROUP BY ao invés de carregar todos os registros
        $contagemPorStatus = $queryContagem
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $solicitacaoPendentes = $contagemPorStatus->get('pendente', 0);
        $solicitacaoEmAtendimento = $contagemPorStatus->get('em atendimento', 0);
        $solicitacaoAgendada = $contagemPorStatus->get('agendado', 0);
        $solicitacaoPausada = $contagemPorStatus->get('atendimento pausado', 0);
        $solicitacaoResolvida = $contagemPorStatus->get('resolvida', 0);
        $solicitacaoRecusada = $contagemPorStatus->get('resolução recusada', 0);
        $solcicitacaoRetornada = $contagemPorStatus->get('retorno solicitante', 0);

        // Na aba de atendimento não mostramos finalizada/cancelada
        if ($request->input('aba') != 'minhas') {
            $solicitacaoFinalizada = 0;
            $solicitacaoCancelada = 0;
        } else {
            $solicitacaoFinalizada = $contagemPorStatus->get('finalizada', 0);
            $solicitacaoCancelada = $contagemPorStatus->get('cancelada', 0);
        }

        // Total considerando apenas os status exibidos na aba
        if ($request->input('aba') != 'minhas') {
            $solicitacoesTotalContagem = $solicitacaoPendentes + $solicitacaoEmAtendimento + $solicitacaoAgendada + $solicitacaoPausada + $solicitacaoResolvida + $solicitacaoRecusada + $solcicitacaoRetornada;
        } else {
            $solicitacoesTotalContagem = $solicitacaoPendentes + $solicitacaoEmAtendimento + $solicitacaoAgendada + $solicitacaoPausada + $solicitacaoResolvida + $solicitacaoFinalizada + $solicitacaoCancelada + $solicitacaoRecusada + $solcicitacaoRetornada;
        }

        return [
            'solicitacoes' => $solicitacoesFinal,
            'paginacao' => [
                'pagina' => $solicitacoesFinal->currentPage(),
                'paginas' => $solicitacoesFinal->lastPage(),
                'porPagina' => $porPagina,
            ],
            'solicitacoesMarcadas' => $request->input('solicitacoesMarcadas', null),
            'colunas' => $colunasComStatus,
            'contagem' => [
                'pendentes' => $solicitacaoPendentes,
                'em_atendimento' => $solicitacaoEmAtendimento,
                'agendado' => $solicitacaoAgendada,
                'atendimento_pausado' => $solicitacaoPausada,
                'resolvida' => $solicitacaoResolvida,
                'finalizada' => $solicitacaoFinalizada,
                'cancelada' => $solicitacaoCancelada,
                'resolucao_recusada' => $solicitacaoRecusada,
                'retorno_solicitante' => $solcicitacaoRetornada,
                'atrasadas' => $solicitacoesAtrasadas,
                'total' => $solicitacoesTotalContagem,
            ],
        ];
    }

    public function getSolicitacao($id)
    {
        $solicitacao = Solicitacao::with([
            'usuarioSolicitante',
            'usuarioResponsavel',
            'assunto',
            'filial',
            'arquivos.file',
            'movimentacoes.usuarioMovimentacao',
            'responsaveisRelacionados',
            'comentarios.arquivos.file',
            'comentarios.usuario',
            'rotinas',
            'agendamentos',
            'aprovacoes.solicitante',
            'aprovacoes.aprovador',
            'aprovacoes.respondidoPor',
            'respostasSelecao',
            'respostasSelecao.file',
            'respostasSelecao.selecao',
            'respostasSelecao.item',
            'filialDeptoSelect',
        ])
            ->where('id', intval($id))
            ->first();

        // TODO: revisar vínculo departamento — responsáveis adicionais (intranet_parametros RESPONSAVEL_ADICIONAL) não portado
        $responsaveisAdicionais = [];

        // Mescla e remove duplicados por matrícula
        $colecaoMescladas = collect($solicitacao->responsaveisRelacionados)
            ->merge($responsaveisAdicionais)
            ->unique(fn($item) => is_array($item) ? $item['MATRICULA'] : $item->matricula)
            ->values();

        $solicitacao->setRelation('responsaveisRelacionados', $colecaoMescladas);

        foreach ($solicitacao->rotinas as $rotina) {
            // TODO: integrar catálogo de rotinas (ERP-legado era ERP legado) — sem equivalente no 5E
            $rotina->dados = null;
        }

        $solicitacao->diasAberto = Carbon::parse($solicitacao->created_at)->diffInDays(Carbon::now());

        $solicitacao->dadosAcesso = SolicitacaoCAcessos::where('SOLICITACAO_ID', $solicitacao->id)
            ->distinct()
            ->select(['TIPO'])
            ->get();

        foreach ($solicitacao->dadosAcesso as $dadosAcesso) {
            $dadosAcesso->dados = SolicitacaoCAcessos::where('SOLICITACAO_ID', $solicitacao->id)
                ->where('tipo', $dadosAcesso->tipo)
                ->get();

            foreach ($dadosAcesso->dados as $dado) {

                if ($dadosAcesso->tipo == 'Filiais') {
                    $dado->descricao = UtilController::nomeFilial($dado->codigo);
                }

                if ($dadosAcesso->tipo == 'Departamentos') {
                    $dado->descricao = \App\Models\Department::where('id', $dado->codigo)->value('name');
                }

                if ($dadosAcesso->tipo == 'Moedas') {
                    // TODO: integrar moedas (ERP-legado era ERP legado) — sem equivalente no 5E
                    $dado->descricao = $dado->codigo;
                }

                if ($dadosAcesso->tipo == 'Bancos') {
                    // TODO: integrar bancos (ERP-legado era ERP legado) — sem equivalente no 5E
                    $dado->descricao = $dado->codigo;
                }

                if ($dadosAcesso->tipo == 'Centros de Custo') {
                    // TODO: integrar centros de custo (ERP-legado era ERP legado) — sem equivalente no 5E
                    $dado->descricao = $dado->codigo;
                }
            }
        }

        $totalComentarios = $solicitacao->comentarios->count();
        foreach ($solicitacao->comentarios as $index => $comentario) {
            $comentario->is_owner = auth()->id() == $comentario->usuario;
            $comentario->is_sistema = $comentario->isSistema();
            $isUltimo = $index === $totalComentarios - 1;
            $dentroDoPrazo = $comentario->created_at->diffInMinutes(now()) <= 5;
            $comentario->pode_excluir = $comentario->is_owner && $isUltimo && ! $comentario->is_sistema && $dentroDoPrazo;
        }

        $solicitacao->usuario_origem = [
            'matricula' => $solicitacao->usuario_origem,
            'nome' => UtilController::nomeFuncionario($solicitacao->usuario_origem),
        ];

        $solicitacao->vendas = SolicitacaoCVendas::where('SOLICITACAO_ID', $solicitacao->id)
            ->get();

        foreach ($solicitacao->vendas as $venda) {
            $venda->filial = [
                'codigo' => $venda->filial,
                'fantasia' => UtilController::nomeFilial($venda->filial),
            ];
            $venda->caixas = json_decode($venda->caixas, true);
            $venda->operador = [
                'matricula' => $venda->operador,
                'nome' => UtilController::nomeFuncionario($venda->operador),
            ];
        }

        $solicitacao->equipamentos = SolicitacaoCEquip::where('SOLICITACAO_ID', $solicitacao->id)->get();

        $solicitacao->usuariosDestino = SolicitacaoCDest::where('SOLICITACAO_ID', $solicitacao->id)->get();

        foreach ($solicitacao->usuariosDestino as $usuario) {
            $usuario->matricula = $usuario->matricula;
            $usuario->nome = UtilController::nomeFuncionario($usuario->matricula);
        }

        // Verifica se o usuário pertence ao mesmo departamento
        $areaUsuario = (auth()->user()?->department_id);
        $mesmoDepartamento = $areaUsuario == $solicitacao->departamento_responsavel;

        // Verifica se o usuário está na relação de responsáveis relacionados
        $matriculasRelacionadas = collect($solicitacao->responsaveisRelacionados)->pluck('matricula')->map(fn($v) => trim($v))->toArray();
        $estaNaListaRelacionados = in_array(trim(auth()->id()), $matriculasRelacionadas);

        // Verifica se o usuário é o responsável atribuído (permite ações mesmo de fora do departamento)
        $isResponsavelAtribuido = $solicitacao->getOriginal('usuario_responsavel')
            && trim($solicitacao->getOriginal('usuario_responsavel')) == trim(auth()->id());

        $solicitacao->isDepartamento = $mesmoDepartamento || $estaNaListaRelacionados || $isResponsavelAtribuido;

        // Verificar se usuário é dono do chamado
        $isOwner = auth()->id() == $solicitacao->usuario_solicitante;

        // Se não é o solicitante, verificar se é da mesma filial configurada
        if (! $isOwner) {
            $codFilialUsuario = (int) optional(auth()->user()?->branches()->first())->id;

            $isOwner = $codFilialUsuario > 0
                && $codFilialUsuario == $solicitacao->filial_id
                && DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
                ->where('condicao1', $codFilialUsuario)
                ->where('valor', 1)
                ->exists();
        }

        $solicitacao->isOwner = $isOwner;

        $solicitacao->usuarioLogado = [
            'matricula' => auth()->id(),
            'nome' => (auth()->user()?->name),
            'areaatuacao' => (auth()->user()?->department_id),
        ];

        // Buscar fotos de perfil dos usuários (solicitante, responsável e aprovadores)
        $matriculasParaFotos = collect([
            $solicitacao->usuario_solicitante,
            $solicitacao->usuario_responsavel,
        ]);

        // Adicionar matrículas dos aprovadores
        foreach ($solicitacao->aprovacoes as $aprovacao) {
            if ($aprovacao->aprovador) {
                $matriculasParaFotos->push($aprovacao->aprovador->matricula);
            }
            if ($aprovacao->solicitante) {
                $matriculasParaFotos->push($aprovacao->solicitante->matricula);
            }
        }

        // Adicionar matrículas dos responsáveis relacionados
        foreach ($solicitacao->responsaveisRelacionados as $responsavel) {
            $matriculasParaFotos->push(is_array($responsavel) ? $responsavel['MATRICULA'] : $responsavel->matricula);
        }

        $matriculasParaFotos = $matriculasParaFotos->filter()->unique()->values();

        $fotos = DB::table('INTRANET_USUARIO')
            ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
            ->whereIn('INTRANET_USUARIO.matricula', $matriculasParaFotos)
            ->select('INTRANET_USUARIO.matricula', 'intranet_files.external_link')
            ->get()
            ->mapWithKeys(function ($item) {
                return [trim($item->matricula) => $item->external_link];
            });

        // Adicionar foto ao usuário solicitante
        if ($solicitacao->usuarioSolicitante) {
            $solicitacao->usuarioSolicitante->foto_perfil = $fotos[trim($solicitacao->usuario_solicitante)] ?? null;
        }

        // Adicionar foto ao usuário responsável
        if ($solicitacao->usuarioResponsavel) {
            $solicitacao->usuarioResponsavel->foto_perfil = $fotos[trim($solicitacao->usuario_responsavel)] ?? null;
        }

        // Adicionar fotos aos aprovadores
        foreach ($solicitacao->aprovacoes as $aprovacao) {
            if ($aprovacao->aprovador) {
                $aprovacao->aprovador->foto_perfil = $fotos[trim($aprovacao->aprovador->matricula)] ?? null;
            }
            if ($aprovacao->solicitante) {
                $aprovacao->solicitante->foto_perfil = $fotos[trim($aprovacao->solicitante->matricula)] ?? null;
            }
        }

        // Adicionar fotos aos responsáveis relacionados
        foreach ($solicitacao->responsaveisRelacionados as $responsavel) {
            $matricula = is_array($responsavel) ? $responsavel['MATRICULA'] : $responsavel->matricula;
            if (is_object($responsavel)) {
                $responsavel->foto_perfil = $fotos[trim($matricula)] ?? null;
            }
        }

        // Buscar etapa atual e etapas disponíveis do assunto
        $etapaAtual = \App\Models\SolicitacaoEtapaAtual::where('solicitacao_id', $solicitacao->id)
            ->with('etapa')
            ->first();

        $solicitacao->etapa_atual = $etapaAtual?->etapa;

        // Buscar todas as etapas ativas do assunto
        $solicitacao->etapas_disponiveis = $solicitacao->assunto_id
            ? \App\Models\SolicitacaoEtapa::where('assunto_id', $solicitacao->assunto_id)
            ->where('ativo', 'S')
            ->orderBy('ordem')
            ->get()
            : [];

        // Sanitizar encoding ERP legado nos nomes de usuário das movimentações
        foreach ($solicitacao->movimentacoes as $mov) {
            if ($mov->usuarioMovimentacao && $mov->usuarioMovimentacao->nome) {
                $mov->usuarioMovimentacao->nome = mb_convert_encoding(
                    $mov->usuarioMovimentacao->nome,
                    'UTF-8',
                    'UTF-8'
                );
            }
        }

        // Retorna com JSON_INVALID_UTF8_SUBSTITUTE para evitar "Malformed UTF-8"
        $json = json_encode($solicitacao, JSON_INVALID_UTF8_SUBSTITUTE);

        return response($json, 200, ['Content-Type' => 'application/json']);
    }

    public function mudarPrioridade(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $prioridadeAnterior = $solicitacao->prioridade;

            $solicitacao->prioridade = $request->input('novaPrioridade');

            $solicitacao->save();

            $alterador = (auth()->user()?->name);

            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Mudança de prioridade',
                'descricao' => "$alterador mudou a prioridade de '$prioridadeAnterior' para '{$request->input('novaPrioridade')}'",
                'usuario_movimentacao' => auth()->id(),
            ]);

            $tituloNot = 'Mudança de prioridade';
            $mensagemNot = "$alterador mudou a prioridade de '$prioridadeAnterior' para '{$request->input('novaPrioridade')}'";
            $origem = 'solicitacoes.mudarPrioridade';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_solicitante], $origem, $link);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'prioridade');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function mudarResponsavel(Request $request)
    {

        DB::beginTransaction();
        try {

            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $idAgendamento = SolicitacaoAgendSol::where('solicitacao_id', $solicitacao->id)->orderBy('id', 'desc')->value('agendamento_id');

            $agendamento = SolicitacaoAgendamento::where('id', $idAgendamento)->whereRaw("status not in ('cancelado', 'finalizado')")->first();
            if ($agendamento) {

                $agendamento->mat_responsavel = $request->input('responsavel');
                $agendamento->save();
            }

            $anterior = UtilController::nomeFuncionario($solicitacao->usuario_responsavel);
            $novo = UtilController::nomeFuncionario($request->input('responsavel'));
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());

            $seAtribuir = $request->input('seAtribuir');

            // Atribuiu a si mesmo
            if ($request->input('responsavel') == auth()->id() || $seAtribuir) {
                $descricao = "$nomeLogado se atribuíu como responsável do chamado";
            }
            // Não exite responsável e atribuiu um responsável
            elseif (empty($solicitacao->usuario_responsavel) && $request->input('responsavel')) {
                $descricao = "$nomeLogado atribuiu o usuário $novo como responsável pelo chamado";
            }
            // Existia um responsável, e enviou para remover
            elseif ($solicitacao->usuario_responsavel && ! $request->input('responsavel')) {
                $descricao = "$nomeLogado removeu o usuário $anterior de responsável pelo chamado";
            }
            // Removeu responsável anterior e colocou um novo
            else {
                $descricao = "$nomeLogado removeu o responsável $anterior, e colocou $novo como novo responsável pelo chamado";
            }

            $solicitacao->movimentacoes()->create([
                'usuario_origem' => $solicitacao->usuario_responsavel,
                'usuario_destino' => $seAtribuir ? auth()->id() : $request->input('responsavel'),
                'tipo_movimentacao' => 'Mudança de responsável',
                'descricao' => $descricao,
                'usuario_movimentacao' => auth()->id(),
            ]);

            if ($seAtribuir) {
                $solicitacao->usuario_responsavel = auth()->id();
            } else {
                $solicitacao->usuario_responsavel = $request->input('responsavel');
            }

            $tituloNot = 'Responsável atribuído';
            $mensagemNot = $descricao;
            $origem = 'solicitacoes.mudarReponsavel';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);
            if ($solicitacao->usuario_responsavel) {
                $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_responsavel, $solicitacao->usuario_solicitante], $origem, $link);
            } else {
                $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_solicitante], $origem, $link);
            }

            $solicitacao->save();

            DB::commit();

            // Notificar via Reverb
            try {
                $reverbService = new SolicitacaoReverbService;
                $solicitacaoData = $solicitacao->fresh()->toArray();

                // Notificar atualização no departamento
                $reverbService->notificarAtualizacao($solicitacaoData, $solicitacao->departamento_responsavel, 'responsavel');

                // Notificar o novo responsável
                if ($solicitacao->usuario_responsavel) {
                    $reverbService->notificarAtribuicao($solicitacaoData, $solicitacao->usuario_responsavel);
                }
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar mudança de responsável', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacao->id,
                    'departamento' => $solicitacao->departamento_responsavel,
                    'error' => $e->getMessage(),
                ]);
            }

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function comentar(Request $request)
    {
        $privado = $request->input('private');
        $tipoPrivado = $request->input('privateType'); // null, 'S' ou 'A'
        // Garantir que comentario nunca seja null/vazio (ERP legado trata '' como NULL)
        $textoComentario = $request->input('comentario');
        if (empty($textoComentario) || trim(strip_tags($textoComentario)) === '') {
            $textoComentario = ' '; // Espaço em branco para ERP legado aceitar
        }

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();
            if ($solicitacao->status == 'retorno solicitante' && $request->input('solicitacao.status') == 'atendimento pausado') {
                $solicitacao->status = 'atendimento pausado';
            }
            $solicitacao->save();

            // Determinar valor do campo private: null (público), 'S' (privado pessoal), 'A' (área de atuação)
            $valorPrivate = null;
            if ($privado && in_array($tipoPrivado, ['S', 'A'])) {
                $valorPrivate = $tipoPrivado;
            } elseif ($privado) {
                $valorPrivate = 'S'; // Fallback para compatibilidade
            }

            $dadosComentario = [
                'usuario' => auth()->id(),
                'comentario' => $textoComentario,
            ];

            if ($valorPrivate) {
                $dadosComentario['private'] = $valorPrivate;
            }

            $comentario = $solicitacao->comentarios()->create($dadosComentario);

            $arquivos = $request->input('arquivos', []);
            if (is_array($arquivos) && count($arquivos) > 0) {
                foreach ($arquivos as $arquivo) {
                    if (isset($arquivo['fileTab']['id'])) {
                        $comentario->arquivos()->create([
                            'solicitacao_id' => $solicitacao->id,
                            'arquivo_id' => $arquivo['fileTab']['id'],
                            'usuario' => auth()->id(),
                        ]);
                    }
                }
            }

            DB::commit();

            // Notificar via Reverb em tempo real
            try {
                $reverbService = new SolicitacaoReverbService;
                $reverbService->notificarComentario(
                    $solicitacao->id,
                    [
                        'id' => $comentario->id,
                        'usuario' => $comentario->usuario,
                        'comentario' => $comentario->comentario,
                        'private' => $comentario->private ?? 'N',
                        'created_at' => $comentario->created_at->toISOString(),
                    ],
                    $solicitacao->departamento_responsavel
                );
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar comentário', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacao->id,
                    'departamento' => $solicitacao->departamento_responsavel,
                    'error' => $e->getMessage(),
                ]);
            }

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Exclui (soft delete) o último comentário de uma solicitação.
     *
     * Regras:
     * - Só o autor do último comentário pode excluí-lo
     * - Comentários do sistema não podem ser excluídos
     * - Limite de 5 minutos após a criação
     */
    public function excluirComentario(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $comentario = SolicitacaoCom::findOrFail($id);
        $matriculaLogado = auth()->id();

        if ($comentario->usuario != $matriculaLogado) {
            return response()->json(['message' => 'Você não tem permissão para excluir este comentário.'], 403);
        }

        if ($comentario->isSistema()) {
            return response()->json(['message' => 'Comentários do sistema não podem ser excluídos.'], 403);
        }

        $ultimoComentario = SolicitacaoCom::where('solicitacao_id', $comentario->solicitacao_id)
            ->orderByDesc('id')
            ->first();

        if (! $ultimoComentario || $ultimoComentario->id !== $comentario->id) {
            return response()->json(['message' => 'Só é possível excluir o último comentário.'], 422);
        }

        $minutosDesdeACriacao = $comentario->created_at->diffInMinutes(now());
        if ($minutosDesdeACriacao > 5) {
            return response()->json(['message' => 'O prazo de 5 minutos para exclusão expirou.'], 422);
        }

        DB::beginTransaction();
        try {
            $solicitacaoId = $comentario->solicitacao_id;
            $departamento = $comentario->solicitacao->departamento_responsavel;

            $comentario->arquivos()->delete();
            $comentario->delete();

            DB::commit();

            try {
                $reverbService = new SolicitacaoReverbService;
                $reverbService->notificarComentarioExcluido($solicitacaoId, $id, $departamento);
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar exclusão de comentário', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacaoId,
                    'comentario_id' => $id,
                    'departamento' => $departamento,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['message' => 'Comentário excluído com sucesso.']);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function iniciarAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $solicitacao->status = 'em atendimento';

            $solicitacao->save();

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Inicio de atendimento',
                'descricao' => (auth()->user()?->name) . ' iniciou o atendimento',
                'usuario_movimentacao' => auth()->id(),
            ]);

            $tituloNot = 'Inicio de atendimento';
            $mensagemNot = (auth()->user()?->name) . ' iniciou o atendimento';
            $origem = 'solicitacoes.iniciarAtendimento';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);
            $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_solicitante], $origem, $link);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function pausarAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $solicitacao->status = 'atendimento pausado';

            $solicitacao->save();

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Pausa de atendimento',
                'descricao' => (auth()->user()?->name) . ' pausou o atendimento',
                'usuario_movimentacao' => auth()->id(),
            ]);

            $tituloNot = 'Pausa de atendimento';
            $mensagemNot = (auth()->user()?->name) . ' pausou o atendimento';
            $origem = 'solicitacoes.pausarAtendimento';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);
            $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_solicitante], $origem, $link);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function resolverAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $solicitacao->status = 'resolvida';
            $solicitacao->data_conclusao = Carbon::now();

            $solicitacao->save();

            // $solicitacao->comentarios()->create([
            //     'usuario' => auth()->id(),
            //     'comentario' => 'Atendimento resolvido, ' . PHP_EOL . $request->input('comentario')
            // ]);

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Atendimento resolvido',
                'descricao' => (auth()->user()?->name) . ' resolveu o atendimento',
                'usuario_movimentacao' => auth()->id(),
            ]);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function recusarAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {

            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $solicitacao->status = 'resolução recusada';
            $solicitacao->data_conclusao = null;

            $solicitacao->save();

            $solicitacao->comentarios()->create([
                'usuario' => auth()->id(),
                'comentario' => 'Resolução recusada, ' . PHP_EOL . $request->input('comentario'),
            ]);

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Resolução recusada',
                'descricao' => (auth()->user()?->name) . ' recusou a resolução do atendimento',
                'usuario_movimentacao' => auth()->id(),
            ]);

            DB::commit();

            // Reabrir fluxo de workflow (se houver) para o atendente continuar de onde parou
            try {
                $workflowService = new WorkflowService(new SolicitacaoReverbService);
                $workflowService->reativarFluxoPorRecusa(
                    $solicitacao,
                    auth()->id(),
                    $request->input('comentario')
                );
            } catch (\Throwable $e) {
                Log::warning('Workflow: Falha ao reativar fluxo após recusa de resolução', [
                    'solicitacao_id' => $solicitacao->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function cancelarAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            // Busca e cancela agendamento atrelado
            $idAgendamento = SolicitacaoAgendSol::where('solicitacao_id', $solicitacao->id)->orderBy('id', 'desc')->value('agendamento_id');
            $agendamento = SolicitacaoAgendamento::where('status', 'finalizado')->where('id', $idAgendamento)->first();
            if ($agendamento) {
                $agendamento->status = 'cancelado';
                $agendamento->data_cancelamento = carbon::now();
                $agendamento->mat_cancelamento = auth()->id();
                $agendamento->save();
            }

            // Cancela lembretes ativos vinculados
            $this->finalizarLembretesVinculados($solicitacao->id, 'cancelado');

            $solicitacao->status = 'cancelada';
            $solicitacao->save();

            $solicitacao->comentarios()->create([
                'usuario' => auth()->id(),
                'comentario' => 'Solicitação cancelada, ' . PHP_EOL . $request->input('comentario'),
            ]);

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Solicitação cancelada',
                'descricao' => (auth()->user()?->name) . ' cancelou a solicitação',
                'usuario_movimentacao' => auth()->id(),
            ]);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function finalizarAtendimento(Request $request)
    {

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();

            $solicitacao->status = 'finalizada';
            $solicitacao->data_conclusao = $solicitacao->data_conclusao ?? Carbon::now();

            $solicitacao->save();

            $solicitacao->comentarios()->create([
                'usuario' => auth()->id(),
                'comentario' => 'Atendimento finalizado',
            ]);

            // Criar movimentação de inicio de atendimento
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Atendimento finalizado',
                'descricao' => (auth()->user()?->name) . ' finalizou o atendimento',
                'usuario_movimentacao' => auth()->id(),
            ]);

            // Finaliza lembretes ativos vinculados a esta solicitação
            $this->finalizarLembretesVinculados($solicitacao->id);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return Response()->json();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function getDados(Request $request)
    {

        $areaAtuacao = (auth()->user()?->department_id);
        $responsaveis = Funcionario::where('areaatuacao', $areaAtuacao)
            ->where('situacao', 'A')
            ->whereNotIn('matricula', [99999999, 7801, 10000]) // excluir usuarios ficiticios
            ->select(['matricula', 'nome'])
            ->get();

        // Buscar fotos de perfil dos responsáveis
        $matriculas = $responsaveis->pluck('matricula')->filter()->unique()->values();
        $fotos = DB::table('INTRANET_USUARIO')
            ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
            ->whereIn('INTRANET_USUARIO.matricula', $matriculas)
            ->select('INTRANET_USUARIO.matricula', 'intranet_files.external_link')
            ->get()
            ->mapWithKeys(function ($item) {
                return [trim($item->matricula) => $item->external_link];
            });

        foreach ($responsaveis as $responsavel) {
            $responsavel->foto_perfil = $fotos[trim($responsavel->matricula)] ?? null;
        }

        $dados = [
            'responsaveis' => $responsaveis,
        ];

        return $dados;
    }

    public function getEnderecoFilial($codFilial)
    {
        // Adaptado (5E): dados de filial vêm de `branches` (model Filial), no lugar de ERP-legado.
        // branches não possui colunas de endereço; retornamos o que temos com nulls para o restante.
        $branch = Filial::where('id', $codFilial)
            ->orWhere('code', $codFilial)
            ->first();

        if (! $branch) {
            return null;
        }

        return (object) [
            'endereco' => null,
            'codigo' => $branch->codigo,
            'numero' => null,
            'complemento' => null,
            'bairro' => null,
            'cep' => null,
            'cidade' => null,
            'uf' => null,
            'razaosocial' => $branch->razaosocial,
            'link_maps' => null, // TODO: storage/cadastro de endereço de filial
        ];
    }

    public function criarAgendamento(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataAgendamento = $request->input('agendamento.data');
            $dataFimAgendamento = $request->input('agendamento.dataFim');
            $filial = $request->input('agendamento.filial');
            $responsavel = $request->input('agendamento.usuarioResponsavel');
            $observacao = $request->input('agendamento.observacao');
            if ($observacao && mb_strlen($observacao) > 4000) {
                return response()->json(['message' => 'A observação não pode ultrapassar 4000 caracteres.'], 422);
            }
            $solicitacoes = $request->input('solicitacoes');
            $userCria = auth()->id();
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());
            $nomeResponsavel = UtilController::nomeFuncionario($responsavel);

            $solicitacoes = $solicitacoes && isset($solicitacoes[0]) ? $solicitacoes : [];

            $existeAgendamento = SolicitacaoAgendamento::where('mat_responsavel', $responsavel)
                ->where('data_agendamento', '<', $dataFimAgendamento)
                ->where('data_fim_agendamento', '>', $dataAgendamento)
                ->where('status', 'ativo')
                ->get();

            if (count($existeAgendamento)) {
                return response()->json(['message' => 'Há conflito de horários com agendamentos existentes para o responsável.'], 400);
            }

            $agendamento = SolicitacaoAgendamento::create([
                'mat_responsavel' => $responsavel,
                'filial' => $filial,
                'data_agendamento' => $dataAgendamento,
                'data_fim_agendamento' => $dataFimAgendamento,
                'user_cria' => $userCria,
                'status' => 'ativo',
                'observacao' => $observacao,

            ]);

            if ($agendamento->id) {
                foreach ($solicitacoes as $solicitacao) {

                    // Adicionar histórico de altrações
                    $solicitacaoData = Solicitacao::where('id', $solicitacao['id'])->first();
                    $solicitacaoData->status = 'agendado';
                    $responsavelAnterior = $solicitacao['usuario_responsavel']['matricula'] ?? null;
                    $solicitacaoData->usuario_responsavel = $responsavel;

                    $solicitacaoData->movimentacoes()->create([
                        'tipo_movimentacao' => 'Agendamento Criado',
                        'descricao' => "$nomeLogado criou um novo agendamento. $nomeResponsavel foi atribuido como responsável pela solicitação e agendamento.",
                        'usuario_origem' => $responsavelAnterior,
                        'usuario_destino' => $responsavel,
                        'usuario_movimentacao' => auth()->id(),
                    ]);

                    $tituloNot = 'Agendamento Criado';
                    $mensagemNot = "$nomeLogado criou um novo agendamento. $nomeResponsavel foi atribuido como responsável pela solicitação e agendamento.";
                    $origem = 'solicitacoes.criarAgendamento';
                    $link = url('/solicitacoes/lista?solicitacao=' . $solicitacaoData->id);

                    if ($responsavelAnterior) {
                        $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacaoData->usuario_responsavel, $responsavelAnterior, $solicitacaoData->usuario_solicitante], $origem, $link);
                    } else {
                        $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacaoData->usuario_responsavel, $solicitacaoData->usuario_solicitante], $origem, $link);
                    }

                    $solicitacaoData->save();

                    $agendSol = SolicitacaoAgendSol::create([
                        'solicitacao_id' => $solicitacao['id'],
                        'agendamento_id' => $agendamento->id,
                    ]);
                }
            }

            DB::commit();

            // Notificar via Reverb para cada solicitação agendada
            foreach ($solicitacoes as $solicitacao) {
                $solicitacaoObj = Solicitacao::find($solicitacao['id']);
                if ($solicitacaoObj) {
                    $this->notificarReverbAtualizacao($solicitacaoObj, 'agendamento');
                }
            }

            // NotificationCenter v2 — notifica o técnico (cascata multi-canal).
            // 1 notificação consolidada por agendamento, fora do loop de solicitações.
            try {
                app(\App\Services\Notifications\Agendamento\AgendamentoNotifier::class)
                    ->notificarCriacao($agendamento);
            } catch (\Throwable $e) {
                Log::error('AgendamentoNotifier(criar): ' . $e->getMessage());
            }

            return response()->json('', 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao criar agendamento.', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Cria um agendamento do tipo "lembrete" para uma solicitação
     *
     * O lembrete é um agendamento simplificado que serve como marcador no calendário.
     * Ao clicar, abre a solicitação diretamente. É finalizado automaticamente
     * quando a solicitação é finalizada.
     */
    public function criarLembrete(Request $request)
    {
        DB::beginTransaction();
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $dataAgendamento = $request->input('data');
            $horaAgendamento = $request->input('hora'); // Opcional
            $observacao = $request->input('observacao');
            $userCria = auth()->id();
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());

            $solicitacao = Solicitacao::where('id', $solicitacaoId)->first();

            if (! $solicitacao) {
                return response()->json(['message' => 'Solicitação não encontrada.'], 404);
            }

            // Formata data/hora
            if ($horaAgendamento) {
                $dataFormatada = $dataAgendamento . ' ' . $horaAgendamento;
            } else {
                $dataFormatada = $dataAgendamento . ' 08:00';
            }

            // Criar o lembrete
            $lembrete = SolicitacaoAgendamento::create([
                'mat_responsavel' => $solicitacao->usuario_responsavel ?? $userCria,
                'filial' => $solicitacao->filial_id,
                'data_agendamento' => $dataFormatada,
                'data_fim_agendamento' => $dataFormatada, // Mesmo horário para lembrete
                'user_cria' => $userCria,
                'status' => 'ativo',
                'observacao' => $observacao,
                'tipo' => SolicitacaoAgendamento::TIPO_LEMBRETE,
            ]);

            // Vincula à solicitação
            SolicitacaoAgendSol::create([
                'solicitacao_id' => $solicitacaoId,
                'agendamento_id' => $lembrete->id,
            ]);

            // Lembrete NÃO altera o status da solicitação (permite continuar atendendo)
            // Apenas atribui responsável se não tiver
            if (! $solicitacao->usuario_responsavel) {
                $solicitacao->usuario_responsavel = $userCria;
                $solicitacao->save();
            }

            // Registra movimentação
            $dataFormatadaBr = date('d/m/Y', strtotime($dataAgendamento));
            $horaTexto = $horaAgendamento ? ' às ' . $horaAgendamento : '';

            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Lembrete Criado',
                'descricao' => "$nomeLogado criou um lembrete para $dataFormatadaBr$horaTexto." . ($observacao ? " Obs: $observacao" : ''),
                'usuario_movimentacao' => auth()->id(),
            ]);

            // Notificações
            $tituloNot = 'Lembrete Criado';
            $mensagemNot = "$nomeLogado criou um lembrete para atender a solicitação #{$solicitacao->id} em $dataFormatadaBr$horaTexto.";
            $origem = 'solicitacoes.criarLembrete';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $usuariosNotificar = array_filter([$solicitacao->usuario_responsavel, $solicitacao->usuario_solicitante]);
            $this->criaNotificacao($tituloNot, $mensagemNot, $usuariosNotificar, $origem, $link);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return response()->json([
                'message' => 'Lembrete criado com sucesso!',
                'lembrete' => $lembrete,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao criar lembrete.', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Cancela um lembrete
     */
    public function cancelarLembrete(Request $request)
    {
        DB::beginTransaction();
        try {
            $lembreteId = $request->input('id');
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());

            $lembrete = SolicitacaoAgendamento::where('id', $lembreteId)
                ->where('tipo', SolicitacaoAgendamento::TIPO_LEMBRETE)
                ->first();

            if (! $lembrete) {
                return response()->json(['message' => 'Lembrete não encontrado.'], 404);
            }

            // Busca solicitação vinculada
            $solicitacaoId = SolicitacaoAgendSol::where('agendamento_id', $lembreteId)->value('solicitacao_id');
            $solicitacao = Solicitacao::find($solicitacaoId);

            // Cancela o lembrete
            $lembrete->status = 'cancelado';
            $lembrete->data_cancelamento = Carbon::now()->format('Y-m-d H:i:s');
            $lembrete->mat_cancelamento = auth()->id();
            $lembrete->save();

            // Verifica se há outros agendamentos ativos para esta solicitação
            $outrosAgendamentosAtivos = SolicitacaoAgendSol::where('solicitacao_id', $solicitacaoId)
                ->whereHas('agendamento', function ($q) use ($lembreteId) {
                    $q->where('id', '!=', $lembreteId)
                        ->whereNotIn('status', ['cancelado', 'finalizado']);
                })
                ->exists();

            // Registra movimentação do cancelamento do lembrete
            if ($solicitacao) {
                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Lembrete Cancelado',
                    'descricao' => "$nomeLogado cancelou o lembrete.",
                    'usuario_movimentacao' => auth()->id(),
                ]);

                // Se não há outros agendamentos ativos e status é 'agendado', volta para pendente
                if (! $outrosAgendamentosAtivos && $solicitacao->status == 'agendado') {
                    $solicitacao->status = 'pendente';
                    $solicitacao->save();
                }
            }

            DB::commit();

            return response()->json(['message' => 'Lembrete cancelado com sucesso!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao cancelar lembrete.', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Edita um lembrete existente
     */
    public function editarLembrete(Request $request)
    {
        DB::beginTransaction();
        try {
            $lembreteId = $request->input('lembrete_id');
            $dataAgendamento = $request->input('data');
            $horaAgendamento = $request->input('hora');
            $observacao = $request->input('observacao');
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());

            $lembrete = SolicitacaoAgendamento::where('id', $lembreteId)
                ->where('tipo', SolicitacaoAgendamento::TIPO_LEMBRETE)
                ->first();

            if (! $lembrete) {
                return response()->json(['message' => 'Lembrete não encontrado.'], 404);
            }

            // Formata data/hora
            if ($horaAgendamento) {
                $dataFormatada = $dataAgendamento . ' ' . $horaAgendamento;
            } else {
                $dataFormatada = $dataAgendamento . ' 08:00';
            }

            // Guarda dados anteriores para log
            $dataAnterior = Carbon::parse($lembrete->data_agendamento)->format('d/m/Y');
            $horaAnterior = Carbon::parse($lembrete->data_agendamento)->format('H:i');

            // Atualiza o lembrete
            $lembrete->data_agendamento = $dataFormatada;
            $lembrete->data_fim_agendamento = $dataFormatada;
            $lembrete->observacao = $observacao;
            $lembrete->save();

            // Busca solicitação vinculada para movimentação
            $solicitacaoId = SolicitacaoAgendSol::where('agendamento_id', $lembreteId)->value('solicitacao_id');
            $solicitacao = Solicitacao::find($solicitacaoId);

            if ($solicitacao) {
                // Registra movimentação
                $dataFormatadaBr = date('d/m/Y', strtotime($dataAgendamento));
                $horaTexto = $horaAgendamento ? ' às ' . $horaAgendamento : '';

                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Lembrete Editado',
                    'descricao' => "$nomeLogado alterou o lembrete de $dataAnterior às $horaAnterior para $dataFormatadaBr$horaTexto." . ($observacao ? " Obs: $observacao" : ''),
                    'usuario_movimentacao' => auth()->id(),
                ]);

                // Notificar via Reverb
                $this->notificarReverbAtualizacao($solicitacao, 'agendamento');
            }

            DB::commit();

            return response()->json([
                'message' => 'Lembrete atualizado com sucesso!',
                'lembrete' => $lembrete,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao editar lembrete.', 'error' => $th->getMessage()], 500);
        }
    }

    public function getAgendamentos($idSolicitacao)
    {

        $idAgendamentos = SolicitacaoAgendSol::where('solicitacao_id', $idSolicitacao)->pluck('agendamento_id');
        $agendamentos = SolicitacaoAgendamento::whereIn('id', $idAgendamentos)->orderBy('data_agendamento', 'desc')->get();

        foreach ($agendamentos as $agendamento) {

            $anexo = DB::table('INTRANET_AGEND_ANEXOS')->where('id_agendamento', $agendamento->id)->get();

            foreach ($anexo as $anexoAg) {
                $arquivo = File::where('id', $anexoAg->id_caminho)->first();
                if ($arquivo) {
                    $arquivoArray = $arquivo->toArray();
                    $arquivoArray['user_cria'] = $anexoAg->user_cria;
                    $anexosArray[] = $arquivoArray;
                }
                $agendamento->anexo = $anexosArray;
            }
            // $agendamento->setAttribute('anexo', $anexosArray);

            if ($agendamento->id_arquivo_assinatura) {
                $agendamento->imagem_assinatura = File::where('id', $agendamento->id_arquivo_assinatura)->first();
            }

            $agendamento->nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $agendamento->descricao = $agendamento->filial . ' - ' . $nomeFilial;
            $agendamento->descricao_completa = $agendamento->filial . '-' . $nomeFilial . ' | ' . $agendamento->nomeResponsavel;
            $agendamento->status = $agendamento->status == 'ativo' ? 'aguardando' : $agendamento->status;
            $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);
            $agendamento->rota = Filial::where('codigo', $agendamento->filial)->value('link_maps');

            // Buscar foto do responsável
            $fotoResponsavel = DB::table('INTRANET_USUARIO')
                ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                ->where('INTRANET_USUARIO.matricula', $agendamento->mat_responsavel)
                ->select('intranet_files.external_link as foto_perfil')
                ->first();
            $agendamento->fotoResponsavel = $fotoResponsavel->foto_perfil ?? null;
        }

        return $agendamentos;
    }

    public function atualizarAgendamento(Request $request)
    {

        try {

            $id_agendamento = $request->input('agendamento.id');
            $dataAgendamento = $request->input('agendamento.data');
            $dataFimAgendamento = $request->input('agendamento.dataFim');
            $filial = $request->input('agendamento.filial');
            $responsavel = $request->input('agendamento.usuarioResponsavel');
            $observacao = $request->input('agendamento.observacao');
            if ($observacao && mb_strlen($observacao) > 4000) {
                return response()->json(['message' => 'A observação não pode ultrapassar 4000 caracteres.'], 422);
            }
            $userCria = auth()->id();
            $nomeLogado = UtilController::nomeFuncionario(auth()->id());
            $nomeResponsavel = UtilController::nomeFuncionario($responsavel);

            $idsSolicitacao = SolicitacaoAgendSol::where('agendamento_id', $id_agendamento)->pluck('solicitacao_id')->toArray();
            $solicitacoes = Solicitacao::whereIn('id', $idsSolicitacao)->get();

            $agendamento = SolicitacaoAgendamento::where('id', $id_agendamento)->first();
            $tecnicoAnterior = $agendamento->mat_responsavel; // captura antes de sobrescrever

            // Captura o estado anterior para montar o "o que mudou" na notificação
            $dadosAnteriores = [
                'filial'           => $agendamento->filial,
                'data_agendamento' => $agendamento->data_agendamento,
                'observacao'       => $agendamento->observacao,
            ];

            $agendamento->mat_responsavel = $responsavel;
            $agendamento->filial = $filial;
            $agendamento->data_agendamento = $dataAgendamento;
            $agendamento->data_fim_agendamento = $dataFimAgendamento;
            $agendamento->user_cria = $userCria;
            // $agendamento->rota = Filial::where('codigo', $agendamento->filial)->value('link_maps');
            $agendamento->observacao = $observacao;

            if ($agendamento->id) {

                foreach ($solicitacoes as $solicitacao) {
                    // Adicionar histórico de altrações
                    $solicitacaoData = Solicitacao::where('id', $solicitacao['id'])->first();
                    $solicitacaoData->status = 'agendado';
                    $responsavelAnterior = $solicitacao['usuario_responsavel'];
                    $solicitacaoData->usuario_responsavel = $responsavel;

                    $solicitacaoData->movimentacoes()->create([
                        'tipo_movimentacao' => 'Agendamento Atualizado!',
                        'descricao' => "$nomeLogado alterou um agendamento. $nomeResponsavel foi atribuido como responsável pela solicitação e agendamento.",
                        'usuario_origem' => $responsavelAnterior,
                        'usuario_destino' => $responsavel,
                        'usuario_movimentacao' => auth()->id(),
                    ]);

                    $tituloNot = 'Agendamento Atualizado';
                    $mensagemNot = "$nomeLogado alterou um agendamento. $nomeResponsavel foi atribuido como responsável pela solicitação e agendamento.";
                    $origem = 'solicitacoes.atualizarAgendamento';
                    $link = url('/solicitacoes/lista?solicitacao=' . $solicitacaoData->id);

                    if ($responsavelAnterior) {
                        $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacaoData->usuario_responsavel, $responsavelAnterior, $solicitacaoData->usuario_solicitante], $origem, $link);
                    } else {
                        $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacaoData->usuario_responsavel, $solicitacaoData->usuario_solicitante], $origem, $link);
                    }

                    $solicitacaoData->save();
                }
                $agendamento->save();
            }

            $agendamento->nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $agendamento->descricao = $agendamento->filial . ' - ' . $nomeFilial;
            $agendamento->descricao_completa = $agendamento->filial . '-' . $nomeFilial . ' | ' . $agendamento->nomeResponsavel;
            $agendamento->status = $agendamento->status == 'ativo' ? 'aguardando' : $agendamento->status;
            $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);

            // NotificationCenter v2 — notifica o técnico (avisa anterior + novo se trocou)
            try {
                app(\App\Services\Notifications\Agendamento\AgendamentoNotifier::class)
                    ->notificarAtualizacao($agendamento, $tecnicoAnterior, $dadosAnteriores);
            } catch (\Throwable $e) {
                Log::error('AgendamentoNotifier(atualizar): ' . $e->getMessage());
            }

            return $agendamento;
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erro ao atualizar agendamento.', 'error' => $th->getMessage()], 500);
        }
    }

    public function cancelarAgendamento(Request $request)
    {
        DB::beginTransaction();
        try {

            $idAgendamento = $request->input('id');
            $idsSolicitacao = SolicitacaoAgendSol::where('agendamento_id', $idAgendamento)->pluck('solicitacao_id');

            $agendamento = SolicitacaoAgendamento::where('id', $idAgendamento)->first();

            // Idempotência: se já está cancelado, não processa de novo (evita
            // notificação duplicada em caso de duplo clique / requisição repetida).
            if (! $agendamento || $agendamento->status === 'cancelado') {
                DB::rollBack();
                return $agendamento;
            }

            $nomeLogado = UtilController::nomeFuncionario(auth()->id());

            foreach ($idsSolicitacao as $id) {

                $solicitacao = Solicitacao::where('id', $id)->first();

                $solicitacao->status = 'pendente';

                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Agendamento Cancelado.',
                    'descricao' => "$nomeLogado cancelou o agendamento.",
                    'usuario_origem' => $agendamento->mat_responsavel,
                    'usuario_movimentacao' => auth()->id(),
                ]);

                $tituloNot = 'Agendamento Cancelado';
                $mensagemNot = "$nomeLogado cancelou o agendamento.";
                $origem = 'solicitacoes.cancelarAgendamento';
                $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

                $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_responsavel, $solicitacao->usuario_solicitante], $origem, $link);
                $solicitacao->usuario_responsavel = null;
                $solicitacao->save();
            }

            $agendamento->data_cancelamento = Carbon::now()->format('Y-m-d H:i:s');
            $agendamento->mat_cancelamento = auth()->id();
            $agendamento->status = 'cancelado';
            $agendamento->save();
            DB::commit();

            // NotificationCenter v2 — notifica o técnico do cancelamento
            try {
                app(\App\Services\Notifications\Agendamento\AgendamentoNotifier::class)
                    ->notificarCancelamento($agendamento);
            } catch (\Throwable $e) {
                Log::error('AgendamentoNotifier(cancelar): ' . $e->getMessage());
            }
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json('Erro ao cancelar agendamento.', 500);
        }
    }

    public function indexAgendamento()
    {

        $solicitacao = Solicitacao::with(['agendamentos', 'assunto'])
            ->whereHas('agendamentos', function ($query) {
                $query->where('mat_responsavel', auth()->id());
            })
            ->orderBy('INTRANET_SOLICITACAO.id', 'asc')
            ->get();

        $usuarioLogado = [
            'matricula' => auth()->id(),
            'nome' => (auth()->user()?->name),
        ];

        $permiteVerTodos = DB::table('INTRANET_PERMISSAO AS P')
            ->join('INTRANET_PERFIL_PERMISSAO AS IPP', 'IPP.IDPERMISSAO', '=', 'P.IDPERMISSAO')
            ->join('INTRANET_USUARIO_PERFIL AS IUP', 'IUP.IDPERFIL', '=', 'IPP.IDPERFIL')
            ->where('IUP.MATRICULA', auth()->id())
            ->where('descricao', 'solicitacoes.agendamento.ver-todos')
            ->exists();

        $permiteVerTodosUser = DB::table('INTRANET_PERMISSAO AS P')
            ->join('INTRANET_USUARIO_PERMISSAO AS IPP', 'IPP.permissao_id', '=', 'P.IDPERMISSAO')
            ->where('IPP.MATRICULA', auth()->id())
            ->where('descricao', 'solicitacoes.agendamento.ver-todos')
            ->exists();

        if ($permiteVerTodos || $permiteVerTodosUser) {
            $deptoUser = (auth()->user()?->department_id);
            $usuariosDepto = Funcionario::where('areaatuacao', $deptoUser)
                ->where('situacao', 'A')
                ->whereNotIn('matricula', [99999999, 7801, 10000]) // excluir usuarios ficiticios
                ->select('matricula', 'nome')->get();
        }

        foreach ($solicitacao as $sol) {

            $sol->nomeSolicitante = UtilController::nomeFuncionario($sol->usuario_solicitante);

            foreach ($sol->agendamentos as $agendamento) {
                $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);
                $agendamento->nomeFilial = UtilController::nomeFilial($agendamento->filial);
                $horario = explode(' ', $agendamento->data_agendamento);
                $agendamento->horario = substr($horario[1], 0, 5);
            }
        }

        return Inertia::render('Solicitacoes/Agendamentos/Index', [
            'solicitacoes' => $solicitacao,
            'usuariosDepto' => $usuariosDepto ?? null,
            'usuarioLogado' => $usuarioLogado,
        ]);
    }

    public function getAgendamentosByUser($id_usuario)
    {
        $solicitacoes = Solicitacao::with(['agendamentos', 'assunto'])
            ->whereHas('agendamentos', function ($query) use ($id_usuario) {
                $query->where('mat_responsavel', $id_usuario);
            })
            ->get()->toArray();

        foreach ($solicitacoes as $sol) {
            $sol['nomeSolicitante'] = UtilController::nomeFuncionario($sol['usuario_responsavel']);
            $sol['nomeFilial'] = UtilController::nomeFilial($sol['filial_id']);

            foreach ($sol['agendamentos'] as $agendamento) {
                $horario = explode(' ', $agendamento['data_agendamento']);
                $agendamento['horario'] = substr($horario[1], 0, 5);
                $agendamento['nomeResponsavel'] = UtilController::nomeFuncionario($agendamento['mat_responsavel']);

                $agendamento['rota'] = Filial::where('codigo', $agendamento->filial ?? null)->value('link_maps');
            }
        }

        return $solicitacoes;
    }

    public function iniciarAgendamento(Request $request)
    {
        DB::beginTransaction();
        try {

            $idAgendamento = $request->input('id_agendamento');
            $idsSolicitacao = SolicitacaoAgendSol::where('agendamento_id', $idAgendamento)->pluck('solicitacao_id');
            $agendamento = SolicitacaoAgendamento::where('id', $idAgendamento)->first();

            foreach ($idsSolicitacao as $id) {
                $solicitacao = Solicitacao::where('id', $id)->first();
                $solicitacao->status = 'em atendimento';

                $solicitacao->save();

                // Criar movimentação de inicio de atendimento
                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Inicio de atendimento',
                    'descricao' => (auth()->user()?->name) . ' iniciou o atendimento',
                    'usuario_movimentacao' => auth()->id(),
                ]);

                $tituloNot = 'Inicio de atendimento';
                $mensagemNot = (auth()->user()?->name) . ' iniciou o atendimento';
                $origem = 'solicitacoes.iniciarAgendamento';
                $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

                $this->criaNotificacao($tituloNot, $mensagemNot, [$solicitacao->usuario_solicitante], $origem, $link);
            }

            $agendamento->status = 'em atendimento';
            $agendamento->mat_inicio_atendimento = auth()->id();
            $agendamento->inicio_atendimento = Carbon::now()->format('Y-m-d H:i:s');
            $agendamento->save();

            $agendamento->nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);
            $agendamento->rota = Filial::where('codigo', $agendamento->filial)->value('link_maps');

            $agendamento->horario_inicial = substr($agendamento->data_agendamento, 11, 5);
            $agendamento->horario_final = substr($agendamento->data_fim_agendamento, 11, 5);
            $agendamento->descricao = $agendamento->filial . ' - ' . $agendamento->nomeFilial;
            $agendamento->descricao_completa = $agendamento->filial . '-' . $agendamento->nomeFilial . ' | ' . $agendamento->nomeResponsavel;

            DB::commit();

            return $agendamento;
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json('Erro ao iniciar o agendamento.', 500);
        }
    }

    public function finalizarAgendamento(Request $request)
    {

        DB::beginTransaction();
        try {

            $idAgendamento = $request->input('id_agendamento');
            $resolveSolicitacao = $request->input('resolveSolicitacao');
            $agendamento = SolicitacaoAgendamento::where('id', $idAgendamento)->first();
            $idAssinatura = $request->input('caminho_assinatura');

            $idsSolicitacao = SolicitacaoAgendSol::where('agendamento_id', $idAgendamento)->pluck('solicitacao_id');
            foreach ($idsSolicitacao as $id) {

                $solicitacao = Solicitacao::where('id', $id)->first();

                if ($resolveSolicitacao) {

                    $solicitacao->status = 'finalizada';
                    $solicitacao->data_conclusao = $solicitacao->data_conclusao ?? Carbon::now();

                    $solicitacao->comentarios()->create([
                        'usuario' => auth()->id(),
                        'comentario' => 'Atendimento foi finalizado através do agendamento ' . $idAgendamento,
                    ]);
                } else {
                    $solicitacao->status = 'pendente';

                    $solicitacao->comentarios()->create([
                        'usuario' => auth()->id(),
                        'comentario' => 'Agendamento foi finalizado sem resolução da solicitação. Motivo: ' . PHP_EOL . $request->input('comentario'),
                    ]);
                }

                $solicitacao->save();
                // Criar movimentação de inicio de atendimento
                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Atendimento finalizado',
                    'descricao' => (auth()->user()?->name) . ' resolveu o atendimento',
                    'usuario_movimentacao' => auth()->id(),
                ]);
            }

            $agendamento->status = 'finalizado';
            $agendamento->data_termino = Carbon::now()->format('Y-m-d H:i:s');
            $agendamento->mat_termino = auth()->id();
            $agendamento->id_arquivo_assinatura = $idAssinatura ?? null;

            $agendamento->save();

            $agendamento->nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);

            DB::commit();

            return $agendamento;
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json('Erro ao pausar o agendamento.', 500);
        }
    }

    public function buscaAgendamentoPorData(Request $request)
    {
        $dataIni = $request->input('dataIni');
        $dataFim = $request->input('dataFim');

        $dataIniFormatada = $dataIni ? Carbon::createFromFormat('d/m/Y', $dataIni)->format('Y-m-d') : date('Y-m-01');
        $dataFimFormatada = $dataFim ? Carbon::createFromFormat('d/m/Y', $dataFim)->format('Y-m-d') : date('Y-m-t');

        $responsavel = $request->input('responsavel');
        $agendamentosQuery = SolicitacaoAgendamento::whereRaw(
            "data_agendamento::date between ?::date AND ?::date",
            [
                $dataIniFormatada,
                $dataFimFormatada,
            ]
        );

        if ($responsavel) {
            $agendamentosQuery->where('mat_responsavel', $responsavel);
        } else {

            $permiteVerTodos = auth()->user()?->hasPermission('solicitacoes.agendamento.ver-todos') ?? false;
            $permiteVerTodosUser = $permiteVerTodos;

            if ($permiteVerTodos || $permiteVerTodosUser) {

                // Adaptado (5E): filtra responsáveis pelo departamento via tabela `users`
                $agendamentosQuery->join('users as p', 'p.id', '=', 'INTRANET_SOLICITACAO_AGEND.mat_responsavel')
                    ->where('p.department_id', (auth()->user()?->department_id));
            }
        }

        $agendamentos = $agendamentosQuery->get();

        foreach ($agendamentos as $agendamento) {
            $nomeFilial = UtilController::nomeFilial($agendamento->filial);
            $agendamento->nomeResponsavel = UtilController::nomeFuncionario($agendamento->mat_responsavel);
            $agendamento->descricao = $agendamento->filial . '-' . $nomeFilial;
            $agendamento->descricao_completa = $agendamento->filial . '-' . $nomeFilial . ' | ' . $agendamento->nomeResponsavel;
            $agendamento->status = $agendamento->status == 'ativo' ? 'aguardando' : $agendamento->status;
            $agendamento->rota = Filial::where('codigo', $agendamento->filial)->value('link_maps');

            // Para lembretes, busca a solicitação vinculada para abrir ao clicar
            if ($agendamento->tipo == SolicitacaoAgendamento::TIPO_LEMBRETE) {
                $solicitacaoId = SolicitacaoAgendSol::where('agendamento_id', $agendamento->id)->value('solicitacao_id');
                $agendamento->solicitacao_id = $solicitacaoId;

                // Busca o título da solicitação para exibir no calendário
                $solicitacao = Solicitacao::where('id', $solicitacaoId)->first();
                if ($solicitacao) {
                    $agendamento->solicitacao_titulo = $solicitacao->titulo;
                    $agendamento->descricao = 'Lembrete: ' . substr($solicitacao->titulo, 0, 30) . (strlen($solicitacao->titulo) > 30 ? '...' : '');
                    $agendamento->descricao_completa = 'Lembrete #' . $solicitacaoId . ' - ' . $solicitacao->titulo;
                }
            }
        }
        $agendamentos->user_logado = auth()->id();

        return $agendamentos;
    }

    public function getCanaisNotif()
    {

        $canais = DB::table('INTRANET_NOTIF_CANAL')
            ->get();

        foreach ($canais as $canal) {

            $existeParam = DB::table('INTRANET_PARAMETROS')
                ->where('MENU', 'SOLICITACOES')
                ->where('SUBMENU', 'CONFIGURAR')
                ->where('PARAMETRO', 'NOTIFICACAO')
                ->where('CONDICAO1', $canal->canal)
                ->where('CONDICAO2', 'GERAL')
                ->exists();

            if (! $existeParam) {

                DB::table('INTRANET_PARAMETROS')->insert([
                    'MENU' => 'SOLICITACOES',
                    'SUBMENU' => 'CONFIGURAR',
                    'PARAMETRO' => 'NOTIFICACAO',
                    'CONDICAO1' => $canal->canal,
                    'CONDICAO2' => 'GERAL',
                    'VALOR' => '0',
                ]);

                $canal->notificacao = 0;
            } else {

                $canal->notificacao = DB::table('INTRANET_PARAMETROS')
                    ->where('MENU', 'SOLICITACOES')
                    ->where('SUBMENU', 'CONFIGURAR')
                    ->where('PARAMETRO', 'NOTIFICACAO')
                    ->where('CONDICAO1', $canal->canal)
                    ->where('CONDICAO2', 'GERAL')
                    ->value('valor');
            }
        }

        return $canais;
    }

    public function saveNotificacoes(Request $request)
    {
        try {

            $canais = $request->input('canais');

            foreach ($canais as $canal) {

                DB::table('INTRANET_PARAMETROS')
                    ->where('MENU', 'SOLICITACOES')
                    ->where('SUBMENU', 'CONFIGURAR')
                    ->where('PARAMETRO', 'NOTIFICACAO')
                    ->where('CONDICAO1', $canal['canal'])
                    ->where('CONDICAO2', 'GERAL')
                    ->update([
                        'VALOR' => $canal['notificacao'],
                    ]);
            }

            return response()->json('Notificações atualizadas com sucesso!', 200);
        } catch (\Throwable $th) {
            return response()->json('Erro ao atualizar as notificações.', 500);
        }
    }

    public function criaNotificacao($titulo, $mensagem, $usuarioEnvio, $origem, $link = null)
    {
        try {
            $canaisNotif = DB::table('INTRANET_PARAMETROS')
                ->select('condicao1')
                ->where('MENU', 'SOLICITACOES')
                ->where('SUBMENU', 'CONFIGURAR')
                ->where('PARAMETRO', 'NOTIFICACAO')
                ->whereIn('CONDICAO1', ['in-app', 'email', 'flutter'])
                ->where('CONDICAO2', 'GERAL')
                ->where('VALOR', '1')
                ->pluck('condicao1');

            $canaisUser = DB::table('intranet_parametros')
                ->where('MENU', 'SOLICITACOES')
                ->where('SUBMENU', 'CONFIGURAR')
                ->where('PARAMETRO', 'NOTIFICACAO')
                ->whereIn('CONDICAO2', $usuarioEnvio)
                ->whereIn('CONDICAO1', $canaisNotif)
                ->where('VALOR', '1')
                ->select('condicao1')
                ->pluck('condicao1');

            $canaisUser = array_unique($canaisUser->toArray());
            NotificacaoController::enviarNotificacaoStatic($titulo, $mensagem, $origem, $canaisUser, $usuarioEnvio, null, null, $link);
        } catch (\Throwable $th) {
            return response()->json('Erro ao enviar notificações.', 500);
        }
    }

    /**
     * Notifica via Reverb uma atualização de solicitação em tempo real
     */
    protected function notificarReverbAtualizacao(Solicitacao $solicitacao, string $tipoAtualizacao = 'geral'): void
    {
        try {
            $reverbService = new SolicitacaoReverbService;
            $reverbService->notificarAtualizacao(
                [
                    'id' => $solicitacao->id,
                    'titulo' => $solicitacao->titulo,
                    'status' => $solicitacao->status,
                    'prioridade' => $solicitacao->prioridade,
                    'usuario_solicitante' => $solicitacao->usuario_solicitante,
                    'usuario_responsavel' => $solicitacao->usuario_responsavel,
                    'departamento_responsavel' => $solicitacao->departamento_responsavel,
                    'updated_at' => $solicitacao->updated_at->toISOString(),
                ],
                $solicitacao->departamento_responsavel,
                $tipoAtualizacao
            );
        } catch (\Throwable $e) {
            Log::error('Reverb: Falha ao notificar atualização', [
                'metodo' => __METHOD__,
                'solicitacao_id' => $solicitacao->id,
                'tipo' => $tipoAtualizacao,
                'departamento' => $solicitacao->departamento_responsavel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function salvarAnexos(Request $request)
    {
        try {

            $idAgendamento = $request->input('id_agendamento');
            $arquivos = $request->input('arquivos');

            foreach ($arquivos as $arquivo) {
                AgendamentoAnexos::create([
                    'id_agendamento' => $idAgendamento,
                    'id_caminho' => $arquivo['id_caminho'],
                    'user_cria' => auth()->id(),
                    'tipo_arquivo' => $arquivo['tipo_arquivo'],
                    'nome_arquivo' => $arquivo['nome_arquivo'],
                ]);
            }

            return response()->json(['message' => 'Anexos salvos com sucesso!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erro ao salvar anexos.', 'error' => $th->getMessage()], 500);
        }
    }

    public function getAnexos($idAgendamento)
    {
        try {

            $anexos = AgendamentoAnexos::where('id_agendamento', $idAgendamento)->get();
            foreach ($anexos as $anexo) {
                $anexo->caminho_ext = File::where('id', $anexo->id_caminho)->value('external_link');
                $anexo->nome_user = UtilController::nomeFuncionario($anexo->user_cria);
            }

            return $anexos;
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erro ao buscar anexos.', 'error' => $th->getMessage()], 500);
        }
    }

    public function getSolAgendamentos($idAgendamento)
    {
        $idSolicitacoes = SolicitacaoAgendSol::where('agendamento_id', $idAgendamento)->pluck('solicitacao_id')->toArray();

        $solicitacoes = Solicitacao::with(['agendamentos', 'assunto'])
            ->whereIn('id', $idSolicitacoes)
            ->orderBy('INTRANET_SOLICITACAO.id', 'asc')
            ->get();

        $solicitacoes->usuarioLogado = auth()->id();
        foreach ($solicitacoes as $sol) {

            $sol->nomeSolicitante = UtilController::nomeFuncionario($sol->usuario_solicitante);
        }

        return $solicitacoes;
    }

    public function getEquipamentos()
    {
        $equipamentos = SolicitacaoEquipamentos::orderBy('id', 'asc')->get();

        return $equipamentos;
    }

    public function addEquipamento(Request $request)
    {

        $equipamentos = $request->input('equipamentos');

        foreach ($equipamentos as $equip) {

            if (array_key_exists('id', $equip)) {

                SolicitacaoEquipamentos::where('id', $equip['id'])->update([
                    'equipamento' => $equip['equipamento'],
                ]);

                continue;
            }

            SolicitacaoEquipamentos::create([
                'equipamento' => $equip['equipamento'],
            ]);
        }

        return response()->json('Equipamento salvo com sucesso!', 200);
    }

    public function deleteEquipamento(Request $request)
    {
        $idEquipamento = $request->input('idEquipamento');
        SolicitacaoEquipamentos::where('id', $idEquipamento)->delete();

        return response()->json('Equipamento salvo com sucesso!', 200);
    }

    public function getDeptoAtivo()
    {
        $departamentosQuery = DB::table('intranet_parametros')
            ->where('menu', 'SOLICITACOES')
            ->where('submenu', 'CONFIGURACOES')
            ->where('parametro', 'DEP_ATIVOS')
            ->where('valor', 1)
            ->get();

        foreach ($departamentosQuery as $depto) {
            $depto->assuntos = SolicitacaoAssunto::where('ativo', 'S')
                ->where('departamento', $depto->condicao1)
                ->orderBy('assunto', 'asc')
                ->get();

            // Carregar selects para cada assunto (apenas os que aparecem no atendimento)
            foreach ($depto->assuntos as $assunto) {
                $selects = SolicitacaoSelecao::where('assunto_id', $assunto->id)
                    ->where('exibir_atendimento', 'S')
                    ->orderBy('ordem')
                    ->get();

                foreach ($selects as $select) {
                    $itens = SolicitacaoSelecaoItem::where('selecao_id', $select->id)->orderBy('valor')->get(['id', 'valor']);
                    $select->valores = $itens->map(function ($item) {
                        return [
                            'code' => $item->id,
                            'label' => $item->valor,
                        ];
                    });
                }

                $assunto->setAttribute('selects', $selects);
            }
        }

        return response()->json([
            'departamentos' => $departamentosQuery,

        ]);
    }

    // Alterar departamento responsável de uma solicitação
    public function alterarDepto(Request $request)
    {

        $departamentoSelecionado = $request->input('deptoSelecionado');
        $solicitacaoId = $request->input('solicitacao_id');
        $comentarioMotivo = $request->input('comentario');
        $assuntoId = $request->input('assunto_id');
        $trocaAssunto = $request->input('troca_assunto', false);

        $solicitacao = Solicitacao::where('id', $solicitacaoId)->first();

        $departamentoAnterior = $solicitacao->departamento_responsavel;
        $assuntoIdAnterior = $solicitacao->assunto_id;
        $assuntoAnterior = $solicitacao->assunto ? $solicitacao->assunto->assunto : 'Sem assunto';

        if (! $trocaAssunto && $departamentoAnterior !== $departamentoSelecionado) {
            $solicitacao->usuario_responsavel = null;
        }

        // Buscar o novo assunto
        $novoAssunto = SolicitacaoAssunto::where('id', $assuntoId)->first();
        $nomeNovoAssunto = $novoAssunto ? $novoAssunto->assunto : 'Sem assunto';

        $solicitacao->assunto_id = $assuntoId;
        $solicitacao->departamento_responsavel = $departamentoSelecionado;

        // Definir mensagens baseadas no tipo de alteração
        if ($trocaAssunto) {
            // Apenas troca de assunto
            $tipoMovimentacao = 'Assunto foi alterado.';
            $descricaoMovimentacao = (auth()->user()?->name) . ' alterou o assunto de "' . $assuntoAnterior . '" para "' . $nomeNovoAssunto . '"';
            $comentarioTexto = 'Assunto alterado, motivo: ' . PHP_EOL . $comentarioMotivo;
            $mensagemSucesso = 'Assunto alterado com sucesso!';
        } else {
            // Alteração completa (departamento + assunto)
            $tipoMovimentacao = 'Departamento responsável foi alterado.';
            $descricaoMovimentacao = (auth()->user()?->name) . ' alterou o departamento responsável de "' . $departamentoAnterior . '" para "' . $departamentoSelecionado . '"';
            $comentarioTexto = 'Departamento alterado, motivo: ' . PHP_EOL . $comentarioMotivo;
            $mensagemSucesso = 'Departamento alterado com sucesso!';
        }

        $solicitacao->movimentacoes()->create([
            'tipo_movimentacao' => $tipoMovimentacao,
            'descricao' => $descricaoMovimentacao,
            'usuario_movimentacao' => auth()->id(),
        ]);

        $solicitacao->comentarios()->create([
            'usuario' => auth()->id(),
            'comentario' => $comentarioTexto,
        ]);

        $solicitacao->save();

        // Salvar respostas dos campos personalizados (selects)
        $respostasSelects = $request->input('selects', []);
        $tiposErp = ['depto_compras', 'depto_funcionario', 'filial_winthor', 'funcao', 'regional'];

        foreach ($respostasSelects as $respostaSelect) {
            $selectId = $respostaSelect['selecao_id'] ?? null;
            $resposta = $respostaSelect['resposta'] ?? null;
            $assuntoIdResposta = $respostaSelect['assunto_id'] ?? $assuntoId;
            $tipo = $respostaSelect['tipo'] ?? null;

            if ($selectId === null || $resposta === null || $resposta === '' || (is_array($resposta) && empty($resposta))) {
                continue;
            }

            // Verifica se já existe resposta para este select
            $existente = SolicitacaoSelecaoResposta::where('solicitacao_id', $solicitacaoId)
                ->where('selecao_id', $selectId)
                ->first();

            // Deletar resposta existente para recriar (mais simples para múltiplos valores)
            if ($existente) {
                SolicitacaoSelecaoResposta::where('solicitacao_id', $solicitacaoId)
                    ->where('selecao_id', $selectId)
                    ->delete();
            }

            // Tratar cada tipo de campo
            if ($tipo === 'selecao' || in_array($tipo, $tiposErp)) {
                $valores = is_array($resposta) ? $resposta : [$resposta];
                foreach ($valores as $valor) {
                    SolicitacaoSelecaoResposta::create([
                        'selecao_id' => $selectId,
                        'texto_resposta' => is_numeric($valor) ? null : (in_array($tipo, $tiposErp) ? null : $valor),
                        'itens_id' => is_numeric($valor) && $tipo === 'selecao' ? $valor : null,
                        'valor_winthor' => in_array($tipo, $tiposErp) ? (string) $valor : null,
                        'solicitacao_id' => $solicitacaoId,
                        'assunto_id' => $assuntoIdResposta,
                    ]);
                }
            } elseif ($tipo === 'cnpj' || $tipo === 'texto' || $tipo === 'numero') {
                SolicitacaoSelecaoResposta::create([
                    'selecao_id' => $selectId,
                    'texto_resposta' => $resposta,
                    'solicitacao_id' => $solicitacaoId,
                    'assunto_id' => $assuntoIdResposta,
                ]);
            } elseif ($tipo === 'data') {
                $data1 = null;
                $data2 = null;

                if (! empty($resposta['datas'])) {
                    $data1 = $resposta['datas'][0]
                        ? Carbon::createFromFormat('Y-m-d', $resposta['datas'][0])
                        : null;

                    $data2 = isset($resposta['datas'][1]) && $resposta['datas'][1]
                        ? Carbon::createFromFormat('Y-m-d', $resposta['datas'][1])
                        : null;
                }

                SolicitacaoSelecaoResposta::create([
                    'selecao_id' => $selectId,
                    'data1' => $data1,
                    'data2' => $data2,
                    'solicitacao_id' => $solicitacaoId,
                    'assunto_id' => $assuntoIdResposta,
                ]);
            }
        }

        // Notificar via Reverb
        $tipoAtualizacao = $trocaAssunto ? 'assunto' : 'departamento';
        $this->notificarReverbAtualizacao($solicitacao, $tipoAtualizacao);

        // Sincronizar fluxo/workflow quando o assunto muda:
        //  1) Se a solicitação tinha um fluxo ativo, encerra-o (auditoria preservada).
        //  2) Se o novo assunto possui fluxo ativo, inicia uma nova execução.
        if ($assuntoIdAnterior !== (int) $assuntoId) {
            try {
                $workflowService = new WorkflowService(new SolicitacaoReverbService);

                $workflowService->encerrarFluxoPorTrocaAssunto(
                    $solicitacao,
                    auth()->id(),
                    $assuntoAnterior,
                    $nomeNovoAssunto,
                    $comentarioMotivo
                );

                $workflowService->iniciarFluxo($solicitacao, auth()->id());
            } catch (\Throwable $e) {
                Log::warning('Workflow: Falha ao sincronizar fluxo após troca de assunto', [
                    'solicitacao_id' => $solicitacao->id,
                    'assunto_anterior_id' => $assuntoIdAnterior,
                    'assunto_novo_id' => $assuntoId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Se mudou de departamento, notificar também o departamento anterior para remover da lista
        if (! $trocaAssunto && $departamentoAnterior !== $departamentoSelecionado) {
            try {
                $departamentoSanitizado = preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $departamentoAnterior)));
                $departamentoSanitizado = trim(preg_replace('/_+/', '_', $departamentoSanitizado), '_');
                $canal = "public.intranet.solicitacoes.departamento.{$departamentoSanitizado}";

                reverbSend($canal, 'atualizada', [
                    'solicitacao_id' => $solicitacao->id,
                    'tipo_atualizacao' => 'transferencia_saida',
                    'departamento' => $departamentoAnterior,
                    'timestamp' => now()->toISOString(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar departamento anterior', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacao->id,
                    'departamento_anterior' => $departamentoAnterior,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => $mensagemSucesso], 200);
    }

    // Alterar solicitante de uma solicitação
    public function alterarSolicitante(Request $request)
    {
        $novoSolicitanteMatricula = $request->input('novo_solicitante');
        $solicitacaoId = $request->input('solicitacao_id');
        $comentarioMotivo = $request->input('comentario');

        $solicitacao = Solicitacao::where('id', $solicitacaoId)->first();

        if (! $solicitacao) {
            return response()->json(['message' => 'Solicitação não encontrada'], 404);
        }

        // Buscar funcionário anterior e novo
        $usuarioAnterior = Funcionario::where('matricula', $solicitacao->usuario_solicitante)->first();
        $novoUsuario = Funcionario::where('matricula', $novoSolicitanteMatricula)->first();

        if (! $novoUsuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $nomeAnterior = $usuarioAnterior ? $usuarioAnterior->nome : 'Desconhecido';
        $nomeNovo = $novoUsuario->nome;

        // Atualizar solicitante (NÃO salva ainda)
        $solicitacao->usuario_solicitante = $novoSolicitanteMatricula;

        // Registrar movimentação
        $solicitacao->movimentacoes()->create([
            'tipo_movimentacao' => 'Solicitante foi alterado',
            'descricao' => (auth()->user()?->name) . ' alterou o solicitante de "' . $nomeAnterior . '" para "' . $nomeNovo . '"',
            'usuario_movimentacao' => auth()->id(),
        ]);

        // Adicionar comentário
        $solicitacao->comentarios()->create([
            'usuario' => auth()->id(),
            'comentario' => 'Solicitante alterado. Motivo: ' . PHP_EOL . $comentarioMotivo,
        ]);

        // Salva no final
        $solicitacao->save();

        // Notificar via Reverb
        $this->notificarReverbAtualizacao($solicitacao, 'solicitante');

        return response()->json(['message' => 'Solicitante alterado com sucesso!'], 200);
    }

    public function prepararImportacao(Request $request)
    {
        try {
            DB::beginTransaction();

            // Verifica se o arquivo foi enviado e está válido
            if (! $request->hasFile('arquivo') || ! $request->file('arquivo')->isValid()) {
                return response()->json(['erro' => 'Arquivo não enviado ou inválido'], 400);
            }

            $arquivo = $request->file('arquivo');

            // Carrega a planilha e pega todas as linhas com cabeçalhos A, B, C...
            $spreadsheet = IOFactory::load($arquivo->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $linhas = $worksheet->toArray(null, true, true, true);

            // Função auxiliar para padronizar comparações de nomes de colunas
            $normalizar = function ($texto) {
                $texto = trim(mb_strtolower($texto));
                $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto); // remove acentos
                $texto = preg_replace('/[^a-z0-9 ]/', '', $texto); // remove símbolos

                return preg_replace('/\s+/', ' ', $texto); // normaliza espaços
            };

            // Lê e remove o cabeçalho (linha 1)
            $cabecalhos = $linhas[1];
            unset($linhas[1]);

            // Cria um mapa de cabeçalhos: nome normalizado => letra da coluna (ex: 'titulo' => 'B')
            $mapaCabecalhos = [];
            foreach ($cabecalhos as $letra => $valorOriginal) {
                $mapaCabecalhos[$normalizar($valorOriginal)] = $letra;
            }

            // Define colunas obrigatórias e normaliza para comparação
            $colunasObrigatorias = [
                'data de cadastro',
                'titulo',
                'descricao',
                'nome contato',
                'prioridade',
                'usuario responsavel',
            ];
            $colunasObrigatoriasNormalizadas = array_map($normalizar, $colunasObrigatorias);
            $cabecalhosNormalizados = array_map($normalizar, $cabecalhos);

            // Verifica colunas obrigatórias ausentes
            $faltando = [];
            foreach ($colunasObrigatoriasNormalizadas as $colunaObrigatoria) {
                if (! in_array($colunaObrigatoria, $cabecalhosNormalizados)) {
                    $index = array_search($colunaObrigatoria, $colunasObrigatoriasNormalizadas);
                    $faltando[] = $colunasObrigatorias[$index];
                }
            }

            if (! empty($faltando)) {
                return response()->json([
                    'erro' => 'Colunas obrigatórias ausentes',
                    'faltando' => $faltando,
                ], 400);
            }

            // Carrega todas as filiais em memória para acesso rápido
            $filiais = Filial::select('codigo', 'fantasia')->get()->keyBy('codigo');

            $lista = [];

            // Loop pelas linhas (a partir da linha 2)
            foreach (array_slice($linhas, 1) as $linha) {
                $obj = [];

                // Monta campos básicos
                $titulo = $linha[$mapaCabecalhos['titulo']] ?? '';
                $descricao = $linha[$mapaCabecalhos['descricao']] ?? '';
                $dataCadastro = $linha[$mapaCabecalhos['data de cadastro']] ?? null;
                $nomeResponsavel = $linha[$mapaCabecalhos['usuario responsavel']] ?? '';
                $nomeContato = $linha[$mapaCabecalhos['nome contato']] ?? '';
                $chaveEmailContato = $mapaCabecalhos[$normalizar('e-mail contato')] ?? null;
                $emailContato = $chaveEmailContato && isset($linha[$chaveEmailContato]) ? $linha[$chaveEmailContato] : null;

                // Buscar número da filial no conteúdo
                $textoBusca = implode(' ', [$nomeContato, $descricao, $titulo]);
                $filialEncontrada = null;
                if (preg_match('/filial\s*[-:]*\s*(\d{1,4})/i', $textoBusca, $matches)) {
                    $numeroFilial = (int) $matches[1];
                } else {
                    $numeroFilial = 2;
                }

                $filial = $filiais[$numeroFilial] ?? null;
                if ($filial) {
                    $filialEncontrada = intval($filial->codigo ?? 2);
                }

                // 🔍 Procurar o solicitante primeiro pelo e-mail, depois pelo nome
                $solicitanteFuncionario = null;

                if (! empty($emailContato)) {
                    $solicitanteFuncionario = Funcionario::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($emailContato))])->first();
                }

                if (! $solicitanteFuncionario && ! empty($nomeContato)) {
                    $solicitanteFuncionario = Funcionario::whereRaw('LOWER(TRIM(nome)) = ?', [trim(mb_strtolower($nomeContato))])->first();
                }

                if (! $solicitanteFuncionario) {
                    $filialSolicitante = $filialEncontrada;

                    // TODO: revisar vínculo departamento/filial — users não tem colunas codfilial/funcao/situacao (ERP legado)
                    $solicitanteFuncionario = \App\Models\User::where('is_active', true)->first();
                }
                // Se não achou, pega o Usuário Intranet
                if (! $solicitanteFuncionario) {
                    $solicitanteFuncionario = Funcionario::find(99999999);
                }

                // Buscar funcionário pela descrição
                $funcionario = Funcionario::whereRaw('LOWER(TRIM(name)) = ?', [trim(mb_strtolower($nomeResponsavel))])->first();

                $obj = [
                    'solicitante' => $solicitanteFuncionario ? $solicitanteFuncionario->matricula : null,
                    'filial' => $filialEncontrada,
                    'assunto' => '',
                    'responsavel' => $funcionario ? $funcionario->matricula : null,
                    'data' => $dataCadastro ? Carbon::createFromFormat('d/m/Y H:i', $dataCadastro) : null,
                    'prioridade' => 'baixa',
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                ];

                $lista[] = $obj;
            }

            DB::commit();

            return response()->json($lista, 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            LogHelper::logException($e, $request);

            return response()->json([
                'erro' => 'Ocorreu um erro, favor entrar em contato com suporte.',
            ], 500);
        }
    }

    public function importar(Request $request)
    {
        DB::beginTransaction();

        try {
            $dados = $request->all();

            $departamento = $dados['departamento_id'] ?? null;
            $assunto = $dados['assunto_id'] ?? null;
            $lista = $dados['lista'] ?? [];

            if (! $departamento || ! $assunto || empty($lista)) {
                return response()->json(['erro' => 'Dados incompletos para importação.'], 400);
            }

            foreach ($lista as $linha) {
                $solicitacao = Solicitacao::create([
                    'titulo' => $linha['titulo'],
                    'descricao' => $linha['descricao'],
                    'departamento_responsavel' => $departamento,
                    'prioridade' => $linha['prioridade'] ?? 'media',
                    'usuario_solicitante' => $linha['solicitante'] ?? 99999999,
                    'filial_id' => $linha['filial'],
                    'assunto_id' => $assunto,
                    'usuario_origem' => $linha['solicitante'] ?? null,
                    'usuario_responsavel' => $linha['responsavel'] ?? null,
                    'status' => 'pendente',
                    'created_at' => $linha['data'] ?? now(),
                    'updated_at' => now(),
                ]);

                $solicitacao->movimentacoes()->create([
                    'tipo_movimentacao' => 'Solicitação importada',
                    'descricao' => 'Solicitação criada via importação por ' . (auth()->user()?->name),
                    'usuario_movimentacao' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'mensagem' => 'Solicitações importadas com sucesso!',
                'importadas' => count($lista),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'erro' => 'Erro ao importar solicitações.',
                'detalhes' => $e->getMessage(),
            ], 500);
        }
    }

    public function possuiResolvidas()
    {

        try {
            return response()->json(Solicitacao::where('USUARIO_SOLICITANTE', auth()->id())->where('STATUS', 'resolvida')->exists(), 200);
        } catch (\Throwable $th) {
            return response()->json(false, 200);
        }
    }

    public function deletarAnexo(Request $request)
    {
        try {
            $idAnexo = $request->input('id');
            AgendamentoAnexos::where('id', $idAnexo)->delete();

            return response()->json(['message' => 'Anexo deletado com sucesso!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erro ao deletar anexo.', 'error' => $th->getMessage()], 500);
        }
    }

    public function retornoSolicitante(Request $request)
    {
        try {
            $solicitacao = Solicitacao::where('id', $request->input('solicitacao.id'))->first();
            $solicitacao->status = 'retorno solicitante';
            $solicitacao->save();

            $solicitacao->comentarios()->create([
                'usuario' => auth()->id(),
                'comentario' => 'Retorno ao Solicitante. ' . PHP_EOL . $request->input('comentario'),
            ]);

            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Retorno ao solicitante',
                'descricao' => (auth()->user()?->name) . ' retornou ao solicitante.',
                'usuario_movimentacao' => auth()->id(),
            ]);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'status');

            return response()->json(['message' => 'Retorno enviado ao solicitante com sucesso!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao enviar retorno ao solicitante.', 'error' => $th->getMessage()], 500);
        }
    }

    public function atualizarPrevisaoEntrega(Request $request)
    {
        try {
            $request->validate([
                'solicitacao_id' => 'required|integer',
                'previsao_entrega' => 'nullable|date|after_or_equal:today',
            ]);

            $solicitacao = Solicitacao::find($request->solicitacao_id);

            if (! $solicitacao) {
                return response()->json(['message' => 'Solicitação não encontrada.'], 404);
            }

            // Verificar se o usuário é o responsável pela solicitação
            if ($solicitacao->usuario_responsavel != auth()->id()) {
                return response()->json(['message' => 'Apenas o responsável pela solicitação pode alterar a previsão de entrega.'], 403);
            }

            // Verificar se a solicitação está em um status que permite alteração da previsão
            $statusNaoPermitidos = ['cancelada', 'finalizada', 'resolvida', 'retorno solicitante'];
            if (in_array($solicitacao->status, $statusNaoPermitidos)) {
                return response()->json([
                    'message' => 'A previsão de entrega não pode ser alterada para solicitações com status: ' . $solicitacao->status . '.',
                ], 400);
            }

            DB::beginTransaction();

            // Converter a data para o final do dia ou null para limpar
            $previsaoEntrega = $request->previsao_entrega
                ? Carbon::parse($request->previsao_entrega)->endOfDay()
                : null;

            $solicitacao->update([
                'previsao_entrega' => $previsaoEntrega,
            ]);

            // Registrar movimentação
            $descricao = $previsaoEntrega
                ? (auth()->user()?->name) . ' atualizou a previsão de entrega para ' . $previsaoEntrega->format('d/m/Y') . '.'
                : (auth()->user()?->name) . ' removeu a previsão de entrega.';

            SolicitacaoMov::create([
                'solicitacao_id' => $solicitacao->id,
                'tipo_movimentacao' => 'Previsão de entrega atualizada',
                'descricao' => $descricao,
                'usuario_movimentacao' => auth()->id(),
            ]);

            DB::commit();

            // Notificar via Reverb
            $this->notificarReverbAtualizacao($solicitacao, 'previsao_entrega');

            return response()->json(['message' => 'Previsão de entrega atualizada com sucesso!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => 'Erro ao atualizar previsão de entrega.', 'error' => $th->getMessage()], 500);
        }
    }

    public function getLiberacoes(Request $request, $assunto_id)
    {
        try {
            $liberacoes = SolicitacaoAssuntoLiberacao::where('assunto_id', $assunto_id)->get();

            // Buscar dados detalhados baseado no tipo
            $liberacoesDetalhadas = $liberacoes->map(function ($liberacao) {
                $detalhes = ['id' => $liberacao->id, 'tipo' => $liberacao->tipo, 'valor' => $liberacao->valor];

                switch ($liberacao->tipo) {
                    case 'filial':
                        $filial = Filial::where('codigo', $liberacao->valor)->first();
                        $detalhes['nome'] = $filial ? $filial->fantasia : 'Filial não encontrada';
                        break;
                    case 'funcionario':
                        $funcionario = Funcionario::where('matricula', $liberacao->valor)->first();
                        $detalhes['nome'] = $funcionario ? $funcionario->nome : 'Funcionário não encontrado';
                        break;
                    case 'areaatuacao':
                        $detalhes['nome'] = $liberacao->valor;
                        break;
                }

                return $detalhes;
            });

            return response()->json($liberacoesDetalhadas);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar liberações'], 500);
        }
    }

    public function salvarLiberacoes(Request $request)
    {
        DB::beginTransaction();
        try {
            $assunto_id = $request->input('assunto_id');
            $liberacoes = $request->input('liberacoes', []);

            // Remover liberações existentes
            SolicitacaoAssuntoLiberacao::where('assunto_id', $assunto_id)->delete();

            // Criar novas liberações
            foreach ($liberacoes as $liberacao) {
                SolicitacaoAssuntoLiberacao::create([
                    'assunto_id' => $assunto_id,
                    'tipo' => $liberacao['tipo'],
                    'valor' => $liberacao['valor'],
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Liberações salvas com sucesso!']);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Erro ao salvar liberações', 'error' => $th->getMessage()], 500);
        }
    }

    public function getDadosLiberacao()
    {
        try {
            // Buscar filiais
            $filiais = Filial::orderBy('fantasia')->get(['codigo', 'fantasia']);

            // Buscar funcionários ativos
            $funcionarios = Funcionario::where('situacao', 'A')
                ->whereNotIn('matricula', [99999999, 7801, 10000])
                ->orderBy('nome')
                ->get(['matricula', 'nome']);

            // Buscar departamentos (áreas de atuação)
            $departamentos = Funcionario::where('situacao', 'A')
                ->whereNotNull('areaatuacao')
                ->select('areaatuacao')
                ->groupBy('areaatuacao')
                ->orderBy('areaatuacao')
                ->pluck('areaatuacao');

            return response()->json([
                'filiais' => $filiais,
                'funcionarios' => $funcionarios,
                'departamentos' => $departamentos,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar dados para liberação'], 500);
        }
    }

    /**
     * Buscar responsáveis de um assunto
     *
     * #22263 - Permissão por Assunto em Solicitações
     */
    public function getResponsaveis($assunto_id)
    {
        try {
            $responsaveis = SolicitacaoAssuntoResponsavel::where('assunto_id', $assunto_id)
                ->with('funcionario:matricula,nome')
                ->get();

            return response()->json($responsaveis->map(fn($resp) => [
                'id' => $resp->id,
                'matricula' => $resp->matricula,
                'nome' => $resp->funcionario?->nome ?? 'Funcionário não encontrado',
            ]));
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar responsáveis'], 500);
        }
    }

    /**
     * Salvar responsáveis de um assunto
     *
     * #22263 - Permissão por Assunto em Solicitações
     */
    public function salvarResponsaveis(Request $request)
    {
        DB::beginTransaction();
        try {
            $assunto_id = $request->input('assunto_id');
            $responsaveis = $request->input('responsaveis', []);

            // Remover responsáveis existentes
            SolicitacaoAssuntoResponsavel::where('assunto_id', $assunto_id)->delete();

            // Criar novos responsáveis
            foreach ($responsaveis as $resp) {
                SolicitacaoAssuntoResponsavel::create([
                    'assunto_id' => $assunto_id,
                    'matricula' => $resp['matricula'],
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Responsáveis salvos com sucesso!']);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Erro ao salvar responsáveis', 'error' => $th->getMessage()], 500);
        }
    }

    public function enviarArquivoParaDossie(Request $request)
    {
        try {
            $fileId = $request->input('file_id');
            $funcionario = $request->input('funcionario');
            $listaSelecionada = $request->input('listaSelecionada');
            $pastaSelecionada = $request->input('pastaSelecionada');
            $solicitacaoId = $request->input('solicitacao_id');

            // Validações
            if (! $fileId) {
                return response()->json(['error' => 'ID do arquivo é obrigatório'], 400);
            }

            if (! $funcionario || ! isset($funcionario['matricula'])) {
                return response()->json(['error' => 'Funcionário é obrigatório'], 400);
            }

            // Verificar se o funcionário é CLT (tipo 'F')
            $funcionarioModel = Funcionario::where('matricula', $funcionario['matricula'])->first();
            if (! $funcionarioModel) {
                return response()->json(['error' => 'Funcionário não encontrado'], 400);
            }

            if ($funcionarioModel->tipo !== 'F') {
                return response()->json(['error' => 'Apenas funcionários CLT podem ter dossiês'], 400);
            }

            if (! $pastaSelecionada && ! $listaSelecionada) {
                return response()->json(['error' => 'Pasta ou dossiê é obrigatório'], 400);
            }

            // Verificar se o arquivo existe
            $file = File::find($fileId);
            if (! $file) {
                return response()->json(['error' => 'Arquivo não encontrado'], 400);
            }

            DB::beginTransaction();

            // Preparar dados para o dossiê
            $documento = [
                'descricao' => "Arquivo da solicitação #{$solicitacaoId}",
                'arquivos' => [[
                    'file' => ['id' => $fileId],
                ]],
            ];

            $params = [
                'funcionario' => $funcionario,
                'documento' => $documento,
                'listaSelecionada' => $listaSelecionada,
                'pastaSelecionada' => $pastaSelecionada,
            ];

            // Usar o método existente do RhController
            $rhController = new \App\Http\Controllers\RhController;
            $request = new Request($params);
            $response = $rhController->dossieAdicionar($request);

            if ($response->getStatusCode() === 200) {
                DB::commit();

                return response()->json(['success' => true, 'message' => 'Arquivo enviado para o dossiê com sucesso']);
            } else {
                DB::rollBack();

                return response()->json(['error' => 'Erro ao adicionar arquivo ao dossiê'], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ========== MÉTODOS DE APROVAÇÃO ==========

    /**
     * Listar aprovações de uma solicitação
     */
    public function listarAprovacoes($solicitacaoId)
    {
        try {
            $aprovacoes = SolicitacaoAprovacao::with(['solicitante', 'aprovador', 'respondidoPor'])
                ->where('solicitacao_id', $solicitacaoId)
                ->orderBy('id', 'desc')
                ->get();

            // Buscar fotos de perfil dos aprovadores, solicitantes e respondidos
            $matriculas = $aprovacoes->pluck('aprovador_matricula')
                ->merge($aprovacoes->pluck('solicitante_matricula'))
                ->merge($aprovacoes->pluck('respondido_por'))
                ->filter()
                ->unique()
                ->values();

            $fotos = DB::table('INTRANET_USUARIO')
                ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                ->whereIn('INTRANET_USUARIO.matricula', $matriculas)
                ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula');

            // Adicionar fotos aos relacionamentos
            foreach ($aprovacoes as $aprovacao) {
                if ($aprovacao->aprovador) {
                    $aprovacao->aprovador->foto_perfil = $fotos[$aprovacao->aprovador_matricula] ?? null;
                }
                if ($aprovacao->solicitante) {
                    $aprovacao->solicitante->foto_perfil = $fotos[$aprovacao->solicitante_matricula] ?? null;
                }
                if ($aprovacao->respondidoPor) {
                    $aprovacao->respondidoPor->foto_perfil = $fotos[$aprovacao->respondido_por] ?? null;
                }
            }

            return response()->json($aprovacoes, 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar aprovações', 'message' => $th->getMessage()], 500);
        }
    }

    /**
     * Criar nova solicitação de aprovação
     */
    public function criarAprovacao(Request $request)
    {
        try {
            $request->validate([
                'solicitacao_id' => 'required|integer',
                'aprovador_matricula' => 'required|integer',
                'observacoes' => 'nullable|string',
            ]);

            $solicitacao = Solicitacao::find($request->solicitacao_id);
            if (! $solicitacao) {
                return response()->json(['error' => 'Solicitação não encontrada'], 404);
            }

            // Verificar permissões: apenas departamento responsável ou quem criou pode solicitar aprovação
            $usuarioLogado = auth()->id();
            $areaUsuario = (\App\Models\User::find($usuarioLogado)?->department_id);
            $podeAprovar = ($areaUsuario == $solicitacao->departamento_responsavel) ||
                ($usuarioLogado == $solicitacao->usuario_solicitante);

            if (! $podeAprovar) {
                return response()->json(['error' => 'Sem permissão para solicitar aprovação nesta solicitação'], 403);
            }

            // Verificar se o aprovador existe
            $aprovador = Funcionario::where('matricula', $request->aprovador_matricula)->first();
            if (! $aprovador) {
                return response()->json(['error' => 'Aprovador não encontrado'], 404);
            }

            DB::beginTransaction();

            // Criar aprovação
            $aprovacao = SolicitacaoAprovacao::create([
                'solicitacao_id' => $request->solicitacao_id,
                'solicitante_matricula' => $usuarioLogado,
                'aprovador_matricula' => $request->aprovador_matricula,
                'observacoes' => $request->observacoes,
                'status' => 'pendente',
            ]);

            // Registrar movimentação
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Aprovação solicitada',
                'descricao' => (auth()->user()?->name) . ' solicitou aprovação de ' . $aprovador->nome,
                'usuario_movimentacao' => $usuarioLogado,
            ]);

            // Enviar notificações para aprovador, responsável e solicitante
            $usuariosNotificacao = array_filter([
                $request->aprovador_matricula, // Aprovador
                $solicitacao->usuario_responsavel, // Responsável
                $solicitacao->usuario_solicitante, // Solicitante
            ]);
            $usuariosNotificacao = array_unique($usuariosNotificacao);

            $titulo = 'Nova aprovação solicitada - Solicitação #' . $solicitacao->id;
            $mensagem = (auth()->user()?->name) . ' solicitou aprovação de ' . $aprovador->nome;
            $origem = 'solicitacoes.aprovacao.criar';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $this->criaNotificacao($titulo, $mensagem, $usuariosNotificacao, $origem, $link);

            DB::commit();

            // Recarregar com relacionamentos
            $aprovacao->load(['solicitante', 'aprovador', 'respondidoPor']);

            // Notificar via Reverb
            try {
                $reverbService = new SolicitacaoReverbService;
                $solicitacaoData = $solicitacao->fresh()->toArray();

                // Notificar atualização no departamento
                $reverbService->notificarAtualizacao($solicitacaoData, $solicitacao->departamento_responsavel, 'aprovacao_solicitada');

                // Notificar o aprovador
                $reverbService->notificarAprovacaoPendente($solicitacaoData, $request->aprovador_matricula);
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar criação de aprovação', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacao->id,
                    'departamento' => $solicitacao->departamento_responsavel,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['success' => true, 'aprovacao' => $aprovacao, 'message' => 'Aprovação solicitada com sucesso!'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['error' => 'Erro ao criar aprovação', 'message' => $th->getMessage()], 500);
        }
    }

    /**
     * Responder aprovação (aprovar/rejeitar)
     */
    public function responderAprovacao(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:aprovada,rejeitada',
                'resposta_observacoes' => 'nullable|string',
            ]);

            // Se for rejeição, a resposta é obrigatória
            if ($request->status === 'rejeitada' && empty(trim($request->resposta_observacoes))) {
                return response()->json(['error' => 'Resposta é obrigatória para rejeitar uma aprovação'], 400);
            }

            $aprovacao = SolicitacaoAprovacao::find($id);
            if (! $aprovacao) {
                return response()->json(['error' => 'Aprovação não encontrada'], 404);
            }

            $usuarioLogado = auth()->id();

            // Verificar se é o aprovador correto
            if ($aprovacao->aprovador_matricula != $usuarioLogado) {
                return response()->json(['error' => 'Apenas o aprovador pode responder esta solicitação'], 403);
            }

            // Verificar se ainda está pendente
            if ($aprovacao->status !== 'pendente') {
                return response()->json(['error' => 'Esta aprovação já foi respondida'], 400);
            }

            DB::beginTransaction();

            // Atualizar aprovação
            $aprovacao->update([
                'status' => $request->status,
                'resposta_observacoes' => $request->resposta_observacoes,
                'respondido_por' => $usuarioLogado,
                'respondido_em' => now(),
            ]);

            $solicitacao = $aprovacao->solicitacao;
            $statusTexto = $request->status === 'aprovada' ? 'aprovou' : 'rejeitou';

            // Registrar movimentação
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Aprovação ' . ($request->status === 'aprovada' ? 'aprovada' : 'rejeitada'),
                'descricao' => (auth()->user()?->name) . ' ' . $statusTexto . ' a solicitação de aprovação',
                'usuario_movimentacao' => $usuarioLogado,
            ]);

            // Enviar notificações para solicitante da aprovação, responsável e solicitante da solicitação
            $usuariosNotificacao = array_filter([
                $aprovacao->solicitante_matricula, // Quem solicitou a aprovação
                $solicitacao->usuario_responsavel, // Responsável da solicitação
                $solicitacao->usuario_solicitante, // Solicitante original
                $usuarioLogado, // Próprio aprovador para confirmar ação
            ]);
            $usuariosNotificacao = array_unique($usuariosNotificacao);

            $titulo = 'Aprovação ' . ($request->status === 'aprovada' ? 'concedida' : 'rejeitada') . ' - Solicitação #' . $solicitacao->id;
            $mensagem = (auth()->user()?->name) . ' ' . $statusTexto . ' a solicitação de aprovação';
            $origem = 'solicitacoes.aprovacao.responder';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $this->criaNotificacao($titulo, $mensagem, $usuariosNotificacao, $origem, $link);

            DB::commit();

            $aprovacao->load(['solicitante', 'aprovador', 'respondidoPor']);

            // Notificar via Reverb
            try {
                $reverbService = new SolicitacaoReverbService;
                $solicitacaoData = $solicitacao->fresh()->toArray();

                // Notificar atualização no departamento
                $tipoAtualizacao = $request->status === 'aprovada' ? 'aprovacao_aprovada' : 'aprovacao_rejeitada';
                $reverbService->notificarAtualizacao($solicitacaoData, $solicitacao->departamento_responsavel, $tipoAtualizacao);
            } catch (\Throwable $e) {
                Log::error('Reverb: Falha ao notificar resposta de aprovação', [
                    'metodo' => __METHOD__,
                    'solicitacao_id' => $solicitacao->id,
                    'departamento' => $solicitacao->departamento_responsavel,
                    'error' => $e->getMessage(),
                ]);
            }

            $mensagemSucesso = $request->status === 'aprovada' ? 'Aprovação concedida com sucesso!' : 'Aprovação rejeitada com sucesso!';

            return response()->json(['success' => true, 'aprovacao' => $aprovacao, 'message' => $mensagemSucesso], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['error' => 'Erro ao responder aprovação', 'message' => $th->getMessage()], 500);
        }
    }

    /**
     * Editar aprovação pendente
     */
    public function editarAprovacao(Request $request, $id)
    {
        try {
            $request->validate([
                'aprovador_matricula' => 'required|integer',
                'observacoes' => 'nullable|string',
            ]);

            $aprovacao = SolicitacaoAprovacao::find($id);
            if (! $aprovacao) {
                return response()->json(['error' => 'Aprovação não encontrada'], 404);
            }

            $usuarioLogado = auth()->id();

            // Verificar se é quem solicitou a aprovação
            if ($aprovacao->solicitante_matricula != $usuarioLogado) {
                return response()->json(['error' => 'Apenas quem solicitou pode editar esta aprovação'], 403);
            }

            // Verificar se ainda está pendente
            if ($aprovacao->status !== 'pendente') {
                return response()->json(['error' => 'Apenas aprovações pendentes podem ser editadas'], 400);
            }

            // Verificar se o novo aprovador existe
            $novoAprovador = Funcionario::where('matricula', $request->aprovador_matricula)->first();
            if (! $novoAprovador) {
                return response()->json(['error' => 'Novo aprovador não encontrado'], 404);
            }

            DB::beginTransaction();

            $aprovadorAnterior = $aprovacao->aprovador;

            // Atualizar aprovação
            $aprovacao->update([
                'aprovador_matricula' => $request->aprovador_matricula,
                'observacoes' => $request->observacoes,
            ]);

            $solicitacao = $aprovacao->solicitacao;

            // Registrar movimentação
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Aprovação editada',
                'descricao' => (auth()->user()?->name) . ' alterou a solicitação de aprovação (aprovador: ' . $aprovadorAnterior->nome . ' → ' . $novoAprovador->nome . ')',
                'usuario_movimentacao' => $usuarioLogado,
            ]);

            // Notificar novo aprovador, responsável e solicitante
            $usuariosNotificacao = array_filter([
                $request->aprovador_matricula, // Novo aprovador
                $solicitacao->usuario_responsavel, // Responsável
                $solicitacao->usuario_solicitante, // Solicitante
            ]);
            $usuariosNotificacao = array_unique($usuariosNotificacao);

            $titulo = 'Aprovação editada - Solicitação #' . $solicitacao->id;
            $mensagem = (auth()->user()?->name) . ' alterou solicitação de aprovação para ' . $novoAprovador->nome;
            $origem = 'solicitacoes.aprovacao.editar';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $this->criaNotificacao($titulo, $mensagem, $usuariosNotificacao, $origem, $link);

            DB::commit();

            $aprovacao->load(['solicitante', 'aprovador', 'respondidoPor']);

            return response()->json(['success' => true, 'aprovacao' => $aprovacao, 'message' => 'Aprovação editada com sucesso!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['error' => 'Erro ao editar aprovação', 'message' => $th->getMessage()], 500);
        }
    }

    /**
     * Cancelar aprovação
     */
    public function cancelarAprovacao($id)
    {
        try {
            $aprovacao = SolicitacaoAprovacao::find($id);
            if (! $aprovacao) {
                return response()->json(['error' => 'Aprovação não encontrada'], 404);
            }

            $usuarioLogado = auth()->id();

            // Verificar se é quem solicitou a aprovação
            if ($aprovacao->solicitante_matricula != $usuarioLogado) {
                return response()->json(['error' => 'Apenas quem solicitou pode cancelar esta aprovação'], 403);
            }

            // Verificar se ainda está pendente
            if ($aprovacao->status !== 'pendente') {
                return response()->json(['error' => 'Apenas aprovações pendentes podem ser canceladas'], 400);
            }

            DB::beginTransaction();

            // Atualizar status para cancelada
            $aprovacao->update([
                'status' => 'cancelada',
                'respondido_por' => $usuarioLogado,
                'respondido_em' => now(),
            ]);

            $solicitacao = $aprovacao->solicitacao;
            $aprovador = $aprovacao->aprovador;

            // Registrar movimentação
            $solicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Aprovação cancelada',
                'descricao' => (auth()->user()?->name) . ' cancelou a solicitação de aprovação de ' . $aprovador->nome,
                'usuario_movimentacao' => $usuarioLogado,
            ]);

            // Notificar aprovador, responsável e solicitante sobre o cancelamento
            $usuariosNotificacao = array_filter([
                $aprovacao->aprovador_matricula, // Aprovador (importante notificar)
                $solicitacao->usuario_responsavel, // Responsável
                $solicitacao->usuario_solicitante, // Solicitante
            ]);
            $usuariosNotificacao = array_unique($usuariosNotificacao);

            $titulo = 'Aprovação cancelada - Solicitação #' . $solicitacao->id;
            $mensagem = (auth()->user()?->name) . ' cancelou a solicitação de aprovação de ' . $aprovador->nome;
            $origem = 'solicitacoes.aprovacao.cancelar';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $this->criaNotificacao($titulo, $mensagem, $usuariosNotificacao, $origem, $link);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Aprovação cancelada com sucesso!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['error' => 'Erro ao cancelar aprovação', 'message' => $th->getMessage()], 500);
        }
    }

    /**
     * Buscar aprovações pendentes do usuário logado para exibir em cards
     */
    public function buscarAprovacoesUsuario(Request $request)
    {
        try {
            $usuarioLogado = auth()->id();

            // Buscar aprovações pendentes onde o usuário é o aprovador
            $aprovacoes = SolicitacaoAprovacao::with([
                'solicitacao' => function ($query) {
                    $query->with([
                        'usuarioSolicitante:matricula,nome,email',
                        'usuarioResponsavel:matricula,nome,email',
                        'assunto:id,assunto',
                        'filial:codigo,fantasia',
                    ]);
                },
                'solicitante:matricula,nome,email',
                'aprovador:matricula,nome,email',
            ])
                ->where('aprovador_matricula', $usuarioLogado)
                ->where('status', 'pendente')
                ->orderBy('created_at', 'desc')
                ->get();

            // Buscar fotos dos responsáveis em lote
            $matriculasResponsaveis = $aprovacoes
                ->map(fn($a) => $a->solicitacao?->usuario_responsavel)
                ->filter()
                ->unique()
                ->values();

            $fotosResponsaveis = [];
            if ($matriculasResponsaveis->count() > 0) {
                $fotosResponsaveis = DB::table('INTRANET_USUARIO')
                    ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                    ->whereIn('INTRANET_USUARIO.matricula', $matriculasResponsaveis)
                    ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula')
                    ->toArray();
            }

            // Formatar dados para os cards
            $cards = $aprovacoes->map(function ($aprovacao) use ($fotosResponsaveis) {
                $solicitacao = $aprovacao->solicitacao;

                return [
                    'id' => $aprovacao->id,
                    'solicitacao_id' => $solicitacao->id,
                    'titulo' => $solicitacao->titulo ?? 'Sem título',
                    'descricao' => $solicitacao->descricao ?? '',
                    'prioridade' => $solicitacao->prioridade,
                    'status' => $solicitacao->status,
                    'departamento' => $solicitacao->departamento_responsavel,
                    'assunto' => $solicitacao->assunto->assunto ?? 'Não informado',
                    'filial' => $solicitacao->filial->fantasia ?? 'Não informada',
                    'solicitante' => [
                        'nome' => $solicitacao->usuarioSolicitante->nome ?? 'Não informado',
                        'email' => $solicitacao->usuarioSolicitante->email ?? '',
                    ],
                    'responsavel' => $solicitacao->usuarioResponsavel ? [
                        'nome' => $solicitacao->usuarioResponsavel->nome,
                        'email' => $solicitacao->usuarioResponsavel->email,
                        'foto_perfil' => $fotosResponsaveis[trim($solicitacao->usuario_responsavel)] ?? null,
                    ] : null,
                    'created_at' => $solicitacao->created_at,
                    'dias_aberto' => $solicitacao->created_at ? $solicitacao->created_at->diffInDays(now()) : 0,
                    'aprovacao' => [
                        'motivo' => $aprovacao->motivo,
                        'observacoes' => $aprovacao->observacoes,
                        'created_at' => $aprovacao->created_at,
                        'solicitante_nome' => $aprovacao->solicitante->nome ?? 'Sistema',
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'aprovacoes' => $cards,
                'total' => $cards->count(),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Erro ao buscar aprovações',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    // ========== MÉTODOS DE RESPONSÁVEIS ADICIONAIS ==========

    /**
     * Buscar responsáveis adicionais de um departamento
     */
    public function getResponsaveisAdicionais($departamento)
    {

        try {
            $responsaveis = DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'RESPONSAVEL_ADICIONAL')
                ->where('condicao1', $departamento)
                ->get();

            $responsaveisDetalhados = [];

            // Buscar fotos em lote
            $matriculas = $responsaveis->pluck('valor')->unique()->values();
            $fotos = DB::table('INTRANET_USUARIO')
                ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                ->whereIn('INTRANET_USUARIO.matricula', $matriculas)
                ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula');

            foreach ($responsaveis as $responsavel) {
                $funcionario = Funcionario::select('matricula', 'nome', 'areaatuacao')
                    ->where('matricula', $responsavel->valor)
                    ->first();

                if ($funcionario) {
                    $responsaveisDetalhados[] = [
                        'matricula' => $funcionario->matricula,
                        'nome' => $funcionario->nome,
                        'departamento_original' => $funcionario->areaatuacao,
                        'foto' => $fotos[$funcionario->matricula] ?? null,
                    ];
                }
            }

            return response()->json($responsaveisDetalhados);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar responsáveis adicionais'], 500);
        }
    }

    /**
     * Adicionar responsável adicional a um departamento
     */
    public function adicionarResponsavelAdicional(Request $request)
    {
        try {
            $departamento = $request->input('departamento');
            $matricula = $request->input('matricula');

            // Verificar se o funcionário existe
            $funcionario = Funcionario::where('matricula', $matricula)->first();
            if (! $funcionario) {
                return response()->json(['error' => 'Funcionário não encontrado'], 404);
            }

            // Verificar se o funcionário já não é do departamento
            if ($funcionario->areaatuacao == $departamento) {
                return response()->json(['error' => 'Funcionário já pertence a este departamento'], 400);
            }

            // Verificar se já não existe este responsável adicional
            $existe = DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'RESPONSAVEL_ADICIONAL')
                ->where('condicao1', $departamento)
                ->where('valor', $matricula)
                ->exists();

            if ($existe) {
                return response()->json(['error' => 'Funcionário já é responsável adicional deste departamento'], 400);
            }

            // Inserir o responsável adicional
            DB::table('intranet_parametros')->insert([
                'menu' => 'SOLICITACOES',
                'submenu' => 'CONFIGURACOES',
                'parametro' => 'RESPONSAVEL_ADICIONAL',
                'condicao1' => $departamento,
                'valor' => $matricula,
            ]);

            return response()->json([
                'message' => 'Responsável adicional adicionado com sucesso!',
                'funcionario' => [
                    'matricula' => $funcionario->matricula,
                    'nome' => $funcionario->nome,
                    'departamento_original' => $funcionario->areaatuacao,
                ],
            ]);
        } catch (\Throwable $th) {

            return response()->json(['error' => 'Erro ao adicionar responsável adicional'], 500);
        }
    }

    /**
     * Remover responsável adicional de um departamento
     */
    public function removerResponsavelAdicional(Request $request)
    {

        try {
            $matricula = $request->input('matricula');

            $removido = DB::table('intranet_parametros')
                ->where('valor', $matricula)
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'RESPONSAVEL_ADICIONAL')
                ->delete();

            if ($removido) {
                return response()->json(['message' => 'Responsável adicional removido com sucesso!']);
            } else {
                return response()->json(['error' => 'Responsável adicional não encontrado'], 404);
            }
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao remover responsável adicional'], 500);
        }
    }

    // ========== ENDPOINTS PARA CAMPOS PRÉ-DEFINIDOS WINTHOR #3196 ==========

    /**
     * Buscar departamentos de compras (ERP-legado)
     */
    public function getDepartamentosCompras()
    {
        try {
            // Adaptado (5E): no lugar de ERP-legado (ERP legado), usamos nossa tabela `departments`.
            $departamentos = \App\Models\Department::orderBy('name')->get();

            return response()->json([
                'sucesso' => true,
                'dados' => $departamentos->map(function ($d) {
                    return [
                        'value' => $d->id,
                        'label' => $d->name,
                    ];
                })->values(),
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar departamentos de compras: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Buscar departamentos de funcionário (AREAATUACAO de ERP-legado)
     */
    public function getDepartamentosFuncionario()
    {
        try {
            // Adaptado (5E): no lugar de AREAATUACAO de ERP-legado (ERP legado), usamos `departments`.
            $departamentos = \App\Models\Department::orderBy('name')->get();

            return response()->json($departamentos->map(function ($d) {
                return [
                    'value' => $d->id,
                    'label' => $d->name,
                ];
            })->values());
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar departamentos de funcionário: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Buscar filiais (ERP-legado)
     */
    public function getFiliaisWinthor()
    {
        try {
            // Adaptado (5E): no lugar de ERP-legado (ERP legado), usamos `branches` (model Filial).
            $filiais = Filial::orderBy('name')->get();

            return response()->json($filiais->map(function ($f) {
                return [
                    'value' => $f->id,
                    'label' => $f->codigo . ' - ' . $f->fantasia,
                ];
            })->values());
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar filiais: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Buscar funções de funcionários (ERP-legado)
     */
    public function getFuncoesWinthor()
    {
        try {
            // 5E: sem catálogo de funções equivalente ao ERP legado (ERP-legado.FUNCAO) — lista vazia.
            return response()->json([
                'sucesso' => true,
                'dados' => [],
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar funções: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Buscar regionais (BS_REGIONAIS)
     */
    public function getRegionais()
    {
        try {
            // 5E: sem entidade de regionais (Biglar BS_REGIONAIS) — lista vazia.
            return response()->json([
                'sucesso' => true,
                'dados' => [],
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar regionais: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Dashboard de solicitações
     */
    public function indexDashboard()
    {
        $permiteVerTodos = auth()->user()?->hasPermission('solicitacoes.dashboard.ver-todos-depto') ?? false;

        // Departamentos ativos (5E: tabela `departments`, no lugar de intranet_parametros DEP_ATIVOS)
        $departamentosQuery = \App\Models\Department::where('is_active', true);

        if (! $permiteVerTodos) {
            $departamentosQuery->where('id', (auth()->user()?->department_id));
        }

        // Mantém a chave `condicao1` (= identificador do depto) que o fluxo/front espera
        $departamentos = $departamentosQuery->orderBy('name')->get()->map(function ($d) {
            return (object) ['id' => $d->id, 'condicao1' => $d->name, 'department_id' => $d->id];
        });

        // Buscar assuntos e responsáveis por departamento
        foreach ($departamentos as $departamento) {
            $departamento->assuntos = SolicitacaoAssunto::where('department_id', $departamento->id)
                ->where('ativo', 'S')
                ->orderBy('assunto')
                ->get();

            // TODO: revisar vínculo departamento — responsáveis adicionais (RESPONSAVEL_ADICIONAL) não portado
            $responsaveisAdicionais = [];

            $responsaveisDepartamento = \App\Models\User::where('department_id', $departamento->id)
                ->where('is_active', true)
                ->get()
                ->map(fn($u) => ['matricula' => $u->id, 'nome' => $u->name])
                ->toArray();

            $departamento->responsaveis = collect(array_merge($responsaveisDepartamento, $responsaveisAdicionais))
                ->unique(fn($item) => is_array($item) ? $item['matricula'] : $item->matricula)
                ->values()
                ->all();
        }

        return Inertia::render('Solicitacoes/Dashboard/Index', [
            'departamentos' => $departamentos,
            'permiteVerTodos' => $permiteVerTodos,
        ]);
    }

    /**
     * Buscar dados do dashboard de solicitações
     */
    public function getDadosDashboard(Request $request)
    {
        try {
            $departamento = $request->input('departamento');
            $assuntos = $request->input('assuntos', []);
            $responsavel = $request->input('responsavel');
            $dataInicio = $request->input('dataInicio');
            $dataFim = $request->input('dataFim');

            // Base query para solicitações do período
            $baseQuery = Solicitacao::query()
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->when($dataInicio, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($dataInicio)))
                ->when($dataFim, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($dataFim)));

            // Total de solicitações no período
            $totalPeriodo = (clone $baseQuery)->count();

            // Contagem por status ATUAL (total, sem filtro de período)
            $porStatus = Solicitacao::query()
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            // Contagem por prioridade
            $porPrioridade = (clone $baseQuery)
                ->select('prioridade', DB::raw('COUNT(*) as total'))
                ->groupBy('prioridade')
                ->pluck('total', 'prioridade')
                ->toArray();

            // Solicitações atrasadas (com previsao_entrega no passado e não finalizadas)
            $atrasadas = (clone $baseQuery)
                ->whereNotNull('previsao_entrega')
                ->whereIn('status', ['pendente', 'em atendimento', 'atendimento pausado', 'agendado', 'retorno solicitante'])
                ->whereDate('previsao_entrega', '<', now()->startOfDay())
                ->count();

            // Tempo médio de resolução (em dias) - solicitações finalizadas no período
            $finalizadasQuery = (clone $baseQuery)->where('status', 'finalizada');
            $tempoMedioResolucao = $finalizadasQuery
                ->selectRaw('AVG(EXTRACT(DAY FROM (updated_at - created_at))) as media')
                ->value('media');

            // Top 5 assuntos mais solicitados
            $topAssuntos = (clone $baseQuery)
                ->select('assunto_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('assunto_id')
                ->groupBy('assunto_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $assunto = SolicitacaoAssunto::find($item->assunto_id);

                    return [
                        'assunto' => $assunto ? $assunto->assunto : 'Sem assunto',
                        'total' => $item->total,
                    ];
                });

            // Ranking de atendentes (por quantidade resolvida no período)
            $rankingAtendentesBase = (clone $baseQuery)
                ->select('usuario_responsavel', DB::raw('COUNT(*) as total'))
                ->where('status', 'finalizada')
                ->whereNotNull('usuario_responsavel')
                ->groupBy('usuario_responsavel')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            // Buscar fotos em lote para o ranking
            $matriculasRanking = $rankingAtendentesBase->pluck('usuario_responsavel')->unique()->values();
            $fotosRanking = DB::table('INTRANET_USUARIO')
                ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                ->whereIn('INTRANET_USUARIO.matricula', $matriculasRanking)
                ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula');

            $rankingAtendentes = $rankingAtendentesBase->map(function ($item) use ($fotosRanking) {
                return [
                    'matricula' => $item->usuario_responsavel,
                    'nome' => UtilController::nomeFuncionario($item->usuario_responsavel),
                    'foto' => $fotosRanking[$item->usuario_responsavel] ?? null,
                    'total' => $item->total,
                ];
            });

            // Evolução no período selecionado
            $dataInicioEvolucao = $dataInicio ? Carbon::parse($dataInicio) : now()->subDays(6);
            $dataFimEvolucao = $dataFim ? Carbon::parse($dataFim) : now();
            $diasPeriodo = $dataInicioEvolucao->diffInDays($dataFimEvolucao) + 1;

            $evolucao = [];
            $tipoAgrupamento = 'dia'; // dia, semana ou mes

            if ($diasPeriodo <= 31) {
                // Agrupar por dia
                $tipoAgrupamento = 'dia';
                for ($i = 0; $i < $diasPeriodo; $i++) {
                    $data = $dataInicioEvolucao->copy()->addDays($i);
                    $evolucao[] = $this->getEvolucaoPorData($data, $data, $departamento, $assuntos, $responsavel, 'd/m');
                }
            } elseif ($diasPeriodo <= 90) {
                // Agrupar por semana
                $tipoAgrupamento = 'semana';
                $dataAtual = $dataInicioEvolucao->copy()->startOfWeek();
                while ($dataAtual <= $dataFimEvolucao) {
                    $fimSemana = $dataAtual->copy()->endOfWeek();
                    if ($fimSemana > $dataFimEvolucao) {
                        $fimSemana = $dataFimEvolucao->copy();
                    }
                    $evolucao[] = $this->getEvolucaoPorData($dataAtual, $fimSemana, $departamento, $assuntos, $responsavel, 'd/m');
                    $dataAtual->addWeek();
                }
            } else {
                // Agrupar por mês
                $tipoAgrupamento = 'mes';
                $dataAtual = $dataInicioEvolucao->copy()->startOfMonth();
                while ($dataAtual <= $dataFimEvolucao) {
                    $fimMes = $dataAtual->copy()->endOfMonth();
                    if ($fimMes > $dataFimEvolucao) {
                        $fimMes = $dataFimEvolucao->copy();
                    }
                    $evolucao[] = $this->getEvolucaoPorData($dataAtual, $fimMes, $departamento, $assuntos, $responsavel, 'M/y');
                    $dataAtual->addMonth();
                }
            }

            // Solicitações abertas ATUALMENTE (sem filtro de período - mostra o backlog atual)
            $abertas = Solicitacao::query()
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->whereNotIn('status', ['finalizada', 'cancelada'])
                ->count();

            // ========== NOVAS MÉTRICAS ==========

            // Resolvidas aguardando feedback do solicitante (total atual)
            $resolvidasAguardando = Solicitacao::query()
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->where('status', 'resolvida')
                ->count();

            // Taxa de resolução no período (finalizadas / criadas * 100)
            $finalizadasPeriodo = (clone $baseQuery)->where('status', 'finalizada')->count();
            $taxaResolucao = $totalPeriodo > 0 ? round(($finalizadasPeriodo / $totalPeriodo) * 100, 1) : 0;

            // Taxa de cancelamento no período
            $canceladasPeriodo = (clone $baseQuery)->where('status', 'cancelada')->count();
            $taxaCancelamento = $totalPeriodo > 0 ? round(($canceladasPeriodo / $totalPeriodo) * 100, 1) : 0;

            // Entregues no prazo vs atrasadas (finalizadas no período)
            $finalizadasNoPrazo = (clone $baseQuery)
                ->where('status', 'finalizada')
                ->where(function ($q) {
                    $q->whereNull('previsao_entrega')
                        ->orWhereRaw('updated_at::date <= previsao_entrega::date');
                })
                ->count();
            $finalizadasComAtraso = $finalizadasPeriodo - $finalizadasNoPrazo;
            $taxaNoPrazo = $finalizadasPeriodo > 0 ? round(($finalizadasNoPrazo / $finalizadasPeriodo) * 100, 1) : 0;

            // Em atendimento há muito tempo (mais de 7 dias) - Top 10
            $emAtendimentoMuitoTempo = Solicitacao::query()
                ->with(['usuarioSolicitante', 'assunto'])
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->whereIn('status', ['em atendimento', 'atendimento pausado'])
                ->whereDate('updated_at', '<', now()->subDays(7))
                ->orderBy('updated_at', 'asc')
                ->limit(10)
                ->get()
                ->map(function ($sol) {
                    return [
                        'id' => $sol->id,
                        'titulo' => $sol->titulo ?: null,
                        'descricao' => $sol->descricao ? mb_substr(strip_tags($sol->descricao), 0, 200) : null,
                        'assunto' => $sol->assunto?->assunto ?? null,
                        'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                        'status' => $sol->status,
                        'dias' => Carbon::parse($sol->updated_at)->diffInDays(now()),
                        'created_at' => $sol->created_at,
                    ];
                });

            // Pendentes sem movimentação (nunca iniciadas, criadas há mais de 3 dias) - Top 10
            $pendentesSemMovimentacao = Solicitacao::query()
                ->with(['usuarioSolicitante', 'assunto'])
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->where('status', 'pendente')
                ->whereDate('created_at', '<', now()->subDays(3))
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get()
                ->map(function ($sol) {
                    return [
                        'id' => $sol->id,
                        'titulo' => $sol->titulo ?: null,
                        'descricao' => $sol->descricao ? mb_substr(strip_tags($sol->descricao), 0, 200) : null,
                        'assunto' => $sol->assunto?->assunto ?? null,
                        'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                        'dias' => Carbon::parse($sol->created_at)->diffInDays(now()),
                        'created_at' => $sol->created_at,
                    ];
                });

            // Solicitações mais antigas abertas (backlog antigo) - Top 10
            $maisAntigasAbertas = Solicitacao::query()
                ->with(['usuarioSolicitante', 'assunto', 'usuarioResponsavel'])
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->whereNotIn('status', ['finalizada', 'cancelada'])
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get()
                ->map(function ($sol) {
                    return [
                        'id' => $sol->id,
                        'titulo' => $sol->titulo ?: null,
                        'descricao' => $sol->descricao ? mb_substr(strip_tags($sol->descricao), 0, 200) : null,
                        'assunto' => $sol->assunto?->assunto ?? null,
                        'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                        'responsavel' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                        'status' => $sol->status,
                        'dias' => Carbon::parse($sol->created_at)->diffInDays(now()),
                        'created_at' => $sol->created_at,
                    ];
                });

            // Contagem de atrasadas ATUAIS por faixa de atraso
            $atrasadasPorFaixa = [
                'ate3dias' => Solicitacao::query()
                    ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                    ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                    ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                    ->whereNotNull('previsao_entrega')
                    ->whereNotIn('status', ['finalizada', 'cancelada', 'resolvida'])
                    ->whereDate('previsao_entrega', '<', now())
                    ->whereDate('previsao_entrega', '>=', now()->subDays(3))
                    ->count(),
                'ate7dias' => Solicitacao::query()
                    ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                    ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                    ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                    ->whereNotNull('previsao_entrega')
                    ->whereNotIn('status', ['finalizada', 'cancelada', 'resolvida'])
                    ->whereDate('previsao_entrega', '<', now()->subDays(3))
                    ->whereDate('previsao_entrega', '>=', now()->subDays(7))
                    ->count(),
                'mais7dias' => Solicitacao::query()
                    ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                    ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                    ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                    ->whereNotNull('previsao_entrega')
                    ->whereNotIn('status', ['finalizada', 'cancelada', 'resolvida'])
                    ->whereDate('previsao_entrega', '<', now()->subDays(7))
                    ->count(),
            ];

            // Lista de solicitações atrasadas - Top 15
            $listaAtrasadas = Solicitacao::query()
                ->with(['usuarioSolicitante', 'assunto', 'usuarioResponsavel'])
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->whereNotNull('previsao_entrega')
                ->whereNotIn('status', ['finalizada', 'cancelada', 'resolvida'])
                ->whereDate('previsao_entrega', '<', now())
                ->orderBy('previsao_entrega', 'asc')
                ->limit(15)
                ->get()
                ->map(function ($sol) {
                    return [
                        'id' => $sol->id,
                        'titulo' => $sol->titulo ?: null,
                        'descricao' => $sol->descricao ? mb_substr(strip_tags($sol->descricao), 0, 200) : null,
                        'assunto' => $sol->assunto?->assunto ?? null,
                        'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                        'responsavel' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                        'status' => $sol->status,
                        'previsao' => $sol->previsao_entrega?->format('d/m/Y'),
                        'diasAtraso' => Carbon::parse($sol->previsao_entrega)->diffInDays(now()),
                        'prioridade' => $sol->prioridade,
                    ];
                });

            // Lista de aguardando feedback (status resolvida) - Top 15
            $listaAguardandoFeedback = Solicitacao::query()
                ->with(['usuarioSolicitante', 'assunto', 'usuarioResponsavel'])
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->where('status', 'resolvida')
                ->orderBy('updated_at', 'asc')
                ->limit(15)
                ->get()
                ->map(function ($sol) {
                    return [
                        'id' => $sol->id,
                        'titulo' => $sol->titulo ?: null,
                        'descricao' => $sol->descricao ? mb_substr(strip_tags($sol->descricao), 0, 200) : null,
                        'assunto' => $sol->assunto?->assunto ?? null,
                        'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                        'responsavel' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                        'segundosAguardando' => Carbon::parse($sol->updated_at)->diffInSeconds(now()),
                        'updated_at' => $sol->updated_at->format('d/m/Y H:i'),
                    ];
                });

            return response()->json([
                'totalPeriodo' => $totalPeriodo,
                'porStatus' => $porStatus,
                'porPrioridade' => $porPrioridade,
                'atrasadas' => $atrasadas,
                'tempoMedioResolucao' => round($tempoMedioResolucao ?? 0, 1),
                'topAssuntos' => $topAssuntos,
                'rankingAtendentes' => $rankingAtendentes,
                'evolucao' => $evolucao,
                'tipoAgrupamento' => $tipoAgrupamento,
                'abertas' => $abertas,
                // Novas métricas
                'resolvidasAguardando' => $resolvidasAguardando,
                'taxaResolucao' => $taxaResolucao,
                'taxaCancelamento' => $taxaCancelamento,
                'finalizadasPeriodo' => $finalizadasPeriodo,
                'finalizadasNoPrazo' => $finalizadasNoPrazo,
                'finalizadasComAtraso' => $finalizadasComAtraso,
                'taxaNoPrazo' => $taxaNoPrazo,
                'emAtendimentoMuitoTempo' => $emAtendimentoMuitoTempo,
                'pendentesSemMovimentacao' => $pendentesSemMovimentacao,
                'maisAntigasAbertas' => $maisAntigasAbertas,
                'atrasadasPorFaixa' => $atrasadasPorFaixa,
                'listaAtrasadas' => $listaAtrasadas,
                'listaAguardandoFeedback' => $listaAguardandoFeedback,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar dados: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Método auxiliar para calcular evolução por período
     */
    private function getEvolucaoPorData($dataInicio, $dataFim, $departamento, $assuntos, $responsavel, $formato)
    {
        $criadas = Solicitacao::query()
            ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
            ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
            ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
            ->whereDate('created_at', '>=', $dataInicio)
            ->whereDate('created_at', '<=', $dataFim)
            ->count();

        $finalizadas = Solicitacao::query()
            ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
            ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
            ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
            ->where('status', 'finalizada')
            ->whereDate('updated_at', '>=', $dataInicio)
            ->whereDate('updated_at', '<=', $dataFim)
            ->count();

        // Label: se for período de um dia só, mostra só a data; senão mostra intervalo
        if ($dataInicio->isSameDay($dataFim)) {
            $label = $dataInicio->format($formato);
        } else {
            $label = $dataInicio->format('d/m') . '-' . $dataFim->format('d/m');
        }

        return [
            'data' => $label,
            'criadas' => $criadas,
            'finalizadas' => $finalizadas,
        ];
    }

    /**
     * Página de relatórios de solicitações
     */
    public function indexRelatorios()
    {
        $permiteVerTodos = auth()->user()?->hasPermission('solicitacoes.relatorios.ver-todos-depto') ?? false;

        // Departamentos ativos (5E: tabela `departments`, no lugar de intranet_parametros DEP_ATIVOS)
        $departamentosQuery = \App\Models\Department::where('is_active', true);

        if (! $permiteVerTodos) {
            $departamentosQuery->where('id', (auth()->user()?->department_id));
        }
        // Mantém a chave `condicao1` (= identificador do depto) que o fluxo/front espera
        $departamentos = $departamentosQuery->orderBy('name')->get()->map(function ($d) {
            return (object) ['id' => $d->id, 'condicao1' => $d->name, 'department_id' => $d->id];
        });

        // Buscar assuntos e responsáveis por departamento
        foreach ($departamentos as $departamento) {
            $departamento->assuntos = SolicitacaoAssunto::where('department_id', $departamento->id)
                ->where('ativo', 'S')
                ->orderBy('assunto')
                ->get();

            // TODO: revisar vínculo departamento — responsáveis adicionais (RESPONSAVEL_ADICIONAL) não portado
            $responsaveisAdicionais = [];

            $departamento->responsaveis = \App\Models\User::where('department_id', $departamento->id)
                ->where('is_active', true)
                ->get()
                ->map(fn($u) => ['matricula' => $u->id, 'nome' => $u->name])
                ->toArray();

            // Mesclar os responsáveis adicionais com os responsáveis do departamento
            $colecaoOriginal = $departamento->responsaveis;
            $colecaoMescladas = collect(array_merge($colecaoOriginal, $responsaveisAdicionais))
                ->unique(fn($item) => is_array($item) ? $item['matricula'] ?? $item['MATRICULA'] : $item->matricula ?? $item->MATRICULA)
                ->values()
                ->all();
            $departamento->responsaveis = $colecaoMescladas;
        }

        // Lista de status
        $statusList = [
            ['value' => 'pendente', 'label' => 'Pendente'],
            ['value' => 'em atendimento', 'label' => 'Em Atendimento'],
            ['value' => 'atendimento pausado', 'label' => 'Atendimento Pausado'],
            ['value' => 'agendado', 'label' => 'Agendado'],
            ['value' => 'retorno solicitante', 'label' => 'Retorno Solicitante'],
            ['value' => 'resolvida', 'label' => 'Resolvida'],
            ['value' => 'finalizada', 'label' => 'Finalizada'],
            ['value' => 'cancelada', 'label' => 'Cancelada'],
        ];

        // Lista de prioridades
        $prioridadeList = [
            ['value' => 'urgente', 'label' => 'Urgente'],
            ['value' => 'alta', 'label' => 'Alta'],
            ['value' => 'media', 'label' => 'Média'],
            ['value' => 'baixa', 'label' => 'Baixa'],
        ];

        return Inertia::render('Solicitacoes/Relatorios/Index', [
            'departamentos' => $departamentos,
            'permiteVerTodos' => $permiteVerTodos,
            'statusList' => $statusList,
            'prioridadeList' => $prioridadeList,
        ]);
    }

    /**
     * Buscar dados do relatório
     */
    public function buscarRelatorio(Request $request)
    {
        try {
            $id = $request->input('id');
            $departamento = $request->input('departamento');
            $codfiliais = $request->input('codfiliais', []);
            $assuntos = $request->input('assuntos', []);
            $responsavel = $request->input('responsavel');
            $solicitante = $request->input('solicitante');
            $status = $request->input('status', []);
            $prioridade = $request->input('prioridade', []);
            $dataInicio = $request->input('dataInicio');
            $dataFim = $request->input('dataFim');
            $atrasadas = $request->input('atrasadas', false);

            $query = Solicitacao::query()
                ->with(['assunto', 'usuarioSolicitante', 'usuarioResponsavel', 'filial', 'etapaAtual.etapa', 'fluxoExecucao.fluxo'])
                ->when($id, fn($q) => $q->where('id', $id))
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($codfiliais), fn($q) => $q->whereIn('filial_id', $codfiliais))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->when($solicitante, fn($q) => $q->where('usuario_solicitante', $solicitante))
                ->when(! empty($status), fn($q) => $q->whereIn('status', $status))
                ->when(! empty($prioridade), fn($q) => $q->whereIn('prioridade', $prioridade))
                ->when($dataInicio, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($dataInicio)))
                ->when($dataFim, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($dataFim)))
                ->when($atrasadas, function ($q) {
                    $q->whereNotNull('previsao_entrega')
                        ->whereNotIn('status', ['finalizada', 'cancelada'])
                        ->whereDate('previsao_entrega', '<', now());
                })
                ->orderBy('created_at', 'desc')
                ->limit(500);

            // Buscar solicitações
            $solicitacoesRaw = $query->get();

            // Detectar fluxos que as solicitações filtradas percorrem.
            // Usado pelo frontend para exibir (ou não) o botão "Exportar Fluxo".
            // A detecção é pelo fluxo da execução (fluxo "dono"), pois um
            // departamento pode ser apenas uma etapa intermediária de um
            // fluxo cujo assunto de entrada pertence a outra área.
            $fluxos = $solicitacoesRaw
                ->map(fn($s) => $s->fluxoExecucao?->fluxo)
                ->filter()
                ->unique('id')
                ->map(fn($f) => ['id' => $f->id, 'nome' => $f->nome])
                ->values();

            // Buscar fotos dos usuários em lote
            $matriculas = $solicitacoesRaw->pluck('usuario_solicitante')
                ->merge($solicitacoesRaw->pluck('usuario_responsavel'))
                ->filter()
                ->unique()
                ->values();

            $fotos = DB::table('INTRANET_USUARIO')
                ->leftJoin('intranet_files', 'INTRANET_USUARIO.foto_perfil_id', '=', 'intranet_files.id')
                ->whereIn('INTRANET_USUARIO.matricula', $matriculas)
                ->pluck('intranet_files.external_link', 'INTRANET_USUARIO.matricula');

            $solicitacoes = $solicitacoesRaw->map(function ($sol) use ($fotos) {
                $diasAberta = (int) Carbon::parse($sol->created_at)->diffInDays(now());
                $diasAtraso = null;
                if ($sol->previsao_entrega && ! in_array($sol->status, ['finalizada', 'cancelada'])) {
                    $atraso = (int) Carbon::parse($sol->previsao_entrega)->diffInDays(now(), false);
                    $diasAtraso = $atraso > 0 ? $atraso : null;
                }

                // Buscar usuários destino
                $usuariosDestino = SolicitacaoCDest::where('SOLICITACAO_ID', $sol->id)->get();
                $usuariosDestinoNomes = $usuariosDestino->map(function ($usuario) {
                    return UtilController::nomeFuncionario($usuario->matricula);
                })->filter()->values()->toArray();

                // Buscar nome do usuário origem
                $usuarioOrigemNome = $sol->usuario_origem ? UtilController::nomeFuncionario($sol->usuario_origem) : null;

                return [
                    'id' => $sol->id,
                    'titulo' => $sol->titulo,
                    'descricao' => $sol->descricao,
                    'assunto' => $sol->assunto?->assunto,
                    'departamento' => $sol->departamento_responsavel,
                    'solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                    'solicitante_matricula' => $sol->usuario_solicitante,
                    'solicitante_foto' => $fotos[$sol->usuario_solicitante] ?? null,
                    'responsavel' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                    'responsavel_matricula' => $sol->usuario_responsavel,
                    'responsavel_foto' => $fotos[$sol->usuario_responsavel] ?? null,
                    'usuario_origem' => $usuarioOrigemNome,
                    'usuarios_destino' => $usuariosDestinoNomes,
                    'solicitacao_pai_id' => $sol->solicitacao_pai_id,
                    'status' => $sol->status,
                    'prioridade' => $sol->prioridade,
                    'etapa_atual' => $sol->etapaAtual ? [
                        'etapa' => $sol->etapaAtual->etapa ? [
                            'id' => $sol->etapaAtual->etapa->id,
                            'nome' => $sol->etapaAtual->etapa->nome,
                            'cor' => $sol->etapaAtual->etapa->cor,
                            'icone' => $sol->etapaAtual->etapa->icone,
                        ] : null,
                    ] : null,
                    'created_at' => $sol->created_at->format('d/m/Y H:i'),
                    'updated_at' => $sol->updated_at->format('d/m/Y H:i'),
                    'previsao_entrega' => $sol->previsao_entrega?->format('d/m/Y'),
                    'dias_aberta' => $diasAberta,
                    'dias_atraso' => $diasAtraso,
                    'data_conclusao' => $sol->data_conclusao?->format('d/m/Y H:i'),
                    'filial' => $sol->filial?->codigo . ' - ' . $sol->filial?->fantasia,
                ];
            });

            return response()->json([
                'solicitacoes' => $solicitacoes,
                'total' => $solicitacoes->count(),
                'fluxos' => $fluxos,
                'tem_fluxo' => $fluxos->isNotEmpty(),
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao buscar relatório: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Exportar relatório filtrado para Excel (módulo de relatórios)
     */
    public function exportarRelatorioFiltros(Request $request)
    {
        try {
            $id = $request->input('id');
            $departamento = $request->input('departamento');
            $codfiliais = $request->input('codfiliais', []);
            $assuntos = $request->input('assuntos', []);
            $responsavel = $request->input('responsavel');
            $solicitante = $request->input('solicitante');
            $status = $request->input('status', []);
            $prioridade = $request->input('prioridade', []);
            $dataInicio = $request->input('dataInicio');
            $dataFim = $request->input('dataFim');
            $atrasadas = $request->input('atrasadas', false);

            $query = Solicitacao::query()
                ->with(['assunto', 'usuarioSolicitante', 'usuarioResponsavel', 'filial', 'etapaAtual.etapa', 'fluxoHistorico'])
                ->when($id, fn($q) => $q->where('id', $id))
                ->when($departamento, fn($q) => $q->where('departamento_responsavel', $departamento))
                ->when(! empty($codfiliais), fn($q) => $q->whereIn('filial_id', $codfiliais))
                ->when(! empty($assuntos), fn($q) => $q->whereIn('assunto_id', $assuntos))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->when($solicitante, fn($q) => $q->where('usuario_solicitante', $solicitante))
                ->when(! empty($status), fn($q) => $q->whereIn('status', $status))
                ->when(! empty($prioridade), fn($q) => $q->whereIn('prioridade', $prioridade))
                ->when($dataInicio, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($dataInicio)))
                ->when($dataFim, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($dataFim)))
                ->when($atrasadas, function ($q) {
                    $q->whereNotNull('previsao_entrega')
                        ->whereNotIn('status', ['finalizada', 'cancelada'])
                        ->whereDate('previsao_entrega', '<', now());
                })
                ->orderBy('created_at', 'desc');

            // ─────────────────────────────────────────────────────────────
            // FLUXO DINÂMICO: quando UM único assunto é selecionado e ele
            // possui um fluxo ativo, adiciona ao relatório as colunas do
            // fluxo (por etapa: Responsável, Início e Dias na Etapa).
            // O "fim" de uma etapa é o "início" da etapa seguinte, então
            // os dias na etapa = (início da próxima etapa − início desta).
            // ─────────────────────────────────────────────────────────────
            $etapasFluxo = collect();
            if (count($assuntos) === 1) {
                $fluxo = SolicitacaoFluxo::where('assunto_id', $assuntos[0])
                    ->where('ativo', 'S')
                    ->orderByDesc('versao')
                    ->first();

                if ($fluxo) {
                    $etapasFluxo = SolicitacaoFluxoEtapa::where('fluxo_id', $fluxo->id)
                        ->where('ativo', 'S')
                        ->orderBy('ordem')
                        ->get(['id', 'nome', 'ordem']);
                }
            }

            // Mapeamento de status para labels amigáveis
            $statusLabels = [
                'pendente' => 'Pendente',
                'em atendimento' => 'Em Atendimento',
                'atendimento pausado' => 'Pausado',
                'agendado' => 'Agendado',
                'retorno solicitante' => 'Aguardando Retorno',
                'resolvida' => 'Resolvida',
                'finalizada' => 'Finalizada',
                'cancelada' => 'Cancelada',
            ];

            // Mapeamento de prioridade para labels amigáveis
            $prioridadeLabels = [
                'urgente' => 'Urgente',
                'alta' => 'Alta',
                'media' => 'Média',
                'baixa' => 'Baixa',
            ];

            $dados = $query->get()->map(function ($sol) use ($statusLabels, $prioridadeLabels, $etapasFluxo) {
                $diasAberta = (int) Carbon::parse($sol->created_at)->diffInDays(now());
                $diasAtraso = null;
                if ($sol->previsao_entrega && ! in_array($sol->status, ['finalizada', 'cancelada'])) {
                    $atraso = (int) Carbon::parse($sol->previsao_entrega)->diffInDays(now(), false);
                    $diasAtraso = $atraso > 0 ? $atraso : null;
                }

                // Buscar usuários destino
                $usuariosDestino = SolicitacaoCDest::where('SOLICITACAO_ID', $sol->id)->get();
                $usuariosDestinoNomes = $usuariosDestino->map(function ($usuario) {
                    return UtilController::nomeFuncionario($usuario->matricula);
                })->filter()->values()->toArray();

                // Buscar nome do usuário origem
                $usuarioOrigemNome = $sol->usuario_origem ? UtilController::nomeFuncionario($sol->usuario_origem) : null;

                $linha = [
                    'ID' => $sol->id,
                    'Título' => $sol->titulo,
                    'Descrição' => $sol->descricao ?? '-',
                    'Assunto' => $sol->assunto?->assunto ?? '-',
                    'Filial' => $sol->filial ? $sol->filial->codigo . ' - ' . $sol->filial->fantasia : null,
                    'Departamento' => $sol->departamento_responsavel,
                    'Solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                    'Mat. Solicitante' => $sol->usuario_solicitante,
                    'Responsável' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                    'Mat. Responsável' => $sol->usuario_responsavel ?? '-',
                    'Usuário Origem' => $usuarioOrigemNome ?? '-',
                    'Usuários Destino' => ! empty($usuariosDestinoNomes) ? implode(', ', $usuariosDestinoNomes) : '-',
                    'Solicitação Pai' => $sol->solicitacao_pai_id ?? '-',
                    'Status' => $statusLabels[$sol->status] ?? $sol->status,
                    'Etapa Andamento' => $sol->etapaAtual?->etapa?->nome ?? '-',
                    'Prioridade' => $prioridadeLabels[$sol->prioridade] ?? $sol->prioridade,
                    'Data Criação' => ExcelDate::dateTimeToExcel($sol->created_at),
                    'Última Atualização' => ExcelDate::dateTimeToExcel($sol->updated_at),
                    'Previsão Entrega' => $sol->previsao_entrega ? ExcelDate::dateTimeToExcel($sol->previsao_entrega) : '-',
                    'Dias Aberta' => $diasAberta,
                    'Dias Atraso' => $diasAtraso ?? '-',
                    'Data de Conclusão' => $sol->data_conclusao ? ExcelDate::dateTimeToExcel($sol->data_conclusao) : '-',
                ];

                // ───────────── Colunas dinâmicas do fluxo ─────────────
                if ($etapasFluxo->isNotEmpty()) {
                    $linha = array_merge($linha, $this->montarColunasFluxo($sol, $etapasFluxo));
                }

                return $linha;
            })->toArray();

            return Excel::download(
                new \App\Exports\SolicitacoesRelatorioExport($dados, $departamento ?? 'Geral'),
                'relatorio_solicitacoes_' . now()->format('Y-m-d_His') . '.xlsx'
            );
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao exportar relatório: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Exportar relatório ORIENTADO A FLUXO.
     *
     * Diferente do export padrão (orientado a assunto), aqui as colunas de
     * etapa vêm do FLUXO ao qual as solicitações pertencem — o que permite
     * incluir etapas de departamentos intermediários (ex.: Utilities &
     * Facilities) que participam de um fluxo cujo assunto de entrada é de
     * outra área.
     *
     * Requer 'fluxo_id'. As demais condições reutilizam os filtros do
     * relatório. A linha-base é a mesma do export padrão; ao final são
     * acrescentadas as colunas do fluxo (por etapa: Responsável, Início,
     * Fim, Dias e Decisão), além de Status do Fluxo, Etapa Atual do Fluxo
     * e Total de Dias no Fluxo.
     */
    public function exportarRelatorioFluxo(Request $request)
    {
        try {
            $fluxoId = $request->input('fluxo_id');

            if (! $fluxoId) {
                return response()->json(['error' => 'Selecione um fluxo para exportar.'], 422);
            }

            $fluxo = SolicitacaoFluxo::find($fluxoId);
            if (! $fluxo) {
                return response()->json(['error' => 'Fluxo não encontrado.'], 404);
            }

            // Etapas do fluxo (ordem define a sequência das colunas)
            $etapasFluxo = SolicitacaoFluxoEtapa::where('fluxo_id', $fluxoId)
                ->where('ativo', 'S')
                ->orderBy('ordem')
                ->get(['id', 'nome', 'ordem', 'departamento']);

            if ($etapasFluxo->isEmpty()) {
                return response()->json(['error' => 'O fluxo selecionado não possui etapas ativas.'], 422);
            }

            // Filtros (mesmos do relatório)
            $id = $request->input('id');
            $departamento = $request->input('departamento');
            $codfiliais = $request->input('codfiliais', []);
            $assuntos = $request->input('assuntos', []);
            $responsavel = $request->input('responsavel');
            $solicitante = $request->input('solicitante');
            $status = $request->input('status', []);
            $prioridade = $request->input('prioridade', []);
            $dataInicio = $request->input('dataInicio');
            $dataFim = $request->input('dataFim');
            $atrasadas = $request->input('atrasadas', false);

            $query = Solicitacao::query()
                ->with(['assunto', 'usuarioSolicitante', 'usuarioResponsavel', 'filial', 'etapaAtual.etapa', 'fluxoHistorico', 'fluxoExecucao.etapaAtual'])
                ->whereHas('fluxoExecucao', fn($q) => $q->where('fluxo_id', $fluxoId))
                // IMPORTANTE: o export por fluxo traz TODAS as solicitações do
                // fluxo, mesmo as que estão em outros departamentos/assuntos.
                // Por isso 'departamento' e 'assuntos' NÃO são aplicados aqui —
                // eles servem apenas para detectar o fluxo na tela. Os demais
                // filtros (filial, período, status, prioridade, responsável,
                // solicitante) permanecem como recortes opcionais.
                ->when($id, fn($q) => $q->where('id', $id))
                ->when(! empty($codfiliais), fn($q) => $q->whereIn('filial_id', $codfiliais))
                ->when($responsavel, fn($q) => $q->where('usuario_responsavel', $responsavel))
                ->when($solicitante, fn($q) => $q->where('usuario_solicitante', $solicitante))
                ->when(! empty($status), fn($q) => $q->whereIn('status', $status))
                ->when(! empty($prioridade), fn($q) => $q->whereIn('prioridade', $prioridade))
                ->when($dataInicio, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($dataInicio)))
                ->when($dataFim, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($dataFim)))
                ->when($atrasadas, function ($q) {
                    $q->whereNotNull('previsao_entrega')
                        ->whereNotIn('status', ['finalizada', 'cancelada'])
                        ->whereDate('previsao_entrega', '<', now());
                })
                ->orderBy('created_at', 'desc');

            $statusLabels = [
                'pendente' => 'Pendente',
                'em atendimento' => 'Em Atendimento',
                'atendimento pausado' => 'Pausado',
                'agendado' => 'Agendado',
                'retorno solicitante' => 'Aguardando Retorno',
                'resolvida' => 'Resolvida',
                'finalizada' => 'Finalizada',
                'cancelada' => 'Cancelada',
            ];

            $prioridadeLabels = [
                'urgente' => 'Urgente',
                'alta' => 'Alta',
                'media' => 'Média',
                'baixa' => 'Baixa',
            ];

            $statusFluxoLabels = [
                'em_andamento' => 'Em Andamento',
                'aguardando_decisao' => 'Aguardando Decisão',
                'aguardando_solicitante' => 'Aguardando Solicitante',
                'concluido' => 'Concluído',
                'cancelado' => 'Cancelado',
            ];

            $dados = $query->get()->map(function ($sol) use ($statusLabels, $prioridadeLabels, $statusFluxoLabels, $etapasFluxo) {
                $diasAberta = (int) Carbon::parse($sol->created_at)->diffInDays(now());
                $diasAtraso = null;
                if ($sol->previsao_entrega && ! in_array($sol->status, ['finalizada', 'cancelada'])) {
                    $atraso = (int) Carbon::parse($sol->previsao_entrega)->diffInDays(now(), false);
                    $diasAtraso = $atraso > 0 ? $atraso : null;
                }

                $usuariosDestino = SolicitacaoCDest::where('SOLICITACAO_ID', $sol->id)->get();
                $usuariosDestinoNomes = $usuariosDestino->map(function ($usuario) {
                    return UtilController::nomeFuncionario($usuario->matricula);
                })->filter()->values()->toArray();

                $usuarioOrigemNome = $sol->usuario_origem ? UtilController::nomeFuncionario($sol->usuario_origem) : null;

                $linha = [
                    'ID' => $sol->id,
                    'Título' => $sol->titulo,
                    'Assunto' => $sol->assunto?->assunto ?? '-',
                    'Filial' => $sol->filial ? $sol->filial->codigo . ' - ' . $sol->filial->fantasia : null,
                    'Departamento' => $sol->departamento_responsavel,
                    'Solicitante' => $sol->usuarioSolicitante?->nome ?? 'N/A',
                    'Mat. Solicitante' => $sol->usuario_solicitante,
                    'Responsável' => $sol->usuarioResponsavel?->nome ?? 'Não atribuído',
                    'Usuário Origem' => $usuarioOrigemNome ?? '-',
                    'Usuários Destino' => ! empty($usuariosDestinoNomes) ? implode(', ', $usuariosDestinoNomes) : '-',
                    'Status' => $statusLabels[$sol->status] ?? $sol->status,
                    'Prioridade' => $prioridadeLabels[$sol->prioridade] ?? $sol->prioridade,
                    'Data Criação' => ExcelDate::dateTimeToExcel($sol->created_at),
                    'Previsão Entrega' => $sol->previsao_entrega ? ExcelDate::dateTimeToExcel($sol->previsao_entrega) : '-',
                    'Dias Aberta' => $diasAberta,
                    'Dias Atraso' => $diasAtraso ?? '-',
                    'Data de Conclusão' => $sol->data_conclusao ? ExcelDate::dateTimeToExcel($sol->data_conclusao) : '-',
                    // Resumo do fluxo
                    'Status do Fluxo' => $statusFluxoLabels[$sol->fluxoExecucao?->status] ?? ($sol->fluxoExecucao?->status ?? '-'),
                    'Etapa Atual (Fluxo)' => $sol->fluxoExecucao?->etapaAtual?->nome ?? '-',
                ];

                // Colunas dinâmicas, etapa a etapa, do fluxo selecionado
                $linha = array_merge($linha, $this->montarColunasFluxoCompleto($sol, $etapasFluxo));

                return $linha;
            })->toArray();

            $nomeFluxo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $fluxo->nome);

            return Excel::download(
                new \App\Exports\SolicitacoesRelatorioExport($dados, $departamento ?? 'Geral'),
                'relatorio_fluxo_' . $nomeFluxo . '_' . now()->format('Y-m-d_His') . '.xlsx'
            );
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao exportar relatório de fluxo: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Monta as colunas dinâmicas do fluxo para uma solicitação no relatório.
     *
     * Para cada etapa do fluxo configurado, gera:
     *   - "{Etapa} - Responsável" : quem moveu a solicitação para a etapa
     *   - "{Etapa} - Início"      : quando a solicitação entrou na etapa
     *   - "{Etapa} - Dias"        : dias entre o início desta etapa e o início
     *                               da próxima (o "fim" de uma etapa é o
     *                               "início" da seguinte). Na última etapa,
     *                               usa a data de conclusão da solicitação.
     *
     * Se uma etapa foi visitada mais de uma vez (retornos por decisão),
     * considera a PRIMEIRA entrada como início e a ÚLTIMA saída como fim.
     *
     * @param  \App\Models\Solicitacao  $sol
     * @param  \Illuminate\Support\Collection  $etapasFluxo  Etapas do fluxo (id, nome, ordem)
     * @return array<string, mixed>
     */
    private function montarColunasFluxo($sol, $etapasFluxo): array
    {
        // Histórico ordenado cronologicamente (asc). A relação vem desc.
        $historico = $sol->fluxoHistorico
            ->sortBy('created_at')
            ->values();

        // Primeira entrada de cada etapa (quando a solicitação CHEGOU nela)
        // e quem realizou a transição que a colocou ali.
        $primeiraEntrada = [];  // etapa_id => Carbon
        $responsavelEntrada = []; // etapa_id => matricula
        // Última saída de cada etapa (quando a solicitação SAIU dela)
        $ultimaSaida = [];      // etapa_id => Carbon

        foreach ($historico as $h) {
            $dataMov = $h->created_at ? Carbon::parse($h->created_at) : null;
            if (! $dataMov) {
                continue;
            }

            // Entrada: a transição moveu para etapa_nova_id
            if ($h->etapa_nova_id) {
                if (! isset($primeiraEntrada[$h->etapa_nova_id])) {
                    $primeiraEntrada[$h->etapa_nova_id] = $dataMov;
                    $responsavelEntrada[$h->etapa_nova_id] = $h->usuario_alteracao;
                }
            }

            // Saída: a transição partiu de etapa_anterior_id (registra a mais recente)
            if ($h->etapa_anterior_id) {
                $ultimaSaida[$h->etapa_anterior_id] = $dataMov;
            }
        }

        $dataConclusao = $sol->data_conclusao ? Carbon::parse($sol->data_conclusao) : null;

        $colunas = [];
        $totalDias = 0;
        $temAlgumaEtapa = false;

        foreach ($etapasFluxo as $etapa) {
            $nome = $etapa->nome;
            $entrada = $primeiraEntrada[$etapa->id] ?? null;

            // Responsável pela etapa (nome no formato do módulo de Compras)
            $matResp = $responsavelEntrada[$etapa->id] ?? null;
            $colunas["{$nome} - Responsável"] = $matResp
                ? (UtilController::nomeFuncionario($matResp) ?: '-')
                : '-';

            // Início da etapa
            $colunas["{$nome} - Início"] = $entrada
                ? ExcelDate::dateTimeToExcel($entrada)
                : '-';

            // Dias na etapa = (fim − início), onde fim = saída registrada
            // (início da próxima etapa). Se a solicitação ainda não saiu
            // desta etapa, usa a data de conclusão ou, na ausência dela, a
            // data atual — assim a etapa em que a solicitação está agora
            // mostra há quantos dias ela está parada nela.
            if ($entrada) {
                $fim = $ultimaSaida[$etapa->id] ?? $dataConclusao ?? Carbon::now();
                $diasEtapa = (int) $entrada->diffInDays($fim);
                $colunas["{$nome} - Dias"] = $diasEtapa;
                $totalDias += $diasEtapa;
                $temAlgumaEtapa = true;
            } else {
                $colunas["{$nome} - Dias"] = '-';
            }
        }

        // Total de dias percorridos no fluxo (soma das etapas com início registrado)
        $colunas['Total de Dias no Fluxo'] = $temAlgumaEtapa ? $totalDias : '-';

        return $colunas;
    }

    /**
     * Monta as colunas do fluxo para o export ORIENTADO A FLUXO.
     *
     * Usa o histórico de WORKFLOW (intranet_solicitacao_fluxo_historico),
     * cujas transições referenciam as etapas do próprio fluxo — incluindo
     * etapas de departamentos intermediários. Para cada etapa gera:
     *   - "{Etapa} - Responsável" : quem moveu a solicitação para a etapa
     *   - "{Etapa} - Início"      : quando entrou na etapa
     *   - "{Etapa} - Fim"         : quando saiu (início da etapa seguinte)
     *   - "{Etapa} - Dias"        : dias entre início e fim (ou até hoje)
     *   - "{Etapa} - Decisão"     : decisão registrada ao sair da etapa
     *
     * Quando a etapa é visitada mais de uma vez (retornos por decisão),
     * considera a PRIMEIRA entrada e a ÚLTIMA saída.
     *
     * @param  \App\Models\Solicitacao  $sol
     * @param  \Illuminate\Support\Collection  $etapasFluxo  Etapas do fluxo (id, nome, ordem, departamento)
     * @return array<string, mixed>
     */
    private function montarColunasFluxoCompleto($sol, $etapasFluxo): array
    {
        $historico = $sol->fluxoHistorico
            ->sortBy('created_at')
            ->values();

        $primeiraEntrada = [];     // etapa_id => Carbon
        $responsavelEntrada = [];  // etapa_id => matricula
        $ultimaSaida = [];         // etapa_id => Carbon
        $decisaoSaida = [];        // etapa_id => string (decisao_label da última saída)

        foreach ($historico as $h) {
            $dataMov = $h->created_at ? Carbon::parse($h->created_at) : null;
            if (! $dataMov) {
                continue;
            }

            if ($h->etapa_nova_id && ! isset($primeiraEntrada[$h->etapa_nova_id])) {
                $primeiraEntrada[$h->etapa_nova_id] = $dataMov;
                $responsavelEntrada[$h->etapa_nova_id] = $h->usuario_alteracao;
            }

            if ($h->etapa_anterior_id) {
                $ultimaSaida[$h->etapa_anterior_id] = $dataMov;
                $decisaoSaida[$h->etapa_anterior_id] = $h->decisao_label;
            }
        }

        $dataConclusao = $sol->data_conclusao ? Carbon::parse($sol->data_conclusao) : null;

        $colunas = [];
        $totalDias = 0;
        $temAlgumaEtapa = false;

        foreach ($etapasFluxo as $etapa) {
            // Inclui o departamento no rótulo, já que o fluxo cruza áreas.
            $nome = $etapa->departamento
                ? "{$etapa->nome} ({$etapa->departamento})"
                : $etapa->nome;

            $entrada = $primeiraEntrada[$etapa->id] ?? null;

            $matResp = $responsavelEntrada[$etapa->id] ?? null;
            $colunas["{$nome} - Responsável"] = $matResp
                ? (UtilController::nomeFuncionario($matResp) ?: '-')
                : '-';

            $colunas["{$nome} - Início"] = $entrada
                ? ExcelDate::dateTimeToExcel($entrada)
                : '-';

            $fim = $entrada ? ($ultimaSaida[$etapa->id] ?? $dataConclusao ?? Carbon::now()) : null;

            // Só mostra "Fim" quando houve saída efetiva (não a data atual).
            $saidaEfetiva = $ultimaSaida[$etapa->id] ?? $dataConclusao ?? null;
            $colunas["{$nome} - Fim"] = ($entrada && $saidaEfetiva)
                ? ExcelDate::dateTimeToExcel($saidaEfetiva)
                : '-';

            if ($entrada) {
                $diasEtapa = (int) $entrada->diffInDays($fim);
                $colunas["{$nome} - Dias"] = $diasEtapa;
                $totalDias += $diasEtapa;
                $temAlgumaEtapa = true;
            } else {
                $colunas["{$nome} - Dias"] = '-';
            }

            $colunas["{$nome} - Decisão"] = $decisaoSaida[$etapa->id] ?? '-';
        }

        $colunas['Total de Dias no Fluxo'] = $temAlgumaEtapa ? $totalDias : '-';

        return $colunas;
    }

    /**
     * Buscar etapas configuradas para um assunto
     */
    public function getEtapas($assunto_id)
    {
        $etapas = \App\Models\SolicitacaoEtapa::where('assunto_id', $assunto_id)
            ->orderBy('ordem')
            ->get();

        return response()->json($etapas);
    }

    /**
     * Salvar etapas de um assunto
     */
    public function salvarEtapas(Request $request)
    {
        $assuntoId = $request->input('assunto_id');
        $etapas = $request->input('etapas', []);

        DB::beginTransaction();
        try {
            // IDs das etapas enviadas
            $idsEnviados = collect($etapas)->pluck('id')->filter()->toArray();

            // Buscar etapas existentes
            $etapasExistentes = \App\Models\SolicitacaoEtapa::where('assunto_id', $assuntoId)
                ->pluck('id')
                ->toArray();

            // Identificar etapas a serem desativadas (não excluir para manter histórico)
            $idsParaDesativar = array_diff($etapasExistentes, $idsEnviados);

            // Desativar etapas removidas
            \App\Models\SolicitacaoEtapa::whereIn('id', $idsParaDesativar)
                ->update(['ativo' => 'N']);

            // Criar ou atualizar etapas enviadas
            foreach ($etapas as $index => $etapa) {
                $etapaModel = $etapa['id']
                    ? \App\Models\SolicitacaoEtapa::find($etapa['id'])
                    : new \App\Models\SolicitacaoEtapa;

                $etapaModel->assunto_id = $assuntoId;
                $etapaModel->nome = $etapa['nome'];
                $etapaModel->descricao = $etapa['descricao'] ?? null;
                $etapaModel->cor = $etapa['cor'] ?? '#3B82F6';
                $etapaModel->icone = $etapa['icone'] ?? 'pi pi-circle';
                $etapaModel->ordem = $index;
                $etapaModel->ativo = 'S';
                $etapaModel->save();
            }

            DB::commit();

            // Retornar etapas atualizadas
            $etapasAtualizadas = \App\Models\SolicitacaoEtapa::where('assunto_id', $assuntoId)
                ->where('ativo', 'S')
                ->orderBy('ordem')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Etapas salvas com sucesso!',
                'etapas' => $etapasAtualizadas,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao salvar etapas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar etapas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clonar etapas de andamento de um assunto para outro
     */
    public function clonarEtapas(Request $request)
    {
        $origemId = $request->input('origem_assunto_id');
        $destinoId = $request->input('destino_assunto_id');

        if (! $origemId || ! $destinoId || $origemId == $destinoId) {
            return response()->json(['success' => false, 'message' => 'Assuntos inválidos'], 422);
        }

        // Verificar se destino já tem etapas ativas
        $existentes = \App\Models\SolicitacaoEtapa::where('assunto_id', $destinoId)
            ->where('ativo', 'S')
            ->count();

        if ($existentes > 0) {
            return response()->json(['success' => false, 'message' => 'Este assunto já possui etapas de andamento'], 422);
        }

        $etapasOrigem = \App\Models\SolicitacaoEtapa::where('assunto_id', $origemId)
            ->where('ativo', 'S')
            ->orderBy('ordem')
            ->get();

        if ($etapasOrigem->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Assunto de origem não possui etapas'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($etapasOrigem as $etapa) {
                \App\Models\SolicitacaoEtapa::create([
                    'assunto_id' => $destinoId,
                    'nome' => $etapa->nome,
                    'descricao' => $etapa->descricao,
                    'cor' => $etapa->cor,
                    'icone' => $etapa->icone,
                    'ordem' => $etapa->ordem,
                    'ativo' => 'S',
                ]);
            }

            DB::commit();

            $novasEtapas = \App\Models\SolicitacaoEtapa::where('assunto_id', $destinoId)
                ->where('ativo', 'S')
                ->orderBy('ordem')
                ->get();

            return response()->json([
                'success' => true,
                'message' => $etapasOrigem->count() . ' etapas clonadas com sucesso!',
                'etapas' => $novasEtapas,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao clonar etapas: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erro ao clonar etapas'], 500);
        }
    }

    /**
     * Alterar etapa de andamento de uma solicitação
     */
    public function alterarEtapa(Request $request)
    {
        $solicitacaoId = $request->input('solicitacao_id');
        $etapaId = $request->input('etapa_id');
        $observacao = $request->input('observacao');
        $matricula = auth()->id();

        DB::beginTransaction();
        try {
            $solicitacao = Solicitacao::findOrFail($solicitacaoId);
            $etapa = \App\Models\SolicitacaoEtapa::findOrFail($etapaId);

            // Buscar etapa anterior (se existir)
            $etapaAtualAnterior = \App\Models\SolicitacaoEtapaAtual::where('solicitacao_id', $solicitacaoId)->first();
            $etapaAnteriorId = $etapaAtualAnterior?->etapa_id;

            // Atualizar ou criar etapa atual
            \App\Models\SolicitacaoEtapaAtual::updateOrCreate(
                ['solicitacao_id' => $solicitacaoId],
                [
                    'etapa_id' => $etapaId,
                    'usuario_alteracao' => $matricula,
                    'data_alteracao' => now(),
                ]
            );

            // Registrar no histórico
            \App\Models\SolicitacaoEtapaHistorico::create([
                'solicitacao_id' => $solicitacaoId,
                'etapa_anterior_id' => $etapaAnteriorId,
                'etapa_nova_id' => $etapaId,
                'usuario_alteracao' => $matricula,
                'observacao' => $observacao,
            ]);

            // Buscar nome da etapa anterior para descrição da movimentação
            $etapaAnteriorNome = $etapaAnteriorId
                ? \App\Models\SolicitacaoEtapa::find($etapaAnteriorId)?->nome ?? 'Não definida'
                : 'Não definida';

            $usuario = Funcionario::where('matricula', $matricula)->first();
            $nomeUsuario = $usuario ? strtoupper($usuario->nome) : 'SISTEMA';

            // Criar movimentação no histórico da solicitação
            SolicitacaoMov::create([
                'solicitacao_id' => $solicitacaoId,
                'usuario_origem' => $matricula,
                'tipo_movimentacao' => 'Etapa alterada',
                'descricao' => "{$nomeUsuario} alterou a etapa de \"{$etapaAnteriorNome}\" para \"{$etapa->nome}\"" . ($observacao ? ". Obs: {$observacao}" : ''),
                'usuario_movimentacao' => $matricula,
                'dados_extras' => [
                    'etapa_id' => $etapa->id,
                    'etapa_nome' => $etapa->nome,
                    'etapa_cor' => $etapa->cor,
                    'etapa_icone' => $etapa->icone,
                ],
            ]);

            DB::commit();

            // ✅ Disparar evento Reverb para atualização em tempo real
            try {
                $reverbService = new \App\Services\SolicitacaoReverbService;
                $solicitacaoData = [
                    'id' => $solicitacao->id,
                    'etapa_atual' => [
                        'etapa_id' => $etapa->id,
                        'etapa' => [
                            'id' => $etapa->id,
                            'nome' => $etapa->nome,
                            'cor' => $etapa->cor,
                            'icone' => $etapa->icone,
                        ],
                    ],
                ];
                // Notificar canal do departamento e do solicitante
                $reverbService->notificarAtualizacao(
                    $solicitacaoData,
                    $solicitacao->departamento_responsavel,
                    'etapa_alterada',
                    $solicitacao->usuario_solicitante // Notifica também o solicitante
                );
            } catch (\Exception $e) {
                Log::warning('Erro ao enviar notificação Reverb de etapa alterada: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Etapa alterada com sucesso!',
                'etapa' => $etapa,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao alterar etapa: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar etapa: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // WORKFLOW / FLUXO DE SOLICITAÇÕES
    // ══════════════════════════════════════════════════════════════

    /**
     * Retorna o fluxo configurado para um assunto (com etapas e decisões).
     */
    public function getFluxo($assunto_id)
    {
        $fluxo = SolicitacaoFluxo::where('assunto_id', $assunto_id)
            ->where('ativo', 'S')
            ->with(['etapas' => function ($q) {
                $q->where('ativo', 'S')->orderBy('ordem');
            }, 'etapas.decisoes' => function ($q) {
                $q->orderBy('ordem');
            }, 'etapas.campos' => function ($q) {
                $q->orderBy('ordem');
            }])
            ->first();

        // Carrega responsáveis permitidos nas etapas já filtradas
        if ($fluxo && $fluxo->etapas->isNotEmpty()) {
            try {
                $fluxo->etapas->load('responsaveisPermitidos');

                // Monta nomes dos responsáveis (encoding seguro do ERP legado)
                foreach ($fluxo->etapas as $etapa) {
                    foreach ($etapa->responsaveisPermitidos as $resp) {
                        $func = Funcionario::where('matricula', $resp->matricula)->first(['matricula', 'nome']);
                        $resp->setAttribute('nome_funcionario', $func ? mb_convert_encoding($func->nome, 'UTF-8', 'auto') : null);
                        $resp->unsetRelation('funcionario');
                    }

                    // Nome do responsável padrão
                    if ($etapa->responsavel_padrao) {
                        $funcPadrao = Funcionario::where('matricula', $etapa->responsavel_padrao)->first(['matricula', 'nome']);
                        $etapa->setAttribute('nome_responsavel_padrao', $funcPadrao ? mb_convert_encoding($funcPadrao->nome, 'UTF-8', 'auto') : null);
                    }
                }
            } catch (\Exception $e) {
                // Tabela ainda não criada — ignora
            }
        }

        return response()->json($fluxo);
    }

    /**
     * Salva o fluxo completo (etapas + decisões) de um assunto.
     * Cria ou atualiza o fluxo, suas etapas e as decisões de cada etapa.
     */
    public function salvarFluxo(Request $request)
    {
        try {
            $assuntoId = $request->input('assunto_id');
            $nome = $request->input('nome', 'Fluxo');
            $descricao = $request->input('descricao');
            $etapasData = $request->input('etapas', []);

            DB::beginTransaction();

            // Buscar fluxo ativo atual do assunto
            $fluxoExistente = SolicitacaoFluxo::where('assunto_id', $assuntoId)
                ->where('ativo', 'S')
                ->first();

            $versionou = false;
            $qtdExecucoesAtivas = 0;

            // ═══════════════════════════════════════════════════════════
            // VERSIONAMENTO: Se o fluxo existente tem solicitações em
            // andamento, NÃO editamos ele — criamos uma nova versão.
            // O fluxo antigo fica intacto (ativo='N') para as execuções
            // que ainda estão rodando nele. Novas solicitações usarão
            // a versão nova.
            // ═══════════════════════════════════════════════════════════
            if ($fluxoExistente && $fluxoExistente->temExecucoesAtivas()) {
                $qtdExecucoesAtivas = $fluxoExistente->qtdExecucoesAtivas();
                $versaoAnterior = $fluxoExistente->versao ?? 1;

                // Desativar fluxo antigo (ele continua existindo para as execuções ativas)
                $fluxoExistente->update(['ativo' => 'N']);

                // Criar nova versão do fluxo
                $fluxo = SolicitacaoFluxo::create([
                    'assunto_id' => $assuntoId,
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'ativo' => 'S',
                    'versao' => $versaoAnterior + 1,
                ]);

                $versionou = true;

                Log::info('Workflow: Fluxo versionado', [
                    'assunto_id' => $assuntoId,
                    'fluxo_antigo_id' => $fluxoExistente->id,
                    'fluxo_novo_id' => $fluxo->id,
                    'versao_anterior' => $versaoAnterior,
                    'versao_nova' => $versaoAnterior + 1,
                    'execucoes_ativas' => $qtdExecucoesAtivas,
                ]);
            } else {
                // Sem execuções ativas — editar in-place normalmente
                $fluxo = SolicitacaoFluxo::updateOrCreate(
                    ['assunto_id' => $assuntoId, 'ativo' => 'S'],
                    [
                        'nome' => $nome,
                        'descricao' => $descricao,
                        'ativo' => 'S',
                        'versao' => $fluxoExistente ? ($fluxoExistente->versao ?? 1) : 1,
                    ]
                );
            }

            // Se versionou, todas as etapas são novas (IDs do frontend pertencem ao fluxo antigo)
            if (! $versionou) {
                // IDs enviados (existentes) — só faz sentido quando editando in-place
                $idsEnviados = collect($etapasData)->pluck('id')->filter()->toArray();

                // Soft-delete das etapas removidas (mantém histórico)
                SolicitacaoFluxoEtapa::where('fluxo_id', $fluxo->id)
                    ->whereNotIn('id', $idsEnviados)
                    ->update(['ativo' => 'N']);
            }

            // Mapeamento de IDs temporários para reais (para vincular decisões)
            // Usa mapas separados para evitar colisão entre IDs reais e índices
            $mapaIds = [];      // mapa: ID original (ou 'temp_N') → ID real
            $mapaByIndex = [];  // mapa: índice do array → ID real

            // Salvar etapas
            foreach ($etapasData as $index => $etapaData) {
                // Validar domínio do campo permitir_solicitante_avancar (N | S | E)
                $permitirSolicitante = $etapaData['permitir_solicitante_avancar'] ?? 'N';
                if (! in_array($permitirSolicitante, ['N', 'S', 'E'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Etapa \"{$etapaData['nome']}\": valor inválido em permitir_solicitante_avancar — deve ser 'N', 'S' ou 'E'.",
                        'errors' => [
                            "etapas.{$index}.permitir_solicitante_avancar" => ["Valor inválido: use 'N' (nenhum), 'S' (permitir) ou 'E' (exclusivo)."],
                        ],
                    ], 422);
                }

                $dadosEtapa = [
                    'fluxo_id' => $fluxo->id,
                    'nome' => $etapaData['nome'],
                    'descricao' => $etapaData['descricao'] ?? null,
                    'departamento' => $etapaData['departamento'],
                    'assunto_id' => $etapaData['assunto_id'] ?? null,
                    'etapa_andamento_id' => $etapaData['etapa_andamento_id'] ?? null,
                    'manter_responsavel' => $etapaData['manter_responsavel'] ?? 'N',
                    'responsavel_padrao' => $etapaData['responsavel_padrao'] ?? null,
                    'permitir_responsavel_externo' => $etapaData['permitir_responsavel_externo'] ?? 'N',
                    'permitir_solicitante_avancar' => $permitirSolicitante,
                    'exibir_campos_assunto' => $etapaData['exibir_campos_assunto'] ?? 'N',
                    'prazo_horas' => $etapaData['prazo_horas'] ?? null,
                    'instrucoes' => $etapaData['instrucoes'] ?? null,
                    'cor' => $etapaData['cor'] ?? '#3B82F6',
                    'icone' => $etapaData['icone'] ?? 'pi pi-circle',
                    'ordem' => $index,
                    'ativo' => 'S',
                ];

                // Se versionou, SEMPRE cria etapas novas (são de um fluxo novo)
                if ($versionou) {
                    $etapa = SolicitacaoFluxoEtapa::create($dadosEtapa);
                } elseif (! empty($etapaData['id']) && is_numeric($etapaData['id'])) {
                    $etapa = SolicitacaoFluxoEtapa::find($etapaData['id']);
                    if ($etapa) {
                        $etapa->update($dadosEtapa);
                    } else {
                        $etapa = SolicitacaoFluxoEtapa::create($dadosEtapa);
                    }
                } else {
                    $etapa = SolicitacaoFluxoEtapa::create($dadosEtapa);
                }

                // Mapear ID temporário para real (mapas separados para não colidir)
                $idOriginal = $etapaData['id'] ?? ('temp_' . $index);
                $mapaIds[$idOriginal] = $etapa->id;
                $mapaByIndex[$index] = $etapa->id;
            }

            // Segunda passada: salvar decisões (agora que todas as etapas existem)
            foreach ($etapasData as $index => $etapaData) {
                $etapaId = $mapaIds[$etapaData['id'] ?? ('temp_' . $index)] ?? $mapaByIndex[$index];
                $decisoesData = $etapaData['decisoes'] ?? [];

                // Remover decisões antigas desta etapa (no caso de edição in-place)
                if (! $versionou) {
                    SolicitacaoFluxoDecisao::where('etapa_fluxo_id', $etapaId)->delete();
                }

                foreach ($decisoesData as $dIndex => $decisaoData) {
                    // Resolver ID destino: pode ser um ID real ou um índice de etapa
                    $destinoId = null;
                    if (isset($decisaoData['etapa_destino_id'])) {
                        $destino = $decisaoData['etapa_destino_id'];
                        // Tentar resolver pelo mapa de IDs, depois pelo mapa de índices
                        $destinoId = $mapaIds[$destino] ?? $mapaByIndex[$destino] ?? (is_numeric($destino) ? $destino : null);
                    }

                    // Se a ação é "finalizar", "cancelar" ou "resolver", destino é null
                    $acao = $decisaoData['acao'] ?? 'avancar';
                    if (in_array($acao, ['finalizar', 'cancelar', 'resolver'])) {
                        $destinoId = null;
                    }

                    SolicitacaoFluxoDecisao::create([
                        'etapa_fluxo_id' => $etapaId,
                        'label' => $decisaoData['label'],
                        'cor' => $decisaoData['cor'] ?? '#3B82F6',
                        'icone' => $decisaoData['icone'] ?? null,
                        'etapa_destino_id' => $destinoId,
                        'acao' => $acao,
                        'etapa_andamento_id' => $decisaoData['etapa_andamento_id'] ?? null,
                        'abrir_solicitacao_assunto_id' => $decisaoData['abrir_solicitacao_assunto_id'] ?? null,
                        'ordem' => $dIndex,
                    ]);
                }
            }

            // Terceira passada: salvar campos das etapas
            foreach ($etapasData as $index => $etapaData) {
                $etapaId = $mapaIds[$etapaData['id'] ?? ('temp_' . $index)] ?? $mapaByIndex[$index];
                $camposData = $etapaData['campos'] ?? [];

                // Buscar decisões desta etapa (já salvas na segunda passada) para resolver decisao_index
                $decisoesEtapa = SolicitacaoFluxoDecisao::where('etapa_fluxo_id', $etapaId)
                    ->orderBy('ordem')
                    ->pluck('id')
                    ->toArray();

                // Remover campos antigos desta etapa (no caso de edição in-place)
                if (! $versionou) {
                    SolicitacaoFluxoEtapaCampo::where('etapa_fluxo_id', $etapaId)->delete();
                }

                foreach ($camposData as $cIndex => $campoData) {
                    // Resolver decisao_index para decisao_id
                    $decisaoId = null;
                    if (isset($campoData['decisao_index']) && $campoData['decisao_index'] !== null) {
                        $decisaoId = $decisoesEtapa[$campoData['decisao_index']] ?? null;
                    }

                    SolicitacaoFluxoEtapaCampo::create([
                        'etapa_fluxo_id' => $etapaId,
                        'decisao_id' => $decisaoId,
                        'label' => $campoData['label'],
                        'tipo' => $campoData['tipo'] ?? 'texto',
                        'placeholder' => $campoData['placeholder'] ?? null,
                        'opcoes' => isset($campoData['opcoes']) ? (is_array($campoData['opcoes']) ? $campoData['opcoes'] : json_decode($campoData['opcoes'], true)) : null,
                        'obrigatorio' => $campoData['obrigatorio'] ?? 'N',
                        'ordem' => $cIndex,
                        'predefinido' => $campoData['predefinido'] ?? 'N',
                        'campo_predefinido_key' => $campoData['campo_predefinido_key'] ?? null,
                    ]);
                }
            }

            // Quarta passada: salvar responsáveis permitidos das etapas
            foreach ($etapasData as $index => $etapaData) {
                $etapaId = $mapaIds[$etapaData['id'] ?? ('temp_' . $index)] ?? $mapaByIndex[$index];
                $responsaveisData = $etapaData['responsaveis_permitidos'] ?? [];

                // Remover responsáveis antigos (no caso de edição in-place)
                if (! $versionou) {
                    SolicitacaoFluxoEtapaResponsavel::where('etapa_fluxo_id', $etapaId)->delete();
                }

                foreach ($responsaveisData as $respData) {
                    if (! empty($respData['matricula'])) {
                        SolicitacaoFluxoEtapaResponsavel::create([
                            'etapa_fluxo_id' => $etapaId,
                            'matricula' => $respData['matricula'],
                        ]);
                    }
                }
            }

            DB::commit();

            // Recarregar fluxo completo
            $fluxo->load(['etapas' => function ($q) {
                $q->where('ativo', 'S')->orderBy('ordem');
            }, 'etapas.decisoes' => function ($q) {
                $q->orderBy('ordem');
            }, 'etapas.campos' => function ($q) {
                $q->orderBy('ordem');
            }]);

            $mensagem = 'Fluxo salvo com sucesso!';
            if ($versionou) {
                $plural = $qtdExecucoesAtivas > 1 ? 'solicitações' : 'solicitação';
                $mensagem = 'Nova versão (v' . $fluxo->versao . ') criada! '
                    . $qtdExecucoesAtivas . ' ' . $plural . ' em andamento continuam no fluxo anterior.';
            }

            return response()->json([
                'success' => true,
                'message' => $mensagem,
                'fluxo' => $fluxo,
                'versionado' => $versionou,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao salvar fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar fluxo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna os dados do fluxo de uma solicitação específica (para exibição no detalhe).
     */
    public function getFluxoSolicitacao($solicitacao_id)
    {
        $solicitacao = Solicitacao::find($solicitacao_id);

        if (! $solicitacao) {
            return response()->json(null);
        }

        $workflowService = new WorkflowService(new SolicitacaoReverbService);
        $dados = $workflowService->obterDadosFluxo($solicitacao);

        // Sanitizar encoding para evitar erro "Malformed UTF-8" do ERP legado
        $json = json_encode($dados, JSON_INVALID_UTF8_SUBSTITUTE);

        return response($json, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Volta a solicitação para a etapa anterior do fluxo.
     */
    public function voltarFluxo(Request $request)
    {
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $observacao = $request->input('observacao');

            $solicitacao = Solicitacao::find($solicitacaoId);
            if (! $solicitacao) {
                return response()->json(['success' => false, 'message' => 'Solicitação não encontrada'], 404);
            }

            $workflowService = new WorkflowService(new SolicitacaoReverbService);
            $resultado = $workflowService->voltarEtapa($solicitacao, auth()->id(), $observacao);

            return response()->json([
                'success' => $resultado['sucesso'],
                'message' => $resultado['mensagem'],
                'etapa_nova' => $resultado['etapa_nova'],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao voltar fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao voltar fluxo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Avança a solicitação para a próxima etapa do fluxo (sem decisão).
     * Usado quando a etapa não tem decisões configuradas — ao concluir, vai para a próxima.
     */
    public function avancarFluxo(Request $request)
    {
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $observacao = $request->input('observacao');
            $respostasSelects = $request->input('respostas_selects', []);

            $solicitacao = Solicitacao::find($solicitacaoId);
            if (! $solicitacao) {
                return response()->json(['success' => false, 'message' => 'Solicitação não encontrada'], 404);
            }

            $workflowService = new WorkflowService(new SolicitacaoReverbService);
            $resultado = $workflowService->avancarEtapa($solicitacao, auth()->id(), $observacao);

            // Salvar respostas dos campos do novo assunto (se houver)
            if ($resultado['sucesso'] && ! empty($respostasSelects)) {
                $solicitacao->refresh();
                $this->salvarRespostasSelects($solicitacaoId, $solicitacao->assunto_id, $respostasSelects);
            }

            // Respeitar http_status retornado pelo WorkflowService (ex.: 403 para guarda de Modo Exclusivo)
            $statusHttp = $resultado['http_status'] ?? ($resultado['sucesso'] ? 200 : 422);

            return response()->json([
                'success' => $resultado['sucesso'],
                'message' => $resultado['mensagem'],
                'etapa_nova' => $resultado['etapa_nova'],
            ], $statusHttp);
        } catch (\Exception $e) {
            Log::error('Erro ao avançar fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao avançar fluxo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Processa uma decisão no fluxo (ex: "Aprovado" ou "Reprovado").
     * Redireciona a solicitação conforme a decisão tomada.
     */
    public function decidirFluxo(Request $request)
    {
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $decisaoId = $request->input('decisao_id');
            $observacao = $request->input('observacao');
            $responsavelMatricula = $request->input('responsavel_matricula');
            $respostasSelects = $request->input('respostas_selects', []);

            $solicitacao = Solicitacao::find($solicitacaoId);
            if (! $solicitacao) {
                return response()->json(['success' => false, 'message' => 'Solicitação não encontrada'], 404);
            }

            $workflowService = new WorkflowService(new SolicitacaoReverbService);
            $resultado = $workflowService->processarDecisao($solicitacao, $decisaoId, auth()->id(), $observacao, $responsavelMatricula);

            // Salvar respostas dos campos do novo assunto (se houver)
            if ($resultado['sucesso'] && ! empty($respostasSelects)) {
                $solicitacao->refresh();
                $this->salvarRespostasSelects($solicitacaoId, $solicitacao->assunto_id, $respostasSelects);
            }

            // Respeitar http_status retornado pelo WorkflowService (ex.: 403 para guarda de Modo Exclusivo)
            $statusHttp = $resultado['http_status'] ?? ($resultado['sucesso'] ? 200 : 422);

            return response()->json([
                'success' => $resultado['sucesso'],
                'message' => $resultado['mensagem'],
                'etapa_nova' => $resultado['etapa_nova'],
            ], $statusHttp);
        } catch (\Exception $e) {
            Log::error('Erro ao processar decisão do fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar decisão: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devolve a solicitação ao fluxo após o solicitante complementar/corrigir.
     * Transfere de volta para o departamento da etapa atual do fluxo.
     */
    public function devolverAoFluxo(Request $request)
    {
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $observacao = $request->input('observacao');

            $solicitacao = Solicitacao::find($solicitacaoId);
            if (! $solicitacao) {
                return response()->json(['success' => false, 'message' => 'Solicitação não encontrada'], 404);
            }

            $workflowService = new WorkflowService(new SolicitacaoReverbService);
            $resultado = $workflowService->retornarDoSolicitante($solicitacao, auth()->id(), $observacao);

            return response()->json([
                'success' => $resultado['sucesso'],
                'message' => $resultado['mensagem'],
                'etapa_nova' => $resultado['etapa_nova'],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao devolver ao fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao devolver ao fluxo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Salva os valores dos campos preenchidos pelo responsável na etapa atual do fluxo.
     */
    public function salvarCamposFluxo(Request $request)
    {
        try {
            $solicitacaoId = $request->input('solicitacao_id');
            $campos = $request->input('campos', []); // [{ etapa_campo_id: X, valor: Y }, ...]

            $solicitacao = Solicitacao::find($solicitacaoId);
            if (! $solicitacao) {
                return response()->json(['success' => false, 'message' => 'Solicitação não encontrada'], 404);
            }

            $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacaoId)->first();
            if (! $execucao || ! $execucao->isAtivo()) {
                return response()->json(['success' => false, 'message' => 'Solicitação não está em um fluxo ativo'], 400);
            }

            $matricula = auth()->id();

            foreach ($campos as $campoData) {
                if (! isset($campoData['etapa_campo_id'])) {
                    continue;
                }

                SolicitacaoFluxoEtapaCampoValor::updateOrCreate(
                    [
                        'execucao_id' => $execucao->id,
                        'etapa_campo_id' => $campoData['etapa_campo_id'],
                    ],
                    [
                        'valor' => $campoData['valor'] ?? null,
                        'usuario_preenchimento' => $matricula,
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Campos salvos com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar campos do fluxo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar campos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna a lista de campos predefinidos disponíveis no sistema.
     */
    public function getCamposPredefinidos()
    {
        return response()->json(SolicitacaoFluxoEtapaCampo::camposPredefinidos());
    }

    /**
     * Finaliza automaticamente todos os lembretes ativos vinculados a uma solicitação
     * Chamado quando a solicitação é finalizada ou cancelada
     */
    protected function finalizarLembretesVinculados($solicitacaoId, $status = 'finalizado')
    {
        try {
            // Busca IDs dos agendamentos vinculados a esta solicitação
            $agendamentosIds = SolicitacaoAgendSol::where('solicitacao_id', $solicitacaoId)
                ->pluck('agendamento_id');

            // Atualiza apenas os lembretes ativos
            SolicitacaoAgendamento::whereIn('id', $agendamentosIds)
                ->where('tipo', SolicitacaoAgendamento::TIPO_LEMBRETE)
                ->where('status', 'ativo')
                ->update([
                    'status' => $status,
                    'data_termino' => Carbon::now()->format('Y-m-d H:i:s'),
                    'mat_termino' => auth()->id() ?? null,
                ]);
        } catch (\Throwable $th) {
            // Log do erro mas não interrompe o fluxo principal
            Log::error('Erro ao finalizar lembretes vinculados: ' . $th->getMessage());
        }
    }

    /**
     * GET /solicitacoes/configuracoes/filiais-lideranca
     * Retorna todas as filiais com flag de configurada para visibilidade de liderança
     */
    public function getFiliaisLideranca()
    {
        $filiaisConfiguradas = DB::table('intranet_parametros')
            ->where('menu', 'SOLICITACOES')
            ->where('submenu', 'CONFIGURACOES')
            ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
            ->where('valor', 1)
            ->pluck('condicao1')
            ->map(fn($v) => (int) $v)
            ->toArray();

        $filiais = Filial::orderBy('id')
            ->get()
            ->map(fn($f) => [
                'codigo' => $f->codigo,
                'fantasia' => $f->fantasia,
                'cidade' => null, // branches não possui cidade
                'configurada' => in_array((int) $f->id, $filiaisConfiguradas),
            ]);

        return response()->json($filiais);
    }

    /**
     * POST /solicitacoes/configuracoes/filiais-lideranca
     * Body: { ativos: [91, 15], inativos: [7, 3] }
     */
    public function storeFiliaisLideranca(Request $request)
    {
        $ativos = $request->input('ativos', []);
        $inativos = $request->input('inativos', []);

        // Remover inativados
        if (! empty($inativos)) {
            DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
                ->whereIn('condicao1', $inativos)
                ->delete();
        }

        // Inserir ativados (se ainda não existem)
        foreach ($ativos as $codFilial) {
            $existe = DB::table('intranet_parametros')
                ->where('menu', 'SOLICITACOES')
                ->where('submenu', 'CONFIGURACOES')
                ->where('parametro', 'FILIAL_VISIBILIDADE_LIDERANCA')
                ->where('condicao1', $codFilial)
                ->exists();

            if (! $existe) {
                DB::table('intranet_parametros')->insert([
                    'menu' => 'SOLICITACOES',
                    'submenu' => 'CONFIGURACOES',
                    'parametro' => 'FILIAL_VISIBILIDADE_LIDERANCA',
                    'condicao1' => $codFilial,
                    'valor' => 1,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Salva respostas dos campos personalizados (selects) de um assunto para uma solicitação.
     * Reutilizado por alterarDepto, avancarFluxo, decidirFluxo, etc.
     */
    private function salvarRespostasSelects(int $solicitacaoId, int $assuntoId, array $respostasSelects): void
    {
        $tiposErp = ['depto_compras', 'depto_funcionario', 'filial_winthor', 'funcao', 'regional'];

        foreach ($respostasSelects as $respostaSelect) {
            $selectId = $respostaSelect['selecao_id'] ?? null;
            $resposta = $respostaSelect['resposta'] ?? null;
            $assuntoIdResposta = $respostaSelect['assunto_id'] ?? $assuntoId;
            $tipo = $respostaSelect['tipo'] ?? null;

            if ($selectId === null || $resposta === null || $resposta === '' || (is_array($resposta) && empty($resposta))) {
                continue;
            }

            // Deletar resposta existente para recriar
            SolicitacaoSelecaoResposta::where('solicitacao_id', $solicitacaoId)
                ->where('selecao_id', $selectId)
                ->delete();

            if ($tipo === 'selecao' || in_array($tipo, $tiposErp)) {
                $valores = is_array($resposta) ? $resposta : [$resposta];
                foreach ($valores as $valor) {
                    SolicitacaoSelecaoResposta::create([
                        'selecao_id' => $selectId,
                        'texto_resposta' => is_numeric($valor) ? null : (in_array($tipo, $tiposErp) ? null : $valor),
                        'itens_id' => is_numeric($valor) && $tipo === 'selecao' ? $valor : null,
                        'valor_winthor' => in_array($tipo, $tiposErp) ? (string) $valor : null,
                        'solicitacao_id' => $solicitacaoId,
                        'assunto_id' => $assuntoIdResposta,
                    ]);
                }
            } elseif ($tipo === 'cnpj' || $tipo === 'texto' || $tipo === 'numero') {
                SolicitacaoSelecaoResposta::create([
                    'selecao_id' => $selectId,
                    'texto_resposta' => $resposta,
                    'solicitacao_id' => $solicitacaoId,
                    'assunto_id' => $assuntoIdResposta,
                ]);
            } elseif ($tipo === 'data') {
                $data1 = null;
                $data2 = null;

                if (! empty($resposta['datas'])) {
                    $data1 = $resposta['datas'][0]
                        ? Carbon::createFromFormat('Y-m-d', $resposta['datas'][0])
                        : null;

                    $data2 = isset($resposta['datas'][1]) && $resposta['datas'][1]
                        ? Carbon::createFromFormat('Y-m-d', $resposta['datas'][1])
                        : null;
                }

                SolicitacaoSelecaoResposta::create([
                    'selecao_id' => $selectId,
                    'data1' => $data1,
                    'data2' => $data2,
                    'solicitacao_id' => $solicitacaoId,
                    'assunto_id' => $assuntoIdResposta,
                ]);
            }
        }
    }
}
