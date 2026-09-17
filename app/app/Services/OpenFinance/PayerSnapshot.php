<?php

namespace App\Services\OpenFinance;

use App\Support\CnpjNormalizer;

/**
 * Visão segura de um pagador TecnoSpeed.
 *
 * Não retém token do pagador, tokensh, accountHash, accounts nem payload bruto.
 */
final readonly class PayerSnapshot
{
    public function __construct(
        public string $cpfCnpj,
        public string $name,
        public bool $exists,
        public bool $statementActived,
        public ?int $status = null,
        public ?bool $active = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload, bool $exists = true): self
    {
        $document = CnpjNormalizer::normalize($payload['cpfCnpj'] ?? $payload['cpf_cnpj'] ?? null)
            ?? CnpjNormalizer::digits($payload['cpfCnpj'] ?? $payload['cpf_cnpj'] ?? null)
            ?? '';

        $name = trim((string) ($payload['name'] ?? ''));

        $statement = self::boolValue(
            $payload['statementActived'] ?? $payload['stamentActived'] ?? false
        );

        $status = isset($payload['status']) && is_numeric($payload['status'])
            ? (int) $payload['status']
            : null;

        $active = array_key_exists('active', $payload) ? self::boolValue($payload['active']) : null;

        return new self(
            cpfCnpj: $document,
            name: $name,
            exists: $exists,
            statementActived: $statement,
            status: $status,
            active: $active,
        );
    }

    public static function notFound(string $cpfCnpj, string $name = ''): self
    {
        return new self(
            cpfCnpj: CnpjNormalizer::normalize($cpfCnpj) ?? CnpjNormalizer::digits($cpfCnpj) ?? '',
            name: $name,
            exists: false,
            statementActived: false,
        );
    }

    /**
     * @return array{
     *     cpf_cnpj: string,
     *     name: string,
     *     exists: bool,
     *     statement_activated: bool,
     *     status: ?int,
     *     active: ?bool
     * }
     */
    public function toArray(): array
    {
        return [
            'cpf_cnpj' => $this->cpfCnpj,
            'name' => $this->name,
            'exists' => $this->exists,
            'statement_activated' => $this->statementActived,
            'status' => $this->status,
            'active' => $this->active,
        ];
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
