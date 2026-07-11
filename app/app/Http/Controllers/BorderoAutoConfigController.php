<?php

namespace App\Http\Controllers;

use App\Models\BorderoAutoConfig;
use App\Services\BorderoAutoGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BorderoAutoConfigController extends Controller
{
    public function index(Request $request, BorderoAutoGroupService $grouper)
    {
        $config = BorderoAutoConfig::current();
        $preview = $grouper->preview($request->user(), $config);

        return Inertia::render('Borderos/AutoConfig', [
            'config' => [
                'min_titles_per_group' => $config->min_titles_per_group,
                'due_grouping' => $config->due_grouping,
                'max_due_span_days' => $config->max_due_span_days,
                'eligibility_mode' => $config->eligibility_mode,
                'eligibility_due_days' => $config->eligibility_due_days ?? 30,
                'cron_enabled' => $config->cron_enabled,
                'last_cron_run_at' => $config->last_cron_run_at?->toIso8601String(),
                'last_cron_created_count' => $config->last_cron_created_count,
            ],
            'options' => [
                'due_grouping' => BorderoAutoConfig::dueGroupingLabels(),
                'eligibility_mode' => BorderoAutoConfig::eligibilityLabels(),
            ],
            'preview' => $preview,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'min_titles_per_group' => ['required', 'integer', 'min:2', 'max:50'],
            'due_grouping' => ['required', Rule::in([
                BorderoAutoConfig::DUE_NONE,
                BorderoAutoConfig::DUE_SAME_DAY,
                BorderoAutoConfig::DUE_MAX_SPAN,
            ])],
            'max_due_span_days' => ['required', 'integer', 'min:1', 'max:90'],
            'eligibility_mode' => ['required', Rule::in([
                BorderoAutoConfig::ELIGIBILITY_ALL,
                BorderoAutoConfig::ELIGIBILITY_DUE_WITHIN,
            ])],
            'eligibility_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cron_enabled' => ['required', 'boolean'],
        ]);

        if ($data['eligibility_mode'] === BorderoAutoConfig::ELIGIBILITY_DUE_WITHIN) {
            $request->validate([
                'eligibility_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            ]);
        } else {
            $data['eligibility_due_days'] = null;
        }

        BorderoAutoConfig::current()->update($data);

        return back()->with('success', 'Configuração salva. A simulação abaixo reflete os títulos abertos agora.');
    }

    public function generate(Request $request, BorderoAutoGroupService $grouper)
    {
        $data = $request->validate([
            'group_keys' => ['required', 'array', 'min:1'],
            'group_keys.*' => ['required', 'string', 'max:160'],
        ]);

        $result = $grouper->generate($request->user(), $data['group_keys']);

        if ($result['created'] === 0) {
            return back()->with('error', 'Nenhum borderô foi criado. Os grupos podem ter ficado inválidos ou com menos títulos que o mínimo.');
        }

        $msg = $result['created'] === 1
            ? '1 borderô criado em rascunho.'
            : "{$result['created']} borderôs criados em rascunho.";

        return redirect('/financeiro/borderos?status=rascunho')
            ->with('success', $msg);
    }
}
