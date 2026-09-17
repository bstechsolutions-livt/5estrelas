<?php

namespace App\Services\OpenFinance;

use App\Models\Branch;
use App\Models\OpenFinancePayer;
use App\Support\CnpjNormalizer;

/**
 * Monta o perfil cadastral do pagador a partir de fontes confiáveis.
 *
 * Fontes de primeira classe: Branch (nome, CNPJ) e perfil Open Finance já
 * persistido. UF de `Comercial\Filial.uf` só entra se tiver 2 letras.
 * Não inventa endereço e não envia `senior_raw` automaticamente.
 */
final class PayerProfileBuilder
{
    /**
     * @return array{
     *     name: string,
     *     cpf_cnpj: string,
     *     cpf_cnpj_masked: string,
     *     email: string,
     *     zipcode: string,
     *     street: string,
     *     address_number: string,
     *     address_complement: string,
     *     neighborhood: string,
     *     city: string,
     *     state: string,
     *     missing: list<string>,
     *     complete: bool
     * }
     */
    public function build(Branch $branch, ?OpenFinancePayer $stored = null): array
    {
        $document = CnpjNormalizer::cnpj14($stored?->cpf_cnpj)
            ?? CnpjNormalizer::cnpj14($branch->cnpj)
            ?? '';

        $name = trim((string) ($stored?->name ?: $branch->name));
        $state = $this->normalizeState($stored?->state)
            ?? $this->stateFromFilial($branch);

        $profile = [
            'name' => $name,
            'cpf_cnpj' => $document,
            'cpf_cnpj_masked' => DocumentDisplay::mask($document),
            'email' => trim((string) ($stored?->email ?? '')),
            'zipcode' => $this->digitsOrEmpty($stored?->zipcode),
            'street' => trim((string) ($stored?->street ?? '')),
            'address_number' => trim((string) ($stored?->address_number ?? '')),
            'address_complement' => trim((string) ($stored?->address_complement ?? '')),
            'neighborhood' => trim((string) ($stored?->neighborhood ?? '')),
            'city' => trim((string) ($stored?->city ?? '')),
            'state' => $state ?? '',
        ];

        $data = $this->toRegistration($profile);
        $profile['missing'] = $data->missingFields();
        $profile['complete'] = $data->isComplete();

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function toRegistration(array $input): PayerRegistrationData
    {
        $state = $this->normalizeState($input['state'] ?? null) ?? '';
        $document = CnpjNormalizer::normalize($input['cpf_cnpj'] ?? null)
            ?? CnpjNormalizer::digits($input['cpf_cnpj'] ?? null)
            ?? '';

        return new PayerRegistrationData(
            name: trim((string) ($input['name'] ?? '')),
            cpfCnpj: $document,
            neighborhood: trim((string) ($input['neighborhood'] ?? '')),
            city: trim((string) ($input['city'] ?? '')),
            state: $state,
            zipcode: $this->digitsOrEmpty($input['zipcode'] ?? null),
            addressNumber: $this->optional($input['address_number'] ?? null),
            street: $this->optional($input['street'] ?? null),
            addressComplement: $this->optional($input['address_complement'] ?? null),
            email: $this->optional($input['email'] ?? null),
        );
    }

    private function stateFromFilial(Branch $branch): ?string
    {
        $filial = $branch->resolveComercialFilial();
        if ($filial === null) {
            return null;
        }

        return $this->normalizeState($filial->uf);
    }

    private function normalizeState(mixed $value): ?string
    {
        $state = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $value) ?? '');

        return strlen($state) === 2 ? $state : null;
    }

    private function digitsOrEmpty(mixed $value): string
    {
        return CnpjNormalizer::digits($value) ?? '';
    }

    private function optional(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
