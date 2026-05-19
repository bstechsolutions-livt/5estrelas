<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserPermissionController extends Controller
{
    public function edit(int $id)
    {
        $user = User::findOrFail($id);

        $permissions = Permission::orderBy('module')->orderBy('label')->get()
            ->groupBy('module')
            ->map(fn ($items) => $items->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'label' => $p->label,
                'description' => $p->description,
            ])->values())
            ->toArray();

        $userPermissions = $user->permissions()->pluck('permissions.id')->toArray();

        return Inertia::render('Users/Permissions', [
            'targetUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'permissions' => $permissions,
            'assigned' => $userPermissions,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        // Captura keys antes do sync (para auditoria)
        $oldKeys = $user->permissions()->pluck('key')->toArray();

        // Proteção: não permitir remover o curinga do último admin
        $wildcard = Permission::where('key', '*')->first();
        if ($wildcard && $user->permissions->contains('id', $wildcard->id)) {
            $isRemovingWildcard = !in_array($wildcard->id, $data['permission_ids'] ?? [], true);
            $otherAdmins = User::whereHas('permissions', fn ($q) => $q->where('key', '*'))
                ->where('id', '!=', $user->id)
                ->count();

            if ($isRemovingWildcard && $otherAdmins === 0) {
                return back()->with('error', 'Não é possível remover a permissão de admin do último administrador.');
            }
        }

        $user->permissions()->sync($data['permission_ids'] ?? []);
        $user->flushPermissionCache();

        $newKeys = Permission::whereIn('id', $data['permission_ids'] ?? [])->pluck('key')->toArray();
        sort($oldKeys);
        sort($newKeys);

        if ($oldKeys !== $newKeys) {
            AuditLogger::log(
                event: 'usuarios.permissions_updated',
                module: 'usuarios',
                description: "Permissões de {$user->name} atualizadas",
                auditable: $user,
                oldValues: ['keys' => $oldKeys],
                newValues: ['keys' => $newKeys],
            );
        }

        return back()->with('success', 'Permissões atualizadas.');
    }
}
