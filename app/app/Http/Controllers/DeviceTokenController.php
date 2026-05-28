<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Flutter chama este endpoint após obter o token FCM.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:150'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;

        DeviceToken::updateOrCreate(
            ['user_id' => $userId, 'token' => $data['token']],
            [
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Remove um token (chamado quando user faz logout no app).
     */
    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
