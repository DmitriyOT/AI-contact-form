import { formatPhone } from './phone';

describe('formatPhone', () => {
  it('форматирует 11 цифр в +7 (XXX) XXX-XX-XX', () => {
    expect(formatPhone('89001234567')).toBe('+7 (900) 123-45-67');
    expect(formatPhone('79001234567')).toBe('+7 (900) 123-45-67');
    expect(formatPhone('9001234567')).toBe('+7 (900) 123-45-67');
  });

  it('форматирует частичный ввод', () => {
    expect(formatPhone('9')).toBe('+7 (9');
    expect(formatPhone('900')).toBe('+7 (900)');
    expect(formatPhone('9001')).toBe('+7 (900) 1');
    expect(formatPhone('9001234')).toBe('+7 (900) 123-4');
  });

  it('выбрасывает не-цифры и обрезает лишнее', () => {
    expect(formatPhone('+7 (900) abc')).toBe('+7 (900)');
    expect(formatPhone('890012345679999')).toBe('+7 (900) 123-45-67');
  });

  it('возвращает пустую строку при пустом вводе', () => {
    expect(formatPhone('')).toBe('');
    expect(formatPhone('+')).toBe('');
  });
});
