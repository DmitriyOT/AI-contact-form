import { render, screen } from '@testing-library/react';
import { Hero } from './Hero';

describe('Hero', () => {
  it('показывает имя, описание и ссылку «Связаться» на форму', () => {
    render(<Hero />);

    expect(
      screen.getByRole('heading', { level: 1, name: 'Иван Петров' }),
    ).toBeInTheDocument();
    expect(screen.getByText(/Проектирую и разрабатываю/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Связаться' })).toHaveAttribute(
      'href',
      '#contact',
    );
  });
});
