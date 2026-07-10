<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalTrail extends Model
{
    protected $fillable = [
        'area', 'order', 'level_name', 'role_label', 'approver_type',
        'default_user_id', 'approver_department_id',
    ];

    public const TYPE_GESTOR_DEPTO = 'gestor_depto';

    public const TYPE_DIRETOR_DEPTO = 'diretor_depto';

    public const TYPE_DEPT_FINANCEIRO = 'dept_financeiro';

    public const TYPE_USUARIO = 'usuario';

    public const TYPE_DEPARTAMENTO = 'departamento';

    public const APPROVER_TYPES = [
        self::TYPE_GESTOR_DEPTO => 'Gestor do departamento',
        self::TYPE_DIRETOR_DEPTO => 'Diretor do departamento',
        self::TYPE_DEPT_FINANCEIRO => 'Equipe do Financeiro',
        self::TYPE_USUARIO => 'Usuário específico',
        self::TYPE_DEPARTAMENTO => 'Departamento específico',
    ];

    /** @deprecated Use approver_type */
    public const LEVELS_FROM_DEPARTMENT = ['departamento', 'diretoria'];

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    public function approverDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'approver_department_id');
    }

    public function effectiveApproverType(): string
    {
        if ($this->approver_type) {
            return $this->approver_type;
        }

        return match ($this->level_name) {
            'departamento' => self::TYPE_GESTOR_DEPTO,
            'diretoria' => self::TYPE_DIRETOR_DEPTO,
            'financeiro' => self::TYPE_DEPT_FINANCEIRO,
            default => self::TYPE_USUARIO,
        };
    }

    public static function trailFor(string $area): \Illuminate\Support\Collection
    {
        return static::where('area', $area)->orderBy('order')->get();
    }

    public static function areaLabels(): array
    {
        $fromDb = ApprovalFlowArea::orderBy('label')->pluck('label', 'area')->all();

        return array_merge(self::AREAS, $fromDb);
    }

    /**
     * Áreas compostas / especiais (não editáveis como fluxo único).
     */
    public const COMPOSITE_AREAS = ['baluarte', 'multi_star'];

    /**
     * Áreas padrão (fallback de rótulo).
     */
    public const AREAS = [
        'matriz' => 'Matriz',
        'filiais' => 'Filiais',
        'compras' => 'Compras',
        'modernizacao' => 'Modernização',
        'comercial' => 'Comercial / Faturamento / Marketing',
        'licitacao' => 'Licitação',
        'dp_rh' => 'DP / RH',
        'juridico' => 'Jurídico',
        'financeiro' => 'Financeiro',
        'baluarte' => 'Baluarte (Matriz + Comercial)',
        'multi_star' => 'Multi / Star (pré-aprovação Luiz Farias)',
    ];
}
