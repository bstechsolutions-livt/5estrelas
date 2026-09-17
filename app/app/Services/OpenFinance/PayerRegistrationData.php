<?php

namespace App\Services\OpenFinance;

use App\Support\CnpjNormalizer;

/**
 * Dados cadastrais necessários para POST /api/v1/payer (Open Finance / Extrato).
 *
 * Campos oficiais: name, cpfCnpj, neighborhood, city, state, zipcode (obrigatórios
 * na documentação). addressNumber/street/addressComplement quando existirem no cadastro.
 *
 * @see https://docs.pagamentobancario.com.br/
 */
final readonly class PayerRegistrationData
{
    public function __construct(
        public string $name,
        public string $cpfCnpj,
        public string $neighborhood,
        public string $city,
        public string $state,
        public string $zipcode,
        public ?string $addressNumber = null,
        public ?string $street = null,
        public ?string $addressComplement = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->name !== ''
            && $this->cpfCnpj !== ''
            && $this->neighborhood !== ''
            && $this->city !== ''
            && $this->state !== ''
            && $this->zipcode !== '';
    }

    /**
     * Payload oficial de criação, sempre com statementActived=true.
     * Nunca inclui token, accounts ou credenciais.
     *
     * @return array<string, mixed>
     */
    public function toCreatePayload(): array
    {
        $payload = [
            'name' => mb_substr($this->name, 0, 250),
            'cpfCnpj' => $this->cpfCnpj,
            'neighborhood' => mb_substr($this->neighborhood, 0, 250),
            'city' => mb_substr($this->city, 0, 250),
            'state' => mb_substr($this->state, 0, 2),
            'zipcode' => mb_substr($this->zipcode, 0, 10),
            'statementActived' => true,
        ];

        if ($this->addressNumber !== null && $this->addressNumber !== '') {
            $payload['addressNumber'] = mb_substr($this->addressNumber, 0, 10);
        }

        if ($this->street !== null && $this->street !== '') {
            $payload['street'] = mb_substr($this->street, 0, 250);
        }

        if ($this->addressComplement !== null && $this->addressComplement !== '') {
            $payload['addressComplement'] = mb_substr($this->addressComplement, 0, 250);
        }

        return $payload;
    }

    public static function digitsOrNull(mixed $value): ?string
    {
        return CnpjNormalizer::digits($value);
    }
}
