<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriberTest extends TestCase
{
    #[Test]
    public function getSubscribedEventsRegistersExceptionListenerAtPriorityTwenty(): void
    {
        self::assertSame(
            [
                KernelEvents::EXCEPTION => ['onKernelException', 20],
            ],
            ApiExceptionSubscriber::getSubscribedEvents()
        );
    }

    #[Test]
    public function onKernelExceptionDoesNothingForNonApiPaths(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/', new \RuntimeException('x'));

        $subscriber->onKernelException($event);

        self::assertFalse($event->hasResponse(), 'unexpected response set for non-API path');

        $event2 = $this->createExceptionEvent('/not-api', new \RuntimeException('x'));
        $subscriber->onKernelException($event2);
        self::assertFalse($event2->hasResponse());
    }

    #[Test]
    public function onKernelExceptionIgnoresBareApiPrefixWithoutTrailingSlashSegment(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api', new \RuntimeException('x'));

        $subscriber->onKernelException($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelExceptionMapsHttpExceptionToJson(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', new NotFoundHttpException('gone'));

        $subscriber->onKernelException($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertSame('gone', $data['message']);
        self::assertArrayNotHasKey('errors', $data);
    }

    #[Test]
    public function onKernelExceptionUsesStatusTextWhenHttpExceptionMessageEmpty(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', new NotFoundHttpException(''));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        $expected = JsonResponse::$statusTexts[404] ?? 'Not Found';
        self::assertSame($expected, $data['message']);
    }

    #[Test]
    public function onKernelExceptionMapsValidationFailureToStructuredErrors(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Too small', null, [], null, 'amount', null),
        ]);
        $exception = new ValidationFailedException(null, $violations);

        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', $exception);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertSame('Validation failed', $data['message']);
        self::assertSame(
            [
                ['field' => 'amount', 'message' => 'Too small'],
            ],
            $data['errors']
        );
    }

    #[Test]
    public function onKernelExceptionMapsNotEncodableJsonToBadRequest(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', new NotEncodableValueException('bad json'));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertSame('Invalid JSON payload', $data['message']);
        self::assertArrayNotHasKey('errors', $data);
    }

    #[Test]
    public function onKernelExceptionMapsGenericExceptionToFiveHundredWithMessage(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', new \RuntimeException('oops'));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertSame('oops', $data['message']);
    }

    #[Test]
    public function onKernelExceptionFallsBackToInternalMessageWhenGenericExceptionEmpty(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $event = $this->createExceptionEvent('/api/foo', new \Exception(''));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());

        $data = $this->decodeJsonResponse($response);
        self::assertFalse($data['success']);
        self::assertSame('Internal Server Error', $data['message']);
    }

    private function createExceptionEvent(string $path, \Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent(
            $kernel,
            Request::create($path, 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(JsonResponse $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
