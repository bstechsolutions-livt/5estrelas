#!/usr/bin/env bash
# Sobe (de forma idempotente) os serviços de infraestrutura de dev exigidos
# pelo backend Laravel: PostgreSQL 16 e Redis. Garante também que o papel e os
# bancos "estrelas"/"estrelas_test" existam. Seguro para rodar em todo boot.
#
# Usado como comando "start" do ambiente de Cloud Agent. Os binários de sistema
# (PostgreSQL, Redis, PHP, Composer, Node) já fazem parte da imagem base.
set -euo pipefail

PG_VERSION="${PG_VERSION:-16}"
DB_USER="${DB_USER:-estrelas}"
DB_PASS="${DB_PASS:-estrelas}"
DB_NAME="${DB_NAME:-estrelas}"
DB_TEST="${DB_TEST:-estrelas_test}"

echo "[services] iniciando PostgreSQL ${PG_VERSION}..."
if ! sudo pg_isready -q 2>/dev/null; then
  sudo pg_ctlcluster "${PG_VERSION}" main start 2>/dev/null || true
fi

# Aguarda o Postgres aceitar conexões (até ~30s).
for _ in $(seq 1 30); do
  if sudo pg_isready -q 2>/dev/null; then break; fi
  sleep 1
done
sudo pg_isready || { echo "[services] PostgreSQL nao ficou pronto" >&2; exit 1; }

echo "[services] garantindo papel e bancos..."
sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='${DB_USER}') THEN
    CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';
  END IF;
END \$\$;
SQL
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 \
  || sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_TEST}'" | grep -q 1 \
  || sudo -u postgres createdb -O "${DB_USER}" "${DB_TEST}"

echo "[services] iniciando Redis..."
if ! redis-cli ping >/dev/null 2>&1; then
  sudo redis-server --daemonize yes 2>/dev/null || true
  for _ in $(seq 1 15); do
    if redis-cli ping >/dev/null 2>&1; then break; fi
    sleep 1
  done
fi
redis-cli ping >/dev/null 2>&1 && echo "[services] Redis OK" || echo "[services] AVISO: Redis indisponivel (dev usa drivers de banco por padrao)"

echo "[services] infraestrutura pronta."
