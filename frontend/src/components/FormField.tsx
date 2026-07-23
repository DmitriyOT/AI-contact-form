import type { ChangeEvent } from 'react';
import type { ContactPayload } from '../api/contact';

interface FormFieldProps {
  name: keyof ContactPayload;
  label: string;
  type: 'text' | 'email' | 'tel' | 'textarea';
  placeholder: string;
  autoComplete: string;
  inputMode?: 'email' | 'tel';
  maxLength?: number;
  value: string;
  error?: string;
  disabled: boolean;
  onChange: (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => void;
  onBlur: (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
}

export function FormField({
  name,
  label,
  type,
  placeholder,
  autoComplete,
  inputMode,
  maxLength,
  value,
  error,
  disabled,
  onChange,
  onBlur,
}: FormFieldProps) {
  const controlProps = {
    id: `field-${name}`,
    name,
    className: 'form__control',
    placeholder,
    value,
    onChange,
    onBlur,
    maxLength,
    'aria-invalid': Boolean(error),
    'aria-describedby': error ? `error-${name}` : undefined,
    disabled,
  };

  return (
    <div className="form__group">
      <label className="form__label" htmlFor={`field-${name}`}>
        {label}
      </label>
      {type === 'textarea' ? (
        <textarea {...controlProps} rows={5} autoComplete={autoComplete} />
      ) : (
        <input
          {...controlProps}
          type={type}
          autoComplete={autoComplete}
          inputMode={inputMode}
        />
      )}
      {maxLength !== undefined && type === 'textarea' && (
        <p className="form__counter">
          {value.length}/{maxLength}
        </p>
      )}
      {error && (
        <p className="form__error" id={`error-${name}`}>
          {error}
        </p>
      )}
    </div>
  );
}
