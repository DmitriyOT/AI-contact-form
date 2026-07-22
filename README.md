# Contact Form API

REST API формы обратной связи с AI-анализом обращений (тональность + классификация) на Symfony 7.4. Тестовое задание на позицию backend-разработчика.

## 1. Как запустить

Требования: Docker + Docker Compose.

```bash
git clone <repo> && cd AI-contact-form
cp .env .env.local   # опционально: локальные переопределения (см. ниже)
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

После запуска доступны:

| Сервис | Адрес |
|---|---|
| API | http://localhost:8082 |
| Swagger UI (docs/openapi.yaml) | http://localhost:8083 |
| Mailpit (перехват email) | http://localhost:8025 |
| ai-mock (демо AI-провайдера) | внутренний, `http://ai-mock` |

> Порт 8082 используется вместо 8080, так как 8080 часто занят другими проектами; меняется в `docker-compose.yml`.

**Переменные окружения.** `.env` (в репозитории) — безопасные дефолты; `.env.local` (в .gitignore) — реальные локальные значения, переопределяет `.env`. Для запуска из коробки `.env.local` не нужен: AI без ключа просто выключается (graceful fallback), а БД и почта работают на дефолтах. Для демо AI без реального ключа добавьте в `.env.local`:

```
AI_BASE_URL=http://ai-mock
AI_API_KEY=demo-key
AI_MODEL=mock-model
MAILER_DSN=smtp://mailer:1025
```

| Переменная | Назначение | Дефолт (.env) |
|---|---|---|
| `DATABASE_URL` | DSN подключения к MySQL | `mysql://app:secret@127.0.0.1:3306/contact_form...` (в `.env.local` — хост `db`) |
| `MAILER_DSN` | DSN SMTP (Mailpit в dev) | `null://null` |
| `MAIL_FROM` | Адрес отправителя | `noreply@example.com` |
| `MAIL_OWNER_EMAIL` | Адрес владельца для уведомлений | `owner@example.com` |
| `AI_API_KEY` | Ключ AI-провайдера (пусто = AI выключен) | пусто |
| `AI_BASE_URL` | Base URL OpenAI-совместимого API | `https://api.openai.com/v1` |
| `AI_MODEL` | Модель | `gpt-4o-mini` |
| `RATE_LIMIT_MAX` | Лимит запросов на IP | `5` |
| `RATE_LIMIT_WINDOW` | Окно лимита, секунд | `3600` |
| `CORS_ALLOW_ORIGIN` | Regex разрешённых Origin | `^https?://localhost(:[0-9]+)?$` |

Полезные команды:

```bash
docker compose logs -f app                                  # логи приложения
docker compose exec app php bin/console cache:pool:clear cache.rate_limiter   # сброс rate limit
docker compose exec app php bin/console debug:router        # список маршрутов
```

> Особенность curl на Windows (Git Bash): кириллица в inline-аргументе `-d '{...}'` доезжает битой. Отправляйте JSON из файла: `curl --data-binary @request.json ...` или используйте `docs/examples.sh` / Postman-коллекцию.

## 2. Стек

- **PHP 8.2**, **Symfony 7.4** (последняя стабильная 7.x; Symfony 8.1 требует PHP >= 8.4, а целевая версия PHP — 8.2)
- **doctrine/dbal + doctrine/migrations** — подключение к MySQL и миграции без ORM-маппинга (ORM установлен транзитивно, но не используется)
- **symfony/mailer + twig** — email-уведомления с HTML-шаблонами
- **symfony/rate-limiter** — антиспам (fixed window, файловое хранилище)
- **symfony/http-client** — AI-интеграция (OpenAI-совместимый Chat Completions API)
- **symfony/validator** — валидация DTO через атрибуты
- **nelmio/cors-bundle** — CORS для локального фронтенда
- **monolog** — логирование (каналы app / access)
- **MySQL 8**, **Mailpit** (перехват почты в dev), **Swagger UI**
- Всё окружение — **Docker Compose** (php:8.2-cli + встроенный сервер `php -S`)

## 3. Архитектура

Слоистая: **Controllers → Services → Repository**. Контроллеры тонкие (HTTP + rate limit + валидация), бизнес-логика в сервисах, SQL — только в репозитории.

