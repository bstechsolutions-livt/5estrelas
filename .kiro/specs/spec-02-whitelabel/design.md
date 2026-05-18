# Spec 02 - Design

## Modelo de dados

### Tabela `settings`
```sql
id          bigint primary key
key         string unique
value       text nullable
type        string default 'string' -- string, color, image, boolean
created_at  timestamp
updated_at  timestamp
```

### Chaves padrão
| key | type | default |
|-----|------|---------|
| app_name | string | "5 Estrelas" |
| primary_color | color | "#3b82f6" |
| secondary_color | color | "#1e1e2d" |
| logo_path | image | null |
| favicon_path | image | null |
| login_bg_path | image | null |

## Backend

### `app/Models/Setting.php`
```php
class Setting extends Model {
    public static function get($key, $default = null) {
        return Cache::rememberForever("setting.$key", fn() =>
            self::where('key', $key)->value('value') ?? $default
        );
    }
    public static function set($key, $value, $type = 'string') {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
        Cache::forget("setting.$key");
    }
    public static function all_keyed() {
        return Cache::rememberForever('settings.all', fn() =>
            self::pluck('value', 'key')->toArray()
        );
    }
}
```

### `HandleInertiaRequests::share()`
Adicionar bloco `theme`:
```php
'theme' => [
    'app_name' => Setting::get('app_name', '5 Estrelas'),
    'primary_color' => Setting::get('primary_color', '#3b82f6'),
    'secondary_color' => Setting::get('secondary_color', '#1e1e2d'),
    'logo_url' => Setting::get('logo_path') ? Storage::url(Setting::get('logo_path')) : null,
    'favicon_url' => Setting::get('favicon_path') ? Storage::url(Setting::get('favicon_path')) : null,
    'login_bg_url' => Setting::get('login_bg_path') ? Storage::url(Setting::get('login_bg_path')) : null,
],
```

### `SettingsController`
- `appearance()` → renderiza `Settings/Appearance.vue` com as settings atuais
- `updateAppearance(Request)` → valida + salva valores texto/cor + faz upload das imagens

### Rotas
```php
Route::get('/settings/aparencia', [SettingsController::class, 'appearance'])->name('settings.appearance');
Route::post('/settings/aparencia', [SettingsController::class, 'updateAppearance']);
```

### Migration
```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('type')->default('string');
    $table->timestamps();
});
```

### Seeder
`SettingsSeeder` insere as 6 chaves padrão. Adicionar ao `DatabaseSeeder::run()`.

## Frontend

### `resources/js/composables/useTheme.js`
```js
export function useTheme() {
    const page = usePage()
    const theme = computed(() => page.props.theme)

    watchEffect(() => {
        if (!theme.value) return
        const root = document.documentElement
        root.style.setProperty('--p-primary-color', theme.value.primary_color)
        root.style.setProperty('--sidebar-bg', theme.value.secondary_color)

        // Favicon
        if (theme.value.favicon_url) {
            const link = document.querySelector("link[rel~='icon']") || document.createElement('link')
            link.rel = 'icon'
            link.href = theme.value.favicon_url
            document.head.appendChild(link)
        }

        // Title
        document.title = theme.value.app_name
    })

    return { theme }
}
```

### Onde aplicar
- `app.js` → invocar `useTheme()` num plugin global ou via inject
- `AppLayout.vue` → usar `theme.app_name` no logo/sidebar e `theme.logo_url` para mostrar imagem
- `Login.vue` → usar `theme.login_bg_url` no background, `theme.app_name` no título, `theme.logo_url` no logo
- Sidebar usa `style="background-color: var(--sidebar-bg)"` (já está)

### Tela `Settings/Appearance.vue`
Formulário com:
- `InputText` para `app_name`
- `ColorPicker` PrimeVue para `primary_color` e `secondary_color`
- `FileUpload` PrimeVue (mode customizado) para `logo`, `favicon`, `login_bg`
- Preview ao lado de cada upload
- Botão "Salvar" → submit via `useForm().post()`
- Toast de sucesso após salvar

## Storage
- `php artisan storage:link` → cria link simbólico `public/storage` → `storage/app/public`
- Pasta de uploads: `storage/app/public/branding/`
- Filename: `logo.{ext}`, `favicon.{ext}`, `login_bg.{ext}` (sobrescreve sempre)

## Cache
- `Cache::rememberForever('setting.{key}')` ao ler
- `Cache::forget` ao salvar cada chave
- Driver: `database` (já configurado)
