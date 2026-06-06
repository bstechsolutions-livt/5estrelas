<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbClient
{
    protected $serverUrl;
    protected $token;
    protected $user;

    public function __construct($user = null, $token = null)
    {
        $scheme = config('reverb.servers.reverb.options.scheme', env('REVERB_SCHEME', 'http'));
        $host = config('reverb.servers.reverb.options.host', env('REVERB_HOST', 'reverb'));
        $port = config('reverb.servers.reverb.options.port', env('REVERB_PORT', 8080));
        $this->serverUrl = "{$scheme}://{$host}:{$port}";
        
        if ($user && $token) {
            $this->user = $user;
            $this->token = $token;
        } else {
            // Carregar credenciais do arquivo
            $this->loadCredentials();
        }
    }

    /**
     * Enviar mensagem para um canal
     */
    public function send($channel, $event, $data, $user = null)
    {
        try {
            $token = $user ? $this->getTokenForUser($user) : $this->token;
            
            if (!$token) {
                throw new \Exception('Token não encontrado para o usuário');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->serverUrl . '/api/broadcast', [
                'channel' => $channel,
                'event' => $event,
                'data' => $data
            ]);

            if ($response->successful()) {
                Log::info('Mensagem enviada para Reverb', [
                    'channel' => $channel,
                    'event' => $event,
                    'user' => $user ?: $this->user
                ]);
                return true;
            } else {
                Log::error('Erro ao enviar para Reverb', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Erro no ReverbClient', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Enviar notificação pública
     */
    public function notification($message, $data = [])
    {
        return $this->send('public.notifications', 'new-notification', array_merge([
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], $data));
    }

    /**
     * Enviar para chat público
     */
    public function chat($message, $user = 'Sistema', $data = [])
    {
        return $this->send('public.chat', 'new-message', array_merge([
            'message' => $message,
            'user' => $user,
            'timestamp' => now()->toISOString()
        ], $data));
    }

    /**
     * Enviar update do sistema
     */
    public function systemUpdate($title, $message, $data = [])
    {
        return $this->send('public.updates', 'system-update', array_merge([
            'title' => $title,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], $data));
    }

    /**
     * Verificar status do servidor
     */
    public function status()
    {
        try {
            $response = Http::get($this->serverUrl . '/api/status');
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Carregar credenciais do arquivo
     */
    protected function loadCredentials()
    {
        $credentialsFile = storage_path('reverb_credentials.txt');
        
        if (!file_exists($credentialsFile)) {
            throw new \Exception('Arquivo de credenciais não encontrado: ' . $credentialsFile);
        }

        $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$user, $token] = explode(':', $line, 2);
                // Usar a primeira credencial encontrada como padrão
                if (!$this->user) {
                    $this->user = trim($user);
                    $this->token = trim($token);
                }
            }
        }

        if (!$this->token) {
            throw new \Exception('Nenhuma credencial válida encontrada no arquivo');
        }
    }

    /**
     * Buscar token para usuário específico
     */
    protected function getTokenForUser($user)
    {
        $credentialsFile = storage_path('reverb_credentials.txt');
        $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$fileUser, $token] = explode(':', $line, 2);
                if (trim($fileUser) === $user) {
                    return trim($token);
                }
            }
        }

        return null;
    }

    /**
     * Listar usuários disponíveis
     */
    public static function getAvailableUsers()
    {
        $credentialsFile = storage_path('reverb_credentials.txt');
        $users = [];
        
        if (file_exists($credentialsFile)) {
            $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_contains($line, ':')) {
                    [$user, $token] = explode(':', $line, 2);
                    $users[] = trim($user);
                }
            }
        }

        return $users;
    }
}
