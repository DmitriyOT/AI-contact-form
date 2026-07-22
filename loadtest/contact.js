// Нагрузочный тест Contact Form API (k6).
// Запуск: bash loadtest/run.sh  (или вручную: docker run --rm --network ai-contact-form_default \
//   -v "$PWD/loadtest:/scripts" grafana/k6 run /scripts/contact.js)
//
// Цель — http://app:8080 внутри docker-сети (минуя nginx и публикацию портов).
//
// Особенность: POST /api/contact ограничен rate limiter'ом (5/час на IP).
// В локальном docker-окружении TRUSTED_PROXIES=REMOTE_ADDR, поэтому уникальный
// X-Forwarded-For на каждый запрос даёт каждому VU «свой» IP и не упирается в лимит —
// лимитер при этом работает и тоже нагружается. Без trusted proxies выставьте
// RATE_LIMIT_MAX повыше или сбрасывайте пул: bin/console cache:pool:clear cache.rate_limiter
import http from 'k6/http';
import { check } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://app:8080';

const contactDuration = new Trend('contact_duration', true);
const contactFailed = new Rate('contact_failed');

export const options = {
  scenarios: {
    // чистая пропускная способность: лёгкий GET без БД-записей и внешних вызовов
    health: {
      executor: 'constant-vus',
      exec: 'health',
      vus: Number(__ENV.VUS || 20),
      duration: __ENV.DURATION || '15s',
    },
    // полный цикл: валидация -> AI -> БД -> 2x SMTP
    contact: {
      executor: 'constant-vus',
      exec: 'contact',
      vus: Number(__ENV.VUS || 20),
      duration: __ENV.DURATION || '15s',
      startTime: __ENV.DURATION || '15s',
    },
  },
  thresholds: {
    // health — лёгкий эндпоинт, должен быть быстрым; contact — с запасом на AI/SMTP
    'http_req_duration{scenario:health}': ['p(95)<1000'],
    contact_duration: ['p(95)<5000'],
    contact_failed: ['rate<0.01'],
  },
};

export function health() {
  const res = http.get(`${BASE_URL}/api/health`);
  check(res, {
    'health 200': (r) => r.status === 200,
    'db up': (r) => r.json('db') === 'up',
  });
}

let vuCounter = 0;

export function contact() {
  // уникальный "клиентский IP" на запрос — обход 5/час лимита (см. шапку файла)
  const fakeIp = `10.${__VU & 255}.${(__VU * 251 + vuCounter) & 65535 >> 8}.${vuCounter & 255}`;
  vuCounter += 1;

  const payload = JSON.stringify({
    name: 'Нагрузочный Тест',
    phone: '+7 900 123-45-67',
    email: 'loadtest@example.com',
    comment: 'Нагрузочное тестирование формы обратной связи, игнорируйте.',
  });

  const res = http.post(`${BASE_URL}/api/contact`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'X-Forwarded-For': fakeIp,
    },
  });

  contactDuration.add(res.timings.duration);
  contactFailed.add(res.status !== 201);
  check(res, {
    'contact 201': (r) => r.status === 201,
  });
}
