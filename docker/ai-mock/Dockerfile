# AI mock server — ONLY for local development and demo purposes.
# Serves docker/ai-mock/index.php on port 80 inside the compose network.
FROM php:8.2-cli

WORKDIR /app
COPY index.php .

CMD ["php", "-S", "0.0.0.0:80", "index.php"]
