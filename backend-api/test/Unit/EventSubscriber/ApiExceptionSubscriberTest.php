<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriberTest extends TestCase
{
    private ApiExceptionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ApiExceptionSubscriber();
    }

    #[Test]
    public function mapsHttpExceptionToJsonForApiRoutes(): void
    {
        $request = Request::create('/api/v1/users');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            Kernel::MAIN_REQUEST,
            new NotFoundHttpException('not found'),
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertFalse($payload['success']);
        self::assertSame('not found', $payload['message']);
    }

    #[Test]
    public function ignoresNonApiRoutes(): void
    {
        $request = Request::create('/some-page');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            Kernel::MAIN_REQUEST,
            new NotFoundHttpException('not found'),
        );

        $this->subscriber->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function mapsValidationFailedException(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('required', '', [], null, 'from_account_id', null),
        ]);
        $request = Request::create('/api/v1/transaction');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            Kernel::MAIN_REQUEST,
            new ValidationFailedException(null, $violations),
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertFalse($payload['success']);
        self::assertArrayHasKey('errors', $payload);
    }
}
