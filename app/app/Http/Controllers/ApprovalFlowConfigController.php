<?php

namespace App\Http\Controllers;

use App\Models\ApprovalFlowArea;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Configuração dinâmica de fluxos de aprovação por área.
 */
class ApprovalFlowConfigController extends Controller
{
    public function index()
    {
        $areaLabels = ApprovalTrail::areaLabels();
        $trails = ApprovalTrail::with(['defaultUser:id,name', 'approverDepartment:id,name'])
            ->orderBy('area')->orderBy('order')->get()
            ->groupBy('area')
            ->map(fn ($levels, $area) => [
                'area' => $area,
                'area_label' => $areaLabels[$area] ?? $area,
                'is_composite' => in_array($area, ApprovalTrail::COMPOSITE_AREAS, true),
                'levels' => $levels->map(fn ($l) => [
                    'id' => $l->id,
                    'order' => $l->order,
                    'level_name' => $l->level_name,
                    'role_label' => $l->role_label,
                    'approver_type' => $l->effectiveApproverType(),
                    'default_user_id' => $l->default_user_id,
                    'default_user_name' => $l->defaultUser?->name,
                    'approver_department_id' => $l->approver_department_id,
                    'approver_department_name' => $l->approverDepartment?->name,
                ])->values(),
            ])->values();

        foreach (ApprovalTrail::COMPOSITE_AREAS as $composite) {
            if ($trails->firstWhere('area', $composite)) {
                continue;
            }
            $trails->push([
                'area' => $composite,
                'area_label' => $areaLabels[$composite] ?? $composite,
                'is_composite' => true,
                'levels' => [],
            ]);
        }

        return Inertia::render('Approvals/FlowConfig', [
            'trails' => $trails,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'approverTypes' => ApprovalTrail::APPROVER_TYPES,
            'compositeAreas' => ApprovalTrail::COMPOSITE_AREAS,
        ]);
    }

    public function update(Request $request)
    {
        $validTypes = array_keys(ApprovalTrail::APPROVER_TYPES);

        $data = $request->validate([
            'trails' => ['required', 'array'],
            'trails.*.area' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'trails.*.area_label' => ['required', 'string', 'max:120'],
            'trails.*.levels' => ['array'],
            'trails.*.levels.*.id' => ['nullable', 'integer', 'exists:approval_trails,id'],
            'trails.*.levels.*.order' => ['required', 'integer', 'min:1'],
            'trails.*.levels.*.role_label' => ['required', 'string', 'max:120'],
            'trails.*.levels.*.approver_type' => ['required', Rule::in($validTypes)],
            'trails.*.levels.*.default_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'trails.*.levels.*.approver_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'deleted_areas' => ['array'],
            'deleted_areas.*' => ['string', 'max:50'],
        ]);

        DB::transaction(function () use ($data) {
            if (! empty($data['deleted_areas'])) {
                foreach ($data['deleted_areas'] as $area) {
                    if (in_array($area, ApprovalTrail::COMPOSITE_AREAS, true)) {
                        continue;
                    }
                    ApprovalTrail::where('area', $area)->delete();
                    ApprovalFlowArea::where('area', $area)->delete();
                }
            }

            foreach ($data['trails'] as $trailData) {
                $area = $trailData['area'];
                if (str_starts_with($area, 'area_')) {
                    $area = \Illuminate\Support\Str::slug($trailData['area_label'], '_');
                    if ($area === '') {
                        continue;
                    }
                }
                if (in_array($area, ApprovalTrail::COMPOSITE_AREAS, true)) {
                    continue;
                }

                ApprovalFlowArea::updateOrCreate(
                    ['area' => $area],
                    ['label' => $trailData['area_label']],
                );

                $incomingIds = collect($trailData['levels'] ?? [])
                    ->pluck('id')
                    ->filter()
                    ->all();

                ApprovalTrail::where('area', $area)
                    ->when($incomingIds !== [], fn ($q) => $q->whereNotIn('id', $incomingIds))
                    ->delete();

                foreach ($trailData['levels'] ?? [] as $level) {
                    $levelName = $this->inferLevelName($level['approver_type'], $level['role_label']);

                    $attrs = [
                        'area' => $area,
                        'order' => $level['order'],
                        'level_name' => $levelName,
                        'role_label' => $level['role_label'],
                        'approver_type' => $level['approver_type'],
                        'default_user_id' => $level['approver_type'] === ApprovalTrail::TYPE_USUARIO
                            || $level['approver_type'] === ApprovalTrail::TYPE_DIRETOR_DEPTO
                            ? ($level['default_user_id'] ?? null)
                            : null,
                        'approver_department_id' => $level['approver_type'] === ApprovalTrail::TYPE_DEPARTAMENTO
                            ? ($level['approver_department_id'] ?? null)
                            : null,
                    ];

                    if (! empty($level['id'])) {
                        ApprovalTrail::where('id', $level['id'])->update($attrs);
                    } else {
                        ApprovalTrail::create($attrs);
                    }
                }
            }
        });

        return back()->with('success', 'Fluxos atualizados com sucesso.');
    }

    private function inferLevelName(string $approverType, string $roleLabel): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $roleLabel) ?? 'etapa');
        $slug = trim($slug, '_') ?: 'etapa';

        if (preg_match('/presid/i', $roleLabel) || str_starts_with($slug, 'presid')) {
            return 'presidencia';
        }

        return match ($approverType) {
            ApprovalTrail::TYPE_GESTOR_DEPTO => 'departamento',
            ApprovalTrail::TYPE_DIRETOR_DEPTO => 'diretoria',
            ApprovalTrail::TYPE_DEPT_FINANCEIRO => 'financeiro',
            default => mb_substr($slug, 0, 40),
        };
    }
}
