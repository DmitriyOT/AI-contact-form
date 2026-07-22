#!/usr/bin/env bash
# Прогон нагрузочного теста k6 против docker-окружения.
# Использование: bash loadtest/run.sh [vus] [duration]
#   bash loadtest/run.sh 20 15s   # значения по умолчанию
set -eu
cd "$(dirname "$0")/.."

VUS="${1:-20}"
DURATION="${2:-15s}"
NETWORK="ai-contact-form_default"

if ! docker compose ps --status running --format '{{.Name}}' | grep -q 'app'; then
  echo "Ошибка: контейнер app не запущен (docker compose up -d)" >&2
  exit 1
fi

MSYS_NO_PATHCONV=1 docker run --rm -i \
  --network "$NETWORK" \
  -v "$PWD/loadtest:/scripts" \
  -e VUS="$VUS" \
  -e DURATION="$DURATION" \
  grafana/k6 run /scripts/contact.js
