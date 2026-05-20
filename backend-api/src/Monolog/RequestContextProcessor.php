<?php

declare(strict_types=1);

namespace App\Monolog;

use App\Entity\User;
use App\EventSubscriber\RequestLifecycleSubscriber;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Enriches log records with HTTP and auth metadata; controllers pass only domain context.
 */
#[AsMonologProcessor]
final class RequestContextProcessor
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $requestId = $request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR);
            if (\is_string($requestId) && '' !== $requestId) {
                $record->extra['request_id'] = $requestId;
            }
            $record->extra['http_method'] = $request->getMethod();
            $record->extra['path'] = $request->getPathInfo();
            $route = $request->attributes->get('_route');
            if (\is_string($route)) {
                $record->extra['route'] = $route;
            }
            $record->extra['client_ip'] = $request->getClientIp();
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $record->extra['user_id'] = $user->getId();
        }

        return $record;
    }
}
