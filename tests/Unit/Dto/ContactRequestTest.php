<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\ContactRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testFromArraySanitizesPlainTextFields(): void
    {
        $dto = ContactRequest::fromArray([
            'name' => '  <b>Иван</b>  ',
            'phone' => ' +7 900 123-45-67 ',
            'email' => ' <script>ivan@example.com</script> ',
            'comment' => 'Текст с <i>HTML</i> сохраняется',
        ]);

        self::assertSame('Иван', $dto->name);
        // phone is normalized to the canonical +7XXXXXXXXXX form
        self::assertSame('+79001234567', $dto->phone);
        // strip_tags removes the tags but keeps the text content
        self::assertSame('ivan@example.com', $dto->email);
        // comment keeps its HTML; it is escaped on output instead
        self::assertSame('Текст с <i>HTML</i> сохраняется', $dto->comment);
    }

    public function testFromArrayIgnoresUnknownFieldsAndMissingKeys(): void
    {
        $dto = ContactRequest::fromArray(['unknown' => 'x']);

        self::assertNull($dto->name);
        self::assertNull($dto->phone);
        self::assertNull($dto->email);
        self::assertNull($dto->comment);
    }

    public function testFromArrayKeepsNonStringValuesForTypeViolation(): void
    {
        $dto = ContactRequest::fromArray(['name' => 123, 'comment' => ['array']]);

        // values pass through untouched so Assert\Type reports a 422, not a 500
        self::assertSame(123, $dto->name);
        self::assertSame(['array'], $dto->comment);

        $violations = $this->validator->validate($dto);
        self::assertGreaterThan(0, count($violations));
    }

    public function testValidPayloadPassesValidation(): void
    {
        $dto = ContactRequest::fromArray([
            'name' => 'Иван Иванов',
            'phone' => '+7 900 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу узнать подробнее о ваших услугах.',
        ]);

        self::assertCount(0, $this->validator->validate($dto));
    }

    public function testPhoneNormalizationToCanonicalForm(): void
    {
        $base = ['name' => 'Иван', 'email' => 'ivan@example.com', 'comment' => 'Комментарий достаточной длины.'];

        self::assertSame('+79001234567', ContactRequest::fromArray($base + ['phone' => '8 (900) 123-45-67'])->phone);
        self::assertSame('+79001234567', ContactRequest::fromArray($base + ['phone' => '+7 900 123 45 67'])->phone);
        self::assertSame('+79001234567', ContactRequest::fromArray($base + ['phone' => '89001234567'])->phone);
    }

    public function testOverlongPhoneRejectedBeforeDatabase(): void
    {
        // normalization strips separators first, so an overlong phone either shrinks to the
        // canonical 12-char form or is rejected here — it can never reach the VARCHAR(20) column
        $dto = ContactRequest::fromArray([
            'name' => 'Иван',
            'phone' => '+7' . str_repeat(' ', 30) . '900 123-45-67 89',
            'email' => 'ivan@example.com',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        $violations = $this->validator->validate($dto);
        self::assertGreaterThan(0, count($violations));
        self::assertSame('phone', $violations->get(0)->getPropertyPath());
    }

    public function testOverlongEmailRejectedBeforeDatabase(): void
    {
        $dto = ContactRequest::fromArray([
            'name' => 'Иван',
            'phone' => '+79001234567',
            // syntactically valid email longer than the VARCHAR(255) column
            'email' => str_repeat('a', 250) . '@example.com',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        $violations = $this->validator->validate($dto);
        self::assertGreaterThan(0, count($violations));
        self::assertSame('email', $violations->get(0)->getPropertyPath());
    }

    #[DataProvider('validPhones')]
    public function testValidPhoneFormats(string $phone): void
    {
        $dto = ContactRequest::fromArray([
            'name' => 'Иван',
            'phone' => $phone,
            'email' => 'ivan@example.com',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        self::assertCount(0, $this->validator->validate($dto), "Телефон {$phone} должен быть валидным");
    }

    public static function validPhones(): iterable
    {
        yield 'plus7 with separators' => ['+7 900 123-45-67'];
        yield 'plus7 bare' => ['+79001234567'];
        yield 'eight with parens' => ['8(900)123-45-67'];
        yield 'eight bare' => ['89001234567'];
        yield 'plus7 with parens and spaces' => ['+7 (900) 123 45 67'];
    }

    #[DataProvider('invalidPhones')]
    public function testInvalidPhoneFormats(string $phone): void
    {
        $dto = ContactRequest::fromArray([
            'name' => 'Иван',
            'phone' => $phone,
            'email' => 'ivan@example.com',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        $violations = $this->validator->validate($dto);
        self::assertGreaterThan(0, count($violations), "Телефон {$phone} должен быть отклонён");
        self::assertSame('phone', $violations->get(0)->getPropertyPath());
    }

    public static function invalidPhones(): iterable
    {
        yield 'no prefix' => ['9001234567'];
        yield 'foreign prefix' => ['+1 555 123-45-67'];
        yield 'too short' => ['+7 900 123-45'];
        yield 'letters' => ['+7 abc def-gh-ij'];
    }

    public function testCommentLengthBoundaries(): void
    {
        $base = ['name' => 'Иван', 'phone' => '+79001234567', 'email' => 'ivan@example.com'];

        $tooShort = ContactRequest::fromArray($base + ['comment' => str_repeat('а', 9)]);
        self::assertGreaterThan(0, count($this->validator->validate($tooShort)));

        $min = ContactRequest::fromArray($base + ['comment' => str_repeat('а', 10)]);
        self::assertCount(0, $this->validator->validate($min));

        $max = ContactRequest::fromArray($base + ['comment' => str_repeat('а', 2000)]);
        self::assertCount(0, $this->validator->validate($max));

        $tooLong = ContactRequest::fromArray($base + ['comment' => str_repeat('а', 2001)]);
        self::assertGreaterThan(0, count($this->validator->validate($tooLong)));
    }

    public function testInvalidEmailRejected(): void
    {
        $dto = ContactRequest::fromArray([
            'name' => 'Иван',
            'phone' => '+79001234567',
            'email' => 'not-an-email',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        $violations = $this->validator->validate($dto);
        self::assertGreaterThan(0, count($violations));
        self::assertSame('email', $violations->get(0)->getPropertyPath());
    }
}
