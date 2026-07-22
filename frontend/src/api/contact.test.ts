import { submitContact } from './contact';

function mockResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

describe('submitContact', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const payload = { name: 'Иван', phone: '+79001234567', email: 'ivan@example.com', comment: 'Тестовое обращение' };

  it('возвращает ok при 201', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(201, { status: 'accepted' })));

    const result = await submitContact(payload);

    expect(result).toEqual({ ok: true });
    expect(fetch).toHaveBeenCalledWith('/api/contact', expect.objectContaining({ method: 'POST' }));
  });

  it('маппит 422 в validation с details', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(422, {
      error: { code: 'validation_failed', message: 'Ошибка валидации', details: { email: ['Некорректный email'] } },
    })));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'validation',
      message: 'Ошибка валидации',
      details: { email: ['Некорректный email'] },
    });
  });

  it('маппит 429 в rate_limit с Retry-After из заголовка', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(
      429,
      { error: { code: 'too_many_requests', message: 'Слишком много запросов, попробуйте позже' } },
      { 'Retry-After': '120' }
    )));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'rate_limit',
      message: 'Слишком много запросов, попробуйте позже',
      retryAfter: 120,
    });
  });

  it('маппит 502 в error с message из ответа', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(502, {
      error: { code: 'email_failed', message: 'Не удалось отправить уведомление, попробуйте позже' },
    })));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'error',
      message: 'Не удалось отправить уведомление, попробуйте позже',
    });
  });

  it('возвращает network при reject fetch', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'network',
      message: 'Сервер недоступен, попробуйте позже',
    });
  });

  it('переживает не-JSON тело ошибки', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>error</html>', { status: 500 })));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'error',
      message: 'Произошла ошибка, попробуйте позже',
    });
  });

  it('429 без заголовка Retry-After даёт дефолтные 60 секунд', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(
      429,
      { error: { code: 'too_many_requests', message: 'Слишком много запросов' } }
    )));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'rate_limit',
      message: 'Слишком много запросов',
      retryAfter: 60,
    });
  });

  it('маппит 400 в error с message из тела', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(400, {
      error: { code: 'bad_request', message: 'Невалидный JSON' },
    })));

    const result = await submitContact(payload);

    expect(result).toEqual({ ok: false, type: 'error', message: 'Невалидный JSON' });
  });

  it('подставляет дефолтное сообщение, если в теле ошибки нет message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockResponse(500, { error: { code: 'internal_error' } })));

    const result = await submitContact(payload);

    expect(result).toEqual({
      ok: false,
      type: 'error',
      message: 'Произошла ошибка, попробуйте позже',
    });
  });
});
