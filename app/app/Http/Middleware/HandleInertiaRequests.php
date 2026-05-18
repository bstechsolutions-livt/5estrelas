<?php

namespace App\Http\Middleware;

use App\Models\Setting;
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
                ] : null,
            ],
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
        ];
    }
}
