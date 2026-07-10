<?php

namespace App\Http\Controllers;

use App\Models\ApprovalTrail;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Configuração de fluxos de aprovação (admin).
 * Permite definir quem aprova em cada nível, por área.
 * Etapas "departamento" vêm do cadastro do departamento (gestor).
 * Etapa "diretoria" usa o diretor do departamento, com fallback ao padrão da área.
 */
class ApprovalFlowConfigController extends Controller
{
    public function index()
    {
        $trails = ApprovalTrail::with('defaultUser:id,name')
            ->orderBy('area')->orderBy('order')->get()
            ->groupBy('area')
            ->map(fn ($levels, $area) => [
                'area' => $area,
                'area_label' => ApprovalTrail::AREAS[$area] ?? $area,
                'levels' => $levels->map(fn ($l) => [
                    'id' => $l->id,
                    'order' => $l->order,
                    'level_name' => $l->level_name,
                    'role_label' => $l->role_label,
                    'default_user_id' => $l->default_user_id,
                    'default_user_name' => $l->defaultUser?->name,
                    'from_department' => $l->level_name === 'departamento',
                    'department_fallback' => $l->level_name === 'diretoria',
                ])->values(),
            ])->values();

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Approvals/FlowConfig', [
            'trails' => $trails,
            'users' => $users,
            'areas' => ApprovalTrail::AREAS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'levels' => ['required', 'array'],
            'levels.*.id' => ['required', 'integer', 'exists:approval_trails,id'],
            'levels.*.default_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        foreach ($data['levels'] as $level) {
            $trail = ApprovalTrail::find($level['id']);
            if (! $trail || $trail->level_name === 'departamento') {
                continue;
            }

            ApprovalTrail::where('id', $level['id'])->update([
                'default_user_id' => $level['default_user_id'],
            ]);
        }

        return back()->with('success', 'Fluxos atualizados com sucesso.');
    }
}
