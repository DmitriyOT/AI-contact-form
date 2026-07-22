export interface ContactPayload {
  name: string;
  phone: string;
  email: string;
  comment: string;
}

export type SubmitResult =
  | { ok: true }
  | { ok: false; type: 'validation'; message: string; details: Record<string, string[]> }
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

const API_URL: string = import.meta.env.VITE_API_URL ?? '';

/**
 * Отправляет обращение на POST /api/contact и разбирает ответ
 * в едином формате бэкенда {"error":{"code","message","details"}}.
 */
export async function submitContact(payload: ContactPayload): Promise<SubmitResult> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  } catch {
    return { ok: false, type: 'network', message: 'Сервер недоступен, попробуйте позже' };
  }

  if (response.status === 201) {
    return { ok: true };
  }

  const body = (await response.json().catch(() => null)) as ApiErrorBody | null;
  const message = body?.error?.message ?? 'Произошла ошибка, попробуйте позже';

  if (response.status === 422) {
    return { ok: false, type: 'validation', message, details: body?.error?.details ?? {} };
  }

  if (response.status === 429) {
    const retryAfter = Number(response.headers.get('Retry-After')) || 60;
    return { ok: false, type: 'rate_limit', message, retryAfter };
  }

  return { ok: false, type: 'error', message };
}
