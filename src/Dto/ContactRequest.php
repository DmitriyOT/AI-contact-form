<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequest
{
    #[Assert\NotBlank(message: 'Укажите имя')]
    #[Assert\Type(type: 'string', message: 'Имя должно быть строкой')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Имя должно содержать минимум {{ limit }} символа',
        maxMessage: 'Имя должно содержать максимум {{ limit }} символов'
    )]
    public mixed $name = null;

    #[Assert\NotBlank(message: 'Укажите телефон')]
    #[Assert\Type(type: 'string', message: 'Телефон должен быть строкой')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{10,20}$/',
        message: 'Некорректный номер телефона'
    )]
    public mixed $phone = null;

    #[Assert\NotBlank(message: 'Укажите email')]
    #[Assert\Type(type: 'string', message: 'Email должен быть строкой')]
    #[Assert\Email(message: 'Некорректный email', mode: Assert\Email::VALIDATION_MODE_STRICT)]
    public mixed $email = null;

    #[Assert\NotBlank(message: 'Укажите текст обращения')]
    #[Assert\Type(type: 'string', message: 'Текст обращения должен быть строкой')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Текст обращения должен содержать минимум {{ limit }} символов',
        maxMessage: 'Текст обращения должен содержать максимум {{ limit }} символов'
    )]
    public mixed $comment = null;

    /**
     * Maps a decoded JSON payload to the DTO with sanitization.
     * Unknown fields are ignored.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->name = self::sanitizePlainText($data['name'] ?? null);
        $dto->phone = self::sanitizePlainText($data['phone'] ?? null);
        $dto->email = self::sanitizePlainText($data['email'] ?? null);
        // comment keeps its HTML; it will be escaped on output/sending
        $dto->comment = isset($data['comment']) && is_string($data['comment'])
            ? trim($data['comment'])
            : ($data['comment'] ?? null);

        return $dto;
    }

    private static function sanitizePlainText(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value; // Type constraint will report it as a validation error
        }

        return trim(strip_tags($value));
    }
}
