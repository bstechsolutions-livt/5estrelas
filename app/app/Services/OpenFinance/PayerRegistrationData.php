<?php

namespace App\Services\OpenFinance;

/**
 * Dados cadastrais necessários para POST /api/v1/payer (Open Finance / Extrato).
 *
 * Campos oficiais obrigatórios (docs.pagamentobancario.com.br):
 * name, cpfCnpj, neighborhood, city, state, zipcode.
 * addressNumber/street/addressComplement/email quando informados.
 * statementActived=true é obrigatório para Extrato Open Finance.
 *
 * @see https://docs.pagamentobancario.com.br/
 * @see https://atendimento.tecnospeed.com.br/hc/pt-br/articles/35691080001047-Criar-Pagador-e-Conta-para-Extrato-Open-Finance
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
        public ?string $email = null,
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
     * @return list<string>
     */
    public function missingFields(): array
    {
        $missing = [];
        foreach ([
            'name' => $this->name,
            'cpf_cnpj' => $this->cpfCnpj,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
        ] as $field => $value) {
            if ($value === '') {
                $missing[] = $field;
            }
        }

        return $missing;
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

        if ($this->email !== null && $this->email !== '') {
            $payload['email'] = mb_substr($this->email, 0, 250);
        }

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
}