```
src/
├── Controller/
│   ├── ContactController.php      # POST /api/contact: rate limit → Content-Type → JSON → DTO → валидация → сервис
│   ├── HealthController.php       # GET /api/health: SELECT 1 по БД (200/503)
│   └── MetricsController.php      # GET /api/metrics: живые счётчики из репозитория
├── Dto/
│   ├── ContactRequest.php         # DTO входа: констрейнты + маппинг/санитизация из JSON
│   └── ContactResult.php          # Результат сервиса (accepted/emailSent/aiProcessed)
├── EventSubscriber/
│   ├── ApiExceptionSubscriber.php # Единый JSON-формат ошибок для всего API
│   └── RequestLogSubscriber.php   # Access-лог запросов (метод, путь, статус, IP, мс)
├── Exception/
│   ├── ValidationFailedHttpException.php  # 422 + details по полям
│   └── EmailSendingException.php          # 502 при сбое отправки владельцу
├── Repository/
│   └── ContactRepository.php      # DBAL: save/countAll/countToday/countByDay
└── Service/
    ├── ContactService.php         # Оркестрация: AI → save → email владельцу → копия пользователю
    ├── ContactMailer.php          # Письма через TemplatedEmail
    └── AiAnalyzer.php             # AI-анализ (тональность + категория), graceful fallback
```

Ключевые решения и паттерны:

- **Symfony** — стек, указанный в вакансии; идиоматичные бандлы вместо велосипедов.
- **DTO + Validator** — валидация декларативна, сообщения на русском; нестроковые типы в JSON ловятся `Assert\Type` → 422, а не 500.
- **Санитизация** на входе: `trim` + `strip_tags` для name/phone/email; comment хранится как есть и экранируется при выводе (Twig autoescape — подтверждено XSS-тестом).
- **Graceful degradation** — всё, что может сломаться вне нашего контроля, деградирует безопасно:
  - AI недоступен/выключен → обращение принимается, `ai: false`, поля в БД NULL;
  - БД недоступна при сохранении → error в лог, письма уходят, 201 (уведомление владельца важнее записи);
  - копия письма пользователю не отправилась → warning, 201;
  - критичен только email владельцу: его сбой → 502.
- **Файловый rate limiter** — требование задания допускает файловое кеширование; storage не нужен за пределами одного инстанса, Redis избыточен. Consume — до валидации: спам мусорными телами тоже тратит лимит.
- **DI/autowiring** — стандартный `services.yaml`, env-параметры через `#[Autowire('%env(...)%')]`.

## 4. Реализация API

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/contact` | Приём обращения (rate limit по IP: 5/час) |
| GET | `/api/health` | Health-check (проверяет MySQL) |
| GET | `/api/metrics` | Статистика обращений из БД |

Спецификация: `docs/openapi.yaml` (OpenAPI 3.0), просмотр — Swagger UI на http://localhost:8083. Примеры: `docs/examples.sh`, `docs/postman_collection.json`.

Реальные ответы (из проверок):

```
POST /api/contact  →  201 {"status":"accepted","message":"Обращение принято","ai":true}

POST /api/contact {}  →  422
{"error":{"code":"validation_failed","message":"Ошибка валидации",
 "details":{"name":["Укажите имя"],"phone":["Укажите телефон"],
            "email":["Укажите email"],"comment":["Укажите текст обращения"]}}}

битый JSON → 400 {"error":{"code":"bad_request","message":"Невалидный JSON"}}
text/plain → 415 {"error":{"code":"unsupported_media_type","message":"Ожидается Content-Type: application/json"}}
6-й запрос за час → 429 {"error":{"code":"too_many_requests","message":"Слишком много запросов, попробуйте позже"}} + Retry-After: <секунды>
сбой SMTP → 502 {"error":{"code":"email_failed","message":"Не удалось отправить уведомление, попробуйте позже"}}

GET /api/health  → 200 {"status":"ok","db":"up"}  |  503 {"status":"error","db":"down"}
GET /api/metrics → 200 {"status":"ok","metrics":{"total":10,"today":10,"last_7_days":{"2026-07-22":10}}}
```

Валидация: name 2–100, phone — международный формат (`+7 ...`, допускаются пробелы/скобки/дефисы), email — strict (egulias), comment 10–2000. Неизвестные поля в JSON игнорируются.

## 5. AI-интеграция

Две функции одним запросом к OpenAI-совместимому Chat Completions API: **тональность** (`positive|neutral|negative`) и **классификация типа обращения** (`вопрос|заказ|жалоба|предложение|сотрудничество|другое`) + краткое резюме. Результат сохраняется в БД (`ai_sentiment`, `ai_category`) и включается в письмо владельцу (блок «AI-анализ»).

Промпт (system message, полный текст):

```
Ты — помощник службы поддержки. Проанализируй обращение клиента и ответь СТРОГО одним JSON-объектом без пояснений и markdown-обёрток:
{"sentiment":"...","category":"...","summary":"..."}

