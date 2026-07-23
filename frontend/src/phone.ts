/**
 * Простое автоформатирование российского номера при вводе: +7 (XXX) XXX-XX-XX.
 * Работает только с цифрами, поэтому не ломает PHONE_PATTERN из validation.ts.
 */
export function formatPhone(value: string): string {
  let digits = value.replace(/\D/g, '');
  if (digits.startsWith('8')) {
    digits = `7${digits.slice(1)}`;
  }
  if (digits && !digits.startsWith('7')) {
    digits = `7${digits}`;
  }
  digits = digits.slice(0, 11);
  if (!digits) {
    return '';
  }

  let result = '+7';
  const area = digits.slice(1, 4);
  if (area) {
    result += ` (${area}`;
    if (area.length === 3) {
      result += ')';
    }
  }
  const first = digits.slice(4, 7);
  if (first) {
    result += ` ${first}`;
  }
  const second = digits.slice(7, 9);
  if (second) {
    result += `-${second}`;
  }
  const third = digits.slice(9, 11);
  if (third) {
    result += `-${third}`;
  }
  return result;
}
