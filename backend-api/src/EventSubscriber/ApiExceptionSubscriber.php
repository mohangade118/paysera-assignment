<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 20],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = (string) $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $e = $event->getThrowable();

        $statusCode = 500;
        $message = 'Internal Server Error';
        $errors = null;

        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage() ?: JsonResponse::$statusTexts[$statusCode] ?? 'Error';
        } elseif ($e instanceof ValidationFailedException) {
            $statusCode = 400;
            $message = 'Validation failed';
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }
        } elseif ($e instanceof NotEncodableValueException) {
            $statusCode = 400;
            $message = 'Invalid JSON payload';
        } else {
            // keep the message for 4xx-ish exceptions that are not HttpExceptionInterface
            $message = $e->getMessage() ?: $message;
        }

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (is_array($errors)) {
            $payload['errors'] = $errors;
        }

        $event->setResponse(new JsonResponse($payload, $statusCode));
    }
}

