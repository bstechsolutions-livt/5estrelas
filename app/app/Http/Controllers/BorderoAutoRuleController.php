<?php

namespace App\Http\Controllers;

use App\Models\BorderoAutoRule;
use App\Services\BorderoAutoGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BorderoAutoRuleController extends Controller
{
    public function index()
    {
        $rules = BorderoAutoRule::query()
            ->orderBy('name')
            ->get()
            ->map(fn (BorderoAutoRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'is_active' => $rule->is_active,
                'min_titles_per_group' => $rule->min_titles_per_group,
                'due_grouping' => $rule->due_grouping,
                'eligibility_mode' => $rule->eligibility_mode,
                'rules_summary' => $rule->rulesSummary(),
                'last_applied_at' => $rule->last_applied_at?->toIso8601String(),
                'last_applied_count' => $rule->last_applied_count,
                'last_cron_at' => $rule->last_cron_at?->toIso8601String(),
                'last_cron_count' => $rule->last_cron_count,
            ]);

        return Inertia::render('Borderos/AutoRules/Index', [
            'rules' => $rules,
        ]);
    }

    public function create(BorderoAutoGroupService $grouper, Request $request)
    {
        return $this->formPage(null, $grouper, $request);
    }

    public function edit(BorderoAutoRule $rule, BorderoAutoGroupService $grouper, Request $request)
    {
        return $this->formPage($rule, $grouper, $request);
    }

    public function store(Request $request, BorderoAutoGroupService $grouper)
    {
        $data = $this->validated($request);
        $applyNow = $data['apply_mode'] === 'now';
        unset($data['apply_mode']);

        $rule = BorderoAutoRule::create([
            ...$data,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return $this->afterSave($request, $grouper, $rule, $applyNow, created: true);
    }

    public function update(BorderoAutoRule $rule, Request $request, BorderoAutoGroupService $grouper)
    {
        $data = $this->validated($request);
        $applyNow = $data['apply_mode'] === 'now';
        unset($data['apply_mode']);

        $rule->update($data);

        return $this->afterSave($request, $grouper, $rule, $applyNow, created: false);
    }

    public function destroy(BorderoAutoRule $rule)
    {
        $rule->delete();

        return redirect('/financeiro/borderos/automatico')
            ->with('success', 'Regra removida.');
    }

    public function toggle(BorderoAutoRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active
            ? 'Regra ativada — será executada no cron das 6h.'
            : 'Regra desativada — não roda no cron.');
    }

    public function simulate(Request $request, BorderoAutoGroupService $grouper)
    {
        $data = $this->validated($request, requireName: false);
        unset($data['apply_mode']);

        $rule = BorderoAutoRule::fromPayload($data);
        $preview = $grouper->preview($request->user(), $rule);

        return response()->json($preview);
    }

    private function formPage(?BorderoAutoRule $rule, BorderoAutoGroupService $grouper, Request $request)
    {
        $draft = $rule
            ? $rule
            : BorderoAutoRule::fromPayload([
                'name' => '',
                'group_by' => ['empresa', 'departamento'],
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'eligibility_due_days' => 30,
            ]);

        $preview = $grouper->preview($request->user(), $draft);

        return Inertia::render('Borderos/AutoRules/Form', [
            'rule' => $rule ? $rule->toFormArray() : null,
            'defaults' => [
                'name' => '',
                'group_by' => ['empresa', 'departamento'],
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'eligibility_due_days' => 30,
            ],
            'options' => [
                'group_by' => BorderoAutoRule::groupByLabels(),
                'group_by_order' => BorderoAutoRule::GROUP_BY_ORDER,
                'due_grouping' => BorderoAutoRule::dueGroupingLabels(),
                'eligibility_mode' => BorderoAutoRule::eligibilityLabels(),
            ],
            'preview' => $preview,
        ]);
    }

    private function afterSave(Request $request, BorderoAutoGroupService $grouper, BorderoAutoRule $rule, bool $applyNow, bool $created)
    {
        if ($applyNow) {
            $result = $grouper->applyRule($request->user(), $rule);

            if ($result['created'] === 0) {
                return redirect('/financeiro/borderos/automatico')
                    ->with('error', 'Regra salva, mas nenhum borderô foi criado — não há títulos elegíveis ou grupos abaixo do mínimo.');
            }

            $msg = $created
                ? "Regra criada e {$result['created']} borderô(s) gerado(s) em rascunho."
                : "Regra atualizada e {$result['created']} borderô(s) gerado(s) em rascunho.";

            return redirect('/financeiro/borderos?status=rascunho')->with('success', $msg);
        }

        $msg = $created
            ? 'Regra criada. Será aplicada automaticamente no cron das 6h.'
            : 'Regra atualizada. Será aplicada automaticamente no cron das 6h.';

        return redirect('/financeiro/borderos/automatico')->with('success', $msg);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $requireName = true): array
    {
        $rules = [
            'group_by' => ['required', 'array', 'min:1'],
            'group_by.*' => ['required', 'string', Rule::in(BorderoAutoRule::GROUP_BY_ORDER)],
            'min_titles_per_group' => ['required', 'integer', 'min:2', 'max:50'],
            'due_grouping' => ['required', Rule::in([
                BorderoAutoRule::DUE_NONE,
                BorderoAutoRule::DUE_SAME_DAY,
                BorderoAutoRule::DUE_MAX_SPAN,
            ])],
            'max_due_span_days' => ['required', 'integer', 'min:1', 'max:90'],
            'eligibility_mode' => ['required', Rule::in([
                BorderoAutoRule::ELIGIBILITY_ALL,
                BorderoAutoRule::ELIGIBILITY_DUE_WITHIN,
            ])],
            'eligibility_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'apply_mode' => ['nullable', Rule::in(['cron', 'now'])],
        ];

        if ($requireName) {
            $rules['name'] = ['required', 'string', 'max:120'];
            $rules['apply_mode'] = ['required', Rule::in(['cron', 'now'])];
        } else {
            $rules['name'] = ['nullable', 'string', 'max:120'];
        }

        $data = $request->validate($rules);

        if ($data['eligibility_mode'] === BorderoAutoRule::ELIGIBILITY_DUE_WITHIN) {
            $request->validate(['eligibility_due_days' => ['required', 'integer', 'min:1', 'max:365']]);
        } else {
            $data['eligibility_due_days'] = null;
        }

        $data['apply_mode'] ??= 'cron';

        $rule = BorderoAutoRule::fromPayload($data);
        $data['group_by'] = $rule->normalizedGroupBy();

        return $data;
    }
}
