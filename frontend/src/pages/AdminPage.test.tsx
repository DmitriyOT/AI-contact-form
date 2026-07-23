import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AdminPage } from './AdminPage';

function renderAdmin() {
  return render(
    <MemoryRouter>
      <AdminPage />
    </MemoryRouter>,
  );
}

function metricsResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('AdminPage', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('показывает форму ввода токена и ссылку на главную', () => {
    vi.stubGlobal('fetch', vi.fn());
    renderAdmin();

    expect(
      screen.getByRole('heading', { name: 'Метрики обращений' }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Токен')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '← На главную' })).toHaveAttribute(
      'href',
      '/',
    );
  });

  it('загружает и показывает метрики по токену, сохраняет токен в localStorage', async () => {
    const user = userEvent.setup();
    const fetchMock = vi.fn().mockResolvedValue(
      metricsResponse(200, {
        status: 'ok',
        metrics: {
          total: 10,
          today: 2,
          last_7_days: { '2026-07-22': 3, '2026-07-21': 7 },
        },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);
    renderAdmin();

    await user.type(screen.getByLabelText('Токен'), 'secret-token');
    await user.click(screen.getByRole('button', { name: 'Показать метрики' }));

    expect(await screen.findByText('10')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('2026-07-22')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/metrics',
      expect.objectContaining({
        headers: { Authorization: 'Bearer secret-token' },
      }),
    );
    expect(localStorage.getItem('metricsToken')).toBe('secret-token');
  });

  it('при 401 показывает сообщение о неверном токене', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(metricsResponse(401, {})));
    renderAdmin();

    await user.type(screen.getByLabelText('Токен'), 'wrong');
    await user.click(screen.getByRole('button', { name: 'Показать метрики' }));

    expect(await screen.findByText('Неверный токен')).toBeInTheDocument();
  });

  it('при 403 показывает сообщение о запрете доступа', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(metricsResponse(403, {})));
    renderAdmin();

    await user.type(screen.getByLabelText('Токен'), 'wrong');
    await user.click(screen.getByRole('button', { name: 'Показать метрики' }));

    expect(
      await screen.findByText('Доступ к метрикам запрещён'),
    ).toBeInTheDocument();
  });

  it('при сетевой ошибке показывает сообщение о недоступности', async () => {
    const user = userEvent.setup();
    vi.stubGlobal(
      'fetch',
      vi.fn().mockRejectedValue(new TypeError('Failed to fetch')),
    );
    renderAdmin();

    await user.type(screen.getByLabelText('Токен'), 'secret-token');
    await user.click(screen.getByRole('button', { name: 'Показать метрики' }));

    expect(
      await screen.findByText('Сервер недоступен, попробуйте позже'),
    ).toBeInTheDocument();
  });

  it('при сохранённом токене загружает метрики автоматически', async () => {
    localStorage.setItem('metricsToken', 'saved-token');
    const fetchMock = vi.fn().mockResolvedValue(
      metricsResponse(200, {
        status: 'ok',
        metrics: { total: 5, today: 1, last_7_days: {} },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);
    renderAdmin();

    expect(await screen.findByText('5')).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/metrics',
      expect.objectContaining({
        headers: { Authorization: 'Bearer saved-token' },
      }),
    );
  });
});
