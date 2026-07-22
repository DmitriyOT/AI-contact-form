import { Hero } from './components/Hero';
import { ContactForm } from './components/ContactForm';

export function App() {
  return (
    <>
      <Hero />
      <main>
        <section className="section" id="contact">
          <div className="container container--narrow">
            <h2 className="section__title">Связаться со мной</h2>
            <p className="section__subtitle">
              Оставьте обращение — я отвечу в ближайшее время.
            </p>
            <ContactForm />
          </div>
        </section>
      </main>
      <footer className="footer">
        <div className="container">© 2026 Иван Петров</div>
      </footer>
    </>
  );
}
