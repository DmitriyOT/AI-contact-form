import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { App } from './App';

function mockHealthOk() {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ status: 'ok', db: 'up' }), {
        status: 200,
      }),
    ),
  );
}

describe('App', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('показывает главную страницу: Hero, форму и футер', async () => {
    mockHealthOk();
    render(
      <MemoryRouter initialEntries={['/']}>
        <App />
      </MemoryRouter>,
    );

    expect(
      screen.getByRole('heading', { level: 1, name: 'Иван Петров' }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('heading', { name: 'Связаться со мной' }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: 'Отправить' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Метрики' })).toHaveAttribute(
      'href',
      '/admin',
    );
  });

  it('показывает страницу метрик на /admin', async () => {
    mockHealthOk();
    render(
      <MemoryRouter initialEntries={['/admin']}>
        <App />
      </MemoryRouter>,
    );

    expect(
      screen.getByRole('heading', { name: 'Метрики обращений' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '← На главную' })).toHaveAttribute(
      'href',
      '/',
    );
  });
});
