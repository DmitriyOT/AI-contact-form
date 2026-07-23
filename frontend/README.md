# Фронтенд — форма обратной связи

React 19 + TypeScript (strict) + Vite 7. Тесты — Vitest + Testing Library.
Node 24 (см. `.nvmrc` и `engines` в `package.json`).

## Команды

```bash
npm install          # установка зависимостей
npm run dev          # dev-сервер Vite
npm test             # тесты (vitest run)
npm run test:watch   # тесты в watch-режиме
npm run build        # type-check (tsc) + продакшн-сборка в dist/
npm run lint         # ESLint (flat config, eslint.config.js)
npm run format       # Prettier --write
npm run format:check # Prettier --check (используется в CI)
```

## Подключение к API

- `VITE_API_URL` — базовый URL бэкенда (см. `.env.example`).
- Пустое значение — запросы идут на тот же origin (`/api/...`):
  - в dev Vite проксирует `/api` на `http://localhost:8082`
    (docker compose маппит бэкенд на этот порт, см. `vite.config.ts`);
  - в продакшне nginx отдаёт статику и проксирует `/api` на бэкенд
    (см. `nginx.conf`).

## Маршруты

- `/` — главная страница: Hero, форма обратной связи, health-индикатор
  (при недоступности `GET /api/health` показывается баннер, форма дизейблится).
- `/admin` — метрики обращений (`GET /api/metrics`). Bearer-токен вводится
  в форму и сохраняется в `localStorage` (ключ `metricsToken`); при сохранённом
  токене метрики загружаются автоматически.

## Прочее

- Черновик обращения сохраняется в `localStorage` (ключ `contactFormDraft`)
  и очищается после успешной отправки.
- Dockerfile — двухстадийный: `node:24-alpine` (lint → test → build) → `nginx:alpine`.