Правила:
- sentiment — тональность обращения, ровно одно из значений: "positive", "neutral", "negative".
- category — тип обращения, ровно одно из значений: "вопрос", "заказ", "жалоба", "предложение", "сотрудничество", "другое".
- summary — краткое резюме обращения на русском языке, одно предложение (до 200 символов).
```

Комментарий передаётся отдельным user-сообщением. Таймаут 10 с, `response_format: json_object` (парсинг на него не полагается: JSON извлекается regex'ом, переживает ```json-обёртки; sentiment проверяется по whitelist, категория вне списка → `другое`).

**Graceful fallback** — AI никогда не роняет запрос:

- `AI_API_KEY` пуст → сервис выключен, `analyze()` сразу null (info в логе один раз);
- сетевая ошибка / таймаут / 4xx-5xx / невалидный или «мусорный» JSON в ответе → warning в лог (без персональных данных), null;
- null → поля в БД NULL, письмо без AI-блока, в ответе `"ai": false`.

**Смена провайдера** — только через env (`AI_BASE_URL`/`AI_API_KEY`/`AI_MODEL`): OpenAI, OpenRouter, Groq, корпоративный прокси. Для локальной демонстрации без ключа в compose есть сервис **ai-mock** (`docker/ai-mock/`) — минимальный OpenAI-совместимый ответчик с «анализом» по ключевым словам (только для dev/демо, помечено в коде).

## 6. Что сделано с помощью AI

Проект сгенерирован AI-ассистентом (Kimi Code CLI) итеративно, по одному коммиту за итерацию. Человек ставил высокоуровневые задачи на каждый коммит (инициализация; error handler + логирование + CORS; валидация; email; rate limiting; персистентность; AI; документация), выбирал стек (Symfony — требование вакансии; изначально планировался Slim 4, первый коммит был переделан), проверял и принимал каждый коммит. Весь код, конфиги, миграции, шаблоны, OpenAPI-спека и этот README написаны ассистентом; проверки — реальные прогоны curl/docker на каждом шаге.

Ручные и совместные исправления в процессе (нашлись именно прогонами):

- `config.platform.php=8.2` в composer.json + downgrade doctrine-bundle 3.2 → 2.18 (бандл 3.x использует typed constants PHP 8.3, под 8.2 падал контейнер);
- `MAILER_DSN`: имя сервиса compose (`mailer`), а не образа (`mailpit`);
- баг ai-mock: он сканировал весь payload, включая system-промпт со словом «жалоба», — всё классифицировалось как негатив; исправлено на анализ только user-сообщения;
- monolog-канал `request` → `access`: в access-лог мусорил RouterListener (`Matched route...`).

## 7. Хранение данных

- **MySQL 8** — таблица `contacts` (миграция `migrations/Version20260722063053.php`):

| Поле | Тип | Комментарий |
|---|---|---|
| id | BIGINT AUTO_INCREMENT PK | |
| name | VARCHAR(100) | |
| phone | VARCHAR(20) | |
| email | VARCHAR(255) | |
| comment | TEXT | HTML не вырезается, экранируется при выводе |
| ai_sentiment | VARCHAR(20) NULL | positive/neutral/negative |
| ai_category | VARCHAR(50) NULL | тип обращения |
| ip_address | VARCHAR(45) NULL | IPv4/IPv6 |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | индекс |

- **Логи** (`var/log/`, вне git): `access.log` — все запросы (метод, путь, статус, IP, длительность; канал `access`), `dev.log`/`prod.log` — ошибки и события приложения (канал app; stack trace исключений).
- **Rate limiting** — файловый кеш-пул `cache.rate_limiter` (var/share/.../pools); сброс: `bin/console cache:pool:clear cache.rate_limiter`.
- **Статистика** `/api/metrics` — из БД на лету, без отдельного хранилища.
- **Email** в dev — Mailpit (in-memory), в проде — реальный SMTP через `MAILER_DSN`.

## Деплой (кратко)

- **VPS**: `git clone` → `docker compose up -d --build` → миграции → в `.env.local` боевые `DATABASE_URL`, `MAILER_DSN`, `AI_API_KEY`, `APP_ENV=prod`. Наружу — reverse proxy (nginx/Caddy) с TLS на порт app.
- **Render/Railway**: деплой из репозитория как Docker-проект (Dockerfile в корне), managed MySQL подключается через `DATABASE_URL`, порты/health-check — `/api/health`. Mailpit/ai-mock/swagger-ui живут в `compose.override.yaml` и в прод не едут — они только для локальной разработки.
