import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { fetchMetrics } from '../api/monitoring';
import type { Metrics } from '../api/monitoring';

const TOKEN_KEY = 'metricsToken';

export function AdminPage() {
  const [token, setToken] = useState(
    () => localStorage.getItem(TOKEN_KEY) ?? '',
  );
  const [metrics, setMetrics] = useState<Metrics | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (value: string, signal?: AbortSignal) => {
    setLoading(true);
    setError('');
    const result = await fetchMetrics(value, signal);
    if (signal?.aborted) {
      return;
    }
    setLoading(false);
    if (result.ok) {
      setMetrics(result.metrics);
    } else {
      setMetrics(null);
      setError(result.message);
    }
  }, []);

  // если токен уже сохранён — загружаем метрики сразу
  useEffect(() => {
    const saved = localStorage.getItem(TOKEN_KEY);
    if (!saved) {
      return;
    }
    const controller = new AbortController();
    void load(saved, controller.signal);
    return () => controller.abort();
  }, [load]);

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    const trimmed = token.trim();
    if (!trimmed) {
      return;
    }
    localStorage.setItem(TOKEN_KEY, trimmed);
    void load(trimmed);
  };

  return (
    <>
      <main className="section" id="main">
        <div className="container container--narrow">
          <h2 className="section__title">Метрики обращений</h2>
          <p className="section__subtitle">
            Данные GET /api/metrics, доступ по Bearer-токену.
          </p>

          <form className="form" onSubmit={handleSubmit}>
            <div className="form__group">
              <label className="form__label" htmlFor="metrics-token">
                Токен
              </label>
              <input
                id="metrics-token"
                className="form__control"
                type="password"
                autoComplete="off"
                placeholder="METRICS_TOKEN"
                value={token}
                onChange={(event) => setToken(event.target.value)}
              />
            </div>
            <button
              className="form__submit"
              type="submit"
              disabled={loading || !token.trim()}
            >
              {loading ? 'Загрузка...' : 'Показать метрики'}
            </button>
            <div className="form__status" aria-live="polite">
              {error && (
                <p className="form__message form__message--error">{error}</p>
              )}
            </div>
          </form>

          {metrics && (
            <div className="metrics">
              <div className="metrics__cards">
                <div className="card">
                  <h3 className="card__title">Всего</h3>
                  <p className="metrics__value">{metrics.total}</p>
                </div>
                <div className="card">
                  <h3 className="card__title">Сегодня</h3>
                  <p className="metrics__value">{metrics.today}</p>
                </div>
              </div>
              <h3 className="metrics__subtitle">За последние 7 дней</h3>
              <ul className="metrics__days">
                {Object.entries(metrics.last_7_days).map(([date, count]) => (
                  <li className="metrics__day" key={date}>
                    <span>{date}</span>
                    <span>{count}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <p className="admin__back">
            <Link className="footer__link" to="/">
              ← На главную
            </Link>
          </p>
        </div>
      </main>
    </>
  );
}
