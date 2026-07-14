<?php

namespace App\Services\Senior;

use App\Models\Receivable;
use App\Models\ReceivableRateio;
use Carbon\Carbon;

class ReceivableMapper
{
    public function businessKey(array $titulo): ?string
    {
        $parts = [];
        foreach (['codEmp', 'codFil', 'numTit', 'codTpt', 'codCli'] as $k) {
            $v = $this->asString($titulo[$k] ?? null);
            if ($v === null || $v === '') {
                return null;
            }
            $parts[] = trim($v);
        }

        return implode('-', $parts);
    }

    public function mapHeader(array $titulo): array
    {
        $out = [];

        foreach (Receivable::seniorHeaderFields() as $code => $type) {
            $col = Receivable::seniorColumn($code);
            $out[$col] = $this->convert($titulo[$code] ?? null, $type);
        }

        $out['title_number'] = $this->asString($titulo['numTit'] ?? null);
        $out['amount'] = $this->convert($titulo['vlrOri'] ?? null, 'money') ?? 0;
        $out['open_amount'] = $this->convert($titulo['vlrAbe'] ?? null, 'money');
        $out['due_date'] = $this->convert($titulo['vctPro'] ?? $titulo['vctOri'] ?? null, 'date');
        $out['issue_date'] = $this->convert($titulo['datEmi'] ?? null, 'date');
        $out['customer_name'] = $this->customerName($titulo);
        $out['description'] = $this->asString($titulo['obsTcr'] ?? null);
        $codTns = $this->asString($titulo['codTns'] ?? null);
        $out['category'] = $codTns !== null ? 'Transação ' . $codTns : null;
        $out['senior_situacao_original'] = $this->asString($titulo['sitTit'] ?? null);
        $out['senior_raw'] = $titulo;

        return $out;
    }

    public function mapRateio(array $rateio): array
    {
        $out = [];
        foreach (ReceivableRateio::SENIOR_FIELDS as $code => $type) {
            $out[ReceivableRateio::seniorColumn($code)] = $this->convert($rateio[$code] ?? null, $type);
        }

        return $out;
    }

    private function customerName(array $titulo): string
    {
        $codCli = $titulo['codCli'] ?? null;
        $obs = trim($this->asString($titulo['obsTcr'] ?? null) ?? '');
        if ($obs !== '') {
            return mb_substr($obs, 0, 120);
        }

        return $codCli !== null && $codCli !== '' ? 'Cliente ' . $codCli : 'Cliente não identificado';
    }

    private function normalizeScalar(mixed $value, bool $forString = false): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return null;
        }
        if (count($value) === 1) {
            return $this->normalizeScalar(reset($value), $forString);
        }

        return $forString
            ? implode('; ', array_map('strval', $value))
            : null;
    }

    private function asString(mixed $value): ?string
    {
        $normalized = $this->normalizeScalar($value, true);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        return (string) $normalized;
    }

    private function convert(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $forString = !in_array($type, ['money', 'rate', 'date', 'int'], true);
        $value = $this->normalizeScalar($value, $forString);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return match ($type) {
                'money', 'rate' => $this->parseNumber($value),
                'date' => $this->parseDate($value),
                'int' => (int) $value,
                default => (string) $value,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseNumber(mixed $value): float
    {
        if (is_array($value)) {
            throw new \InvalidArgumentException('valor numérico inválido: array');
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if (str_contains($s, ',') && (strrpos($s, ',') > strrpos($s, '.') || !str_contains($s, '.'))) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) {
            throw new \InvalidArgumentException("valor numérico inválido: {$value}");
        }

        return (float) $s;
    }

    /**
     * Converte data da Senior para Y-m-d (calendário), sem shift de timezone.
     */
    private function parseDate(mixed $value): string
    {
        if (is_array($value)) {
            throw new \InvalidArgumentException('data inválida: array');
        }
        $s = trim((string) $value);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        foreach (['Y-m-d\TH:i:s', 'd/m/Y H:i:s'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s);
            } catch (\Throwable) {
                continue;
            }
            if ($dt !== false) {
                return $dt->toDateString();
            }
        }

        return Carbon::parse($s)->toDateString();
    }
}
