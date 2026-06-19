<?php

namespace App\Http\Controllers;

use App\Models\PayableRole;
use App\Models\User;
use App\Services\PayableAlcadaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Gestão da Alçada do Contas a Pagar (Alcada_CP).
 *
 * Define quem ocupa cada papel do fluxo de pagamento (pagador, conciliador,
 * assinante). Protegido por `financeiro.contas_pagar.alcada_gerenciar`.
 */
class PayableAlcadaController extends Controller
{
    public function index(PayableAlcadaService $alcada)
    {
        return Inertia::render('Payables/Alcada', [
            'roles' => $alcada->map(),
            'availableUsers' => User::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request, PayableAlcadaService $alcada)
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys(PayableRole::ROLES))],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $alcada->assign($data['role'], (int) $data['user_id'], $request->user());

        return back()->with('success', 'Responsável adicionado à alçada.');
    }

    public function destroy(string $role, int $userId, Request $request, PayableAlcadaService $alcada)
    {
        abort_unless(array_key_exists($role, PayableRole::ROLES), 404);

        $alcada->unassign($role, $userId, $request->user());

        return back()->with('success', 'Responsável removido da alçada.');
    }
}
