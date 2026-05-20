<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\JwtCreatedSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class JwtCreatedSubscriberTest extends TestCase
{
    #[Test]
    public function enrichesJwtPayloadForAppUser(): void
    {
        $user = new User();
        $user->setFirstName('Amit')
            ->setLastName('Sharma')
            ->setEmail('test@example.com')
            ->setContactNo('1234567890')
            ->setPassword('hashed')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $event = new JWTCreatedEvent(['sub' => '1'], $user);
        $subscriber = new JwtCreatedSubscriber();
        $subscriber->onJwtCreated($event);

        $data = $event->getData();
        self::assertSame('test@example.com', $data['email']);
        self::assertSame('Amit', $data['firstName']);
        self::assertSame('Sharma', $data['lastName']);
    }

    #[Test]
    public function ignoresNonAppUsers(): void
    {
        $otherUser = $this->createMock(UserInterface::class);
        $event = new JWTCreatedEvent(['sub' => '1'], $otherUser);
        $subscriber = new JwtCreatedSubscriber();
        $subscriber->onJwtCreated($event);

        self::assertSame(['sub' => '1'], $event->getData());
    }
}
