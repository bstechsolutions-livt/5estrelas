<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\AuditLogger;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Profile/Index', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
                'permissions' => $user->permissionKeys(),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'avatar.max' => 'A foto não pode ter mais de 10MB.',
            'avatar.image' => 'O arquivo enviado deve ser uma imagem.',
            'avatar.mimes' => 'A foto deve ser JPG, PNG ou WebP.',
        ]);

        $oldValues = [];
        $newValues = [];

        if ($user->name !== $data['name']) {
            $oldValues['name'] = $user->name;
            $newValues['name'] = $data['name'];
            $user->name = $data['name'];
        }

        if ($user->email !== $data['email']) {
            $oldValues['email'] = $user->email;
            $newValues['email'] = $data['email'];
            $user->email = $data['email'];
        }

        if ($request->hasFile('avatar')) {
            // Remove avatar antigo se existir
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $file = $request->file('avatar');
            $ext = $file->getClientOriginalExtension();
            $filename = "avatars/{$user->id}_" . time() . ".{$ext}";
            Storage::disk('public')->putFileAs('', $file, $filename);
            $oldValues['avatar_path'] = $user->avatar_path;
            $newValues['avatar_path'] = $filename;
            $user->avatar_path = $filename;
        }

        // Save sem disparar trait Auditable do User para não duplicar
        // (a trait registra updated; aqui registramos perfil.updated com diff curado)
        $user->saveQuietly();

        if (!empty($newValues)) {
            AuditLogger::log(
                event: 'perfil.updated',
                module: 'perfil',
                description: 'Atualizou os próprios dados',
                auditable: $user,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        return back()->with('success', 'Perfil atualizado com sucesso.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', new \App\Rules\StrongPassword()],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->saveQuietly();

        AuditLogger::log(
            event: 'perfil.password_changed',
            module: 'perfil',
            description: 'Trocou a própria senha',
            auditable: $user,
        );

        return back()->with('success', 'Senha atualizada com sucesso.');
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        $oldPath = $user->avatar_path;

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = null;
        $user->saveQuietly();

        AuditLogger::log(
            event: 'perfil.avatar_removed',
            module: 'perfil',
            description: 'Removeu a própria foto de perfil',
            auditable: $user,
            oldValues: ['avatar_path' => $oldPath],
            newValues: ['avatar_path' => null],
        );

        return back()->with('success', 'Foto removida.');
    }
}
