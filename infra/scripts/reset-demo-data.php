<?php

/**
 * Remove dados de demonstração / teste (Financeiro + Gestão de Contratos).
 *
 * Financeiro (--financeiro):
 *   - Títulos com senior_raw._demo = true (PayableSeeder)
 *   - Importações OFX demo (file_name demo-*)
 *   - NÃO remove títulos reais da Senior (sem flag _demo)
 *
 * Contratos (--contratos):
 *   - Contratos, alvarás, equipamentos, anexos, reajustes (dados do GestaoContratosDemoSeeder)
 *   - Mantém cadastros: tipos de índice, tipos de alvará, tipos de equipamento
 *
 * Uso:
 *   php infra/scripts/reset-demo-data.php --dry-run
 *   php infra/scripts/reset-demo-data.php --financeiro
 *   php infra/scripts/reset-demo-data.php --contratos
 *   php infra/scripts/reset-demo-data.php --all
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApprovalStep;
use App\Models\BankStatementImport;
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
    $demoPayables = Payable::query()
        ->whereJsonContains('senior_raw->_demo', true)
        ->get();

    $demoOfx = BankStatementImport::query()
        ->where('file_name', 'like', 'demo-%')
        ->get();

    $report['financeiro'] = [
        'titulos_demo' => $demoPayables->count(),
        'ofx_demo' => $demoOfx->count(),
        'titulos_totais_antes' => Payable::count(),
    ];

    if (! $dryRun) {
        DB::transaction(function () use ($demoPayables, $demoOfx) {
            $ids = $demoPayables->pluck('id');

            if ($ids->isNotEmpty()) {
                ApprovalStep::whereIn('payable_id', $ids)->delete();

                PayableDocument::whereIn('payable_id', $ids)->each(function (PayableDocument $doc) {
                    if ($doc->path) {
                        Storage::disk('public')->delete($doc->path);
                    }
                    $doc->delete();
                });

                PayableComment::whereIn('payable_id', $ids)->delete();
                PayableRateio::whereIn('payable_id', $ids)->delete();
                Payable::whereIn('id', $ids)->delete();
            }

            $demoOfx->each(function (BankStatementImport $import) {
                if ($import->file_path) {
                    Storage::disk('local')->delete($import->file_path);
                }
                $import->delete();
            });
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

            if (DB::getSchemaBuilder()->hasTable('bs_gestao_equipamento_hist_validade')) {
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

echo ($dryRun ? "[DRY-RUN] " : '') . "Reset demo data\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($dryRun) {
    echo "Nada foi alterado. Rode sem --dry-run para executar.\n";
} else {
    if ($financeiro) {
        echo 'Títulos restantes (reais): ' . Payable::count() . "\n";
    }
    if ($contratos) {
        echo 'Contratos restantes: ' . BsGestaoContrato::count() . "\n";
    }
    echo "Concluído.\n";
}
