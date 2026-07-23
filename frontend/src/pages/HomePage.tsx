import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Hero } from '../components/Hero';
import { ContactForm } from '../components/ContactForm';
import { checkHealth } from '../api/monitoring';

export function HomePage() {
  const [apiDown, setApiDown] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    void checkHealth(controller.signal).then((up) => {
      if (!controller.signal.aborted) {
        setApiDown(!up);
      }
    });
    return () => controller.abort();
  }, []);

  return (
    <>
      <a className="skip-link" href="#main">
        Перейти к содержимому
      </a>
      <Hero />
      <main id="main">
        {apiDown && (
          <div className="health-banner" role="alert">
            Сервис временно недоступен — форма отключена. Попробуйте зайти
            позже.
          </div>
        )}
        <section className="section" id="contact">
          <div className="container container--narrow">
            <h2 className="section__title">Связаться со мной</h2>
            <p className="section__subtitle">
              Оставьте обращение — я отвечу в ближайшее время.
            </p>
            <ContactForm disabled={apiDown} />
          </div>
        </section>
      </main>
      <footer className="footer">
        <div className="container">
          © 2026 Иван Петров ·{' '}
          <Link className="footer__link" to="/admin">
            Метрики
          </Link>
        </div>
      </footer>
    </>
  );
}
