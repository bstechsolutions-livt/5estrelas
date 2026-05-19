<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function appearance()
    {
        return Inertia::render('Settings/Appearance', [
            'settings' => [
                'app_name' => Setting::get('app_name', '5 Estrelas'),
                'primary_color' => Setting::get('primary_color', '#3b82f6'),
                'secondary_color' => Setting::get('secondary_color', '#1e1e2d'),
                'logo_url' => Setting::get('logo_path') ? Storage::url(Setting::get('logo_path')) : null,
                'logo_mobile_url' => Setting::get('logo_mobile_path') ? Storage::url(Setting::get('logo_mobile_path')) : null,
                'favicon_url' => Setting::get('favicon_path') ? Storage::url(Setting::get('favicon_path')) : null,
                'login_bg_url' => Setting::get('login_bg_path') ? Storage::url(Setting::get('login_bg_path')) : null,
                'login_bg_mobile_url' => Setting::get('login_bg_mobile_path') ? Storage::url(Setting::get('login_bg_mobile_path')) : null,
            ],
        ]);
    }

    public function updateAppearance(Request $request)
    {
        $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:20480'],
            'logo_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:20480'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg,webp', 'max:5120'],
            'login_bg' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:30720'],
            'login_bg_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:30720'],
        ]);

        // Captura valores anteriores (para auditoria)
        $previous = [
            'app_name' => Setting::get('app_name'),
            'primary_color' => Setting::get('primary_color'),
            'secondary_color' => Setting::get('secondary_color'),
            'logo_path' => Setting::get('logo_path'),
            'logo_mobile_path' => Setting::get('logo_mobile_path'),
            'favicon_path' => Setting::get('favicon_path'),
            'login_bg_path' => Setting::get('login_bg_path'),
            'login_bg_mobile_path' => Setting::get('login_bg_mobile_path'),
        ];

        Setting::set('app_name', $request->app_name);
        Setting::set('primary_color', $request->primary_color, 'color');
        Setting::set('secondary_color', $request->secondary_color, 'color');

        $uploads = [
            'logo' => 'logo_path',
            'logo_mobile' => 'logo_mobile_path',
            'favicon' => 'favicon_path',
            'login_bg' => 'login_bg_path',
            'login_bg_mobile' => 'login_bg_mobile_path',
        ];

        foreach ($uploads as $field => $settingKey) {
            if ($request->hasFile($field)) {
                $path = $this->storeBranding($request->file($field), $field);
                Setting::set($settingKey, $path, 'image');
            }
        }

        // Calcula diff (apenas campos que mudaram)
        $current = [
            'app_name' => $request->app_name,
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'logo_path' => Setting::get('logo_path'),
            'logo_mobile_path' => Setting::get('logo_mobile_path'),
            'favicon_path' => Setting::get('favicon_path'),
            'login_bg_path' => Setting::get('login_bg_path'),
            'login_bg_mobile_path' => Setting::get('login_bg_mobile_path'),
        ];

        $oldDiff = [];
        $newDiff = [];
        foreach ($current as $k => $v) {
            if ($previous[$k] !== $v) {
                $oldDiff[$k] = $previous[$k];
                $newDiff[$k] = $v;
            }
        }

        if (!empty($newDiff)) {
            AuditLogger::log(
                event: 'aparencia.updated',
                module: 'aparencia',
                description: 'Aparência do sistema atualizada',
                oldValues: $oldDiff,
                newValues: $newDiff,
            );
        }

        return back()->with('success', 'Aparência atualizada com sucesso.');
    }

    private function storeBranding($file, string $name): string
    {
        $ext = $file->getClientOriginalExtension();
        $filename = "branding/{$name}_" . time() . ".{$ext}";
        Storage::disk('public')->putFileAs('', $file, $filename);
        return $filename;
    }
}
