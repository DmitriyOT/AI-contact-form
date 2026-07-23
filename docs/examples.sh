#!/usr/bin/env bash
# Примеры запросов к Contact Form API (локально: http://localhost:8082)
# Использование: bash docs/examples.sh
# Важно: JSON с кириллицей надёжнее отправлять из файла (--data-binary @file),
# а не inline-аргументом — см. README (особенность curl на Windows).
set -u
BASE="${BASE_URL:-http://localhost:8082}"
# Токен для GET /api/metrics (см. METRICS_TOKEN в .env.local)
METRICS_TOKEN="${METRICS_TOKEN:-dev-metrics-token}"

echo '=== 1. Health check ==='
curl -s -w '\nHTTP %{http_code}\n' "$BASE/api/health"

echo '=== 2. Metrics без токена -> 401 ==='
curl -s -w '\nHTTP %{http_code}\n' "$BASE/api/metrics"

echo '=== 3. Metrics с Bearer-токеном -> 200 ==='
curl -s -w '\nHTTP %{http_code}\n' "$BASE/api/metrics" \
  -H "Authorization: Bearer $METRICS_TOKEN"

echo '=== 4. Валидное обращение -> 201 (ai: true/false и analysis: объект/null в зависимости от AI) ==='
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/contact" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ivan Ivanov","phone":"+7 900 123-45-67","email":"ivan@example.com","comment":"I would like to know more about your services."}'

echo '=== 5. Пустое тело {} -> 422 с details по всем полям ==='
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/contact" \
  -H 'Content-Type: application/json' \
  -d '{}'

echo '=== 6. Невалидный email + короткий комментарий -> 422 ==='
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/contact" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ivan","phone":"+79001234567","email":"not-an-email","comment":"short"}'

echo '=== 7. Битый JSON -> 400 ==='
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/contact" \
  -H 'Content-Type: application/json' \
  -d '{invalid'

echo '=== 8. Неверный Content-Type -> 415 ==='
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/contact" \
  -H 'Content-Type: text/plain' \
  -d 'name=Ivan'

echo '=== 9. Rate limit: 6 запросов подряд при лимите 5/час -> последние 429 с Retry-After ==='
for i in 1 2 3 4 5 6; do
  curl -s -o /dev/null -w "request $i: HTTP %{http_code}\n" -X POST "$BASE/api/contact" \
    -H 'Content-Type: application/json' \
    -d '{"name":"Ivan Ivanov","phone":"+79001234567","email":"ivan@example.com","comment":"Rate limit test message."}'
done

echo '=== 10. CORS preflight ==='
curl -s -i -X OPTIONS "$BASE/api/contact" \
  -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: POST' | grep -iE '^HTTP|access-control'
