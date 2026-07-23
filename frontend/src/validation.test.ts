import { validateContact, PHONE_PATTERN } from './validation';

const VALID = {
  name: 'Иван Иванов',
  phone: '+7 900 123-45-67',
  email: 'ivan@example.com',
  comment: 'Хочу узнать подробнее о ваших услугах.',
};

describe('PHONE_PATTERN (зеркало бэкенда)', () => {
  it.each([
    '+7 900 123-45-67',
    '8(900)123-45-67',
    '+79001234567',
    '89001234567',
    '8 900 123-45-67',
    '8 (900) 123-45-67',
    '+7(900)1234567',
  ])('принимает %s', (phone) => {
    expect(PHONE_PATTERN.test(phone)).toBe(true);
  });

  it.each([
    '9999999999', // без префикса +7/8
    '+19001234567', // чужая страна
    '12345',
    '+7 900 123', // короткий
    '9001234567', // 10 цифр без префикса
    '+7 900 123-45-678', // длинный
  ])('отклоняет %s', (phone) => {
    expect(PHONE_PATTERN.test(phone)).toBe(false);
  });
});

describe('validateContact', () => {
  it('принимает валидную форму', () => {
    expect(validateContact(VALID)).toEqual({});
  });

  it('отклоняет номер без префикса +7/8 с понятным сообщением', () => {
    const errors = validateContact({ ...VALID, phone: '9999999999' });

    expect(errors.phone).toBe('Введите телефон в формате +7 900 123-45-67');
  });

  it('принимает номер через 8 со скобками и дефисами', () => {
    expect(validateContact({ ...VALID, phone: '8(900)123-45-67' })).toEqual({});
  });

  it('требует каждое поле с отдельным сообщением', () => {
    const errors = validateContact({
      name: '',
      phone: '',
      email: '',
      comment: '',
    });

    expect(errors).toEqual({
      name: 'Укажите имя',
      phone: 'Укажите телефон',
      email: 'Укажите email',
      comment: 'Укажите текст обращения',
    });
  });

  it('считает имя из одних пробелов пустым', () => {
    const errors = validateContact({ ...VALID, name: '   ' });

    expect(errors.name).toBe('Укажите имя');
  });

  it.each([
    { name: 'И', label: 'короче минимума (1)' },
    { name: 'А'.repeat(101), label: 'длиннее максимума (101)' },
  ])('отклоняет имя: $label', ({ name }) => {
    expect(validateContact({ ...VALID, name }).name).toBe(
      'Имя должно быть от 2 до 100 символов',
    );
  });

  it.each([
    { name: 'Аб', label: 'минимум (2)' },
    { name: 'А'.repeat(100), label: 'максимум (100)' },
  ])('принимает имя на границе длины: $label', ({ name }) => {
    expect(validateContact({ ...VALID, name })).toEqual({});
  });

  it.each([
    { comment: 'а'.repeat(9), label: 'короче минимума (9)' },
    { comment: 'а'.repeat(2001), label: 'длиннее максимума (2001)' },
  ])('отклоняет комментарий: $label', ({ comment }) => {
    expect(validateContact({ ...VALID, comment }).comment).toBe(
      'Текст обращения должен быть от 10 до 2000 символов',
    );
  });

  it.each([
    { comment: 'а'.repeat(10), label: 'минимум (10)' },
    { comment: 'а'.repeat(2000), label: 'максимум (2000)' },
  ])('принимает комментарий на границе длины: $label', ({ comment }) => {
    expect(validateContact({ ...VALID, comment })).toEqual({});
  });

  it.each(['ivan@', 'ivan@example', 'ivan example.com', '@example.com'])(
    'отклоняет некорректный email %s',
    (email) => {
      expect(validateContact({ ...VALID, email }).email).toBe(
        'Некорректный email',
      );
    },
  );
});
