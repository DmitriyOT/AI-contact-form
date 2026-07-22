export function Hero() {
  return (
    <header className="hero">
      <div className="container">
        <p className="hero__eyebrow">Backend-разработчик</p>
        <h1 className="hero__title">Иван Петров</h1>
        <p className="hero__lead">
          Проектирую и разрабатываю серверную часть веб-приложений: API, интеграции,
          надёжные и поддерживаемые решения.
        </p>
        <div className="hero__cards">
          <div className="card">
            <h3 className="card__title">Навыки</h3>
            <ul className="card__list">
              <li>PHP, Symfony</li>
              <li>JavaScript / TypeScript</li>
              <li>MySQL, Redis</li>
              <li>Docker, CI/CD</li>
            </ul>
          </div>
          <div className="card">
            <h3 className="card__title">Проекты</h3>
            <ul className="card__list">
              <li>REST API с AI-интеграцией</li>
              <li>Внутренние биллинговые сервисы</li>
              <li>Интеграции со сторонними API</li>
            </ul>
          </div>
        </div>
      </div>
    </header>
  );
}
