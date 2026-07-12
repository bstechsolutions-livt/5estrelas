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

        $byUsuPlt = [];
        foreach ($seniorUsers as $su) {
            $plt = $this->normalizeKey($su['usuPlt'] ?? '');
            if ($plt !== '') {
                $byUsuPlt[$plt] = $su['codUsu'];
            }
            $emailLike = strtolower(trim((string) ($su['usuPlt'] ?? '')));
            if ($emailLike !== '' && str_contains($emailLike, '@')) {
                $byUsuPlt[$this->normalizeKey($emailLike)] = $su['codUsu'];
                $local = strtok($emailLike, '@') ?: '';
                if ($local !== '') {
                    $byUsuPlt[$this->normalizeKey($local)] = $su['codUsu'];
                }
            }
        }

        $matched = 0;
        $skipped = 0;
        $ambiguous = 0;

        foreach (User::orderBy('id')->get() as $user) {
            if ($user->senior_cod_usu && !$force) {
                $skipped++;

                continue;
            }

            $codUsu = $this->matchUser($user, $byUsuPlt, $seniorUsers);
            if ($codUsu === null) {
                $this->warn("SKIP #{$user->id} {$user->name} <{$user->email}> — sem match");
                $skipped++;

                continue;
            }

            $existing = User::where('senior_cod_usu', $codUsu)->where('id', '!=', $user->id)->exists();
            if ($existing) {
                $this->warn("AMBIG #{$user->id} {$user->name} → codUsu {$codUsu} já usado por outro usuário");
                $ambiguous++;

                continue;
            }

            $this->line(sprintf(
                '%s #%d %s → codUsu %d',
                $dryRun ? '[dry]' : 'OK',
                $user->id,
                $user->name,
                $codUsu,
            ));

            if (!$dryRun) {
                $user->update(['senior_cod_usu' => $codUsu]);
            }

            $matched++;
        }

        $this->info("Concluído: {$matched} vinculados, {$skipped} sem match/já preenchidos, {$ambiguous} ambíguos.");

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
                    'usuPlt' => trim((string) ($row['usuPlt'] ?? '')),
                ];
            }
        }

        return array_values($all);
    }

    /** @param array<string, int> $byUsuPlt */
    private function matchUser(User $user, array $byUsuPlt, array $seniorUsers): ?int
    {
        $email = strtolower(trim($user->email));
        if ($email !== '' && isset($byUsuPlt[$this->normalizeKey($email)])) {
            return $byUsuPlt[$this->normalizeKey($email)];
        }

        $emailLocal = strtolower(strtok($user->email, '@') ?: '');
        if ($emailLocal !== '') {
            if (isset($byUsuPlt[$this->normalizeKey($emailLocal)])) {
                return $byUsuPlt[$this->normalizeKey($emailLocal)];
            }
            foreach ($byUsuPlt as $plt => $codUsu) {
                if ($emailLocal === $plt || str_contains($emailLocal, $plt) || str_contains($plt, $emailLocal)) {
                    return $codUsu;
                }
            }
        }

        foreach ($seniorUsers as $su) {
            $plt = strtolower(trim((string) ($su['usuPlt'] ?? '')));
            if ($plt === '' || str_ends_with($plt, '@')) {
                continue;
            }
            if ($email !== '' && ($plt === $email || str_starts_with($plt, $emailLocal . '@') || str_starts_with($email, rtrim($plt, '@')))) {
                return (int) $su['codUsu'];
            }
        }

        $nameKey = $this->normalizeKey($user->name);
        if ($nameKey !== '') {
            foreach ($byUsuPlt as $plt => $codUsu) {
                if ($nameKey === $plt || str_contains($nameKey, $plt) || str_contains($plt, $nameKey)) {
                    return $codUsu;
                }
            }
        }

        $firstToken = $this->normalizeKey(strtok($user->name, ' ') ?: '');
        if ($firstToken !== '' && strlen($firstToken) >= 4) {
            foreach ($seniorUsers as $su) {
                $plt = $this->normalizeKey($su['usuPlt'] ?? '');
                if ($plt !== '' && (str_starts_with($plt, $firstToken) || str_starts_with($firstToken, $plt))) {
                    return (int) $su['codUsu'];
                }
            }
        }

        return null;
    }

    private function normalizeKey(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = preg_replace('/[^a-z0-9]+/u', '', $v) ?? $v;

        return $v;
    }
}
