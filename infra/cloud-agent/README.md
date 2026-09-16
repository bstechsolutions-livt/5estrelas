# Ambiente de Cloud Agent (Cursor)

Scripts usados pelo ambiente de desenvolvimento dos Cloud Agents do Cursor.
Reproduzem o "Setup local" do [README](../../README.md) de forma não-interativa
e idempotente.

## Imagem base (snapshot)

Os pacotes de sistema abaixo já fazem parte da imagem base do ambiente
(instalados em Ubuntu 24.04):

- PHP 8.3 + extensões: `pgsql`, `pdo_pgsql`, `mbstring`, `xml`, `zip`,
  `sqlite3`, `curl`, `bcmath`, `gd`, `intl`, `redis`
- Composer 2
- Node 20+ / npm
- PostgreSQL 16 (cluster `main`)
- Redis

## Scripts

| Script | Papel no ambiente | O que faz |
|--------|-------------------|-----------|
| `install.sh` | `install` (bootstrap) | `composer install`, `.env` + `APP_KEY`, cria diretórios de runtime, `npm ci`, `npm run build`, `php artisan migrate --force` e seed inicial (só quando o banco está vazio). |
| `start.sh` | `start` (todo boot) | Chama `services.sh` e sobe os serviços de dev de longa duração em background (idempotente via pidfile), esperando o web responder. |
| `services.sh` | infraestrutura | Sobe PostgreSQL e Redis e garante o papel/bancos `estrelas` e `estrelas_test`. Usado por `install.sh` e `start.sh`. |

## Serviços de longa duração (subidos pelo `start.sh`)

Rodam em background, com logs/pids em `app/storage/logs/cloud-agent/`:

- `web` — `php artisan serve --host=0.0.0.0 --port=8090`
- `vite` — `npm run dev` (HMR na porta 5173)
- `reverb` — `php artisan reverb:start --host=0.0.0.0 --port=8080`
- `queue` — `php artisan queue:work`

A aplicação fica em `http://localhost:8090`. Login de dev: `admin@5estrelas.com.br` / `password`.
