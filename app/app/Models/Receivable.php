<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    public const SENIOR_FIELD_GROUPS = [
        'identificacao' => [
            'codEmp' => 'int', 'codFil' => 'int', 'numTit' => 'string', 'codTpt' => 'string',
            'codCli' => 'int', 'codTns' => 'string', 'codSnf' => 'string', 'codRep' => 'int',
            'codSac' => 'int', 'codCrp' => 'string', 'codNtg' => 'int', 'seqImo' => 'int',
            'dupCre' => 'string', 'titEfe' => 'string',
        ],
        'valores' => [
            'sitTit' => 'string', 'vlrOri' => 'money', 'vlrAbe' => 'money', 'vlrDsc' => 'money',
            'vlrBco' => 'money', 'codMoe' => 'string', 'codFpg' => 'int', 'codMpt' => 'string',
        ],
        'datas' => [
            'datEmi' => 'date', 'datEnt' => 'date', 'vctOri' => 'date', 'vctPro' => 'date',
            'datPpt' => 'date', 'datDsc' => 'date', 'datNeg' => 'date',
        ],
        'conta_centro_custo' => [
            'ctaFin' => 'int', 'ctaRed' => 'int', 'codCcu' => 'string', 'numPrj' => 'int',
            'codFpj' => 'int', 'codPor' => 'string', 'codCrt' => 'string',
        ],
        'origem_fiscal' => [
            'filNfc' => 'int', 'numNfc' => 'int', 'filNfv' => 'int', 'numNfv' => 'int',
            'filCtr' => 'int', 'numCtr' => 'int', 'obsTcr' => 'string',
        ],
    ];

    public const SITUACAO_LABELS = [
        'AB' => 'Aberto',
        'ABE' => 'Aberto',
        'NOR' => 'Normal',
        'PEN' => 'Pendente',
        'PAG' => 'Pago',
        'LIQ' => 'Liquidado',
        'BAI' => 'Baixado',
        'CAN' => 'Cancelado',
    ];

    public static function seniorColumn(string $code): string
    {
        return strtolower($code);
    }

    public static function seniorHeaderFields(): array
    {
        return array_merge(...array_values(self::SENIOR_FIELD_GROUPS));
    }

    public static function seniorColumns(): array
    {
        return array_map(
            fn (string $code) => self::seniorColumn($code),
            array_keys(self::seniorHeaderFields()),
        );
    }

    protected $fillable = [
        'title_number', 'customer_name', 'customer_document', 'amount', 'open_amount',
        'due_date', 'issue_date', 'description', 'category', 'branch_id', 'senior_id',
        'senior_situacao_original', 'senior_raw', 'senior_synced_at', 'senior_missing_at',
    ];

    public function getFillable()
    {
        return array_values(array_unique(array_merge(parent::getFillable(), self::seniorColumns())));
    }

    protected function casts(): array
    {
        $casts = [
            'amount' => 'decimal:2',
            'open_amount' => 'decimal:2',
            'due_date' => 'date',
            'issue_date' => 'date',
            'senior_missing_at' => 'datetime',
            'senior_synced_at' => 'datetime',
            'senior_raw' => 'array',
        ];

        foreach (self::seniorHeaderFields() as $code => $type) {
            $col = self::seniorColumn($code);
            $casts[$col] = match ($type) {
                'money' => 'decimal:2',
                'rate' => 'decimal:6',
                'date' => 'date',
                'int' => 'integer',
                default => 'string',
            };
        }

        return $casts;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rateios(): HasMany
    {
        return $this->hasMany(ReceivableRateio::class);
    }

    public function isMissingInSenior(): bool
    {
        return $this->senior_missing_at !== null;
    }

    public function scopeExcludeMissingInSenior($query)
    {
        return $query->whereNull('senior_missing_at');
    }

    public function isFromSenior(): bool
    {
        return filled($this->senior_id);
    }

    public function situacaoLabel(): string
    {
        $code = strtoupper(trim((string) ($this->senior_situacao_original ?? '')));

        return self::SITUACAO_LABELS[$code] ?? ($code !== '' ? $code : 'Indefinida');
    }

    public static function attachEmpresaNome(iterable $receivables): void
    {
        $items = collect($receivables);
        $codEmps = $items->map(fn (Receivable $r) => $r->codemp)->filter()->unique()->values();

        $map = $codEmps->isEmpty()
            ? collect()
            : \App\Models\Comercial\Filial::whereIn('cod_emp', $codEmps)
                ->get(['cod_emp', 'nome', 'fantasia', 'apelido'])
                ->groupBy('cod_emp')
                ->map(fn ($grupo) => $grupo->first()->apelido ?: $grupo->first()->fantasia ?: $grupo->first()->nome);

        foreach ($items as $r) {
            $r->setAttribute('empresa_nome', $r->codemp ? ($map[$r->codemp] ?? null) : null);
        }
    }

    public static function attachFilialNome(iterable $receivables): void
    {
        $items = collect($receivables);
        $codFils = $items->map(fn (Receivable $r) => $r->codfil)->filter()->unique()->values();

        $branchMap = $codFils->isEmpty()
            ? collect()
            : Branch::whereIn('code', $codFils->map(fn ($c) => (string) $c))->get()
                ->keyBy(fn (Branch $b) => (string) $b->code)
                ->map(fn (Branch $b) => $b->display_name);

        foreach ($items as $r) {
            $filial = $r->codfil ? ($branchMap[(string) $r->codfil] ?? null) : null;
            $r->setAttribute('filial_nome', $filial ?: $r->getAttribute('empresa_nome'));
        }
    }

    public static function attachOrigemSenior(iterable $receivables): void
    {
        foreach ($receivables as $receivable) {
            if ($receivable->isFromSenior()) {
                $receivable->setAttribute('origem_senior', true);
                $receivable->setAttribute('situacao_label', $receivable->situacaoLabel());
            }
        }
    }
}
