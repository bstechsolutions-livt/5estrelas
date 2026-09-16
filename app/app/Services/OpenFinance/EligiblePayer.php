<?php

namespace App\Services\OpenFinance;

/**
 * CNPJ elegível do sistema (empresa/filial) para ser pagador Open Finance.
 * Dados cadastrais vêm do cadastro existente; não duplica razão social/endereço.
 */
final readonly class EligiblePayer
{
    public function __construct(
        public string $cpfCnpj,
        public string $name,
        public string $companyLabel,
        public ?int $comercialFilialId,
        public ?int $branchId,
        public ?PayerRegistrationData $registration,
    ) {}

    public function canRegister(): bool
    {
        return $this->registration !== null && $this->registration->isComplete();
    }
}
