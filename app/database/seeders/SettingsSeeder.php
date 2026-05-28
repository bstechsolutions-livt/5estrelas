<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'app_name', 'value' => '5 Estrelas', 'type' => 'string'],
            ['key' => 'primary_color', 'value' => '#3b82f6', 'type' => 'color'],
            ['key' => 'secondary_color', 'value' => '#1e1e2d', 'type' => 'color'],
            ['key' => 'logo_path', 'value' => null, 'type' => 'image'],
            ['key' => 'logo_mobile_path', 'value' => null, 'type' => 'image'],
            ['key' => 'favicon_path', 'value' => null, 'type' => 'image'],
            ['key' => 'login_bg_path', 'value' => null, 'type' => 'image'],
            ['key' => 'login_bg_mobile_path', 'value' => null, 'type' => 'image'],

            // Segurança
            ['key' => 'security.password_min_length', 'value' => '8', 'type' => 'integer'],
            ['key' => 'security.password_require_letter', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'security.password_require_number', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'security.max_login_attempts', 'value' => '5', 'type' => 'integer'],
            ['key' => 'security.lockout_minutes', 'value' => '15', 'type' => 'integer'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
