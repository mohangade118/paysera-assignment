<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\JwtCreatedSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class JwtCreatedSubscriberTest extends TestCase
{
    #[Test]
    public function getSubscribedEventsRegistersJwtCreatedHandler(): void
    {
        self::assertSame(
            [
                Events::JWT_CREATED => 'onJwtCreated',
            ],
            JwtCreatedSubscriber::getSubscribedEvents()
        );
    }

    #[Test]
    public function onJwtCreatedAddsProfileFieldsWhenUserIsEntityUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('jane.doe@example.com');
        $user->method('getFirstName')->willReturn('Jane');
        $user->method('getLastName')->willReturn('Doe');

        $payload = ['sub' => '9', 'username' => 'jdoe'];

        $event = new JWTCreatedEvent($payload, $user);
        (new JwtCreatedSubscriber())->onJwtCreated($event);

        self::assertSame(
            [
                'sub' => '9',
                'username' => 'jdoe',
                'email' => 'jane.doe@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
            ],
            $event->getData()
        );
    }

    #[Test]
    public function onJwtCreatedDoesNothingWhenUserIsNotEntityUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $payload = ['sub' => 'legacy', 'roles' => ['ROLE_USER']];

        $event = new JWTCreatedEvent($payload, $user);
        (new JwtCreatedSubscriber())->onJwtCreated($event);

        self::assertSame($payload, $event->getData());
    }
}
