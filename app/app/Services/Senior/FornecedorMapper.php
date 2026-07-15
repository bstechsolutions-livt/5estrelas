<?php

namespace App\Services\Senior;

class FornecedorMapper
{
    public function businessKey(array $fornecedor): ?string
    {
        $codEmp = $fornecedor['codEmp'] ?? null;
        $codFor = $fornecedor['codFor'] ?? null;
        if ($codEmp === null || $codEmp === '' || $codFor === null || $codFor === '') {
            return null;
        }

        return trim((string) $codEmp) . '-' . trim((string) $codFor);
    }

    public function map(array $fornecedor): array
    {
        $name = trim((string) (
            $fornecedor['nomFor']
            ?? $fornecedor['razSoc']
            ?? $fornecedor['apeFor']
            ?? ''
        ));

        $tradeName = trim((string) ($fornecedor['apeFor'] ?? ''));
        $cnpj = $this->normalizeCnpj($fornecedor['cgcCpf'] ?? $fornecedor['numCgc'] ?? null);

        return [
            'cod_emp' => (int) ($fornecedor['codEmp'] ?? 0),
            'cod_for' => (int) ($fornecedor['codFor'] ?? 0),
            'name' => $name !== '' ? $name : ('Fornecedor ' . ($fornecedor['codFor'] ?? '?')),
            'trade_name' => $tradeName !== '' ? $tradeName : null,
            'cnpj' => $cnpj,
            'senior_raw' => $fornecedor,
            'senior_synced_at' => now(),
        ];
    }

    private function normalizeCnpj(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Senior às vezes devolve float textual (ex.: "19593175000188.0").
        $raw = preg_replace('/\.0+$/', '', trim((string) $value)) ?? '';
        $digits = preg_replace('/\D/', '', $raw);

        return $digits !== '' ? $digits : null;
    }
}
