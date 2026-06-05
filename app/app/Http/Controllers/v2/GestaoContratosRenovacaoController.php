<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\v2\BsGestaoContrato;
use App\Models\v2\GestaoContratoRenovacao;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GestaoContratosRenovacaoController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    //               LISTAGEM DE RENOVAÇÕES
    // ═══════════════════════════════════════════════════════════════

  /**
   * Lista renovações com filtros.
   */
  public function index(Request $request)
  {
    try {
      $query = GestaoContratoRenovacao::with(['contrato.filial']);

      if ($request->contrato_id) {
        $query->where('contrato_id', $request->contrato_id);
      }
      if ($request->status) {
        $query->where('status', $request->status);
      }
      if ($request->dentro_divergencia !== null) {
        $query->where('dentro_divergencia', $request->dentro_divergencia);
      }

      $query->orderBy('data_renovacao', 'desc');

      $perPage = $request->per_page ?? 15;
      $renovacoes = $query->paginate($perPage);

      return response()->json([
        'sucesso' => true,
        'dados' => $renovacoes,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao listar renovações', ['error' => $th->getMessage()]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao listar renovações',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  /**
   * Retorna dados para a tela de renovação de um contrato.
   * Verifica divergência e retorna informações necessárias.
   */
  public function prepararRenovacao(Request $request, $contratoId)
  {
    try {
      $contrato = BsGestaoContrato::with(['filial', 'renovacoes'])->findOrFail($contratoId);

      // Verificar se o contrato tem vínculo com compras (é recorrente)
      if (!$contrato->id_solicitacao_compras) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Este contrato não é recorrente (sem vínculo com Compras)',
        ], 400);
      }

      // Informações de divergência
      $percentualLimite = $contrato->percentual_divergencia ?? 10;
      $valorAtual = $contrato->valor_mensal;
      $limiteMaximo = round($valorAtual * (1 + $percentualLimite / 100), 2);

      return response()->json([
        'sucesso' => true,
        'dados' => [
          'contrato' => $contrato,
          'regra_divergencia' => [
            'valor_atual' => $valorAtual,
            'percentual_limite' => $percentualLimite,
            'valor_maximo_renovacao' => $limiteMaximo,
          ],
          'historico_renovacoes' => $contrato->renovacoes,
        ],
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao preparar renovação', [
        'contrato_id' => $contratoId,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao preparar renovação',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

    // ═══════════════════════════════════════════════════════════════
    //                  EXECUTAR RENOVAÇÃO
    // ═══════════════════════════════════════════════════════════════

  /**
   * Executa renovação do contrato.
   * Ponto 4: Só renova dentro da divergência. Fora → nova solicitação de compras.
   */
  public function renovar(Request $request, $contratoId)
  {
    try {
      DB::beginTransaction();

      $contrato = BsGestaoContrato::findOrFail($contratoId);
      $matricula = auth()->id();

      if (!$contrato->id_solicitacao_compras) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Este contrato não é recorrente',
        ], 400);
      }

      $valorNovo = $request->valor_novo;
      $novaDataInicio = $request->nova_data_inicio;
      $novaDataFim = $request->nova_data_fim;
      $valorAnterior = $contrato->valor_mensal;
      $percentualLimite = $contrato->percentual_divergencia ?? 10;

      // Verificar divergência
      $resultado = GestaoContratoRenovacao::verificarDivergencia(
        $valorAnterior,
        $valorNovo,
        $percentualLimite
      );

      if (!$resultado['dentro']) {
        // Fora da divergência → registra com status PENDENTE_COMPRAS
        $renovacao = GestaoContratoRenovacao::create([
          'contrato_id' => $contratoId,
          'data_renovacao' => Carbon::now(),
          'nova_data_inicio' => $novaDataInicio,
          'nova_data_fim' => $novaDataFim,
          'valor_anterior' => $valorAnterior,
          'valor_novo' => $valorNovo,
          'percentual_variacao' => $resultado['percentual'],
          'percentual_divergencia_limite' => $percentualLimite,
          'dentro_divergencia' => false,
          'status' => 'PENDENTE_COMPRAS',
          'observacoes' => $request->observacoes,
          'created_by' => $matricula,
        ]);

        DB::commit();

        return response()->json([
          'sucesso' => false,
          'mensagem' => "⚠️ Renovação FORA da divergência permitida ({$resultado['percentual']}% vs limite de {$percentualLimite}%). É necessário criar uma nova solicitação de compras para aprovação.",
          'dados' => [
            'renovacao' => $renovacao,
            'necessita_compras' => true,
            'percentual_variacao' => $resultado['percentual'],
            'limite' => $percentualLimite,
            'valor_maximo' => $resultado['limite_valor'],
          ],
        ], 422);
      }

      // Dentro da divergência → renova diretamente
      $renovacao = GestaoContratoRenovacao::create([
        'contrato_id' => $contratoId,
        'data_renovacao' => Carbon::now(),
        'nova_data_inicio' => $novaDataInicio,
        'nova_data_fim' => $novaDataFim,
        'valor_anterior' => $valorAnterior,
        'valor_novo' => $valorNovo,
        'percentual_variacao' => $resultado['percentual'],
        'percentual_divergencia_limite' => $percentualLimite,
        'dentro_divergencia' => true,
        'status' => 'APROVADA',
        'observacoes' => $request->observacoes,
        'created_by' => $matricula,
      ]);

      // Atualizar contrato com novos valores
      $contrato->update([
        'valor_mensal' => $valorNovo,
        'data_inicio' => $novaDataInicio,
        'data_fim' => $novaDataFim,
        'provisao_mensal' => $valorNovo,
        'updated_by' => $matricula,
      ]);

      DB::commit();

      return response()->json([
        'sucesso' => true,
        'mensagem' => "✅ Contrato renovado com sucesso! Variação de {$resultado['percentual']}% (dentro do limite de {$percentualLimite}%)",
        'dados' => [
          'renovacao' => $renovacao,
          'contrato' => $contrato->fresh(['filial']),
        ],
      ]);
    } catch (\Throwable $th) {
      DB::rollBack();
      Log::error('Erro ao renovar contrato', [
        'contrato_id' => $contratoId,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao renovar contrato',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

    // ═══════════════════════════════════════════════════════════════
    //           CONTRATOS QUE PRECISAM RENOVAR
    // ═══════════════════════════════════════════════════════════════

  /**
   * Lista contratos que precisam de renovação (vencendo nos próximos X dias).
   */
  public function contratosParaRenovar(Request $request)
  {
    try {
      $dias = (int) ($request->dias ?? 60);

      $contratos = BsGestaoContrato::with(['filial', 'renovacoes' => function ($q) {
        $q->latest('data_renovacao')->limit(1);
      }])
        ->ativos()
        ->comRecorrencia()
        ->where('data_fim', '<=', Carbon::now()->addDays($dias))
        ->where('data_fim', '>=', Carbon::now())
        ->orderBy('data_fim')
        ->get()
        ->map(function ($contrato) {
          $contrato->limite_renovacao = round(
            $contrato->valor_mensal * (1 + ($contrato->percentual_divergencia ?? 10) / 100),
            2
          );
          return $contrato;
        });

      return response()->json([
        'sucesso' => true,
        'dados' => $contratos,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao listar contratos para renovar', ['error' => $th->getMessage()]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao listar contratos para renovação',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }
}
