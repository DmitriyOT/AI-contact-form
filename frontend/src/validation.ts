import type { ContactPayload } from './api/contact';

export type FieldErrors = Partial<Record<keyof ContactPayload, string>>;

// зеркалит правила бэкенда (src/Dto/ContactRequest.php)
// российский формат: +7 или 8, далее ровно 10 цифр, между ними пробелы/скобки/дефисы
export const PHONE_PATTERN = /^(\+7|8)[\s\-(]*\d{3}[\s\)-]*\d{3}[\s-]*\d{2}[\s-]*\d{2}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function validateContact(values: ContactPayload): FieldErrors {
  const errors: FieldErrors = {};

  const name = values.name.trim();
  if (!name) {
    errors.name = 'Укажите имя';
  } else if (name.length < 2 || name.length > 100) {
    errors.name = 'Имя должно быть от 2 до 100 символов';
  }

  const phone = values.phone.trim();
  if (!phone) {
    errors.phone = 'Укажите телефон';
  } else if (!PHONE_PATTERN.test(phone)) {
    errors.phone = 'Введите телефон в формате +7 900 123-45-67';
  }

  const email = values.email.trim();
  if (!email) {
    errors.email = 'Укажите email';
  } else if (!EMAIL_PATTERN.test(email)) {
    errors.email = 'Некорректный email';
  }

  const comment = values.comment.trim();
  if (!comment) {
    errors.comment = 'Укажите текст обращения';
  } else if (comment.length < 10 || comment.length > 2000) {
    errors.comment = 'Текст обращения должен быть от 10 до 2000 символов';
  }

  return errors;
}
