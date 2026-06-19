<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    use Auditable;

    /**
     * Campos de WORKFLOW interno do 5estrelas. NÃO são sobrescritos pela
     * sincronização com a Senior (ver Payables_Sync / requirement 4 e 8).
     */
    public const WORKFLOW_FIELDS = [
        'status', 'prepared_by', 'approved_by', 'sent_for_approval_at',
        'approved_at', 'rejection_reason', 'bordero_id',
        'paid_at', 'payment_method', 'paid_by',
        'conciliated_at', 'conciliated_by', 'conciliation_notes', 'divergence_reason',
    ];

    /** Formas de pagamento aceitas no registro de pagamento (Spec alçada+pagamento). */
    public const PAYMENT_METHODS = [
        'PIX' => 'PIX',
        'TED' => 'TED',
        'Boleto' => 'Boleto',
        'Dinheiro' => 'Dinheiro',
        'Outro' => 'Outro',
    ];

    /**
     * Campos de cabeçalho da Senior (ConsultarTitulosAbertosCP v3 — Apêndice A.2),
     * agrupados por categoria semântica. Reutilizado pela migration, pelo
     * Payable_Mapper, pelo DemoSeeder e pelo agrupamento do Payable_Details_View.
     *
     * Chave = código Senior (camelCase). Valor = tipo lógico:
     *   int | string | money | rate | date.
     * A coluna no banco é o código em minúsculas (ver seniorColumn()).
     */
    public const SENIOR_FIELD_GROUPS = [
        'identificacao' => [
            'codEmp' => 'int', 'codFil' => 'int', 'numTit' => 'string', 'codTpt' => 'string',
            'codFor' => 'int', 'codTns' => 'string', 'codNtg' => 'int', 'docIdeFav' => 'string',
            'codDfs' => 'int', 'codFrj' => 'string', 'cpgSub' => 'string', 'gerTep' => 'string',
            'seqCgt' => 'int', 'seqImo' => 'int', 'tipEfe' => 'string',
        ],
        'valores' => [
            'sitTit' => 'string', 'vlrOri' => 'money', 'vlrAbe' => 'money', 'vlrDsc' => 'money',
            'codMoe' => 'string', 'codFpg' => 'int', 'codMpt' => 'string',
        ],
        'datas' => [
            'datEmi' => 'date', 'datEnt' => 'date', 'vctOri' => 'date', 'vctPro' => 'date',
            'datPpt' => 'date', 'ultPgt' => 'date', 'datDsc' => 'date',
        ],
        'juros_descontos' => [
            'perDsc' => 'int', 'tolDsc' => 'int', 'antDsc' => 'string', 'perJrs' => 'int',
            'jrsDia' => 'rate', 'tipJrs' => 'string', 'tolJrs' => 'int', 'proJrs' => 'string',
            'perMul' => 'int', 'tolMul' => 'int', 'datNeg' => 'date', 'jrsNeg' => 'money',
            'mulNeg' => 'money', 'dscNeg' => 'money', 'outNeg' => 'money',
        ],
        'conta_centro_custo' => [
            'ctaFin' => 'int', 'ctaRed' => 'int', 'codCcu' => 'string', 'numPrj' => 'int',
            'codFpj' => 'int', 'codPor' => 'string', 'codCrt' => 'string', 'filCcr' => 'int',
            'numCcr' => 'int',
        ],
        'origem_fiscal' => [
            'filNfc' => 'int', 'numNfc' => 'int', 'forNfc' => 'int', 'snfNfc' => 'string',
            'filCtr' => 'int', 'numCtr' => 'int', 'ctrFre' => 'int', 'ctrNre' => 'int',
            'filNff' => 'int', 'numNff' => 'int', 'forNff' => 'int', 'filNfv' => 'int',
            'numNfv' => 'int', 'snfNfv' => 'string', 'filOcp' => 'int', 'numOcp' => 'int',
            'ocpFre' => 'int', 'ocpNre' => 'int', 'obsTcp' => 'string',
        ],
    ];

    /** Coluna no banco para um código Senior (codEmp -> codemp). */
    public static function seniorColumn(string $code): string
    {
        return strtolower($code);
    }

    /** Lista plana [codigoSenior => tipo] de todos os campos de cabeçalho. */
    public static function seniorHeaderFields(): array
    {
        return array_merge(...array_values(self::SENIOR_FIELD_GROUPS));
    }

    /** Lista de colunas (snake/lower) de origem Senior no banco. */
    public static function seniorColumns(): array
    {
        return array_map(
            fn (string $code) => self::seniorColumn($code),
            array_keys(self::seniorHeaderFields()),
        );
    }

    protected $fillable = [
        'title_number', 'supplier_name', 'supplier_cnpj', 'amount',
        'due_date', 'issue_date', 'description', 'category', 'status',
        'branch_id', 'prepared_by', 'approved_by', 'sent_for_approval_at',
        'approved_at', 'rejection_reason', 'bordero_id', 'senior_id',
        'paid_at', 'payment_method', 'paid_by',
        'conciliated_at', 'conciliated_by', 'conciliation_notes', 'divergence_reason',
        'senior_situacao_original', 'senior_missing_at', 'senior_raw',
        'senior_synced_at',
    ];

    /**
     * As colunas de origem Senior (Apêndice A.2) são adicionadas ao fillable
     * dinamicamente em getFillable() a partir de seniorColumns().
     */
    public function getFillable()
    {
        return array_values(array_unique(array_merge(parent::getFillable(), self::seniorColumns())));
    }

    protected function casts(): array
    {
        $casts = [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'issue_date' => 'date',
            'sent_for_approval_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'date',
            'conciliated_at' => 'date',
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

    protected string $auditableModule = 'financeiro.contas_pagar';
    protected string $auditableEventPrefix = 'contas_pagar';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Título {$this->title_number} criado - {$this->supplier_name} R$ {$this->amount}",
            'updated' => "Título {$this->title_number} atualizado",
            'deleted' => "Título {$this->title_number} excluído",
            default => null,
        };
    }

    // Status labels
    public const STATUS_LABELS = [
        'pendente' => 'Pendente',
        'em_preparacao' => 'Em Preparação',
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'pago' => 'Pago',
        'conciliado' => 'Conciliado',
        'divergente' => 'Divergente',
    ];

    public const STATUS_COLORS = [
        'pendente' => 'warn',
        'em_preparacao' => 'info',
        'aguardando_aprovacao' => 'warn',
        'aprovado' => 'success',
        'reprovado' => 'danger',
        'pago' => 'success',
        'conciliado' => 'success',
        'divergente' => 'danger',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function conciliator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliated_by');
    }

    public function bordero(): BelongsTo
    {
        return $this->belongsTo(Bordero::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PayableDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PayableComment::class)->orderBy('created_at');
    }

    public function rateios(): HasMany
    {
        return $this->hasMany(PayableRateio::class);
    }

    /** Título existe localmente mas não consta mais na Senior (baixado/excluído). */
    public function isMissingInSenior(): bool
    {
        return $this->senior_missing_at !== null;
    }
}
