import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { submitContact } from '../api/contact';
import type { ContactAnalysis, ContactPayload } from '../api/contact';
import { validateContact } from '../validation';
import type { FieldErrors } from '../validation';
import { COMMENT_MAX, NAME_MAX } from '../validation';
import { formatPhone } from '../phone';
import { FormField } from './FormField';

type FormStatus = 'idle' | 'submitting' | 'success' | 'error';

interface SuccessData {
  message: string;
  ai: boolean;
  analysis: ContactAnalysis | null;
}

const EMPTY_FORM: ContactPayload = {
  name: '',
  phone: '',
  email: '',
  comment: '',
};

const DRAFT_KEY = 'contactFormDraft';

const SENTIMENT_LABELS: Record<ContactAnalysis['sentiment'], string> = {
  positive: 'Тональность: позитивная',
  neutral: 'Тональность: нейтральная',
  negative: 'Тональность: негативная',
};

const PRIORITY_CLASSES: Record<ContactAnalysis['priority'], string> = {
  низкий: 'badge--low',
  средний: 'badge--medium',
  высокий: 'badge--high',
  срочный: 'badge--urgent',
};

const FIELDS: Array<{
  name: keyof ContactPayload;
  label: string;
  type: 'text' | 'email' | 'tel' | 'textarea';
  placeholder: string;
  autoComplete: string;
  inputMode?: 'email' | 'tel';
  maxLength?: number;
}> = [
  {
    name: 'name',
    label: 'Имя',
    type: 'text',
    placeholder: 'Иван Иванов',
    autoComplete: 'name',
    maxLength: NAME_MAX,
  },
  {
    name: 'phone',
    label: 'Телефон',
    type: 'tel',
    placeholder: '+7 (900) 123-45-67',
    autoComplete: 'tel',
    inputMode: 'tel',
  },
  {
    name: 'email',
    label: 'Email',
    type: 'email',
    placeholder: 'ivan@example.com',
    autoComplete: 'email',
    inputMode: 'email',
  },
  {
    name: 'comment',
    label: 'Обращение',
    type: 'textarea',
    placeholder: 'Опишите ваш вопрос...',
    autoComplete: 'off',
    maxLength: COMMENT_MAX,
  },
];

function loadDraft(): ContactPayload {
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) {
      return EMPTY_FORM;
    }
    return {
      ...EMPTY_FORM,
      ...(JSON.parse(raw) as Partial<ContactPayload>),
    };
  } catch {
    return EMPTY_FORM;
  }
}

export function ContactForm({ disabled = false }: { disabled?: boolean }) {
  const [values, setValues] = useState<ContactPayload>(loadDraft);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState<FormStatus>('idle');
  const [serverMessage, setServerMessage] = useState('');
  const [success, setSuccess] = useState<SuccessData | null>(null);
  const [retrySeconds, setRetrySeconds] = useState(0);
  const abortRef = useRef<AbortController | null>(null);
  const prevRetryRef = useRef(0);

  // обратный отсчёт Retry-After при 429
  const isCountingDown = retrySeconds > 0;
  useEffect(() => {
    if (!isCountingDown) {
      return;
    }
    const timer = setInterval(() => {
      setRetrySeconds((seconds) => Math.max(0, seconds - 1));
    }, 1000);
    return () => clearInterval(timer);
  }, [isCountingDown]);

  // по окончании отсчёта снимаем статус ошибки.
  // Логика вынесена из setState-апдейтера: в StrictMode апдейтер вызывается дважды
  useEffect(() => {
    if (prevRetryRef.current > 0 && retrySeconds === 0) {
      setStatus('idle');
    }
    prevRetryRef.current = retrySeconds;
  }, [retrySeconds]);

  // черновик обращения в localStorage
  useEffect(() => {
    if (Object.values(values).some(Boolean)) {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(values));
    } else {
      localStorage.removeItem(DRAFT_KEY);
    }
  }, [values]);

  // отмена запроса при размонтировании (с роутингом — при уходе на другую страницу)
  useEffect(() => {
    return () => abortRef.current?.abort();
  }, []);

  const handleChange = (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    const field = event.target.name as keyof ContactPayload;
    const value =
      field === 'phone' ? formatPhone(event.target.value) : event.target.value;
    setValues((prev) => ({ ...prev, [field]: value }));

    // после первой ошибки поле перевалидируется на change — ошибка снимается сразу при исправлении
    if (errors[field]) {
      const fieldError = validateContact({
        ...EMPTY_FORM,
        [field]: value,
      })[field];
      setErrors((prev) => ({ ...prev, [field]: fieldError }));
    }

    // любое изменение после успешной отправки убирает баннер «Спасибо!»
    if (status === 'success') {
      setStatus('idle');
      setSuccess(null);
    }
  };

  const handleBlur = (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    const field = event.target.name as keyof ContactPayload;
    const fieldError = validateContact({
      ...EMPTY_FORM,
      [field]: values[field],
    })[field];
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
    setSuccess(null);

    const controller = new AbortController();
    abortRef.current = controller;

    const result = await submitContact(
      {
        name: values.name.trim(),
        phone: values.phone.trim(),
        email: values.email.trim(),
        comment: values.comment.trim(),
      },
      controller.signal,
    );

    // запрос отменён при размонтировании — состояние уже нельзя менять
    if (controller.signal.aborted) {
      return;
    }

    if (result.ok) {
      setStatus('success');
      setSuccess({
        message: result.message,
        ai: result.ai,
        analysis: result.analysis,
      });
      setValues(EMPTY_FORM);
      setErrors({});
      localStorage.removeItem(DRAFT_KEY);
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

  const isBlocked = disabled || status === 'submitting' || retrySeconds > 0;

  return (
    <form
      className="form"
      onSubmit={handleSubmit}
      noValidate
      aria-busy={status === 'submitting'}
    >
      {FIELDS.map((field) => (
        <FormField
          key={field.name}
          {...field}
          value={values[field.name]}
          error={errors[field.name]}
          disabled={disabled || status === 'submitting'}
          onChange={handleChange}
          onBlur={handleBlur}
        />
      ))}

      <button className="form__submit" type="submit" disabled={isBlocked}>
        {status === 'submitting' && (
          <span className="form__spinner" aria-hidden="true" />
        )}
        {status === 'submitting'
          ? 'Отправка...'
          : retrySeconds > 0
            ? `Повторите через ${retrySeconds} сек`
            : 'Отправить'}
      </button>

      <div className="form__status" aria-live="polite">
        {status === 'success' && success && (
          <div className="form__success">
            <p className="form__message form__message--success">
              {success.message}
            </p>
            {success.analysis && (
              <div className="analysis">
                <div className="analysis__badges">
                  <span className="badge">{success.analysis.category}</span>
                  <span
                    className={`badge ${PRIORITY_CLASSES[success.analysis.priority]}`}
                  >
                    Приоритет: {success.analysis.priority}
                  </span>
                  <span className="badge">
                    {SENTIMENT_LABELS[success.analysis.sentiment]}
                  </span>
                </div>
                <p className="analysis__summary">{success.analysis.summary}</p>
              </div>
            )}
            {!success.ai && (
              <p className="form__note">
                AI-анализ временно недоступен — обращение сохранено без него.
              </p>
            )}
          </div>
        )}
        {status === 'error' && serverMessage && (
          <p className="form__message form__message--error">{serverMessage}</p>
        )}
      </div>
    </form>
  );
}
