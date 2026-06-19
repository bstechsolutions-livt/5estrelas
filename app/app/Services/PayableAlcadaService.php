<?php

namespace App\Services;

use App\Models\PayableRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Regras da Alçada do Contas a Pagar (Alcada_CP).
 *
 * Centraliza:
 *  - elegibilidade (quem pode executar uma ação do fluxo) — consumida por PayableController@pay;
 *  - o "mapa" da alçada para a tela administrativa;
 *  - associar/remover responsáveis com auditoria.
 *
 * Elegibilidade SEMPRE ignora usuários inativos, sem precisar removê-los da alçada.
 * A leitura é feita do banco a cada chamada (sem cache), então alterações valem
 * em tempo real para a próxima ação.
 */
class PayableAlcadaService
{
    /** Usuários ATIVOS associados ao papel (base da elegibilidade). */
    public function eligibleUsers(string $role): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('id', PayableRole::where('role', $role)->pluck('user_id'))
            ->get(['id', 'name', 'email']);
    }

    /** true se o usuário está ATIVO e associado ao papel. */
    public function isAssigned(User $user, string $role): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return PayableRole::where('role', $role)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Há pelo menos um responsável ATIVO no papel? (ex.: alçada de pagamento configurada). */
    public function hasRole(string $role): bool
    {
        return $this->eligibleUsers($role)->isNotEmpty();
    }

    /**
     * Mapa para a tela administrativa: cada papel com label, descrição e usuários
     * associados. Inclui inativos (flag is_active) para o admin poder removê-los.
     */
    public function map(): array
    {
        $byRole = PayableRole::with('user:id,name,email,is_active')->get()->groupBy('role');

        $out = [];
        foreach (PayableRole::ROLES as $role => $label) {
            $users = ($byRole[$role] ?? collect())
                ->filter(fn ($pr) => $pr->user !== null)
                ->map(fn ($pr) => [
                    'id' => $pr->user->id,
                    'name' => $pr->user->name,
                    'email' => $pr->user->email,
                    'is_active' => (bool) $pr->user->is_active,
                ])
                ->sortBy('name')
                ->values()
                ->all();

            $out[] = [
                'role' => $role,
                'label' => $label,
                'description' => PayableRole::ROLE_DESCRIPTIONS[$role] ?? null,
                'users' => $users,
            ];
        }

        return $out;
    }

    /** Associa um usuário a um papel (idempotente, sem duplicar — R2.4) + auditoria 'contas_pagar.alcada_atribuido'. */
    public function assign(string $role, int $userId, User $actor): PayableRole
    {
        $payableRole = PayableRole::firstOrCreate(['role' => $role, 'user_id' => $userId]);

        if ($payableRole->wasRecentlyCreated) {
            $user = User::find($userId);
            AuditLogger::log(
                event: 'contas_pagar.alcada_atribuido',
                module: 'financeiro.contas_pagar',
                description: "Adicionou {$user?->name} ao papel " . (PayableRole::ROLES[$role] ?? $role) . ' na alçada do contas a pagar',
                auditable: $payableRole,
                newValues: ['role' => $role, 'user_id' => $userId],
            );
        }

        return $payableRole;
    }

    /** Remove um usuário de um papel + auditoria 'contas_pagar.alcada_removido'. */
    public function unassign(string $role, int $userId, User $actor): void
    {
        $payableRole = PayableRole::where('role', $role)->where('user_id', $userId)->first();
        if (! $payableRole) {
            return;
        }

        $user = User::find($userId);
        AuditLogger::log(
            event: 'contas_pagar.alcada_removido',
            module: 'financeiro.contas_pagar',
            description: "Removeu {$user?->name} do papel " . (PayableRole::ROLES[$role] ?? $role) . ' na alçada do contas a pagar',
            auditable: $payableRole,
            oldValues: ['role' => $role, 'user_id' => $userId],
        );

        $payableRole->delete();
    }
}
