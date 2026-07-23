<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ContactRequest;
use App\Service\ContactMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ContactMailerTest extends TestCase
{
    public function testOwnerSubjectIsFlaggedForHighPriority(): void
    {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')
            ->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
                $sent[] = $email;
            });

        $this->createMailer($mailer)->sendOwnerNotification($this->contactRequest(), [
            'sentiment' => 'negative',
            'category' => 'жалоба',
            'summary' => 's',
            'priority' => 'срочный',
            'draft_reply' => 'd',
        ]);

        self::assertSame('[СРОЧНЫЙ] Новое обращение с сайта', $sent[0]->getSubject());
        self::assertSame('owner@example.com', $sent[0]->getTo()[0]->getAddress());
    }

    public function testOwnerSubjectWithoutPriorityFlag(): void
    {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $contactMailer = $this->createMailer($mailer);
        $contactMailer->sendOwnerNotification($this->contactRequest(), ['priority' => 'низкий']);
        $contactMailer->sendOwnerNotification($this->contactRequest()); // AI недоступен

        self::assertSame('Новое обращение с сайта', $sent[0]->getSubject());
        self::assertSame('Новое обращение с сайта', $sent[1]->getSubject());
    }

    public function testUserCopyGoesToContactEmail(): void
    {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')
            ->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
                $sent[] = $email;
            });

        $this->createMailer($mailer)->sendUserCopy($this->contactRequest());

        self::assertSame('ivan@example.com', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('Мы получили ваше обращение', $sent[0]->getSubject());
    }

    private function createMailer(MailerInterface $mailer): ContactMailer
    {
        return new ContactMailer($mailer, 'noreply@example.com', 'owner@example.com');
    }

    private function contactRequest(): ContactRequest
    {
        return ContactRequest::fromArray([
            'name' => 'Иван Иванов',
            'phone' => '+7 900 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу узнать подробнее о ваших услугах.',
        ]);
    }
}
