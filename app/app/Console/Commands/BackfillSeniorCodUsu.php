<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Senior\SeniorException;
use App\Services\Senior\SeniorUsuarioClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillSeniorCodUsu extends Command
{
    protected $signature = 'users:backfill-senior-cod-usu
        {--dry-run : Apenas simula, sem gravar}
        {--force : Sobrescreve senior_cod_usu já preenchido}
        {--from-cache : Usa storage/app/senior_usuarios.json em vez de chamar a API}';

    protected $description = 'Vincula usuários da intranet ao codUsu Senior (match por usuPlt/e-mail/nome)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $seniorUsers = $this->loadSeniorUsers();

        if ($seniorUsers === []) {
            $this->error('Nenhum usuário Senior disponível. Rode senior:sync-usuarios ou habilite a integração.');

            return self::FAILURE;
        }

        $withLogin = 0;
        foreach ($seniorUsers as $su) {
            if ($this->cleanUsuPlt($su['usuPlt'] ?? '') !== '') {
                $withLogin++;
            }
        }
        $this->comment(sprintf(
            'Senior: %d usuários, %d com usuPlt utilizável (restante sem login na API).',
            count($seniorUsers),
            $withLogin,
        ));

        $matched = 0;
        $skipped = 0;
        $ambiguous = 0;
        $unmatched = [];

        foreach (User::orderBy('id')->get() as $user) {
            if ($user->senior_cod_usu && !$force) {
                $skipped++;

                continue;
            }

            $result = $this->matchUser($user, $seniorUsers);
            if ($result === null) {
                $this->warn("SKIP #{$user->id} {$user->name} <{$user->email}> — sem match");
                $unmatched[] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
                $skipped++;

                continue;
            }

            if ($result['ambiguous']) {
                $this->warn(sprintf(
                    'AMBIG #%d %s <%s> — %d candidatos: %s',
                    $user->id,
                    $user->name,
                    $user->email,
                    count($result['candidates']),
                    implode(', ', array_map(
                        fn (array $c) => "codUsu {$c['codUsu']} ({$c['usuPlt']}, {$c['reason']})",
                        $result['candidates'],
                    )),
                ));
                $ambiguous++;

                continue;
            }

            $codUsu = (int) $result['codUsu'];
            $existing = User::where('senior_cod_usu', $codUsu)->where('id', '!=', $user->id)->exists();
            if ($existing) {
                $this->warn("AMBIG #{$user->id} {$user->name} → codUsu {$codUsu} já usado por outro usuário");
                $ambiguous++;

                continue;
            }

            $this->line(sprintf(
                '%s #%d %s → codUsu %d (%s, %s)',
                $dryRun ? '[dry]' : 'OK',
                $user->id,
                $user->name,
                $codUsu,
                $result['usuPlt'],
                $result['reason'],
            ));

            if (!$dryRun) {
                $user->update(['senior_cod_usu' => $codUsu]);
            }

            $matched++;
        }

        $this->newLine();
        $this->info("Concluído: {$matched} vinculados, {$skipped} sem match/já preenchidos, {$ambiguous} ambíguos.");

        if ($unmatched !== []) {
            $this->newLine();
            $this->comment('Usuários ainda sem senior_cod_usu (' . count($unmatched) . '):');
            foreach ($unmatched as $u) {
                $this->line("  #{$u['id']} {$u['name']} <{$u['email']}>");
            }
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{codUsu:int,usuPlt:string,codEmp?:int,codFil?:int}> */
    private function loadSeniorUsers(): array
    {
        if ($this->option('from-cache')) {
            $path = 'senior_usuarios.json';
            if (!Storage::disk('local')->exists($path)) {
                return [];
            }
            $data = json_decode(Storage::disk('local')->get($path), true) ?: [];

            return $data['usuarios'] ?? [];
        }

        if (!config('senior.enabled', false)) {
            $path = 'senior_usuarios.json';
            if (Storage::disk('local')->exists($path)) {
                $this->comment('Senior desabilitado — usando cache local.');

                return json_decode(Storage::disk('local')->get($path), true)['usuarios'] ?? [];
            }

            return [];
        }

        $client = SeniorUsuarioClient::fromConfig();
        $all = [];
        foreach (config('senior.cod_emps', [2, 3]) as $codEmp) {
            try {
                $rows = $client->exportarAbrangencia((int) $codEmp, (int) config('senior.cod_fil', 1));
            } catch (SeniorException $e) {
                $this->warn("emp {$codEmp}: {$e->getMessage()}");

                continue;
            }
            foreach ($rows as $row) {
                $codUsu = (int) ($row['codUsu'] ?? 0);
                if ($codUsu <= 0) {
                    continue;
                }
                $all[$codUsu] = [
                    'codUsu' => $codUsu,
                    'usuPlt' => $this->cleanUsuPlt($row['usuPlt'] ?? ''),
                ];
            }
        }

        return array_values($all);
    }

    /**
     * @param  array<int, array{codUsu:int,usuPlt:string}>  $seniorUsers
     * @return array{codUsu:int,usuPlt:string,reason:string,ambiguous:bool,candidates:array<int,array{codUsu:int,usuPlt:string,reason:string}>}|null
     */
    private function matchUser(User $user, array $seniorUsers): ?array
    {
        $candidates = [];
        $nameTokens = $this->nameTokens($user->name);
        $email = strtolower(trim($user->email));
        $emailLocal = strtolower(strtok($user->email, '@') ?: '');

        foreach ($seniorUsers as $su) {
            $usuPlt = $this->cleanUsuPlt($su['usuPlt'] ?? '');
            if ($usuPlt === '') {
                continue;
            }

            $reason = $this->matchReason($email, $emailLocal, $nameTokens, $usuPlt);
            if ($reason === null) {
                continue;
            }

            $candidates[] = [
                'codUsu' => (int) $su['codUsu'],
                'usuPlt' => $usuPlt,
                'reason' => $reason,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        $byCod = [];
        foreach ($candidates as $c) {
            $byCod[$c['codUsu']] = $c;
        }
        $unique = array_values($byCod);

        if (count($unique) === 1) {
            return [
                'codUsu' => $unique[0]['codUsu'],
                'usuPlt' => $unique[0]['usuPlt'],
                'reason' => $unique[0]['reason'],
                'ambiguous' => false,
                'candidates' => $unique,
            ];
        }

        return [
            'codUsu' => 0,
            'usuPlt' => '',
            'reason' => '',
            'ambiguous' => true,
            'candidates' => $unique,
        ];
    }

    /** @param  array<int, string>  $nameTokens */
    private function matchReason(string $email, string $emailLocal, array $nameTokens, string $usuPlt): ?string
    {
        $pltLower = strtolower($usuPlt);
        $pltLocal = strtok($pltLower, '@') ?: $pltLower;
        $pltSegments = array_values(array_filter(
            array_map(fn (string $s) => $this->normalizeKey($s), explode('.', $pltLocal)),
            fn (string $s) => $s !== '',
        ));

        if ($email !== '' && $this->normalizeKey($email) === $this->normalizeKey($pltLower)) {
            return 'email-exato';
        }

        if ($emailLocal !== '' && $this->normalizeKey($emailLocal) === $this->normalizeKey($pltLocal)) {
            return 'login-exato';
        }

        if ($emailLocal !== '' && $this->normalizeKey(str_replace('.', '', $emailLocal)) === $this->normalizeKey(str_replace('.', '', $pltLocal))) {
            return 'login-normalizado';
        }

        if ($emailLocal !== '' && str_starts_with($pltLocal, $emailLocal . '.')) {
            return 'login-prefixo';
        }

        if (count($pltSegments) >= 2 && count($nameTokens) >= 2) {
            $first = $nameTokens[0];
            $last = $nameTokens[count($nameTokens) - 1];
            if ($first === $pltSegments[0] && $last === $pltSegments[count($pltSegments) - 1]) {
                return 'nome-primeiro-ultimo';
            }
            if ($first === $pltSegments[0] && in_array($pltSegments[count($pltSegments) - 1], $nameTokens, true)) {
                return 'nome-primeiro-segmento';
            }
        }

        if (count($pltSegments) >= 2 && $this->segmentsInTokens($pltSegments, $nameTokens, $emailLocal)) {
            return 'nome-segmentos';
        }

        return null;
    }

    /** @param  array<int, string>  $segments
     * @param  array<int, string>  $nameTokens */
    private function segmentsInTokens(array $segments, array $nameTokens, string $emailLocal): bool
    {
        $pool = $nameTokens;
        if ($emailLocal !== '') {
            $pool[] = $this->normalizeKey($emailLocal);
        }

        foreach ($segments as $segment) {
            if (strlen($segment) < 2) {
                return false;
            }
            $found = false;
            foreach ($pool as $token) {
                if ($token === $segment || (strlen($segment) >= 4 && str_contains($token, $segment))) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private function nameTokens(string $name): array
    {
        $clean = preg_replace('/^(dra?\.?|sr\.?|sra\.?)\s+/iu', '', trim($name)) ?? trim($name);
        if (!preg_match_all('/[\p{L}]+/u', $clean, $m)) {
            return [];
        }

        $tokens = [];
        foreach ($m[0] as $word) {
            $key = $this->normalizeKey($word);
            if (strlen($key) >= 2) {
                $tokens[] = $key;
            }
        }

        return $tokens;
    }

    private function cleanUsuPlt(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '' || $v === 'true') {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeKey(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = preg_replace('/[^a-z0-9]+/', '', $v) ?? $v;

        return $v;
    }
}
