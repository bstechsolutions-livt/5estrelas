<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Filial;
use App\Models\v2\BsGestaoAlvara;
use App\Models\v2\BsGestaoContrato;
use App\Models\v2\BsGestaoContratoAnexo;
use App\Models\v2\BsGestaoContratoReajuste;
use App\Models\v2\BsGestaoEquipamento;
use App\Models\v2\BsGestaoTipoAlvara;
use App\Models\v2\BsGestaoTipoIndice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GestaoContratosController extends Controller
{
  // ═══════════════════════════════════════════════════════════════
  //                         DASHBOARD
  // ═══════════════════════════════════════════════════════════════

  public function getDashboard(Request $request)
  {
    try {
      // Contratos
      $totalContratosLocacao = BsGestaoContrato::locacao()->ativos()->count();
      $totalContratosServico = BsGestaoContrato::servico()->ativos()->count();
      $contratosVencendo30 = BsGestaoContrato::ativos()->vencendoEm(30)->count();
      $contratosVencendo90 = BsGestaoContrato::ativos()->vencendoEm(90)->count();

      // Valores
      $valorTotalLocacao = BsGestaoContrato::locacao()->ativos()->sum('valor_mensal');
      $valorTotalServico = BsGestaoContrato::servico()->ativos()->sum('valor_mensal');

      // Alvarás
      $totalAlvaras = BsGestaoAlvara::vigentes()->count();
      $alvarasVencendo30 = BsGestaoAlvara::vencendoEm(30)->count();
      $alvarasVencidos = BsGestaoAlvara::vencidos()->count();

      // Contratos vencidos
      $contratosVencidos = BsGestaoContrato::ativos()->vencidos()->count();

      // Equipamentos
      $equipTotal = BsGestaoEquipamento::count();
      $equipVencendo30 = BsGestaoEquipamento::vencendo(30)->count();
      $equipVencidos = BsGestaoEquipamento::vencidos()->count();
      $equipManutencao = BsGestaoEquipamento::emManutencao()->count();

      // Próximos vencimentos
      $proximosVencimentos = BsGestaoContrato::with(['filial', 'tipoIndice'])
        ->ativos()
        ->where('data_fim', '>=', Carbon::now())
        ->orderBy('data_fim')
        ->limit(10)
        ->get();

      $proximosAlvarasVencer = BsGestaoAlvara::with(['filial', 'tipoAlvara'])
        ->where('data_validade', '>=', Carbon::now())
        ->orderBy('data_validade')
        ->limit(10)
        ->get();

      $proximosEquipamentosVencer = BsGestaoEquipamento::with(['filial', 'tipoEquipamento'])
        ->vencendo(60)
        ->orderBy('data_validade')
        ->limit(10)
        ->get();

      return response()->json([
        'sucesso' => true,
        'dados' => [
          'contratos' => [
            'total_locacao' => $totalContratosLocacao,
            'total_servico' => $totalContratosServico,
            'vencendo_30_dias' => $contratosVencendo30,
            'vencendo_90_dias' => $contratosVencendo90,
            'vencidos' => $contratosVencidos,
            'valor_total_locacao' => $valorTotalLocacao,
            'valor_total_servico' => $valorTotalServico,
          ],
          'alvaras' => [
            'total' => $totalAlvaras,
            'vencendo_30_dias' => $alvarasVencendo30,
            'vencidos' => $alvarasVencidos,
          ],
          'equipamentos' => [
            'total' => $equipTotal,
            'vencendo_30_dias' => $equipVencendo30,
            'vencidos' => $equipVencidos,
            'em_manutencao' => $equipManutencao,
          ],
          'proximos_vencimentos' => $proximosVencimentos,
          'proximos_alvaras_vencer' => $proximosAlvarasVencer,
          'proximos_equipamentos_vencer' => $proximosEquipamentosVencer,
        ],
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter dashboard de gestão', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter dashboard',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         CONTRATOS
  // ═══════════════════════════════════════════════════════════════

  public function getContratos(Request $request)
  {
    try {
      $query = BsGestaoContrato::with(['filial', 'tipoIndice']);

      // Filtros
      if ($request->tipo) {
        $query->where('tipo', $request->tipo);
      }
      if ($request->status) {
        $query->where('status', $request->status);
      }
      if ($request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->busca) {
        $busca = $request->busca;
        $query->where(function ($q) use ($busca) {
          $q->where('razao_social_loja', 'like', "%{$busca}%")
            ->orWhere('nome_locador', 'like', "%{$busca}%")
            ->orWhere('imobiliaria', 'like', "%{$busca}%")
            ->orWhere('cnpj_loja', 'like', "%{$busca}%");
        });
      }

      // Ordenação
      $orderBy = $request->order_by ?? 'data_fim';
      $orderDir = $request->order_dir ?? 'asc';
      $query->orderBy($orderBy, $orderDir);

      // Paginação
      $perPage = $request->per_page ?? 15;
      $contratos = $query->paginate($perPage);

      return response()->json([
        'sucesso' => true,
        'dados' => $contratos,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter contratos', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter contratos',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function getContrato(Request $request, $id)
  {
    try {
      $contrato = BsGestaoContrato::with(['filial', 'tipoIndice', 'reajustes', 'anexos'])
        ->findOrFail($id);

      return response()->json([
        'sucesso' => true,
        'dados' => $contrato,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter contrato', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter contrato',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeContrato(Request $request)
  {
    try {
      $dados = $request->all();
      $dados['created_by'] = auth()->id();

      $contrato = BsGestaoContrato::create($dados);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Contrato criado com sucesso',
        'dados' => $contrato,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao criar contrato', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao criar contrato',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function updateContrato(Request $request, $id)
  {
    try {
      $contrato = BsGestaoContrato::findOrFail($id);

      $dados = $request->all();
      $dados['updated_by'] = auth()->id();

      $contrato->update($dados);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Contrato atualizado com sucesso',
        'dados' => $contrato,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao atualizar contrato', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar contrato',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteContrato(Request $request, $id)
  {
    try {
      $contrato = BsGestaoContrato::findOrFail($id);
      $contrato->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Contrato excluído com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir contrato', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir contrato',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         REAJUSTES
  // ═══════════════════════════════════════════════════════════════

  public function storeReajuste(Request $request, $contratoId)
  {
    try {
      $contrato = BsGestaoContrato::findOrFail($contratoId);

      $dados = $request->all();
      $dados['contrato_id'] = $contratoId;
      $dados['created_by'] = auth()->id();

      $reajuste = BsGestaoContratoReajuste::create($dados);

      // Atualiza valor do contrato
      if ($request->valor_reajustado) {
        $contrato->update([
          'valor_mensal' => $request->valor_reajustado,
          'data_proximo_reajuste' => Carbon::parse($request->data_reajuste)->addYear(),
        ]);
      }

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Reajuste registrado com sucesso',
        'dados' => $reajuste,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao registrar reajuste', [
        'contrato_id' => $contratoId,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao registrar reajuste',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         ANEXOS
  // ═══════════════════════════════════════════════════════════════

  public function uploadAnexo(Request $request, $contratoId)
  {
    try {
      $contrato = BsGestaoContrato::findOrFail($contratoId);

      if (! $request->hasFile('arquivo')) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Nenhum arquivo enviado',
        ], 400);
      }

      $arquivo = $request->file('arquivo');
      $path = $arquivo->store('gestao/contratos/' . $contratoId, 'public');

      $anexo = BsGestaoContratoAnexo::create([
        'contrato_id' => $contratoId,
        'tipo' => $request->tipo ?? 'DOCUMENTO',
        'nome_arquivo' => $arquivo->getClientOriginalName(),
        'caminho' => $path,
        'tamanho' => $arquivo->getSize(),
        'mime_type' => $arquivo->getMimeType(),
        'descricao' => $request->descricao,
        'created_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Anexo enviado com sucesso',
        'dados' => $anexo,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao enviar anexo', [
        'contrato_id' => $contratoId,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao enviar anexo',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteAnexo(Request $request, $id)
  {
    try {
      $anexo = BsGestaoContratoAnexo::findOrFail($id);

      // Remove arquivo do storage
      if (Storage::disk('public')->exists($anexo->caminho)) {
        Storage::disk('public')->delete($anexo->caminho);
      }

      $anexo->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Anexo excluído com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir anexo', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir anexo',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         ALVARÁS
  // ═══════════════════════════════════════════════════════════════

  public function getAlvaras(Request $request)
  {
    try {
      $query = BsGestaoAlvara::with(['filial', 'tipoAlvara']);

      // Filtros
      if ($request->status) {
        $query->where('status', $request->status);
      }
      if ($request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->tipo_alvara_id) {
        $query->where('tipo_alvara_id', $request->tipo_alvara_id);
      }
      if ($request->busca) {
        $busca = $request->busca;
        $query->where(function ($q) use ($busca) {
          $q->where('numero_documento', 'like', "%{$busca}%")
            ->orWhere('orgao_emissor', 'like', "%{$busca}%");
        });
      }

      // Ordenação
      $orderBy = $request->order_by ?? 'data_validade';
      $orderDir = $request->order_dir ?? 'asc';
      $query->orderBy($orderBy, $orderDir);

      // Paginação
      $perPage = $request->per_page ?? 15;
      $alvaras = $query->paginate($perPage);

      return response()->json([
        'sucesso' => true,
        'dados' => $alvaras,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter alvarás', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter alvarás',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function getAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::with(['filial', 'tipoAlvara'])
        ->findOrFail($id);

      return response()->json([
        'sucesso' => true,
        'dados' => $alvara,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter alvará',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeAlvara(Request $request)
  {
    try {
      $dados = $request->all();
      $dados['created_by'] = auth()->id();

      $alvara = BsGestaoAlvara::create($dados);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Alvará criado com sucesso',
        'dados' => $alvara,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao criar alvará', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao criar alvará',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function updateAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::findOrFail($id);

      $dados = $request->all();
      $dados['updated_by'] = auth()->id();

      $alvara->update($dados);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Alvará atualizado com sucesso',
        'dados' => $alvara,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao atualizar alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar alvará',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::findOrFail($id);
      $alvara->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Alvará excluído com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir alvará',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         DADOS AUXILIARES
  // ═══════════════════════════════════════════════════════════════

  public function getTiposIndice(Request $request)
  {
    try {
      $tipos = BsGestaoTipoIndice::where('ativo', true)->orderBy('nome')->get();

      return response()->json([
        'sucesso' => true,
        'dados' => $tipos,
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter tipos de índice',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function getTiposAlvara(Request $request)
  {
    try {
      $tipos = BsGestaoTipoAlvara::where('ativo', true)->orderBy('nome')->get();

      return response()->json([
        'sucesso' => true,
        'dados' => $tipos,
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter tipos de alvará',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function getFiliais(Request $request)
  {
    try {
      $filiais = Filial::orderBy('name')
        ->get()
        ->map(fn ($f) => [
          'codfilial' => $f->id,
          'filial' => $f->name,
          'fantasia' => $f->name,
          'cgc' => $f->cnpj,
        ]);

      return response()->json([
        'sucesso' => true,
        'dados' => $filiais,
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter filiais',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         EXPORTAÇÃO
  // ═══════════════════════════════════════════════════════════════

  public function exportarContratos(Request $request)
  {
    try {
      $tipo = $request->input('tipo', 'LOCACAO');

      $query = BsGestaoContrato::with(['filial', 'tipoIndice', 'reajustes'])
        ->selectRaw('bs_gestao_contratos.*, documento_locador as documento_locador_str')
        ->where('tipo', $tipo);

      // Filtros
      if ($request->has('filial_id') && $request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->has('status') && $request->status) {
        $query->where('status', $request->status);
      }
      if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
          $q->where('descricao', 'like', "%{$search}%")
            ->orWhere('nome_locador', 'like', "%{$search}%");
        });
      }

      $contratos = $query->orderBy('filial_id')->orderBy('data_fim')->get();

      // Definir colunas baseado no tipo
      if ($tipo === 'LOCACAO') {
        $colunas = [
          ['nome' => 'id', 'label' => 'ID', 'tipo' => 'numero'],
          ['nome' => 'filial_nome', 'label' => 'Filial', 'tipo' => 'texto'],
          ['nome' => 'descricao', 'label' => 'Descrição', 'tipo' => 'texto'],
          ['nome' => 'nome_locador', 'label' => 'Locador', 'tipo' => 'texto'],
          ['nome' => 'documento_locador', 'label' => 'CNPJ/CPF Locador', 'tipo' => 'texto'],
          ['nome' => 'endereco_imovel', 'label' => 'Endereço', 'tipo' => 'texto'],
          ['nome' => 'cidade', 'label' => 'Cidade', 'tipo' => 'texto'],
          ['nome' => 'estado', 'label' => 'UF', 'tipo' => 'texto'],
          ['nome' => 'cep', 'label' => 'CEP', 'tipo' => 'texto'],
          ['nome' => 'data_inicio', 'label' => 'Início', 'tipo' => 'data'],
          ['nome' => 'data_fim', 'label' => 'Vencimento', 'tipo' => 'data'],
          ['nome' => 'valor_mensal', 'label' => 'Valor Mensal', 'tipo' => 'moeda'],
          ['nome' => 'valor_atual', 'label' => 'Valor Atual', 'tipo' => 'moeda'],
          ['nome' => 'valor_iptu', 'label' => 'IPTU Anual', 'tipo' => 'moeda'],
          ['nome' => 'tipo_indice_nome', 'label' => 'Índice', 'tipo' => 'texto'],
          ['nome' => 'mes_reajuste', 'label' => 'Mês Reajuste', 'tipo' => 'numero'],
          ['nome' => 'status', 'label' => 'Status', 'tipo' => 'texto'],
        ];
        $nomeArquivo = 'contratos_locacao_';
      } else {
        $colunas = [
          ['nome' => 'id', 'label' => 'ID', 'tipo' => 'numero'],
          ['nome' => 'filial_nome', 'label' => 'Filial', 'tipo' => 'texto'],
          ['nome' => 'descricao_servico', 'label' => 'Descrição', 'tipo' => 'texto'],
          ['nome' => 'nome_locador', 'label' => 'Fornecedor', 'tipo' => 'texto'],
          ['nome' => 'documento_locador', 'label' => 'CNPJ Fornecedor', 'tipo' => 'texto'],
          ['nome' => 'data_inicio', 'label' => 'Início', 'tipo' => 'data'],
          ['nome' => 'data_fim', 'label' => 'Vencimento', 'tipo' => 'data'],
          ['nome' => 'valor_mensal', 'label' => 'Valor Mensal', 'tipo' => 'moeda'],
          ['nome' => 'valor_atual', 'label' => 'Valor Atual', 'tipo' => 'moeda'],
          ['nome' => 'tipo_indice_nome', 'label' => 'Índice', 'tipo' => 'texto'],
          ['nome' => 'mes_reajuste', 'label' => 'Mês Reajuste', 'tipo' => 'numero'],
          ['nome' => 'status', 'label' => 'Status', 'tipo' => 'texto'],
        ];
        $nomeArquivo = 'contratos_servico_';
      }

      // Helper para formatar CNPJ/CPF (evita notação científica no Excel)
      $formatarDocumento = function ($doc) {
        if (!$doc) return '-';
        $doc = preg_replace('/[^0-9]/', '', (string) $doc); // Remove tudo que não é número
        if (strlen($doc) === 14) {
          // CNPJ: 00.000.000/0000-00
          return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12, 2);
        } elseif (strlen($doc) === 11) {
          // CPF: 000.000.000-00
          return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
        }
        return $doc; // Retorna como está se não for CNPJ nem CPF
      };

      // Transformar dados para exportação
      $dados = $contratos->map(function ($contrato) use ($tipo, $formatarDocumento) {
        $base = [
          'id' => $contrato->id,
          'filial_nome' => $contrato->filial ? $contrato->filial_id . ' - ' . $contrato->filial->razaosocial : $contrato->filial_id,
        ];

        // Usa documento_locador_str que vem do TO_CHAR do Oracle (sem perda de precisão)
        $docLocador = $contrato->documento_locador_str ?? $contrato->documento_locador;

        if ($tipo === 'LOCACAO') {
          return array_merge($base, [
            'descricao' => $contrato->descricao ?? '-',
            'nome_locador' => $contrato->nome_locador ?? '-',
            'documento_locador' => $formatarDocumento($docLocador),
            'endereco_imovel' => $contrato->endereco_imovel ?? '-',
            'cidade' => $contrato->cidade ?? '-',
            'estado' => $contrato->estado ?? '-',
            'cep' => $contrato->cep ?? '-',
            'data_inicio' => $contrato->data_inicio?->format('d/m/Y') ?? '-',
            'data_fim' => $contrato->data_fim?->format('d/m/Y') ?? '-',
            'valor_mensal' => $contrato->valor_mensal ? 'R$ ' . number_format($contrato->valor_mensal, 2, ',', '.') : '-',
            'valor_atual' => $contrato->valor_atual ? 'R$ ' . number_format($contrato->valor_atual, 2, ',', '.') : '-',
            'valor_iptu' => $contrato->valor_iptu ? 'R$ ' . number_format($contrato->valor_iptu, 2, ',', '.') : '-',
            'tipo_indice_nome' => $contrato->tipoIndice?->nome ?? '-',
            'mes_reajuste' => $contrato->mes_reajuste ?? '-',
            'status' => $contrato->status ?? '-',
          ]);
        } else {
          // SERVICO
          return array_merge($base, [
            'descricao_servico' => $contrato->descricao_servico ?? '-',
            'nome_locador' => $contrato->nome_locador ?? '-',
            'documento_locador' => $formatarDocumento($docLocador),
            'data_inicio' => $contrato->data_inicio?->format('d/m/Y') ?? '-',
            'data_fim' => $contrato->data_fim?->format('d/m/Y') ?? '-',
            'valor_mensal' => $contrato->valor_mensal ? 'R$ ' . number_format($contrato->valor_mensal, 2, ',', '.') : '-',
            'valor_atual' => $contrato->valor_atual ? 'R$ ' . number_format($contrato->valor_atual, 2, ',', '.') : '-',
            'tipo_indice_nome' => $contrato->tipoIndice?->nome ?? '-',
            'mes_reajuste' => $contrato->mes_reajuste ?? '-',
            'status' => $contrato->status ?? '-',
          ]);
        }
      })->toArray();

      return app(\App\Services\UtilService::class)->exportToExcel($dados, $colunas, $nomeArquivo);
    } catch (\Throwable $th) {
      Log::error('Erro ao exportar contratos: ' . $th->getMessage(), [
        'trace' => $th->getTraceAsString(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao exportar contratos: ' . $th->getMessage(),
      ], 500);
    }
  }

  public function exportarAlvaras(Request $request)
  {
    try {
      $query = BsGestaoAlvara::with(['filial', 'tipoAlvara']);

      // Filtros
      if ($request->has('filial_id') && $request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->has('status') && $request->status) {
        $query->where('status', $request->status);
      }
      if ($request->has('tipo_alvara_id') && $request->tipo_alvara_id) {
        $query->where('tipo_alvara_id', $request->tipo_alvara_id);
      }
      if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
          $q->where('numero_documento', 'like', "%{$search}%")
            ->orWhere('descricao', 'like', "%{$search}%")
            ->orWhere('orgao_emissor', 'like', "%{$search}%");
        });
      }

      $alvaras = $query->orderBy('filial_id')->orderBy('data_validade')->get();

      $colunas = [
        ['nome' => 'id', 'label' => 'ID', 'tipo' => 'numero'],
        ['nome' => 'filial_nome', 'label' => 'Filial', 'tipo' => 'texto'],
        ['nome' => 'tipo_alvara_nome', 'label' => 'Tipo', 'tipo' => 'texto'],
        ['nome' => 'numero_documento', 'label' => 'Nº Documento', 'tipo' => 'texto'],
        ['nome' => 'descricao', 'label' => 'Descrição', 'tipo' => 'texto'],
        ['nome' => 'orgao_emissor', 'label' => 'Órgão Emissor', 'tipo' => 'texto'],
        ['nome' => 'data_emissao', 'label' => 'Emissão', 'tipo' => 'data'],
        ['nome' => 'data_validade', 'label' => 'Validade', 'tipo' => 'data'],
        ['nome' => 'custo_renovacao', 'label' => 'Custo Renovação', 'tipo' => 'moeda'],
        ['nome' => 'responsavel_nome', 'label' => 'Responsável', 'tipo' => 'texto'],
        ['nome' => 'responsavel_email', 'label' => 'Email', 'tipo' => 'texto'],
        ['nome' => 'responsavel_telefone', 'label' => 'Telefone', 'tipo' => 'texto'],
        ['nome' => 'status', 'label' => 'Status', 'tipo' => 'texto'],
      ];

      // Transformar dados para exportação
      $dados = $alvaras->map(function ($alvara) {
        return [
          'id' => $alvara->id,
          'filial_nome' => $alvara->filial ? $alvara->filial_id . ' - ' . $alvara->filial->razaosocial : $alvara->filial_id,
          'tipo_alvara_nome' => $alvara->tipoAlvara?->descricao ?? '-',
          'numero_documento' => $alvara->numero_documento ?? '-',
          'descricao' => $alvara->descricao ?? '-',
          'orgao_emissor' => $alvara->orgao_emissor ?? '-',
          'data_emissao' => $alvara->data_emissao?->format('d/m/Y') ?? '-',
          'data_validade' => $alvara->data_validade?->format('d/m/Y') ?? '-',
          'custo_renovacao' => $alvara->custo_renovacao ? 'R$ ' . number_format($alvara->custo_renovacao, 2, ',', '.') : '-',
          'responsavel_nome' => $alvara->responsavel_renovacao ?? '-',
          'responsavel_email' => $alvara->responsavel_email ?? '-',
          'responsavel_telefone' => $alvara->responsavel_telefone ?? '-',
          'status' => $alvara->status ?? '-',
        ];
      })->toArray();

      return app(\App\Services\UtilService::class)->exportToExcel($dados, $colunas, 'alvaras_');
    } catch (\Throwable $th) {
      Log::error('Erro ao exportar alvarás: ' . $th->getMessage(), [
        'trace' => $th->getTraceAsString(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao exportar alvarás: ' . $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                    ANEXOS DE ALVARÁS
  // ═══════════════════════════════════════════════════════════════

  public function uploadAnexoAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::findOrFail($id);

      if (! $request->hasFile('arquivo')) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Nenhum arquivo enviado',
        ], 400);
      }

      // Remove arquivo anterior se existir
      if ($alvara->arquivo_path && Storage::disk('public')->exists($alvara->arquivo_path)) {
        Storage::disk('public')->delete($alvara->arquivo_path);
      }

      $arquivo = $request->file('arquivo');
      $path = $arquivo->store('gestao/alvaras/' . $id, 'public');

      $alvara->update([
        'arquivo_path' => $path,
        'arquivo_nome' => $arquivo->getClientOriginalName(),
        'updated_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Anexo enviado com sucesso',
        'dados' => [
          'arquivo_path' => $path,
          'arquivo_nome' => $arquivo->getClientOriginalName(),
        ],
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao enviar anexo do alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao enviar anexo',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteAnexoAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::findOrFail($id);

      if ($alvara->arquivo_path && Storage::disk('public')->exists($alvara->arquivo_path)) {
        Storage::disk('public')->delete($alvara->arquivo_path);
      }

      $alvara->update([
        'arquivo_path' => null,
        'arquivo_nome' => null,
        'updated_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Anexo removido com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao remover anexo do alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao remover anexo',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function downloadAnexoAlvara(Request $request, $id)
  {
    try {
      $alvara = BsGestaoAlvara::findOrFail($id);

      if (! $alvara->arquivo_path || ! Storage::disk('public')->exists($alvara->arquivo_path)) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Arquivo não encontrado',
        ], 404);
      }

      $nomeArquivo = $alvara->arquivo_nome ?? basename($alvara->arquivo_path);
      $caminhoCompleto = Storage::disk('public')->path($alvara->arquivo_path);

      return response()->download($caminhoCompleto, $nomeArquivo);
    } catch (\Throwable $th) {
      Log::error('Erro ao baixar anexo do alvará', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao baixar anexo',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }
}
