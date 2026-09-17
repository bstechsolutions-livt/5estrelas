<?php

namespace App\Models;

use App\Services\OpenFinance\PayerRegistrationData;
use App\Support\CnpjNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenFinancePayer extends Model
{
    public const PROVIDER_TECNOSPEED = 'tecnospeed';

    protected $fillable = [
        'branch_id',
        'environment',
        'cpf_cnpj',
        'name',
        'email',
        'zipcode',
        'street',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'provider',
        'statement_activated',
        'provider_status',
        'provider_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_activated' => 'boolean',
            'provider_synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(OpenFinanceAccountConnection::class);
    }

    public function registrationData(): PayerRegistrationData
    {
        return new PayerRegistrationData(
            name: (string) $this->name,
            cpfCnpj: (string) $this->cpf_cnpj,
            neighborhood: (string) ($this->neighborhood ?? ''),
            city: (string) ($this->city ?? ''),
            state: (string) ($this->state ?? ''),
            zipcode: CnpjNormalizer::digits($this->zipcode) ?? (string) ($this->zipcode ?? ''),
            addressNumber: $this->address_number,
            street: $this->street,
            addressComplement: $this->address_complement,
            email: $this->email,
        );
    }

    public function hasCompleteRegistration(): bool
    {
        return $this->registrationData()->isComplete();
    }
}
