<?php

use App\Models\Receivable;
use App\Models\ReceivableRateio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->string('title_number')->nullable();
            $table->string('customer_name');
            $table->string('customer_document', 30)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('open_amount', 15, 2)->nullable();
            $table->date('due_date');
            $table->date('issue_date')->nullable();
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('senior_id')->nullable();
            $table->string('senior_situacao_original', 20)->nullable();
            $table->json('senior_raw')->nullable();
            $table->timestamp('senior_synced_at')->nullable();
            $table->timestamp('senior_missing_at')->nullable();
            $table->timestamps();

            foreach (Receivable::seniorHeaderFields() as $code => $type) {
                $this->addSeniorColumn($table, $code, $type);
            }

            $table->unique('senior_id', 'receivables_senior_id_unique');
            $table->index('due_date');
            $table->index('senior_situacao_original');
            $table->index('senior_missing_at');
            $table->index('codemp');
            $table->index('customer_name');
        });

        Schema::create('receivable_rateios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            foreach (ReceivableRateio::SENIOR_FIELDS as $code => $type) {
                $this->addSeniorColumn($table, $code, $type);
            }
            $table->timestamps();
            $table->index('receivable_id');
        });

        Schema::create('receivable_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('environment')->nullable();
            $table->string('mode')->default('incremental');
            $table->string('trigger')->default('agendado');
            $table->string('status')->default('em_andamento');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_sync_runs');
        Schema::dropIfExists('receivable_rateios');
        Schema::dropIfExists('receivables');
    }
};
