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
});
