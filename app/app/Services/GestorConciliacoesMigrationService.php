<?php

namespace App\Services;

use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Models\SeniorSupplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GestorConciliacoesMigrationService
{
    private array $enterprises = [];

    private array $suppliers = [];

    private array $gestorUsers = [];

    private array $laravelUsersByEmail = [];

    private array $cnpjToCodFors = [];

    /** @var Collection<int, Payable> */
    private Collection $payables;

    /** @var list<array<string, mixed>> */
    private array $openDocuments = [];

    public function __construct(
        private readonly string $exportPath,
        private readonly string $confidence = 'high',
        private readonly bool $execute = false,
        private readonly bool $skipComments = false,
        private readonly bool $skipFiles = false,
        private readonly ?string $reportPath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->loadExport();
        $this->payables = Payable::query()->get();
        $this->buildSupplierIndex();
        $this->buildUserIndex();

        $report = $this->buildMatchingReport();
        $result = [
            'dry_run' => ! $this->execute,
            'confidence_filter' => $this->confidence,
            'matching' => array_merge($report['summary'], ['total_open' => $report['total_open']]),
            'migrated' => [],
            'failures' => [],
            'files' => ['attempted' => 0, 'imported' => 0, 'failed' => 0],
            'comments' => ['attempted' => 0, 'imported' => 0],
        ];

        $toMigrate = collect($report['matches'])
            ->filter(fn (array $m) => $m['confidence'] === $this->confidence)
            ->values();

        foreach ($toMigrate as $match) {
            try {
                $migrated = $this->migrateMatch($match, $result);
                $result['migrated'][] = $migrated;
            } catch (\Throwable $e) {
                $result['failures'][] = [
                    'gestor_id' => $match['gestor_id'],
                    'payable_id' => $match['payable_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($this->reportPath) {
            $fullReport = array_merge($report, [
                'executed_at' => now()->toIso8601String(),
                'dry_run' => ! $this->execute,
                'migration_result' => $result,
            ]);
            $dir = dirname($this->reportPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->reportPath, json_encode($fullReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $result;
    }

    private function loadExport(): void
    {
        $base = rtrim($this->exportPath, '/');

        foreach ($this->readJsonl("{$base}/enterprises/documents.jsonl") as $row) {
            $this->enterprises[$row['_id']] = $row;
        }

        foreach ($this->readJsonl("{$base}/suppliers/documents.jsonl") as $row) {
            $this->suppliers[$row['_id']] = $row;
        }

        foreach ($this->readJsonl("{$base}/users/documents.jsonl") as $row) {
            $email = strtolower(trim($row['profile']['mainEmail'] ?? ''));
            $this->gestorUsers[$row['_id']] = [
                'email' => $email,
                'name' => trim(($row['profile']['firstName'] ?? '') . ' ' . ($row['profile']['lastName'] ?? '')),
            ];
        }

        $skipped = config('gestor_migration.skipped_statuses', ['included']);
        $cnpjMap = config('gestor_migration.enterprise_cnpj_to_codemp', []);

        foreach ($this->readJsonl("{$base}/documents/documents.jsonl") as $doc) {
            if (in_array($doc['status'] ?? '', $skipped, true)) {
                continue;
            }

            $details = $doc['details'] ?? [];
            $supplier = $this->suppliers[$details['supplier'] ?? ''] ?? [];
            $enterprise = $this->enterprises[$doc['originEnterpriseId'] ?? ''] ?? [];
            $entCnpj = $this->normalizeCnpj($enterprise['cnpj'] ?? null);

            $this->openDocuments[] = [
                'gestor_id' => $doc['_id'],
                'status' => $doc['status'],
                'value' => round((float) ($details['value'] ?? 0), 2),
                'due' => $this->msToDate($details['expirationDate'] ?? null),
                'sup_cnpj' => $this->normalizeCnpj($supplier['cnpj'] ?? null),
                'sup_name' => $supplier['name'] ?? null,
                'codemp' => $cnpjMap[$entCnpj] ?? null,
                'ent_cnpj' => $entCnpj,
                'description' => $details['description'] ?? null,
                'files' => $doc['files'] ?? [],
                'comments' => $doc['comments'] ?? [],
                'history' => $doc['history'] ?? [],
                'rectificationReason' => $doc['rectificationReason'] ?? null,
                'retentionReason' => $doc['retentionReason'] ?? null,
            ];
        }
    }

    private function buildSupplierIndex(): void
    {
        foreach (SeniorSupplier::query()->whereNotNull('cnpj')->get() as $supplier) {
            $cnpj = $this->normalizeCnpj($supplier->cnpj);
            if ($cnpj) {
                $this->cnpjToCodFors[$cnpj][] = [(int) $supplier->cod_emp, (int) $supplier->cod_for];
            }
        }
    }

    private function buildUserIndex(): void
    {
        foreach (User::query()->whereNotNull('email')->get(['id', 'email']) as $user) {
            $this->laravelUsersByEmail[strtolower($user->email)] = $user->id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMatchingReport(): array
    {
        $matches = [];
        $summary = ['high' => 0, 'medium' => 0, 'low' => 0, 'ambiguous' => 0, 'none' => 0];
        $byStatus = [];

        foreach ($this->openDocuments as $doc) {
            $match = $this->matchDocument($doc);
            $summary[$match['confidence']]++;
            $byStatus[$doc['status']][$match['confidence']] = ($byStatus[$doc['status']][$match['confidence']] ?? 0) + 1;

            $matches[] = array_merge($doc, $match);
        }

        return [
            'summary' => $summary,
            'total_open' => count($this->openDocuments),
            'by_status' => $byStatus,
            'matches' => $matches,
            'unmatched' => collect($matches)->where('confidence', 'none')->values()->all(),
            'ambiguous' => collect($matches)->where('confidence', 'ambiguous')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function matchDocument(array $doc): array
    {
        if (! $doc['codemp']) {
            return ['confidence' => 'none', 'reason' => 'codemp_nao_mapeado'];
        }

        $codemp = (int) $doc['codemp'];
        $value = $doc['value'];
        $due = $doc['due'];

        $amountMatch = fn (Payable $p) => $this->amountMatches($p, $value);
        $dateMatch = fn (Payable $p) => $this->dateMatches($p, $due);

        $candidates = $this->payables->filter(
            fn (Payable $p) => (int) $p->codemp === $codemp && $amountMatch($p) && $dateMatch($p)
        );

        if ($candidates->count() === 1) {
            return $this->matchResult('high', $candidates->first()->id, 'codemp_amount_due');
        }

        if (! empty($doc['sup_cnpj']) && isset($this->cnpjToCodFors[$doc['sup_cnpj']])) {
            $codfors = array_column(
                array_filter($this->cnpjToCodFors[$doc['sup_cnpj']], fn ($x) => $x[0] === $codemp),
                1
            );
            if ($codfors) {
                $byFor = $candidates->filter(fn (Payable $p) => in_array((int) $p->codfor, $codfors, true));
                if ($byFor->count() === 1) {
                    return $this->matchResult('high', $byFor->first()->id, 'codemp_codfor_amount_due');
                }
            }
        }

        if ($candidates->count() > 1) {
            return [
                'confidence' => 'ambiguous',
                'candidate_ids' => $candidates->pluck('id')->all(),
                'strategy' => 'multiple_codemp_amount_due',
            ];
        }

        $amountOnly = $this->payables->filter(fn (Payable $p) => (int) $p->codemp === $codemp && $amountMatch($p));
        if ($amountOnly->count() === 1) {
            return $this->matchResult('medium', $amountOnly->first()->id, 'codemp_amount_only');
        }

        $dueOnly = $this->payables->filter(fn (Payable $p) => (int) $p->codemp === $codemp && $dateMatch($p));
        if ($dueOnly->count() === 1) {
            return $this->matchResult('low', $dueOnly->first()->id, 'codemp_due_only');
        }

        return ['confidence' => 'none', 'reason' => 'sem_correspondencia'];
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function migrateMatch(array $match, array &$result): array
    {
        $payable = $this->payables->firstWhere('id', $match['payable_id']);
        if (! $payable) {
            throw new \RuntimeException("Payable {$match['payable_id']} não encontrado.");
        }

        $workflow = $this->buildWorkflowUpdate($match);
        $out = [
            'gestor_id' => $match['gestor_id'],
            'payable_id' => $payable->id,
            'senior_id' => $payable->senior_id,
            'gestor_status' => $match['status'],
            'new_status' => $workflow['status'],
            'strategy' => $match['strategy'] ?? null,
            'comments_imported' => 0,
            'files_imported' => 0,
        ];

        if ($this->execute) {
            DB::transaction(function () use ($payable, $workflow, $match, &$out, &$result) {
                $old = $payable->only(array_merge(Payable::WORKFLOW_FIELDS, ['id', 'senior_id']));
                $payable->update($workflow);

                AuditLogger::log(
                    event: 'financeiro.contas_pagar.gestor_migration.updated',
                    module: 'financeiro.contas_pagar',
                    description: "Migração gestor → payable #{$payable->id} ({$match['status']})",
                    auditable: $payable,
                    oldValues: $old,
                    newValues: $workflow,
                    metadata: ['gestor_id' => $match['gestor_id'], 'strategy' => $match['strategy'] ?? null],
                );

                if (! $this->skipComments) {
                    $result['comments']['attempted'] += count($match['comments'] ?? []) + $this->extraCommentsCount($match);
                    $out['comments_imported'] = $this->importComments($payable, $match);
                    $result['comments']['imported'] += $out['comments_imported'];
                }

                if (! $this->skipFiles) {
                    $fileStats = $this->importFiles($payable, $match);
                    $out['files_imported'] = $fileStats['imported'];
                    $result['files']['attempted'] += $fileStats['attempted'];
                    $result['files']['imported'] += $fileStats['imported'];
                    $result['files']['failed'] += $fileStats['failed'];
                }
            });
        } else {
            $out['would_update'] = $workflow;
            $out['comments_to_import'] = count($match['comments'] ?? []) + $this->extraCommentsCount($match);
            $out['files_to_import'] = count($match['files'] ?? []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function buildWorkflowUpdate(array $match): array
    {
        $statusMap = config('gestor_migration.status_map', []);
        $gestorStatus = $match['status'];
        $newStatus = $statusMap[$gestorStatus] ?? 'pendente';

        $update = ['status' => $newStatus];

        if ($gestorStatus === 'awaiting-rectification' && filled($match['rectificationReason'])) {
            $update['rejection_reason'] = $match['rectificationReason'];
        }

        if ($gestorStatus === 'awaiting-inclusion') {
            $update['approved_at'] = $update['approved_at'] ?? now();
        }

        $history = $this->extractHistoryActors($match['history'] ?? []);
        if ($history['prepared_by']) {
            $update['prepared_by'] = $history['prepared_by'];
        }
        if ($history['approved_by']) {
            $update['approved_by'] = $history['approved_by'];
        }
        if ($history['sent_for_approval_at']) {
            $update['sent_for_approval_at'] = $history['sent_for_approval_at'];
        }
        if ($history['approved_at']) {
            $update['approved_at'] = $history['approved_at'];
        }

        return $update;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @return array{prepared_by: ?int, approved_by: ?int, sent_for_approval_at: ?Carbon, approved_at: ?Carbon}
     */
    private function extractHistoryActors(array $history): array
    {
        $preparedBy = null;
        $approvedBy = null;
        $sentAt = null;
        $approvedAt = null;

        foreach ($history as $event) {
            $type = $event['type'] ?? '';
            $userId = $this->resolveGestorUserId($event['by'] ?? null);
            $at = isset($event['at']) ? Carbon::createFromTimestampMs((int) $event['at']) : null;

            if (in_array($type, ['sent-to-analysis', 'sent-to-department-approval', 'sent-to-reanalysis'], true) && $userId) {
                $preparedBy = $userId;
            }
            if ($type === 'sent-to-approval' && $at) {
                $sentAt = $at;
            }
            if ($type === 'approved' && $userId) {
                $approvedBy = $userId;
                $approvedAt = $at ?? $approvedAt;
            }
        }

        return [
            'prepared_by' => $preparedBy,
            'approved_by' => $approvedBy,
            'sent_for_approval_at' => $sentAt,
            'approved_at' => $approvedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function importComments(Payable $payable, array $match): int
    {
        $imported = 0;
        $prefix = '[Gestor] ';

        foreach ($match['comments'] ?? [] as $comment) {
            $body = trim($comment['content'] ?? '');
            if ($body === '') {
                continue;
            }

            $userId = $this->resolveGestorUserId($comment['authorId'] ?? null);
            $createdAt = isset($comment['createdAt'])
                ? Carbon::createFromTimestampMs((int) $comment['createdAt'])
                : now();

            if ($this->commentExists($payable->id, $body, $createdAt)) {
                continue;
            }

            PayableComment::create([
                'payable_id' => $payable->id,
                'user_id' => $userId,
                'body' => $prefix . $body,
                'type' => 'comment',
                'metadata' => [
                    'migrated_from' => 'gestor',
                    'gestor_id' => $match['gestor_id'],
                ],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $imported++;
        }

        if (($match['status'] ?? '') === 'awaiting-release' && filled($match['retentionReason'])) {
            $body = $prefix . 'Retenção: ' . $match['retentionReason'];
            if (! $this->commentExists($payable->id, $body)) {
                PayableComment::create([
                    'payable_id' => $payable->id,
                    'user_id' => null,
                    'body' => $body,
                    'type' => 'comment',
                    'metadata' => ['migrated_from' => 'gestor', 'gestor_retention' => true],
                ]);
                $imported++;
            }
        }

        if (($match['status'] ?? '') === 'awaiting-inclusion') {
            $body = $prefix . 'Documento aprovado no gestor legado (aguardando inclusão em conciliação).';
            if (! $this->commentExists($payable->id, $body)) {
                PayableComment::create([
                    'payable_id' => $payable->id,
                    'user_id' => null,
                    'body' => $body,
                    'type' => 'status_change',
                    'metadata' => ['migrated_from' => 'gestor', 'gestor_status' => 'awaiting-inclusion'],
                ]);
                $imported++;
            }
        }

        return $imported;
    }

    private function extraCommentsCount(array $match): int
    {
        $n = 0;
        if (($match['status'] ?? '') === 'awaiting-release' && filled($match['retentionReason'])) {
            $n++;
        }
        if (($match['status'] ?? '') === 'awaiting-inclusion') {
            $n++;
        }

        return $n;
    }

    private function commentExists(int $payableId, string $body, ?Carbon $createdAt = null): bool
    {
        $q = PayableComment::query()
            ->where('payable_id', $payableId)
            ->where('body', $body);

        if ($createdAt) {
            $q->where('created_at', $createdAt);
        }

        return $q->exists();
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array{attempted: int, imported: int, failed: int}
     */
    private function importFiles(Payable $payable, array $match): array
    {
        $files = $match['files'] ?? [];
        if ($files === []) {
            return ['attempted' => 0, 'imported' => 0, 'failed' => 0];
        }

        $storageIds = collect($files)->pluck('storageId')->filter()->unique()->values()->all();
        $urls = $this->resolveConvexUrls($storageIds);

        $imported = 0;
        $failed = 0;

        foreach ($files as $file) {
            $storageId = $file['storageId'] ?? null;
            $url = $storageId ? ($urls[$storageId] ?? null) : null;
            if (! $url) {
                $failed++;
                continue;
            }

            $description = $file['description'] ?? 'anexo';
            $safeName = Str::slug($description, '_') ?: 'anexo';

            if (PayableDocument::where('payable_id', $payable->id)->where('name', 'like', "{$safeName}_{$storageId}.%")->exists()) {
                continue;
            }

            try {
                $response = Http::timeout(120)->get($url);
                if (! $response->successful()) {
                    $failed++;
                    continue;
                }

                $mime = $response->header('Content-Type') ?: 'application/octet-stream';
                $ext = $this->extensionFromMime($mime);
                $filename = "{$safeName}_{$storageId}.{$ext}";
                $path = "payables/docs/gestor_{$payable->id}_{$filename}";
                Storage::disk('public')->put($path, $response->body());

                PayableDocument::create([
                    'payable_id' => $payable->id,
                    'uploaded_by' => null,
                    'name' => $filename,
                    'doc_type' => $this->mapDocType($description),
                    'path' => $path,
                    'mime_type' => $mime,
                    'size' => strlen($response->body()),
                ]);
                $imported++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['attempted' => count($files), 'imported' => $imported, 'failed' => $failed];
    }

    /**
     * @param  list<string>  $storageIds
     * @return array<string, string>
     */
    private function resolveConvexUrls(array $storageIds): array
    {
        $deployKey = config('gestor_migration.convex.deploy_key');
        $legadoPath = config('gestor_migration.convex.legado_path');
        $batchSize = (int) config('gestor_migration.convex.url_batch_size', 40);

        if (! $deployKey || ! is_dir($legadoPath)) {
            return [];
        }

        $urls = [];
        foreach (array_chunk($storageIds, $batchSize) as $chunk) {
            $script = "{$legadoPath}/scripts/resolve_storage_urls.mjs";
            if (! is_file($script)) {
                break;
            }

            $process = new Process(
                ['node', $script, json_encode(array_values($chunk))],
                $legadoPath,
                array_merge($_ENV, $_SERVER, [
                    'CONVEX_DEPLOY_KEY' => $deployKey,
                    'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
                ]),
                null,
                180,
            );
            $process->run();

            if (! $process->isSuccessful()) {
                continue;
            }

            $decoded = json_decode(trim($process->getOutput()), true);
            if (is_array($decoded)) {
                $urls = array_merge($urls, $decoded);
            }
        }

        return $urls;
    }

    private function mapDocType(string $description): string
    {
        $d = mb_strtoupper($description);

        return match (true) {
            str_contains($d, 'NF') || str_contains($d, 'NOTA') => 'nota_fiscal',
            str_contains($d, 'BOLETO') => 'boleto',
            str_contains($d, 'RELAT') || str_contains($d, 'RATEIO') => 'relatorio',
            str_contains($d, 'COMPROV') || str_contains($d, 'RECIBO') || str_contains($d, 'TRCT') => 'comprovacao',
            default => 'outro',
        };
    }

    private function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') => 'xlsx',
            str_contains($mime, 'word') => 'docx',
            str_contains($mime, 'zip') => 'zip',
            default => 'bin',
        };
    }

    private function amountMatches(Payable $payable, float $value): bool
    {
        return round((float) $payable->amount, 2) === $value
            || round((float) ($payable->vlrabe ?? 0), 2) === $value
            || round((float) ($payable->vlrori ?? 0), 2) === $value;
    }

    private function dateMatches(Payable $payable, ?string $due): bool
    {
        if (! $due) {
            return false;
        }

        $dates = array_filter([
            $payable->due_date?->format('Y-m-d'),
            $payable->vctori?->format('Y-m-d'),
            $payable->vctpro?->format('Y-m-d'),
        ]);

        return in_array($due, $dates, true);
    }

    private function resolveGestorUserId(?string $gestorAuthorId): ?int
    {
        if (! $gestorAuthorId) {
            return null;
        }

        $email = $this->gestorUsers[$gestorAuthorId]['email'] ?? null;
        if (! $email) {
            return null;
        }

        return $this->laravelUsersByEmail[$email] ?? null;
    }

    private function normalizeCnpj(?string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj ?? '') ?? '';
    }

    private function msToDate(mixed $ms): ?string
    {
        if (! $ms) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $ms)->format('Y-m-d');
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function readJsonl(string $path): \Generator
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Arquivo de export não encontrado: {$path}");
        }

        $handle = fopen($path, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }
        fclose($handle);
    }

    /**
     * @return array<string, mixed>
     */
    private function matchResult(string $confidence, int $payableId, string $strategy): array
    {
        return [
            'confidence' => $confidence,
            'payable_id' => $payableId,
            'strategy' => $strategy,
        ];
    }
}
