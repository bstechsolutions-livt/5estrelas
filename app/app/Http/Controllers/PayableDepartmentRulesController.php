<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PayableDepartmentRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Regras de classificação automática de títulos Senior por departamento.
 *
 * Usa codCcu (centro de custo) e obsTcp (observação/descrição do título na Senior).
 */
class PayableDepartmentRulesController extends Controller
{
    public function index()
    {
        $rulesByDept = PayableDepartmentRule::query()
            ->get()
            ->keyBy('department_id');

        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Department $dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'slug' => $dept->slug,
                'codccu_text' => PayableDepartmentRule::formatLines($rulesByDept[$dept->id]->codccu ?? []),
                'description_text' => PayableDepartmentRule::formatLines($rulesByDept[$dept->id]->description_patterns ?? []),
            ]);

        return Inertia::render('Payables/DepartmentRules', [
            'departments' => $departments,
            'help' => [
                'codccu' => 'Código do centro de custo (campo codCcu da Senior). Se o título tiver esse código, entra no departamento.',
                'description' => 'Texto da observação do título na Senior (campo obsTcp). No Hub aparece como Descrição na lista. Uma palavra ou trecho por linha; o sistema adiciona % automaticamente.',
                'workflow' => 'Títulos já enviados para aprovação usam o departamento gravado no workflow — essas regras valem para títulos importados da Senior ainda sem departamento.',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'rules.*.codccu_text' => ['nullable', 'string', 'max:5000'],
            'rules.*.description_text' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rules'] as $row) {
                $codccu = PayableDepartmentRule::parseLines($row['codccu_text'] ?? null);
                $patterns = PayableDepartmentRule::normalizePatterns(
                    PayableDepartmentRule::parseLines($row['description_text'] ?? null),
                );

                if ($codccu === [] && $patterns === []) {
                    PayableDepartmentRule::where('department_id', $row['department_id'])->delete();

                    continue;
                }

                PayableDepartmentRule::updateOrCreate(
                    ['department_id' => $row['department_id']],
                    [
                        'codccu' => $codccu,
                        'description_patterns' => $patterns,
                    ],
                );
            }
        });

        return back()->with('success', 'Regras de classificação salvas.');
    }
}
