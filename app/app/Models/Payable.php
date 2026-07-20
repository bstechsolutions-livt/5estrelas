<?php

namespace App\Models;

use App\Services\Senior\SupplierDisplayNameResolver;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    use Auditable;

    /**
     * A4 (feedback do cliente): lançamento manual criado sem vencimento recebe
     * vencimento automático de 72h (3 dias), empurrando para o próximo dia útil
     * se cair em fim de semana. Títulos vindos da Senior (com senior_id) mantêm
     * o vencimento real (vctOri/vctPro) — a regra NÃO se aplica a eles.
     */
    protected static function booted(): void
    {
        static::creating(function (Payable $payable) {
            if (empty($payable->due_date) && empty($payable->senior_id)) {
                $payable->due_date = self::defaultDueDate();
            }
        });
    }

    /** Vencimento default = data base + 3 dias corridos, rolando p/ dia útil. */
    public static function defaultDueDate($from = null): \Illuminate\Support\Carbon
    {
        $date = ($from ? \Illuminate\Support\Carbon::parse($from) : \Illuminate\Support\Carbon::now())
            ->startOfDay()
            ->addDays(3);

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    /**
     * Campos de WORKFLOW interno do 5estrelas. NÃO são sobrescritos pela
     * sincronização com a Senior (ver Payables_Sync / requirement 4 e 8).
     */
    public const WORKFLOW_FIELDS = [
        'status', 'prepared_by', 'approved_by', 'sent_for_approval_at',
        'approved_at', 'rejection_reason', 'bordero_id', 'department_id',
        'nickname',
        'paid_at', 'payment_method', 'paid_by',
        'payment_priority', 'payment_sla_date', 'priority_set_by', 'priority_set_at',
        'conciliated_at', 'conciliated_by', 'conciliation_notes', 'divergence_reason',
        'allocation_imported_at', 'allocation_imported_by', 'allocation_source_file',
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
            'codFor' => 'int', 'codFav' => 'int', 'codTns' => 'string', 'codNtg' => 'int', 'docIdeFav' => 'string',
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
        'title_number', 'nickname', 'supplier_name', 'supplier_cnpj', 'amount',
        'due_date', 'issue_date', 'description', 'category', 'status',
        'branch_id', 'department_id', 'senior_cod_usu', 'prepared_by', 'approved_by', 'sent_for_approval_at',
        'approved_at', 'rejection_reason', 'bordero_id', 'senior_id',
        'paid_at', 'payment_method', 'paid_by',
        'payment_priority', 'payment_sla_date', 'priority_set_by', 'priority_set_at',
        'conciliated_at', 'conciliated_by', 'conciliation_notes', 'divergence_reason',
        'allocation_imported_at', 'allocation_imported_by', 'allocation_source_file',
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
            'payment_sla_date' => 'date',
            'priority_set_at' => 'datetime',
            'conciliated_at' => 'date',
            'allocation_imported_at' => 'datetime',
            'senior_missing_at' => 'datetime',
            'senior_synced_at' => 'datetime',
            'senior_raw' => 'array',
            'senior_cod_usu' => 'integer',
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

    public const STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO = 'aguardando_vinculo_departamento';

    // Status labels
    public const STATUS_LABELS = [
        'pendente' => 'Pendente',
        self::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO => 'Aguard. sincronização',
        'em_preparacao' => 'Em Preparação',
        'aguardando_aprovacao' => 'Em Aprovação',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'pago' => 'Pago',
        'aguardando_conciliacao' => 'Aguardando Conciliação',
        'conciliado' => 'Conciliado',
        'divergente' => 'Divergente',
        'encerrado' => 'Encerrado',
    ];

    public const STATUS_COLORS = [
        'pendente' => 'warn',
        self::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO => 'secondary',
        'em_preparacao' => 'info',
        'aguardando_aprovacao' => 'warn',
        'aprovado' => 'success',
        'reprovado' => 'danger',
        'pago' => 'success',
        'aguardando_conciliacao' => 'warn',
        'conciliado' => 'success',
        'divergente' => 'danger',
        'encerrado' => 'info',
    ];

    public const PRIORITY_LABELS = [
        'normal' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const PRIORITY_VALUES = ['normal', 'alta', 'urgente'];

    public const PRIORITY_COLORS = [
        'normal' => 'secondary',
        'alta' => 'warn',
        'urgente' => 'danger',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class);
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

    public function prioritySetter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_set_by');
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

    public function allocationLines(): HasMany
    {
        return $this->hasMany(PayableAllocationLine::class)->orderBy('line_order')->orderBy('id');
    }

    public function allocationImporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocation_imported_by');
    }

    /** Título existe localmente mas não consta mais na Senior (baixado/excluído). */
    public function isMissingInSenior(): bool
    {
        return $this->senior_missing_at !== null;
    }

    /** Exclui títulos baixados na Senior das listagens padrão de CP. */
    public function scopeExcludeMissingInSenior($query)
    {
        return $query->whereNull('senior_missing_at');
    }

    /** Título foi reprovado no fluxo e devolvido para pendente (aguarda correção). */
    public function wasRejectedBack(): bool
    {
        return $this->status === 'pendente' && filled($this->rejection_reason);
    }

    /** Importado da Senior aguardando dept/fornecedor na sincronização. */
    public function isAwaitingDepartmentLink(): bool
    {
        return $this->status === self::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO;
    }

    /** @alias isAwaitingDepartmentLink */
    public function isAwaitingSeniorSync(): bool
    {
        return $this->isAwaitingDepartmentLink();
    }

    /** Motivo legível quando status = aguardando sincronização. */
    public function awaitingSyncDetail(): ?string
    {
        if (! $this->isAwaitingSeniorSync()) {
            return null;
        }

        $parts = [];
        if ($this->department_id === null) {
            $parts[] = 'departamento';
        }
        if ((new \App\Services\Senior\SupplierDisplayNameResolver())->isGeneric($this->supplier_name)) {
            $parts[] = 'fornecedor';
        }

        if ($parts === []) {
            return 'Dados Senior pendentes';
        }

        return 'Aguardando '.implode(' e ', $parts).' na sincronização';
    }

    /**
     * A3 (feedback do cliente): a tela principal mostra a EMPRESA por NOME,
     * nunca por código. O nome vem da tabela de filiais/empresas espelhada da
     * Senior (bs_comercial_filiais), resolvida pelo codEmp do título.
     *
     * Resolve em LOTE e injeta `empresa_nome` (apelido da empresa / cod_emp).
     *
     * @param iterable<Payable> $payables
     */
    public static function attachEmpresaNome(iterable $payables): void
    {
        $items = collect($payables);

        $codEmps = $items
            ->map(fn (Payable $p) => $p->codemp)
            ->filter(fn ($c) => $c !== null)
            ->unique()
            ->values();

        $map = $codEmps->isEmpty()
            ? collect()
            : \App\Models\Comercial\Filial::whereIn('cod_emp', $codEmps)
                ->where('ativo', true)
                ->get(['cod_emp', 'cod_fil', 'nome', 'fantasia', 'apelido'])
                ->groupBy('cod_emp')
                ->map(function ($grupo) {
                    $row = $grupo->firstWhere('cod_fil', 1) ?? $grupo->first();

                    return $row->apelido ?: $row->fantasia ?: $row->nome;
                });

        foreach ($items as $p) {
            $p->setAttribute('empresa_nome', $p->codemp ? ($map[$p->codemp] ?? null) : null);
        }
    }

    /** Título criado na intranet (sem Business Key da Senior). */
    public function isHubManual(): bool
    {
        return empty($this->senior_id);
    }

    /** Título importado via sync Senior. */
    public function isFromSenior(): bool
    {
        return ! $this->isHubManual();
    }

    /**
     * Marca títulos criados no Hub (futuro: lançamento manual na intranet).
     * Títulos Senior não recebem badge — evita ruído enquanto a base é 100% Senior.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachOrigemHub(iterable $payables): void
    {
        foreach ($payables as $payable) {
            if ($payable->isHubManual()) {
                $payable->setAttribute('origem_hub', true);
            }
        }
    }

    /**
     * Marca títulos importados da Senior (sync ERP).
     *
     * @param iterable<Payable> $payables
     */
    public static function attachOrigemSenior(iterable $payables): void
    {
        foreach ($payables as $payable) {
            if ($payable->isFromSenior()) {
                $payable->setAttribute('origem_senior', true);
            }
        }
    }

    /** @return array<string, 'senior'|'hub'> */
    public static function fieldOriginsForSenior(): array
    {
        return [
            'supplier_name' => 'senior',
            'amount' => 'senior',
            'due_date' => 'senior',
            'title_number' => 'senior',
            'empresa_nome' => 'senior',
            'filial_nome' => 'senior',
            'supplier_cnpj' => 'senior',
            'issue_date' => 'senior',
            'codntg' => 'senior',
            'codccu' => 'senior',
            'ctafin' => 'senior',
            'department_nome' => 'hub',
            'description' => 'senior',
            'launcher_nome' => 'senior',
            'payment_priority' => 'hub',
            'payment_sla_date' => 'hub',
            'documents' => 'hub',
            'comments' => 'hub',
            'status' => 'hub',
            'bordero' => 'hub',
        ];
    }

    /**
     * Nome de quem lançou o título na Senior (UsuGer → payables.senior_cod_usu → users.senior_cod_usu).
     * Sem usuário intranet mapeado: label com o código Senior.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachLauncherNome(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $cods = $items
            ->map(fn (Payable $p) => (int) ($p->senior_cod_usu ?? 0))
            ->filter(fn (int $c) => $c > 0)
            ->unique()
            ->values();

        $usersByCod = $cods->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('senior_cod_usu', $cods->all())
                ->get(['id', 'name', 'senior_cod_usu'])
                ->groupBy(fn (User $u) => (int) $u->senior_cod_usu)
                ->map(fn ($group) => $group->first());

        foreach ($items as $p) {
            $cod = (int) ($p->senior_cod_usu ?? 0);
            if ($cod <= 0) {
                $p->setAttribute('launcher_nome', null);
                $p->setAttribute('launcher_label', null);

                continue;
            }

            $nome = $usersByCod->get($cod)?->name;
            $p->setAttribute('launcher_nome', $nome);
            $p->setAttribute(
                'launcher_label',
                $nome ?: "Usuário Senior #{$cod}",
            );
        }
    }

    public static function attachFieldOrigins(Payable $payable): void
    {
        if (! $payable->isFromSenior()) {
            return;
        }

        $payable->setAttribute('field_origins', self::fieldOriginsForSenior());
    }

    /**
     * Apelido da filial operacional (cod_emp + cod_fil), foco da intranet.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachFilialNome(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $pairs = $items
            ->filter(fn (Payable $p) => $p->codemp && $p->codfil)
            ->map(fn (Payable $p) => (int) $p->codemp . '-' . (int) $p->codfil)
            ->unique()
            ->values();

        $branchByPair = $pairs->isEmpty()
            ? collect()
            : Branch::query()
                ->where('is_active', true)
                ->whereNotNull('cod_emp')
                ->whereNotNull('cod_fil')
                ->get()
                ->keyBy(fn (Branch $b) => $b->cod_emp . '-' . $b->cod_fil);

        $comercialByPair = $pairs->isEmpty()
            ? collect()
            : \App\Models\Comercial\Filial::query()
                ->where('ativo', true)
                ->get(['cod_emp', 'cod_fil', 'apelido', 'nome', 'fantasia'])
                ->keyBy(fn ($f) => $f->cod_emp . '-' . $f->cod_fil);

        foreach ($items as $p) {
            $nome = null;

            if ($p->relationLoaded('branch') && $p->branch) {
                $nome = $p->branch->operationalFilialName();
            } else {
                $key = ($p->codemp && $p->codfil) ? ((int) $p->codemp . '-' . (int) $p->codfil) : null;

                if ($key && $branchByPair->has($key)) {
                    $nome = $branchByPair[$key]->operationalFilialName();
                } elseif ($key && $comercialByPair->has($key)) {
                    $f = $comercialByPair[$key];
                    $nome = $f->apelido ?: $f->fantasia ?: $f->nome;
                } elseif ($p->codemp && $p->codfil) {
                    $nome = \App\Models\Comercial\Filial::apelidoFilial((int) $p->codemp, (int) $p->codfil);
                }
            }

            $p->setAttribute('filial_nome', $nome);
            $p->setAttribute('filial_label', self::formatFilialLabel($p->codfil, $nome));
        }
    }

    /**
     * Nome do fornecedor para exibição (cadastro Senior → nome válido → GFD/TRCT → código).
     *
     * @param iterable<Payable> $payables
     */
    public static function attachSupplierDisplayName(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $pairs = $items
            ->filter(fn (Payable $p) => $p->codemp && $p->codfor)
            ->map(fn (Payable $p) => (int) $p->codemp . '-' . (int) $p->codfor)
            ->unique()
            ->values();

        $supplierByPair = $pairs->isEmpty()
            ? collect()
            : SeniorSupplier::query()
                ->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$codEmp, $codFor] = explode('-', $pair, 2);
                        $q->orWhere(fn ($qq) => $qq->where('cod_emp', (int) $codEmp)->where('cod_for', (int) $codFor));
                    }
                })
                ->get()
                ->keyBy(fn (SeniorSupplier $s) => $s->cod_emp . '-' . $s->cod_for);

        $resolver = new SupplierDisplayNameResolver();

        foreach ($items as $p) {
            $key = ($p->codemp && $p->codfor) ? ((int) $p->codemp . '-' . (int) $p->codfor) : null;
            $supplier = $key && $supplierByPair->has($key) ? $supplierByPair[$key] : null;
            $p->setAttribute('supplier_display_name', $resolver->resolveForPayable($p, $supplier));
        }
    }

    public static function formatFilialLabel(?int $codFil, ?string $nome): ?string
    {
        $nome = filled($nome) ? trim($nome) : null;

        if (! $codFil && ! $nome) {
            return null;
        }

        if ($codFil) {
            $cod = str_pad((string) (int) $codFil, 2, '0', STR_PAD_LEFT);

            return $nome ? "Filial {$cod} — {$nome}" : "Filial {$cod}";
        }

        return $nome;
    }

    /**
     * Nome do departamento (workflow ou lançador Senior → usuário intranet). Sem fallback.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachDepartmentNome(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $classifier = app(\App\Services\PayableDepartmentClassifier::class);

        foreach ($items as $p) {
            $dept = $classifier->departmentForPayable($p);
            $p->setAttribute('department_nome', $dept?->name);
        }
    }

    /**
     * Labels de centro de custo (codCcu) e conta financeira (ctaFin) via chart_of_accounts / regras.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachAccountingLabels(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $ccuCodes = $items
            ->map(fn (Payable $p) => trim((string) ($p->codccu ?? '')))
            ->filter(fn (string $c) => $c !== '' && $c !== '0')
            ->unique()
            ->values();

        $ctaCodes = $items
            ->map(fn (Payable $p) => (int) ($p->ctafin ?? 0))
            ->filter(fn (int $c) => $c > 0)
            ->map(fn (int $c) => (string) $c)
            ->unique()
            ->values();

        $ccuRows = $ccuCodes->isEmpty()
            ? collect()
            : ChartOfAccount::query()
                ->where('account_type', ChartOfAccount::TYPE_CENTRO_CUSTO)
                ->whereIn('code', $ccuCodes)
                ->get();

        $ctaRows = $ctaCodes->isEmpty()
            ? collect()
            : ChartOfAccount::query()
                ->where('account_type', ChartOfAccount::TYPE_CONTA_FINANCEIRA)
                ->whereIn('code', $ctaCodes)
                ->get();

        foreach ($items as $p) {
            $codccu = trim((string) ($p->codccu ?? ''));
            $ccuNome = null;
            if ($codccu !== '' && $codccu !== '0') {
                $ccuNome = self::resolveChartDescription(
                    $ccuRows,
                    $codccu,
                    $p->codemp !== null ? (int) $p->codemp : null,
                    ChartOfAccount::TYPE_CENTRO_CUSTO,
                );
            }

            $ctafin = (int) ($p->ctafin ?? 0);
            $ctaNome = null;
            $ctaCode = $ctafin > 0 ? (string) $ctafin : null;
            if ($ctaCode) {
                $ctaNome = self::resolveChartDescription(
                    $ctaRows,
                    $ctaCode,
                    $p->codemp !== null ? (int) $p->codemp : null,
                    ChartOfAccount::TYPE_CONTA_FINANCEIRA,
                );
            }

            $p->setAttribute('centro_custo_nome', $ccuNome);
            $p->setAttribute(
                'centro_custo_label',
                $codccu === '' || $codccu === '0'
                    ? null
                    : ($ccuNome && $ccuNome !== $codccu && ! str_starts_with($ccuNome, 'Centro de custo ')
                        ? "{$ccuNome} ({$codccu})"
                        : $codccu),
            );
            $p->setAttribute('conta_financeira', $ctaCode);
            $p->setAttribute('conta_financeira_nome', $ctaNome);
            $p->setAttribute(
                'conta_financeira_label',
                $ctaCode === null
                    ? null
                    : ($ctaNome && $ctaNome !== $ctaCode && ! str_starts_with($ctaNome, 'Conta financeira ')
                        ? "{$ctaNome} ({$ctaCode})"
                        : $ctaCode),
            );
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, ChartOfAccount> $rows
     */
    private static function resolveChartDescription(
        \Illuminate\Support\Collection $rows,
        string $code,
        ?int $codemp,
        string $accountType,
    ): ?string {
        $match = null;
        if ($codemp !== null) {
            $match = $rows->first(
                fn (ChartOfAccount $a) => $a->code === $code && (int) $a->codemp === $codemp,
            );
        }
        $match ??= $rows->first(fn (ChartOfAccount $a) => $a->code === $code);

        $description = filled($match?->description)
            ? trim((string) $match->description)
            : ChartOfAccount::deriveDescription($accountType, $code, $codemp);

        $generic = $accountType === ChartOfAccount::TYPE_CENTRO_CUSTO
            ? 'Centro de custo '.$code
            : 'Conta financeira '.$code;

        if ($description === '' || $description === $generic) {
            return $description === $generic ? null : ($description !== '' ? $description : null);
        }

        return $description;
    }

    /** ok | warning (≤3 dias) | overdue — null se sem SLA ou já pago/encerrado. */
    public static function resolveSlaStatus(Payable $payable): ?string
    {
        if (! $payable->payment_sla_date) {
            return null;
        }

        if (in_array($payable->status, ['pago', 'aguardando_conciliacao', 'conciliado', 'encerrado'], true)) {
            return null;
        }

        $today = now()->startOfDay();
        $sla = $payable->payment_sla_date->copy()->startOfDay();

        if ($sla->lt($today)) {
            return 'overdue';
        }

        if ($sla->lte($today->copy()->addDays(3))) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * @param iterable<Payable> $payables
     */
    public static function attachPriorityMeta(iterable $payables): void
    {
        foreach ($payables as $payable) {
            $payable->setAttribute(
                'priority_label',
                $payable->payment_priority
                    ? (self::PRIORITY_LABELS[$payable->payment_priority] ?? $payable->payment_priority)
                    : null,
            );
            $payable->setAttribute('sla_status', self::resolveSlaStatus($payable));
        }
    }

    /**
     * Momento atual no fluxo (etapa de workflow), para a listagem de CP.
     *
     * @param iterable<Payable> $payables
     */
    public static function attachWorkflowMoment(iterable $payables): void
    {
        $items = collect($payables);
        if ($items->isEmpty()) {
            return;
        }

        $awaitingIds = $items
            ->filter(fn (Payable $p) => $p->status === 'aguardando_aprovacao')
            ->pluck('id')
            ->values();

        $pendingSteps = $awaitingIds->isEmpty()
            ? collect()
            : ApprovalStep::query()
                ->whereIn('payable_id', $awaitingIds)
                ->where('status', 'pendente')
                ->with('assignee:id,name')
                ->orderBy('order')
                ->get()
                ->groupBy('payable_id')
                ->map(fn ($steps) => $steps->first());

        foreach ($items as $payable) {
            [$moment, $detail, $tone] = self::resolveWorkflowMoment(
                $payable,
                $pendingSteps->get($payable->id),
            );
            $payable->setAttribute('workflow_moment', $moment);
            $payable->setAttribute('workflow_moment_detail', $detail);
            $payable->setAttribute('workflow_moment_tone', $tone);
        }
    }

    /**
     * @return array{0: string, 1: ?string, 2: string}
     */
    private static function resolveWorkflowMoment(Payable $payable, ?ApprovalStep $currentStep): array
    {
        return match ($payable->status) {
            'pendente' => $payable->wasRejectedBack()
                ? ['Recusado — corrigir', null, 'danger']
                : ($payable->bordero_id
                    ? ['No borderô', null, 'info']
                    : ['Aguardando envio', null, 'warn']),
            self::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO => [
                'Aguard. sincronização',
                $payable->awaitingSyncDetail(),
                'secondary',
            ],
            'em_preparacao' => ['Em preparação', null, 'info'],
            'aguardando_aprovacao' => self::workflowMomentFromApprovalStep($currentStep),
            'aprovado' => ['Aguardando pagamento', null, 'success'],
            'pago', 'aguardando_conciliacao' => ['Aguardando conciliação', 'Pago no banco', 'warn'],
            default => [
                self::STATUS_LABELS[$payable->status] ?? $payable->status,
                null,
                self::STATUS_COLORS[$payable->status] ?? 'secondary',
            ],
        };
    }

    /**
     * @return array{0: string, 1: ?string, 2: string}
     */
    private static function workflowMomentFromApprovalStep(?ApprovalStep $step): array
    {
        if (! $step) {
            return ['Fluxo não iniciado', 'Etapa de aprovação ausente', 'warn'];
        }

        $stepLabel = $step->role_label
            ?: (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name);
        $assignee = $step->assignee?->name;

        if ($assignee) {
            return [$assignee, $stepLabel, 'warn'];
        }

        return [$stepLabel, 'Aguardando aprovador', 'warn'];
    }
}
