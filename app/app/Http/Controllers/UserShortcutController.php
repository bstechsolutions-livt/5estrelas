<?php

namespace App\Http\Controllers;

use App\Models\UserShortcut;
use App\Support\MenuCatalog;
use Illuminate\Http\Request;

class UserShortcutController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'menu_keys' => ['array'],
            'menu_keys.*' => ['string'],
        ]);

        $user = $request->user();
        $available = collect(MenuCatalog::availableTo($user))->pluck('key')->toArray();

        $keys = collect($data['menu_keys'] ?? [])
            ->filter(fn ($k) => in_array($k, $available, true))
            ->unique()
            ->values()
            ->toArray();

        // Sync: remove tudo e cria de novo na ordem
        UserShortcut::where('user_id', $user->id)->delete();

        foreach ($keys as $i => $key) {
            UserShortcut::create([
                'user_id' => $user->id,
                'menu_key' => $key,
                'position' => $i,
            ]);
        }

        return back()->with('success', 'Atalhos atualizados.');
    }
}
