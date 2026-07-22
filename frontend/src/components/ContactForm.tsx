import { useEffect, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { submitContact } from '../api/contact';
import type { ContactPayload } from '../api/contact';
import { validateContact } from '../validation';
import type { FieldErrors } from '../validation';

type FormStatus = 'idle' | 'submitting' | 'success' | 'error';

const EMPTY_FORM: ContactPayload = { name: '', phone: '', email: '', comment: '' };

const FIELDS: Array<{
  name: keyof ContactPayload;
  label: string;
  type: 'text' | 'email' | 'tel' | 'textarea';
  placeholder: string;
  autoComplete: string;
}> = [
  { name: 'name', label: 'Имя', type: 'text', placeholder: 'Иван Иванов', autoComplete: 'name' },
  { name: 'phone', label: 'Телефон', type: 'tel', placeholder: '+7 900 123-45-67', autoComplete: 'tel' },
  { name: 'email', label: 'Email', type: 'email', placeholder: 'ivan@example.com', autoComplete: 'email' },
  { name: 'comment', label: 'Обращение', type: 'textarea', placeholder: 'Опишите ваш вопрос...', autoComplete: 'off' },
];

export function ContactForm() {
  const [values, setValues] = useState<ContactPayload>(EMPTY_FORM);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState<FormStatus>('idle');
  const [serverMessage, setServerMessage] = useState('');
  const [retrySeconds, setRetrySeconds] = useState(0);

  // обратный отсчёт Retry-After при 429
  useEffect(() => {
    if (retrySeconds <= 0) {
      return;
    }
    const timer = setInterval(() => {
      setRetrySeconds((seconds) => {
        if (seconds <= 1) {
          setStatus('idle');
          return 0;
        }
        return seconds - 1;
      });
    }, 1000);
    return () => clearInterval(timer);
  }, [retrySeconds > 0]);

  const handleChange = (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = event.target;
    setValues((prev) => ({ ...prev, [name]: value }));
  };

  const handleBlur = (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const field = event.target.name as keyof ContactPayload;
    const fieldError = validateContact({ ...EMPTY_FORM, [field]: values[field] })[field];
    setErrors((prev) => ({ ...prev, [field]: fieldError }));
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();

    const validationErrors = validateContact(values);
    setErrors(validationErrors);
    if (Object.values(validationErrors).some(Boolean)) {
      return;
    }

    setStatus('submitting');
    setServerMessage('');

    const result = await submitContact({
      name: values.name.trim(),
      phone: values.phone.trim(),
      email: values.email.trim(),
      comment: values.comment.trim(),
    });

    if (result.ok) {
      setStatus('success');
      setValues(EMPTY_FORM);
      setErrors({});
      return;
    }

    setStatus('error');
    switch (result.type) {
      case 'validation': {
        setServerMessage(result.message);
        const fieldErrors: FieldErrors = {};
        for (const [field, messages] of Object.entries(result.details)) {
          fieldErrors[field as keyof ContactPayload] = messages[0];
        }
        setErrors(fieldErrors);
        break;
      }
      case 'rate_limit':
        setServerMessage(result.message);
        setRetrySeconds(result.retryAfter);
        break;
      default:
        setServerMessage(result.message);
    }
  };

  const isBlocked = status === 'submitting' || retrySeconds > 0;

  return (
    <form className="form" onSubmit={handleSubmit} noValidate>
      {FIELDS.map((field) => (
        <div className="form__group" key={field.name}>
          <label className="form__label" htmlFor={`field-${field.name}`}>
            {field.label}
          </label>
          {field.type === 'textarea' ? (
            <textarea
              id={`field-${field.name}`}
              name={field.name}
              className="form__control"
              placeholder={field.placeholder}
              rows={5}
              value={values[field.name]}
              onChange={handleChange}
              onBlur={handleBlur}
              aria-invalid={Boolean(errors[field.name])}
              aria-describedby={errors[field.name] ? `error-${field.name}` : undefined}
              disabled={status === 'submitting'}
            />
          ) : (
            <input
              id={`field-${field.name}`}
              name={field.name}
              type={field.type}
              className="form__control"
              placeholder={field.placeholder}
              autoComplete={field.autoComplete}
              value={values[field.name]}
              onChange={handleChange}
              onBlur={handleBlur}
              aria-invalid={Boolean(errors[field.name])}
              aria-describedby={errors[field.name] ? `error-${field.name}` : undefined}
              disabled={status === 'submitting'}
            />
          )}
          {errors[field.name] && (
            <p className="form__error" id={`error-${field.name}`} role="alert">
              {errors[field.name]}
            </p>
          )}
        </div>
      ))}

      <button className="form__submit" type="submit" disabled={isBlocked}>
        {status === 'submitting' ? 'Отправка...' : retrySeconds > 0 ? `Повторите через ${retrySeconds} сек` : 'Отправить'}
      </button>

      <div className="form__status" aria-live="polite">
        {status === 'success' && (
          <p className="form__message form__message--success" role="status">
            Обращение принято. Спасибо!
          </p>
        )}
        {status === 'error' && serverMessage && (
          <p className="form__message form__message--error" role="alert">
            {serverMessage}
          </p>
        )}
      </div>
    </form>
  );
}
