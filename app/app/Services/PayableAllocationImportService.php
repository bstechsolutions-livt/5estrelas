<?php

namespace App\Services;

use App\Models\Payable;
use App\Models\PayableAllocationLine;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PayableAllocationImportService
{
    /** Mapeamento de cabeçalhos normalizados → campo do modelo. */
    private const HEADER_MAP = [
        'ord' => 'line_order',
        'ordem' => 'line_order',
        '#' => 'line_order',
        'nome' => 'person_name',
        'nomecompleto' => 'person_name',
        'beneficiario' => 'person_name',
        'data' => 'payment_date',
        'datapagamento' => 'payment_date',
        'cpf' => 'document_id',
        'documento' => 'document_id',
        'funcao' => 'role_label',
        'função' => 'role_label',
        'funcão' => 'role_label',
        'cargo' => 'role_label',
        'pix' => 'pix_key',
        'chavepix' => 'pix_key',
        'qtd' => 'quantity',
        'qtdextra' => 'quantity',
        'quantidade' => 'quantity',
        'valorunitario' => 'unit_amount',
        'valorunitário' => 'unit_amount',
        'unitario' => 'unit_amount',
        'subtotal' => 'amount',
        'valor' => 'amount',
    ];

    /**
     * @return array{lines: int, total: float, warnings: string[]}
     */
    public function import(Payable $payable, UploadedFile $file, int $userId): array
    {
        $parsed = $this->parseFile($file);
        $warnings = [];

        if ($parsed['lines'] === []) {
            throw ValidationException::withMessages([
                'file' => 'Nenhuma linha de rateio encontrada na planilha.',
            ]);
        }

        $total = round(array_sum(array_column($parsed['lines'], 'amount')), 2);
        $payableAmount = round((float) $payable->amount, 2);

        if ($total > 0 && abs($total - $payableAmount) > 0.02) {
            $warnings[] = sprintf(
                'Soma das linhas (R$ %s) difere do valor do título (R$ %s).',
                number_format($total, 2, ',', '.'),
                number_format($payableAmount, 2, ',', '.'),
            );
        }

        DB::transaction(function () use ($payable, $parsed, $file, $userId) {
            PayableAllocationLine::where('payable_id', $payable->id)->delete();

            foreach ($parsed['lines'] as $line) {
                PayableAllocationLine::create(array_merge($line, ['payable_id' => $payable->id]));
            }

            $payable->update([
                'allocation_imported_at' => now(),
                'allocation_imported_by' => $userId,
                'allocation_source_file' => $file->getClientOriginalName(),
            ]);
        });

        return [
            'lines' => count($parsed['lines']),
            'total' => $total,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>}
     */
    public function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $headerRowIndex = $this->findHeaderRowIndex($rows);
        if ($headerRowIndex === null) {
            throw ValidationException::withMessages([
                'file' => 'Cabeçalho não reconhecido. Esperado colunas como NOME COMPLETO, SUBTOTAL, PIX, etc.',
            ]);
        }

        $columnMap = $this->buildColumnMap($rows[$headerRowIndex]);
        if (! in_array('amount', $columnMap, true) && ! in_array('person_name', $columnMap, true)) {
            throw ValidationException::withMessages([
                'file' => 'Colunas obrigatórias ausentes (nome e/ou subtotal/valor).',
            ]);
        }

        $lines = [];
        $rowKeys = array_keys($rows);
        $startIdx = array_search($headerRowIndex, $rowKeys, true);

        for ($i = $startIdx + 1; $i < count($rowKeys); $i++) {
            $row = $rows[$rowKeys[$i]];
            if ($this->isEmptyRow($row) || $this->isTotalRow($row)) {
                continue;
            }

            $line = $this->mapRow($row, $columnMap);
            if ($line === null) {
                continue;
            }

            $lines[] = $line;
        }

        return ['lines' => $lines];
    }

    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($v) => $this->normalizeHeader((string) ($v ?? '')), array_values($row));
            $hasName = $this->rowHasAny($normalized, ['nome', 'nomecompleto', 'beneficiario']);
            $hasAmount = $this->rowHasAny($normalized, ['subtotal', 'valor', 'amount']);

            if ($hasName && $hasAmount) {
                return $index;
            }
        }

        return null;
    }

    private function rowHasAny(array $headers, array $needles): bool
    {
        foreach ($headers as $h) {
            foreach ($needles as $needle) {
                if ($h === $needle || str_contains($h, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, string> colLetter => field
     */
    private function buildColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $col => $value) {
            $key = $this->normalizeHeader((string) ($value ?? ''));
            if ($key === '') {
                continue;
            }
            $field = self::HEADER_MAP[$key] ?? null;
            if ($field !== null) {
                $map[$col] = $field;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row, array $columnMap): ?array
    {
        $out = [
            'line_order' => null,
            'person_name' => null,
            'payment_date' => null,
            'document_id' => null,
            'role_label' => null,
            'pix_key' => null,
            'quantity' => null,
            'unit_amount' => null,
            'amount' => null,
        ];

        foreach ($columnMap as $col => $field) {
            $raw = $row[$col] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            $out[$field] = match ($field) {
                'line_order' => (int) preg_replace('/\D/', '', (string) $raw) ?: null,
                'payment_date' => $this->parseDate($raw),
                'quantity', 'unit_amount', 'amount' => $this->parseMoney($raw),
                'document_id' => $this->normalizeDocument((string) $raw),
                default => trim((string) $raw) ?: null,
            };
        }

        if (empty($out['person_name']) && $out['amount'] === null) {
            return null;
        }

        if ($out['amount'] === null && $out['unit_amount'] !== null && $out['quantity'] !== null) {
            $out['amount'] = round($out['unit_amount'] * $out['quantity'], 2);
        }

        if ($out['amount'] === null) {
            return null;
        }

        return $out;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', '', $value) ?? '';
        $value = str_replace(["\n", "\r"], '', $value);

        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isTotalRow(array $row): bool
    {
        foreach ($row as $cell) {
            $v = mb_strtoupper(trim((string) ($cell ?? '')));
            if ($v === 'TOTAL' || str_starts_with($v, 'TOTAL')) {
                return true;
            }
        }

        return false;
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $s = trim((string) $value);
        $s = preg_replace('/R\$\s*/i', '', $s) ?? $s;
        $s = trim($s);

        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        if (! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $s = trim((string) $value);
        $s = str_replace('\\', '/', $s);

        foreach (['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $s)->toDateString();
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDocument(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        return trim($value);
    }
}
