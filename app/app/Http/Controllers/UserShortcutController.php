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
            'slot' => ['nullable', 'in:dashboard,mobile_nav'],
            'menu_keys' => ['array'],
            'menu_keys.*' => ['string'],
        ]);

        $slot = $data['slot'] ?? 'dashboard';
        $maxByLot = $slot === 'mobile_nav' ? 4 : 8;

        $user = $request->user();
        $available = collect(MenuCatalog::availableTo($user))->pluck('key')->toArray();

        $keys = collect($data['menu_keys'] ?? [])
            ->filter(fn ($k) => in_array($k, $available, true))
            ->filter(fn ($k) => $k !== 'dashboard') // dashboard nunca é atalho
            ->unique()
            ->take($maxByLot)
            ->values()
            ->toArray();

        // Sync por slot
        UserShortcut::where('user_id', $user->id)->where('slot', $slot)->delete();

        foreach ($keys as $i => $key) {
            UserShortcut::create([
                'user_id' => $user->id,
                'slot' => $slot,
                'menu_key' => $key,
                'position' => $i,
            ]);
        }

        return back()->with('success', 'Atalhos atualizados.');
    }
}
