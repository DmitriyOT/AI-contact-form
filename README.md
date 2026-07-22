# Contact Form API

REST API for a contact form with AI integration (test assignment).

## Stack

- PHP 8.2, Symfony 7.4
- MySQL 8 (Doctrine DBAL)
- Monolog, Symfony Mailer, Symfony HttpClient, Symfony Validator
- Docker / Docker Compose

## Run

```bash
docker compose up -d --build
```

API will be available at `http://localhost:8082` (host port 8082 is used because 8080 may be occupied by other projects; change the port mapping in `docker-compose.yml` if needed).

Local overrides go to `.env.local` (not committed); committed `.env` holds safe defaults.

- `GET /api/health` — health check (verifies MySQL connectivity)
- `POST /api/contact` — contact form endpoint (stub for now)
- `GET /api/metrics` — metrics endpoint (stub for now)
