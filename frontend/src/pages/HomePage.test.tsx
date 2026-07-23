import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HomePage } from './HomePage';

function renderHome() {
  return render(
    <MemoryRouter>
      <HomePage />
    </MemoryRouter>,
  );
}

describe('HomePage (health-индикатор)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('не показывает баннер, когда /api/health отвечает 200', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ status: 'ok', db: 'up' }), {
          status: 200,
        }),
      ),
    );
    renderHome();

    // ждём завершения health-запроса, чтобы не было акт-варнингов
    await screen.findByRole('button', { name: 'Отправить' });
    expect(
      screen.queryByText(/Сервис временно недоступен/),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Отправить' })).toBeEnabled();
  });

  it('показывает баннер и дизейблит форму, когда сервис недоступен', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockRejectedValue(new TypeError('Failed to fetch')),
    );
    renderHome();

    expect(
      await screen.findByText(/Сервис временно недоступен/),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Отправить' })).toBeDisabled();
    expect(screen.getByLabelText('Имя')).toBeDisabled();
  });

  it('показывает баннер, когда /api/health отвечает 503', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ status: 'error', db: 'down' }), {
          status: 503,
        }),
      ),
    );
    renderHome();

    expect(
      await screen.findByText(/Сервис временно недоступен/),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Отправить' })).toBeDisabled();
  });
});
