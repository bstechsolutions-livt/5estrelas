<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($request->hasFile('avatar')) {
            // Remove avatar antigo se existir
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $file = $request->file('avatar');
            $ext = $file->getClientOriginalExtension();
            $filename = "avatars/{$user->id}_" . time() . ".{$ext}";
            Storage::disk('public')->putFileAs('', $file, $filename);
            $user->avatar_path = $filename;
        }

        $user->save();

        return back()->with('success', 'Perfil atualizado com sucesso.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'Senha atualizada com sucesso.');
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = null;
        $user->save();

        return back()->with('success', 'Foto removida.');
    }
}
