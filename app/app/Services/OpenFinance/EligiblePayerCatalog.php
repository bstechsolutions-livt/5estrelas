<?php

namespace App\Services\OpenFinance;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use App\Support\CnpjNormalizer;

/**
 * CNPJs elegíveis a pagador Open Finance, a partir do cadastro já existente
 * (filiais Senior / branches). Sem CNPJ hardcoded.
 */
class EligiblePayerCatalog
{
    /**
     * @return list<EligiblePayer>
     */
    public function all(): array
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('id')->get();
        /** @var array<string, Branch> $branchByCnpj */
        $branchByCnpj = [];
        foreach ($branches as $branch) {
            $cnpj = CnpjNormalizer::cnpj14($branch->cnpj);
            if ($cnpj !== null && ! isset($branchByCnpj[$cnpj])) {
                $branchByCnpj[$cnpj] = $branch;
            }
        }

        /** @var array<string, EligiblePayer> $byDocument */
        $byDocument = [];

        foreach (Filial::query()->where('ativo', true)->orderBy('id')->get() as $filial) {
            $cnpj = CnpjNormalizer::cnpj14($filial->cnpj);
            if ($cnpj === null || isset($byDocument[$cnpj])) {
                continue;
            }

            $byDocument[$cnpj] = $this->fromFilial($filial, $cnpj, $branchByCnpj[$cnpj] ?? null);
        }

        foreach ($branchByCnpj as $cnpj => $branch) {
            if (isset($byDocument[$cnpj])) {
                continue;
            }

            $byDocument[$cnpj] = $this->fromBranch($branch, $cnpj);
        }

        $list = array_values($byDocument);
        usort($list, fn (EligiblePayer $a, EligiblePayer $b) => strcmp($a->companyLabel, $b->companyLabel));

        return $list;
    }

    public function find(string $cpfCnpj): ?EligiblePayer
    {
        $document = CnpjNormalizer::cnpj14($cpfCnpj)
            ?? CnpjNormalizer::normalize($cpfCnpj)
            ?? CnpjNormalizer::digits($cpfCnpj);

        if ($document === null) {
            return null;
        }

        foreach ($this->all() as $payer) {
            if ($payer->cpfCnpj === $document) {
                return $payer;
            }
        }

        return null;
    }

    private function fromFilial(Filial $filial, string $cnpj, ?Branch $branch): EligiblePayer
    {
        $name = trim((string) ($filial->nome ?: $filial->fantasia ?: $filial->label));
        $label = trim((string) ($filial->label ?: $name));

        return new EligiblePayer(
            cpfCnpj: $cnpj,
            name: $name,
            companyLabel: $label,
            comercialFilialId: $filial->id,
            branchId: $branch?->id,
            registration: $this->registrationFromFilial($filial, $cnpj, $name),
        );
    }

    private function fromBranch(Branch $branch, string $cnpj): EligiblePayer
    {
        $name = trim((string) ($branch->name ?: $branch->resolveDisplayName()));

        return new EligiblePayer(
            cpfCnpj: $cnpj,
            name: $name,
            companyLabel: $branch->resolveDisplayName(),
            comercialFilialId: null,
            branchId: $branch->id,
            registration: null,
        );
    }

    private function registrationFromFilial(Filial $filial, string $cnpj, string $name): PayerRegistrationData
    {
        $raw = is_array($filial->senior_raw) ? $filial->senior_raw : [];

        return new PayerRegistrationData(
            name: $name,
            cpfCnpj: $cnpj,
            neighborhood: $this->str($raw['baiFil'] ?? null) ?? '',
            city: $this->str($raw['cidFil'] ?? null) ?? '',
            state: $this->uf($filial->uf ?? $raw['sigUfs'] ?? null) ?? '',
            zipcode: $this->cep($raw['cepFil'] ?? null) ?? '',
            addressNumber: $this->str($raw['eenFil'] ?? null),
            street: $this->str($raw['endFil'] ?? null),
            addressComplement: $this->str($raw['cplEnd'] ?? null),
        );
    }

    private function str(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function uf(mixed $value): ?string
    {
        $s = $this->str($value);
        if ($s === null) {
            return null;
        }

        $uf = strtoupper(mb_substr($s, 0, 2));

        return preg_match('/^[A-Z]{2}$/', $uf) ? $uf : null;
    }

    private function cep(mixed $value): ?string
    {
        $digits = CnpjNormalizer::digits($value);
        if ($digits === null) {
            return null;
        }

        return mb_substr($digits, 0, 10);
    }
}
