import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ContactForm } from './ContactForm';

function fillValidForm() {
  return {
    name: 'Иван Иванов',
    phone: '+7 900 123-45-67',
    email: 'ivan@example.com',
    comment: 'Хочу узнать подробнее о ваших услугах.',
  };
}

async function typeValidForm(user: ReturnType<typeof userEvent.setup>) {
  const values = fillValidForm();
  await user.type(screen.getByLabelText('Имя'), values.name);
  await user.type(screen.getByLabelText('Телефон'), values.phone);
  await user.type(screen.getByLabelText('Email'), values.email);
  await user.type(screen.getByLabelText('Обращение'), values.comment);
}

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

describe('ContactForm', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('показывает ошибки по всем полям при пустой отправке', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);

    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Укажите имя')).toBeInTheDocument();
    expect(screen.getByText('Укажите телефон')).toBeInTheDocument();
    expect(screen.getByText('Укажите email')).toBeInTheDocument();
    expect(screen.getByText('Укажите текст обращения')).toBeInTheDocument();
  });

  it('показывает ошибки при невалидном email и коротком комментарии', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);

    await user.type(screen.getByLabelText('Имя'), 'Иван');
    await user.type(screen.getByLabelText('Телефон'), '+79001234567');
    await user.type(screen.getByLabelText('Email'), 'not-an-email');
    await user.type(screen.getByLabelText('Обращение'), 'коротко');
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Некорректный email')).toBeInTheDocument();
    expect(screen.getByText('Текст обращения должен быть от 10 до 2000 символов')).toBeInTheDocument();
  });

  it('при 201 показывает успех и очищает форму', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(201, { status: 'accepted', message: 'Обращение принято', ai: true })));
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Обращение принято. Спасибо!')).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByLabelText('Имя')).toHaveValue('');
      expect(screen.getByLabelText('Обращение')).toHaveValue('');
    });
  });

  it('при 422 показывает ошибки сервера под нужными полями', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, {
      error: {
        code: 'validation_failed',
        message: 'Ошибка валидации',
        details: { email: ['Некорректный email'], comment: ['Текст обращения должен содержать минимум 10 символов'] },
      },
    })));
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Некорректный email')).toBeInTheDocument();
    expect(screen.getByText('Текст обращения должен содержать минимум 10 символов')).toBeInTheDocument();
    expect(screen.getByText('Ошибка валидации')).toBeInTheDocument();
  });

  it('при 429 показывает сообщение о лимите и блокирует кнопку', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(
      429,
      { error: { code: 'too_many_requests', message: 'Слишком много запросов, попробуйте позже' } },
      { 'Retry-After': '300' }
    )));
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Слишком много запросов, попробуйте позже')).toBeInTheDocument();
    const button = screen.getByRole('button', { name: /Повторите через/ });
    expect(button).toBeDisabled();
    expect(button).toHaveTextContent('Повторите через 300 сек');
  });

  it('при сетевой ошибке показывает сообщение о недоступности', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Сервер недоступен, попробуйте позже')).toBeInTheDocument();
  });

  it('не отправляет повторно во время submitting (защита от двойного сабмита)', async () => {
    const user = userEvent.setup();
    let resolveFetch: (value: Response) => void = () => {};
    const fetchMock = vi.fn().mockImplementation(
      () => new Promise<Response>((resolve) => { resolveFetch = resolve; })
    );
    vi.stubGlobal('fetch', fetchMock);
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(screen.getByRole('button', { name: 'Отправка...' })).toBeDisabled();
    resolveFetch(jsonResponse(201, { status: 'accepted' }));
    expect(await screen.findByText('Обращение принято. Спасибо!')).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('показывает ошибку поля при уходе с пустого поля (blur) и ставит aria-invalid', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);

    const nameInput = screen.getByLabelText('Имя');
    await user.click(nameInput);
    await user.tab();

    expect(await screen.findByText('Укажите имя')).toBeInTheDocument();
    expect(nameInput).toHaveAttribute('aria-invalid', 'true');
    expect(nameInput).toHaveAttribute('aria-describedby', 'error-name');

    await user.type(nameInput, 'Иван');
    await user.tab();

    expect(screen.queryByText('Укажите имя')).not.toBeInTheDocument();
    expect(nameInput).toHaveAttribute('aria-invalid', 'false');
  });

  it('blur по невалидному телефону показывает ошибку формата', async () => {
    const user = userEvent.setup();
    render(<ContactForm />);

    await user.type(screen.getByLabelText('Телефон'), '9999999999');
    await user.tab();

    expect(await screen.findByText('Введите телефон в формате +7 900 123-45-67')).toBeInTheDocument();
  });

  it('при 502 показывает сообщение об ошибке отправки', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(502, {
      error: { code: 'email_failed', message: 'Не удалось отправить уведомление, попробуйте позже' },
    })));
    render(<ContactForm />);

    await typeValidForm(user);
    await user.click(screen.getByRole('button', { name: 'Отправить' }));

    expect(await screen.findByText('Не удалось отправить уведомление, попробуйте позже')).toBeInTheDocument();
    // форма не очищается — пользователь может повторить отправку
    expect(screen.getByLabelText('Имя')).toHaveValue('Иван Иванов');
    expect(screen.getByRole('button', { name: 'Отправить' })).toBeEnabled();
  });
});
