const API_URL: string = import.meta.env.VITE_API_URL ?? '';

const REQUEST_TIMEOUT_MS = 10_000;

/** Проверяет доступность сервиса через GET /api/health. */
export async function checkHealth(signal?: AbortSignal): Promise<boolean> {
  try {
    const response = await fetch(`${API_URL}/api/health`, {
      signal: signal
        ? AbortSignal.any([AbortSignal.timeout(REQUEST_TIMEOUT_MS), signal])
        : AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
    return response.ok;
  } catch {
    return false;
  }
}

export interface Metrics {
  total: number;
  today: number;
  last_7_days: Record<string, number>;
}

interface ApiMetricsBody {
  metrics?: {
    total?: number;
    today?: number;
    last_7_days?: Record<string, number>;
  };
}

export type MetricsResult =
  | { ok: true; metrics: Metrics }
  | {
      ok: false;
      type: 'unauthorized' | 'forbidden' | 'error' | 'network';
      message: string;
    };

/** Запрашивает GET /api/metrics с Bearer-токеном. */
export async function fetchMetrics(
  token: string,
  signal?: AbortSignal,
): Promise<MetricsResult> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/metrics`, {
      headers: { Authorization: `Bearer ${token}` },
      signal: signal
        ? AbortSignal.any([AbortSignal.timeout(REQUEST_TIMEOUT_MS), signal])
        : AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
  } catch {
    return {
      ok: false,
      type: 'network',
      message: 'Сервер недоступен, попробуйте позже',
    };
  }

  if (response.ok) {
    const body = (await response
      .json()
      .catch(() => null)) as ApiMetricsBody | null;
    return {
      ok: true,
      metrics: {
        total: body?.metrics?.total ?? 0,
        today: body?.metrics?.today ?? 0,
        last_7_days: body?.metrics?.last_7_days ?? {},
      },
    };
  }

  if (response.status === 401) {
    return { ok: false, type: 'unauthorized', message: 'Неверный токен' };
  }
  if (response.status === 403) {
    return {
      ok: false,
      type: 'forbidden',
      message: 'Доступ к метрикам запрещён',
    };
  }
  return {
    ok: false,
    type: 'error',
    message: 'Не удалось загрузить метрики, попробуйте позже',
  };
}
