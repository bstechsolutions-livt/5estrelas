<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\UserShortcut;
use App\Support\MenuCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_path ? Storage::url($request->user()->avatar_path) : null,
                    'permissions' => $request->user()->permissionKeys(),
                ] : null,
            ],
            'shortcuts' => fn () => $request->user() ? $this->resolveShortcuts($request->user(), 'dashboard') : [],
            'mobileNavShortcuts' => fn () => $request->user() ? $this->resolveShortcuts($request->user(), 'mobile_nav') : [],
            'menuOptions' => fn () => $request->user() ? MenuCatalog::availableTo($request->user()) : [],
            'theme' => [
                'app_name' => Setting::get('app_name', '5 Estrelas'),
                'primary_color' => Setting::get('primary_color', '#3b82f6'),
                'secondary_color' => Setting::get('secondary_color', '#1e1e2d'),
                'logo_url' => Setting::get('logo_path') ? Storage::url(Setting::get('logo_path')) : null,
                'logo_mobile_url' => Setting::get('logo_mobile_path') ? Storage::url(Setting::get('logo_mobile_path')) : null,
                'favicon_url' => Setting::get('favicon_path') ? Storage::url(Setting::get('favicon_path')) : null,
                'login_bg_url' => Setting::get('login_bg_path') ? Storage::url(Setting::get('login_bg_path')) : null,
                'login_bg_mobile_url' => Setting::get('login_bg_mobile_path') ? Storage::url(Setting::get('login_bg_mobile_path')) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'is_mobile_app' => $request->header('X-Client') === '5estrelas-app'
                || str_contains((string) $request->userAgent(), '5Estrelas'),
        ];
    }

    private function resolveShortcuts($user, string $slot = 'dashboard'): array
    {
        $keys = UserShortcut::where('user_id', $user->id)
            ->where('slot', $slot)
            ->orderBy('position')
            ->pluck('menu_key')
            ->toArray();

        return collect($keys)
            ->map(fn ($k) => MenuCatalog::findByKey($k))
            ->filter()
            ->values()
            ->all();
    }
}
