<?php

use App\Models\Payable;
use App\Models\PayableRateio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espelhamento do título a pagar da Senior (ConsultarTitulosAbertosCP v3).
 * - Expande `payables` com os 72 campos de cabeçalho (Apêndice A.2) + controle de sync.
 * - Cria `payable_rateios` (Apêndice A.3, 1:N com payable).
 * - Cria `payable_sync_runs` (observabilidade — requirement 9).
 *
 * Workflow_Fields existentes são preservados (nenhuma coluna removida/alterada).
 */
return new class extends Migration
{
    private function addSeniorColumn(Blueprint $table, string $code, string $type): void
    {
        $col = strtolower($code);
        $column = match ($type) {
            'money' => $table->decimal($col, 18, 2),
            'rate' => $table->decimal($col, 18, 6),
            'date' => $table->date($col),
            'int' => $table->bigInteger($col),
            default => $table->string($col, 255),
        };
        $column->nullable();
    }

    public function up(): void
    {
        // ── payables: campos de cabeçalho da Senior + controle de sincronização ──
        Schema::table('payables', function (Blueprint $table) {
            foreach (Payable::seniorHeaderFields() as $code => $type) {
                $this->addSeniorColumn($table, $code, $type);
            }

            // Situação original da Senior (sitTit) preservada quando não mapeada.
            $table->string('senior_situacao_original')->nullable();
            // Conteúdo bruto do título (campos sem coluna dedicada / fallback). Req 3.4/3.8.
            $table->json('senior_raw')->nullable();
            // Horário da última sincronização que tocou neste título.
            $table->timestamp('senior_synced_at')->nullable();
            // Título ausente na Senior (baixado/excluído) — req 7.
            $table->timestamp('senior_missing_at')->nullable();

            $table->index('senior_missing_at');
        });

        // senior_id passa a ser a Business_Key única (nullable permite múltiplos nulos no PG).
        Schema::table('payables', function (Blueprint $table) {
            $table->unique('senior_id', 'payables_senior_id_unique');
        });

        // ── payable_rateios (Apêndice A.3) ──
        Schema::create('payable_rateios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            foreach (PayableRateio::SENIOR_FIELDS as $code => $type) {
                $this->addSeniorColumn($table, $code, $type);
            }
            $table->timestamps();

            $table->index('payable_id');
        });

        // ── payable_sync_runs (requirement 9) ──
        Schema::create('payable_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('environment')->nullable();   // HML | PRD
            $table->string('mode')->default('incremental'); // incremental | full
            $table->string('trigger')->default('agendado'); // manual | agendado
            $table->string('status')->default('em_andamento'); // em_andamento|sucesso|falha|ignorado
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_sync_runs');
        Schema::dropIfExists('payable_rateios');

        Schema::table('payables', function (Blueprint $table) {
            $table->dropUnique('payables_senior_id_unique');
            $table->dropIndex(['senior_missing_at']);

            $cols = Payable::seniorColumns();
            $cols[] = 'senior_situacao_original';
            $cols[] = 'senior_raw';
            $cols[] = 'senior_synced_at';
            $cols[] = 'senior_missing_at';
            $table->dropColumn($cols);
        });
    }
};
