<?php

/**
 * Remove dados de TESTE inseridos para validação (Financeiro + Gestão de Contratos).
 *
 * Não é só seeder/mock: apaga TUDO que foi cadastrado para testar o fluxo,
 * para o cliente começar com base limpa e dados reais (Senior, importação, etc.).
 *
 * Financeiro (--financeiro):
 *   - Todos os títulos, borderôs, etapas de aprovação, anexos, comentários, rateios
 *   - Todas as importações OFX / conciliação
 *   - Mantém: alçada (payable_roles), filiais, usuários, fluxos configurados
 *
 * Contratos (--contratos):
 *   - Contratos, alvarás, equipamentos, anexos, reajustes
 *   - Mantém: tipos de índice, tipos de alvará, tipos de equipamento
 *
 * Uso:
 *   php infra/scripts/reset-demo-data.php --dry-run --all
 *   php infra/scripts/reset-demo-data.php --financeiro
 *   php infra/scripts/reset-demo-data.php --contratos
 *   php infra/scripts/reset-demo-data.php --all
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApprovalStep;
use App\Models\BankStatementImport;
use App\Models\Bordero;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Models\PayableRateio;
use App\Models\v2\BsGestaoAlvara;
use App\Models\v2\BsGestaoContrato;
use App\Models\v2\BsGestaoContratoAnexo;
use App\Models\v2\BsGestaoContratoReajuste;
use App\Models\v2\BsGestaoEquipamento;
use App\Models\v2\BsGestaoEquipamentoFoto;
use App\Models\v2\BsGestaoEquipamentoOcorrencia;
use App\Models\v2\BsGestaoEquipamentoTratativa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$dryRun = in_array('--dry-run', $argv, true);
$all = in_array('--all', $argv, true);
$financeiro = $all || in_array('--financeiro', $argv, true);
$contratos = $all || in_array('--contratos', $argv, true);

if (! $financeiro && ! $contratos) {
    echo "Informe --financeiro, --contratos ou --all (opcional: --dry-run).\n";
    exit(1);
}

$report = [];

if ($financeiro) {
    $report['financeiro'] = [
        'titulos' => Payable::count(),
        'borderos' => Bordero::count(),
        'etapas_aprovacao' => ApprovalStep::count(),
        'documentos' => PayableDocument::count(),
        'comentarios' => PayableComment::count(),
        'ofx_imports' => BankStatementImport::count(),
    ];

    if (! $dryRun) {
        DB::transaction(function () {
            PayableDocument::all()->each(function (PayableDocument $doc) {
                if ($doc->path) {
                    Storage::disk('public')->delete($doc->path);
                }
            });

            ApprovalStep::query()->delete();
            PayableDocument::query()->delete();
            PayableComment::query()->delete();
            PayableRateio::query()->delete();
            Payable::query()->delete();
            Bordero::query()->delete();

            BankStatementImport::all()->each(function (BankStatementImport $import) {
                if ($import->file_path) {
                    Storage::disk('local')->delete($import->file_path);
                }
            });
            BankStatementImport::query()->delete();

            if (Schema::hasTable('payable_sync_runs')) {
                DB::table('payable_sync_runs')->delete();
            }
        });
    }
}

if ($contratos) {
    $report['contratos'] = [
        'contratos' => BsGestaoContrato::count(),
        'alvaras' => BsGestaoAlvara::count(),
        'equipamentos' => BsGestaoEquipamento::count(),
        'anexos' => BsGestaoContratoAnexo::count(),
        'reajustes' => BsGestaoContratoReajuste::count(),
        'fotos_equipamento' => BsGestaoEquipamentoFoto::count(),
    ];

    if (! $dryRun) {
        DB::transaction(function () {
            BsGestaoEquipamentoFoto::all()->each(function (BsGestaoEquipamentoFoto $foto) {
                if ($foto->arquivo_path) {
                    Storage::disk('public')->delete($foto->arquivo_path);
                }
            });
            BsGestaoEquipamentoFoto::query()->delete();

            if (Schema::hasTable('bs_gestao_equipamento_hist_validade')) {
                DB::table('bs_gestao_equipamento_hist_validade')->delete();
            }

            BsGestaoEquipamentoTratativa::query()->delete();
            BsGestaoEquipamentoOcorrencia::query()->delete();
            BsGestaoEquipamento::query()->delete();

            BsGestaoContratoAnexo::all()->each(function (BsGestaoContratoAnexo $anexo) {
                if ($anexo->caminho) {
                    Storage::disk('public')->delete($anexo->caminho);
                }
            });
            BsGestaoContratoAnexo::query()->delete();
            BsGestaoContratoReajuste::query()->delete();
            BsGestaoAlvara::query()->delete();
            BsGestaoContrato::query()->delete();
        });
    }
}

echo ($dryRun ? "[DRY-RUN] " : '') . "Limpeza de dados de teste\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($dryRun) {
    echo "Nada foi alterado. Rode sem --dry-run para executar.\n";
} else {
    if ($financeiro) {
        echo 'Títulos restantes: ' . Payable::count() . "\n";
        echo 'Borderôs restantes: ' . Bordero::count() . "\n";
    }
    if ($contratos) {
        echo 'Contratos restantes: ' . BsGestaoContrato::count() . "\n";
    }
    echo "Concluído.\n";
}
