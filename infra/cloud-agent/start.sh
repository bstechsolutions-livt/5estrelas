#!/usr/bin/env bash
# Comando "start" do ambiente de Cloud Agent. Roda a cada boot, DEPOIS do
# install. Sobe a infraestrutura (PostgreSQL + Redis) e os serviços de
# desenvolvimento de longa duração em background, de forma idempotente, e então
# retorna. Cada serviço loga em app/storage/logs/cloud-agent/<nome>.log e tem um
# pidfile <nome>.pid usado para evitar processos duplicados.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_DIR="${REPO_ROOT}/app"
RUN_DIR="${APP_DIR}/storage/logs/cloud-agent"
mkdir -p "${RUN_DIR}"

# 1) Infraestrutura (PostgreSQL + Redis + papel/bancos).
bash "${REPO_ROOT}/infra/cloud-agent/services.sh"

# 2) Serviços de dev de longa duração (idempotente via pidfile).
start_service() {
  local name="$1" cmd="$2"
  local pidfile="${RUN_DIR}/${name}.pid"
  if [ -f "${pidfile}" ] && kill -0 "$(cat "${pidfile}" 2>/dev/null)" 2>/dev/null; then
    echo "[start] ${name} já está rodando (pid $(cat "${pidfile}"))."
    return 0
  fi
  echo "[start] iniciando ${name}..."
  # 'exec' faz o processo do serviço herdar o PID do shell, então o pidfile
  # aponta direto para ele.
  nohup bash -lc "cd '${APP_DIR}' && exec ${cmd}" >"${RUN_DIR}/${name}.log" 2>&1 &
  echo $! >"${pidfile}"
  disown || true
}

start_service "web"    "php artisan serve --host=0.0.0.0 --port=8090"
start_service "vite"   "npm run dev -- --host 0.0.0.0"
start_service "reverb" "php artisan reverb:start --host=0.0.0.0 --port=8080"
start_service "queue"  "php artisan queue:work --tries=1 --timeout=0"

# 3) Espera o servidor web responder (até ~30s) para um start "pronto".
for _ in $(seq 1 30); do
  if curl -fsS -o /dev/null "http://127.0.0.1:8090/login" 2>/dev/null; then
    echo "[start] web respondendo em http://localhost:8090"
    break
  fi
  sleep 1
done

echo "[start] serviços iniciados (logs/pids em ${RUN_DIR})."
