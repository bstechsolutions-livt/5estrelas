<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Filial;
use App\Models\v2\BsGestaoEquipamento;
use App\Models\v2\BsGestaoEquipamentoFoto;
use App\Models\v2\BsGestaoEquipamentoOcorrencia;
use App\Models\v2\BsGestaoEquipamentoTratativa;
use App\Models\v2\BsGestaoTipoEquipamento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GestaoEquipamentosController extends Controller
{
  // ═══════════════════════════════════════════════════════════════
  //                    TIPOS DE EQUIPAMENTO
  // ═══════════════════════════════════════════════════════════════

  public function getTiposEquipamento()
  {
    try {
      $tipos = BsGestaoTipoEquipamento::where('ativo', true)
        ->orderBy('nome')
        ->get();

      return response()->json([
        'sucesso' => true,
        'dados' => $tipos,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter tipos de equipamento', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter tipos de equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeTipoEquipamento(Request $request)
  {
    try {
      $request->validate([
        'nome' => 'required|string|max:255',
      ]);

      $tipo = BsGestaoTipoEquipamento::create($request->all());

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Tipo de equipamento criado com sucesso',
        'dados' => $tipo,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao criar tipo de equipamento', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao criar tipo de equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function updateTipoEquipamento(Request $request, $id)
  {
    try {
      $tipo = BsGestaoTipoEquipamento::findOrFail($id);
      $tipo->update($request->all());

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Tipo de equipamento atualizado com sucesso',
        'dados' => $tipo,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao atualizar tipo de equipamento', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar tipo de equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteTipoEquipamento($id)
  {
    try {
      $tipo = BsGestaoTipoEquipamento::findOrFail($id);

      $countEquipamentos = BsGestaoEquipamento::where('tipo_equipamento_id', $id)->count();

      if ($countEquipamentos > 0) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => "Não é possível excluir: existem {$countEquipamentos} equipamentos vinculados a este tipo",
        ], 409);
      }

      $tipo->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Tipo de equipamento excluído com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir tipo de equipamento', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir tipo de equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                       EQUIPAMENTOS
  // ═══════════════════════════════════════════════════════════════

  public function getEquipamentos(Request $request)
  {
    try {
      $query = BsGestaoEquipamento::with([
        'filial',
        'tipoEquipamento',
        'fotos',
      ]);

      // Filtros
      if ($request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->tipo_equipamento_id) {
        $query->where('tipo_equipamento_id', $request->tipo_equipamento_id);
      }
      if ($request->status) {
        if ($request->status === 'VENCIDO') {
          $query->vencidos();
        } elseif ($request->status === 'VENCENDO') {
          $query->vencendo();
        } elseif ($request->status === 'EM_MANUTENCAO') {
          $query->emManutencao();
        } elseif ($request->status === 'VIGENTE') {
          $query->vigentes();
        }
      }
      if ($request->busca) {
        $busca = $request->busca;
        $query->where(function ($q) use ($busca) {
          $q->where('numero_identificacao', 'like', "%{$busca}%")
            ->orWhere('carga', 'like', "%{$busca}%")
            ->orWhere('localizacao', 'like', "%{$busca}%");
        });
      }

      $query->orderBy('data_validade', 'asc');

      $equipamentos = $query->paginate(20);

      return response()->json([
        'sucesso' => true,
        'dados' => $equipamentos,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter equipamentos', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter equipamentos',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function getEquipamento($id)
  {
    try {
      $equipamento = BsGestaoEquipamento::with([
        'filial',
        'tipoEquipamento',
        'ocorrencias' => fn ($q) => $q->orderBy('data_ocorrencia', 'desc'),
        'tratativas' => fn ($q) => $q->orderBy('data_registro', 'desc'),
        'fotos',
      ])->findOrFail($id);

      return response()->json([
        'sucesso' => true,
        'dados' => $equipamento,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter equipamento', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeEquipamento(Request $request)
  {
    try {
      $request->validate([
        'filial_id' => 'required',
        'tipo_equipamento_id' => 'required',
        'data_validade' => 'required|date',
        'status' => 'required|string',
      ]);

      // Validar que o tipo está ativo
      $tipo = BsGestaoTipoEquipamento::findOrFail($request->tipo_equipamento_id);
      if (! $tipo->ativo) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'O tipo de equipamento selecionado está inativo',
          'erros' => ['tipo_equipamento_id' => ['O tipo de equipamento selecionado está inativo']],
        ], 422);
      }

      $dados = $request->all();
      $dados['created_by'] = auth()->id();

      $equipamento = BsGestaoEquipamento::create($dados);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Equipamento cadastrado com sucesso',
        'dados' => $equipamento,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao criar equipamento', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao criar equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function updateEquipamento(Request $request, $id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $dados = $request->all();
      $dados['updated_by'] = auth()->id();

      // Verificar se data_validade mudou para registrar histórico
      $dataValidadeAnterior = $equipamento->getOriginal('data_validade');
      $dataValidadeNova = $request->data_validade ?? null;

      $equipamento->update($dados);

      // Inserir histórico se data_validade mudou
      if ($dataValidadeNova && $dataValidadeAnterior != $dataValidadeNova) {
        DB::table('bs_gestao_equipamento_hist_validade')->insert([
          'equipamento_id' => $equipamento->id,
          'data_validade_anterior' => $dataValidadeAnterior,
          'data_validade_nova' => $dataValidadeNova,
          'status_anterior' => $equipamento->getOriginal('status'),
          'status_novo' => $request->status ?? $equipamento->status,
          'alterado_por' => auth()->id(),
          'alterado_em' => now(),
        ]);
      }

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Equipamento atualizado com sucesso',
        'dados' => $equipamento,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao atualizar equipamento', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteEquipamento($id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);
      $equipamento->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Equipamento excluído com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir equipamento', [
        'id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir equipamento',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                       EXPORTAÇÃO
  // ═══════════════════════════════════════════════════════════════

  public function exportarEquipamentos(Request $request)
  {
    try {
      $query = BsGestaoEquipamento::with([
        'filial',
        'tipoEquipamento',
      ]);

      // Filtros (mesmos do getEquipamentos)
      if ($request->filial_id) {
        $query->where('filial_id', $request->filial_id);
      }
      if ($request->tipo_equipamento_id) {
        $query->where('tipo_equipamento_id', $request->tipo_equipamento_id);
      }
      if ($request->status) {
        if ($request->status === 'VENCIDO') {
          $query->vencidos();
        } elseif ($request->status === 'VENCENDO') {
          $query->vencendo();
        } elseif ($request->status === 'EM_MANUTENCAO') {
          $query->emManutencao();
        } elseif ($request->status === 'VIGENTE') {
          $query->vigentes();
        }
      }
      if ($request->busca) {
        $busca = $request->busca;
        $query->where(function ($q) use ($busca) {
          $q->where('numero_identificacao', 'like', "%{$busca}%")
            ->orWhere('carga', 'like', "%{$busca}%")
            ->orWhere('localizacao', 'like', "%{$busca}%");
        });
      }

      $equipamentos = $query->orderBy('data_validade', 'asc')->get();

      $colunas = [
        ['nome' => 'filial', 'label' => 'Filial', 'tipo' => 'texto'],
        ['nome' => 'razao_social', 'label' => 'Razão Social', 'tipo' => 'texto'],
        ['nome' => 'tipo', 'label' => 'Tipo', 'tipo' => 'texto'],
        ['nome' => 'numero_identificacao', 'label' => 'Nº Identificação', 'tipo' => 'texto'],
        ['nome' => 'carga', 'label' => 'Carga', 'tipo' => 'texto'],
        ['nome' => 'peso_kg', 'label' => 'Peso (kg)', 'tipo' => 'numero'],
        ['nome' => 'qtd_projeto', 'label' => 'Qtd Projeto', 'tipo' => 'numero'],
        ['nome' => 'localizacao', 'label' => 'Localização', 'tipo' => 'texto'],
        ['nome' => 'vencimento', 'label' => 'Vencimento', 'tipo' => 'data'],
        ['nome' => 'status', 'label' => 'Status', 'tipo' => 'texto'],
        ['nome' => 'ultima_tratativa', 'label' => 'Última Tratativa', 'tipo' => 'texto'],
      ];

      $dados = $equipamentos->map(function ($equip) {
        return [
          'filial' => $equip->filial ? $equip->filial_id . ' - ' . $equip->filial->fantasia : $equip->filial_id,
          'razao_social' => $equip->filial?->razaosocial ?? '-',
          'tipo' => $equip->tipoEquipamento?->nome ?? '-',
          'numero_identificacao' => $equip->numero_identificacao ?? '-',
          'carga' => $equip->carga ?? '-',
          'peso_kg' => $equip->peso_kg ?? '-',
          'qtd_projeto' => $equip->qtd_projeto ?? '-',
          'localizacao' => $equip->localizacao ?? '-',
          'vencimento' => $equip->data_validade?->format('d/m/Y') ?? '-',
          'status' => $equip->status_computado ?? '-',
          'ultima_tratativa' => $equip->ultima_tratativa ?? '-',
        ];
      })->toArray();

      return app(\App\Services\UtilService::class)->exportToExcel($dados, $colunas, 'equipamentos_');
    } catch (\Throwable $th) {
      Log::error('Erro ao exportar equipamentos: ' . $th->getMessage(), [
        'trace' => $th->getTraceAsString(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao exportar equipamentos: ' . $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                       TRATATIVAS
  // ═══════════════════════════════════════════════════════════════

  public function getTratativas($id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $tratativas = BsGestaoEquipamentoTratativa::where('equipamento_id', $id)
        ->orderBy('data_registro', 'desc')
        ->get();

      return response()->json([
        'sucesso' => true,
        'dados' => $tratativas,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter tratativas', [
        'equipamento_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter tratativas',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeTratativa(Request $request, $id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $request->validate([
        'descricao' => 'required|string',
        'data_registro' => 'required|date',
      ]);

      $tratativa = BsGestaoEquipamentoTratativa::create([
        'equipamento_id' => $id,
        'descricao' => $request->descricao,
        'data_registro' => $request->data_registro,
        'created_by' => auth()->id(),
        'created_by_nome' => auth()->user()?->name,
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Tratativa registrada com sucesso',
        'dados' => $tratativa,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao registrar tratativa', [
        'equipamento_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao registrar tratativa',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                       OCORRÊNCIAS
  // ═══════════════════════════════════════════════════════════════

  public function getOcorrencias($id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $ocorrencias = BsGestaoEquipamentoOcorrencia::with('fotos')
        ->where('equipamento_id', $id)
        ->orderBy('data_ocorrencia', 'desc')
        ->get();

      return response()->json([
        'sucesso' => true,
        'dados' => $ocorrencias,
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter ocorrências', [
        'equipamento_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter ocorrências',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function storeOcorrencia(Request $request, $id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $request->validate([
        'tipo_ocorrencia' => 'required|string',
        'descricao' => 'required|string',
        'data_ocorrencia' => 'required|date',
      ]);

      // Validar que tipo_ocorrencia é um dos tipos válidos
      if (! array_key_exists($request->tipo_ocorrencia, BsGestaoEquipamentoOcorrencia::TIPOS)) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Tipo de ocorrência inválido',
          'erros' => ['tipo_ocorrencia' => ['Tipo de ocorrência inválido']],
        ], 422);
      }

      // Se tipo é OUTRO, exigir tipo_ocorrencia_descricao
      if ($request->tipo_ocorrencia === BsGestaoEquipamentoOcorrencia::TIPO_OUTRO) {
        if (! $request->tipo_ocorrencia_descricao) {
          return response()->json([
            'sucesso' => false,
            'mensagem' => 'A descrição do tipo é obrigatória quando o tipo é "Outro"',
            'erros' => ['tipo_ocorrencia_descricao' => ['A descrição do tipo é obrigatória quando o tipo é "Outro"']],
          ], 422);
        }
      }

      $ocorrencia = BsGestaoEquipamentoOcorrencia::create([
        'equipamento_id' => $id,
        'tipo_ocorrencia' => $request->tipo_ocorrencia,
        'tipo_ocorrencia_descricao' => $request->tipo_ocorrencia_descricao,
        'descricao' => $request->descricao,
        'data_ocorrencia' => $request->data_ocorrencia,
        'created_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Ocorrência registrada com sucesso',
        'dados' => $ocorrencia,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao registrar ocorrência', [
        'equipamento_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao registrar ocorrência',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                         FOTOS
  // ═══════════════════════════════════════════════════════════════

  public function uploadFoto(Request $request, $id)
  {
    try {
      $equipamento = BsGestaoEquipamento::findOrFail($id);

      $request->validate([
        'arquivo' => 'required|file|max:10240|mimes:jpg,jpeg,png',
      ]);

      // Verificar limite de 5 fotos
      $countFotos = BsGestaoEquipamentoFoto::where('fotoable_type', BsGestaoEquipamento::class)
        ->where('fotoable_id', $id)
        ->count();

      if ($countFotos >= 5) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Limite de 5 fotos atingido para este equipamento',
        ], 422);
      }

      $file = $request->file('arquivo');
      $extension = $file->getClientOriginalExtension();
      $fileName = Str::uuid() . '.' . $extension;
      $path = "gestao/equipamentos/{$id}/fotos/{$fileName}";

      Storage::disk('public')->put($path, file_get_contents($file));

      $foto = BsGestaoEquipamentoFoto::create([
        'fotoable_type' => BsGestaoEquipamento::class,
        'fotoable_id' => $id,
        'arquivo_path' => $path,
        'arquivo_nome' => $file->getClientOriginalName(),
        'arquivo_tamanho' => $file->getSize(),
        'arquivo_mime' => $file->getMimeType(),
        'created_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Foto enviada com sucesso',
        'dados' => $foto,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao fazer upload de foto do equipamento', [
        'equipamento_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao fazer upload de foto',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function uploadFotoOcorrencia(Request $request, $id)
  {
    try {
      $ocorrencia = BsGestaoEquipamentoOcorrencia::findOrFail($id);

      $request->validate([
        'arquivo' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf',
      ]);

      // Verificar limite de 5 fotos
      $countFotos = BsGestaoEquipamentoFoto::where('fotoable_type', BsGestaoEquipamentoOcorrencia::class)
        ->where('fotoable_id', $id)
        ->count();

      if ($countFotos >= 5) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Limite de 5 fotos atingido para esta ocorrência',
        ], 422);
      }

      $file = $request->file('arquivo');
      $extension = $file->getClientOriginalExtension();
      $fileName = Str::uuid() . '.' . $extension;
      $path = "gestao/equipamentos/ocorrencias/{$id}/fotos/{$fileName}";

      Storage::disk('public')->put($path, file_get_contents($file));

      $foto = BsGestaoEquipamentoFoto::create([
        'fotoable_type' => BsGestaoEquipamentoOcorrencia::class,
        'fotoable_id' => $id,
        'arquivo_path' => $path,
        'arquivo_nome' => $file->getClientOriginalName(),
        'arquivo_tamanho' => $file->getSize(),
        'arquivo_mime' => $file->getMimeType(),
        'created_by' => auth()->id(),
      ]);

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Foto enviada com sucesso',
        'dados' => $foto,
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro de validação',
        'erros' => $e->errors(),
      ], 422);
    } catch (\Throwable $th) {
      Log::error('Erro ao fazer upload de foto da ocorrência', [
        'ocorrencia_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao fazer upload de foto',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function deleteFoto($id)
  {
    try {
      $foto = BsGestaoEquipamentoFoto::findOrFail($id);

      // Remover arquivo do storage
      if (Storage::disk('public')->exists($foto->arquivo_path)) {
        Storage::disk('public')->delete($foto->arquivo_path);
      }

      $foto->delete();

      return response()->json([
        'sucesso' => true,
        'mensagem' => 'Foto excluída com sucesso',
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao excluir foto', [
        'foto_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao excluir foto',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  public function downloadFoto($id)
  {
    try {
      $foto = BsGestaoEquipamentoFoto::findOrFail($id);

      if (! Storage::disk('public')->exists($foto->arquivo_path)) {
        return response()->json([
          'sucesso' => false,
          'mensagem' => 'Arquivo não encontrado',
        ], 404);
      }

      return response()->download(Storage::disk('public')->path($foto->arquivo_path), $foto->arquivo_nome);
    } catch (\Throwable $th) {
      Log::error('Erro ao fazer download de foto', [
        'foto_id' => $id,
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao fazer download de foto',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //                        DASHBOARD
  // ═══════════════════════════════════════════════════════════════

  public function getDashboard(Request $request)
  {
    try {
      $total = BsGestaoEquipamento::count();
      $vencidos = BsGestaoEquipamento::vencidos()->count();
      $vencendo = BsGestaoEquipamento::vencendo()->count();
      $emManutencao = BsGestaoEquipamento::emManutencao()->count();

      $proximosAVencer = BsGestaoEquipamento::with([
        'filial',
        'tipoEquipamento',
      ])
        ->where('data_validade', '>=', Carbon::now())
        ->where('status', '!=', 'EM_MANUTENCAO')
        ->orderBy('data_validade', 'asc')
        ->limit(10)
        ->get();

      // Resumo por filial
      $resumoPorFilial = BsGestaoEquipamento::select('filial_id')
        ->selectRaw('COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN data_validade < CURRENT_DATE AND status != 'EM_MANUTENCAO' THEN 1 ELSE 0 END) as vencidos")
        ->selectRaw("SUM(CASE WHEN data_validade BETWEEN CURRENT_DATE AND CURRENT_DATE + 10 AND status != 'EM_MANUTENCAO' THEN 1 ELSE 0 END) as vencendo")
        ->selectRaw("SUM(CASE WHEN data_validade > CURRENT_DATE + 10 AND status != 'EM_MANUTENCAO' THEN 1 ELSE 0 END) as vigentes")
        ->selectRaw("SUM(CASE WHEN status = 'EM_MANUTENCAO' THEN 1 ELSE 0 END) as em_manutencao")
        ->groupBy('filial_id')
        ->get()
        ->map(function ($item) {
          $filial = Filial::find($item->filial_id);

          return [
            'filial_id' => $item->filial_id,
            'filial_nome' => $filial ? $item->filial_id . ' - ' . ($filial->fantasia ?? $filial->razaosocial) : $item->filial_id,
            'total' => (int) $item->total,
            'vencidos' => (int) $item->vencidos,
            'vencendo' => (int) $item->vencendo,
            'vigentes' => (int) $item->vigentes,
            'em_manutencao' => (int) $item->em_manutencao,
          ];
        });

      return response()->json([
        'sucesso' => true,
        'dados' => [
          'total' => $total,
          'vencidos' => $vencidos,
          'vencendo' => $vencendo,
          'em_manutencao' => $emManutencao,
          'proximos_a_vencer' => $proximosAVencer,
          'resumo_por_filial' => $resumoPorFilial,
        ],
      ]);
    } catch (\Throwable $th) {
      Log::error('Erro ao obter dashboard de equipamentos', [
        'error' => $th->getMessage(),
      ]);

      return response()->json([
        'sucesso' => false,
        'mensagem' => 'Erro ao obter dashboard de equipamentos',
        'dados' => $th->getMessage(),
      ], 500);
    }
  }
}
