<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Impersonation;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function store(Request $request, int $id)
    {
        if (Impersonation::isActive()) {
            return back()->with('error', 'Saia da sessão atual antes de entrar como outro usuário.');
        }

        $actor = $request->user();
        if (! $actor->hasPermission('usuarios.impersonar')) {
            abort(403);
        }

        $target = User::findOrFail($id);

        if ($target->id === $actor->id) {
            return back()->with('error', 'Você já está logado como este usuário.');
        }

        if (! $target->is_active) {
            return back()->with('error', 'Não é possível entrar como um usuário inativo.');
        }

        AuditLogger::log(
            event: 'usuarios.impersonation_start',
            module: 'usuarios',
            description: "{$actor->name} entrou como {$target->name}",
            auditable: $target,
            metadata: [
                'impersonator_id' => $actor->id,
                'impersonator_name' => $actor->name,
                'target_id' => $target->id,
                'target_name' => $target->name,
            ],
        );

        Impersonation::start($actor, $target);

        return redirect()
            ->route('dashboard')
            ->with('warning', "Você está vendo o sistema como {$target->name}. Use \"Voltar ao meu usuário\" para retornar.");
    }

    public function destroy(Request $request)
    {
        if (! Impersonation::isActive()) {
            return redirect()->route('dashboard');
        }

        $target = $request->user();
        $impersonator = Impersonation::stop();

        if ($impersonator) {
            AuditLogger::log(
                event: 'usuarios.impersonation_stop',
                module: 'usuarios',
                description: "{$impersonator->name} saiu da sessão de {$target->name}",
                auditable: $target,
                metadata: [
                    'impersonator_id' => $impersonator->id,
                    'impersonator_name' => $impersonator->name,
                    'target_id' => $target->id,
                    'target_name' => $target->name,
                ],
            );
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'Você voltou ao seu usuário.');
    }
}
