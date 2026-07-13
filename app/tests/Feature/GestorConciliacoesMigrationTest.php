<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Services\GestorConciliacoesMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GestorConciliacoesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $exportPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportPath = storage_path('framework/testing/gestor-export-' . uniqid());
        File::ensureDirectoryExists("{$this->exportPath}/documents");
        File::ensureDirectoryExists("{$this->exportPath}/enterprises");
        File::ensureDirectoryExists("{$this->exportPath}/suppliers");
        File::ensureDirectoryExists("{$this->exportPath}/users");

        File::put("{$this->exportPath}/enterprises/documents.jsonl", json_encode([
            '_id' => 'ent-1',
            'cnpj' => '07.179.495/0001-07',
        ]) . "\n");

        File::put("{$this->exportPath}/suppliers/documents.jsonl", '');
        File::put("{$this->exportPath}/users/documents.jsonl", '');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportPath);
        parent::tearDown();
    }

    public function test_matches_by_exact_title_number(): void
    {
        $payable = Payable::create([
            'title_number' => '116766720244',
            'senior_id' => '8-1-116766720244-01-6176',
            'supplier_name' => 'Fornecedor',
            'amount' => 3795.02,
            'due_date' => '2026-07-14',
            'status' => 'pendente',
            'codemp' => 8,
            'codfil' => 1,
        ]);

        $this->writeGestorDocument([
            '_id' => 'gestor-title-1',
            'status' => 'awaiting-receipt',
            'originEnterpriseId' => 'ent-1',
            'details' => [
                'documentNumber' => '116766720244',
                'value' => 3795.02,
                'expirationDate' => 1_783_911_600_000,
                'description' => 'Parcela 5x6',
            ],
            'history' => [
                ['type' => 'sent-to-analysis', 'at' => 1_770_000_000_000, 'by' => 'u1'],
                ['type' => 'sent-to-approval', 'at' => 1_771_000_000_000, 'by' => 'u1'],
                ['type' => 'approved', 'at' => 1_772_000_000_000, 'by' => 'u2'],
            ],
        ]);

        $report = $this->runDryMatchReport();

        $match = collect($report['matches'])->firstWhere('gestor_id', 'gestor-title-1');
        $this->assertNotNull($match);
        $this->assertSame('high', $match['confidence']);
        $this->assertSame($payable->id, $match['payable_id']);
        $this->assertSame('title_number', $match['strategy']);
    }

    public function test_matches_by_codemp_amount_and_due_tolerance_when_title_differs(): void
    {
        Payable::create([
            'title_number' => '116766720243',
            'supplier_name' => 'Parcela 4x6',
            'amount' => 3795.02,
            'due_date' => '2026-06-15',
            'status' => 'pendente',
            'codemp' => 8,
            'codfil' => 1,
        ]);

        $target = Payable::create([
            'title_number' => '116766720244',
            'supplier_name' => 'Parcela 5x6',
            'amount' => 3795.02,
            'due_date' => '2026-07-14',
            'status' => 'pendente',
            'codemp' => 8,
            'codfil' => 1,
        ]);

        Payable::create([
            'title_number' => '116766720245',
            'supplier_name' => 'Parcela 6x6',
            'amount' => 3795.02,
            'due_date' => '2026-08-13',
            'status' => 'pendente',
            'codemp' => 8,
            'codfil' => 1,
        ]);

        $this->writeGestorDocument([
            '_id' => 'gestor-tolerance-1',
            'status' => 'awaiting-receipt',
            'originEnterpriseId' => 'ent-1',
            'details' => [
                'documentNumber' => '011676675',
                'value' => 3795.02,
                'expirationDate' => 1_783_911_600_000,
                'description' => 'PAGAMENTO EXECUÇÃO RAILSON 5X6',
            ],
            'history' => [
                ['type' => 'sent-to-analysis', 'at' => 1_770_000_000_000, 'by' => 'u1'],
                ['type' => 'sent-to-approval', 'at' => 1_771_000_000_000, 'by' => 'u1'],
                ['type' => 'approved', 'at' => 1_772_000_000_000, 'by' => 'u2'],
            ],
        ]);

        $report = $this->runDryMatchReport();
        $match = collect($report['matches'])->firstWhere('gestor_id', 'gestor-tolerance-1');

        $this->assertNotNull($match);
        $this->assertSame('high', $match['confidence']);
        $this->assertSame($target->id, $match['payable_id']);
        $this->assertSame('codemp_amount_due_tolerance', $match['strategy']);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function writeGestorDocument(array $document): void
    {
        File::append(
            "{$this->exportPath}/documents/documents.jsonl",
            json_encode($document, JSON_UNESCAPED_UNICODE) . "\n",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function runDryMatchReport(): array
    {
        $reportPath = storage_path('framework/testing/gestor-report-' . uniqid() . '.json');
        $service = new GestorConciliacoesMigrationService(
            exportPath: $this->exportPath,
            confidence: 'high',
            execute: false,
            skipComments: true,
            skipFiles: true,
            reportPath: $reportPath,
        );

        $service->run();

        return json_decode(file_get_contents($reportPath), true);
    }
}
