#!/usr/bin/env bash
# Bootstrap idempotente do repositório para Cloud Agents (comando "install").
# Deve ser executado a partir da raiz do repositório.
#
# Pré-requisitos de sistema (PHP 8.3 + extensões, Composer, Node 20+,
# PostgreSQL 16, Redis) já fazem parte da imagem base do ambiente. Aqui só
# preparamos dependências e estado derivado do código-fonte.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_DIR="${REPO_ROOT}/app"

# Sobe Postgres/Redis: o migrate precisa do banco disponível durante o install.
bash "${REPO_ROOT}/infra/cloud-agent/services.sh"

cd "${APP_DIR}"

echo "[install] .env..."
[ -f .env ] || cp .env.example .env

echo "[install] composer install..."
composer install --prefer-dist --no-progress --no-interaction

echo "[install] APP_KEY..."
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "[install] diretórios de runtime..."
mkdir -p storage/framework/views \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/app/public \
         bootstrap/cache

echo "[install] dependências de frontend..."
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi

echo "[install] build do frontend (gera public/build/manifest.json)..."
npm run build

echo "[install] migrations..."
php artisan migrate --force

# Seed base (admins + settings) apenas se o banco ainda não tiver usuários,
# para não sobrescrever dados de trabalho do agente em re-execuções.
USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')"
if [ "${USER_COUNT:-0}" = "0" ]; then
  echo "[install] seed inicial (db:seed)..."
  php artisan db:seed --force
else
  echo "[install] banco já populado (${USER_COUNT} usuários); pulando seed."
fi

echo "[install] concluído."
