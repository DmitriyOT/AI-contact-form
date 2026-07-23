<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ContactRequest;
use App\Exception\EmailSendingException;
use App\Repository\ContactRepository;
use App\Service\AiAnalyzer;
use App\Service\ContactMailer;
use App\Service\ContactService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class ContactServiceTest extends TestCase
{
    public function testHappyPathPersistsAndSendsBothEmails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->willReturn(1);
        $connection->method('lastInsertId')->willReturn('42');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send');

        $service = $this->createService($connection, $mailer, aiEnabled: true);
        $result = $service->handle($this->contactRequest(), '127.0.0.1');

        self::assertTrue($result->accepted);
        self::assertSame('Обращение принято', $result->message);
        self::assertTrue($result->aiProcessed);
    }

    public function testAiDisabledResultsInAiProcessedFalse(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('lastInsertId')->willReturn('1');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send');

        $service = $this->createService($connection, $mailer, aiEnabled: false);
        $result = $service->handle($this->contactRequest());

        self::assertTrue($result->accepted);
        self::assertFalse($result->aiProcessed);
    }

    public function testPersistenceFailureFailsFastWith503(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willThrowException(new \RuntimeException('db down'));

        $mailer = $this->createMock(MailerInterface::class);
        // no email must go out when the record was never persisted (retry stays duplicate-free)
        $mailer->expects(self::never())->method('send');

        $service = $this->createService($connection, $mailer, aiEnabled: false);

        $this->expectException(ServiceUnavailableHttpException::class);
        $service->handle($this->contactRequest());
    }

    public function testOwnerEmailFailureThrowsEmailSendingException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('lastInsertId')->willReturn('1');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')
            ->willThrowException(new TransportException('smtp down'));

        $service = $this->createService($connection, $mailer, aiEnabled: false);

        $this->expectException(EmailSendingException::class);
        $this->expectExceptionMessage('Не удалось отправить уведомление');
        $service->handle($this->contactRequest());
    }

    public function testUserCopyFailureIsNonCritical(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('lastInsertId')->willReturn('1');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')
            ->willReturnCallback(static function () use (&$call): void {
                ++$call;
                if (2 === $call) {
                    throw new TransportException('user mailbox rejected');
                }
            });

        $service = $this->createService($connection, $mailer, aiEnabled: false);
        $result = $service->handle($this->contactRequest());

        self::assertTrue($result->accepted);
    }

    private function createService(Connection $connection, MailerInterface $mailer, bool $aiEnabled): ContactService
    {
        $httpClient = $aiEnabled
            ? new MockHttpClient([new MockResponse(json_encode([
                'choices' => [['message' => ['content' => '{"sentiment":"neutral","category":"вопрос","summary":"s"}']]],
            ]))])
            : new MockHttpClient();

        $aiAnalyzer = new AiAnalyzer(
            $httpClient,
            new NullLogger(),
            'https://ai.example/v1',
            $aiEnabled ? 'key' : '',
            'test-model'
        );

        return new ContactService(
            new ContactRepository($connection),
            new ContactMailer($mailer, 'noreply@example.com', 'owner@example.com'),
            $aiAnalyzer,
            new NullLogger(),
        );
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
