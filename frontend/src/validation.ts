import type { ContactPayload } from './api/contact';

export type FieldErrors = Partial<Record<keyof ContactPayload, string>>;

// зеркалит правила бэкенда (src/Dto/ContactRequest.php)
export const PHONE_PATTERN = /^\+?[0-9\s\-()]{10,20}$/;
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
    errors.phone = 'Некорректный номер телефона';
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
