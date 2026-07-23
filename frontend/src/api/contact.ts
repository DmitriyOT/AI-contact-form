export interface ContactPayload {
  name: string;
  phone: string;
  email: string;
  comment: string;
}

// зеркалит ContactAccepted из docs/openapi.yaml (ответ 201)
export interface ContactAnalysis {
  sentiment: 'positive' | 'neutral' | 'negative';
  category: string;
  priority: 'низкий' | 'средний' | 'высокий' | 'срочный';
  summary: string;
}

export type SubmitResult =
  | {
      ok: true;
      message: string;
      ai: boolean;
      analysis: ContactAnalysis | null;
    }
  | {
      ok: false;
      type: 'validation';
      message: string;
      details: Record<string, string[]>;
    }
  | { ok: false; type: 'rate_limit'; message: string; retryAfter: number }
  | { ok: false; type: 'error'; message: string }
  | { ok: false; type: 'network'; message: string };

interface ApiErrorBody {
  error?: {
    code?: string;
    message?: string;
    details?: Record<string, string[]>;
  };
}

interface ApiAcceptedBody {
  message?: string;
  ai?: boolean;
  analysis?: ContactAnalysis | null;
}

const API_URL: string = import.meta.env.VITE_API_URL ?? '';

// backend worst case: AI timeout (10s) + two SMTP sends — 15s covers it with margin
const REQUEST_TIMEOUT_MS = 15_000;

/**
 * Отправляет обращение на POST /api/contact и разбирает ответ:
 * успех — {"status":"accepted","message","ai","analysis"},
 * ошибка — {"error":{"code","message","details"}}.
 *
 * Внешний signal (например, отмена при размонтировании компонента)
 * комбинируется с таймаутом через AbortSignal.any.
 */
export async function submitContact(
  payload: ContactPayload,
  signal?: AbortSignal,
): Promise<SubmitResult> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      // otherwise a hung connection leaves the form in "sending" state forever
      signal: signal
        ? AbortSignal.any([AbortSignal.timeout(REQUEST_TIMEOUT_MS), signal])
        : AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
  } catch (error) {
    const isTimeout =
      error instanceof DOMException && error.name === 'TimeoutError';
    return {
      ok: false,
      type: 'network',
      message: isTimeout
        ? 'Сервер не отвечает, попробуйте позже'
        : 'Сервер недоступен, попробуйте позже',
    };
  }

  if (response.status === 201) {
    const body = (await response
      .json()
      .catch(() => null)) as ApiAcceptedBody | null;
    return {
      ok: true,
      message: body?.message ?? 'Обращение принято. Спасибо!',
      ai: body?.ai ?? false,
      analysis: body?.analysis ?? null,
    };
  }

  const body = (await response.json().catch(() => null)) as ApiErrorBody | null;
  const message = body?.error?.message ?? 'Произошла ошибка, попробуйте позже';

  if (response.status === 422) {
    return {
      ok: false,
      type: 'validation',
      message,
      details: body?.error?.details ?? {},
    };
  }

  if (response.status === 429) {
    const header = response.headers.get('Retry-After');
    const parsed = header === null ? NaN : Number(header);
    // Number.isFinite, а не `|| 60`: легитимный 0 не должен превращаться в 60
    const retryAfter = Number.isFinite(parsed) && parsed >= 0 ? parsed : 60;
    return { ok: false, type: 'rate_limit', message, retryAfter };
  }

  return { ok: false, type: 'error', message };
}
