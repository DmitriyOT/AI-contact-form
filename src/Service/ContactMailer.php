<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')]
        private readonly string $fromEmail,
        #[Autowire('%env(MAIL_OWNER_EMAIL)%')]
        private readonly string $ownerEmail,
    ) {
    }

    /**
     * @param array<string, string>|null $aiData AI analysis data, rendered only when present
     */
    public function sendOwnerNotification(ContactRequest $contact, ?array $aiData = null): void
    {
        // high-priority requests are flagged right in the subject line
        $priority = $aiData['priority'] ?? null;
        $subject = in_array($priority, ['высокий', 'срочный'], true)
            ? sprintf('[%s] Новое обращение с сайта', mb_strtoupper((string) $priority))
            : 'Новое обращение с сайта';

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail))
            ->to(new Address($this->ownerEmail))
            ->subject($subject)
            ->htmlTemplate('email/owner_notification.html.twig')
            ->context([
                'contact' => $contact,
                'ai' => $aiData,
                'date' => new DateTimeImmutable(),
            ]);

        $this->mailer->send($email);
    }

    public function sendUserCopy(ContactRequest $contact): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail))
            ->to(new Address($contact->email))
            ->subject('Мы получили ваше обращение')
            ->htmlTemplate('email/user_copy.html.twig')
            ->context([
                'contact' => $contact,
            ]);

        $this->mailer->send($email);
    }
}
