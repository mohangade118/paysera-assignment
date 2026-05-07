<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\AccountRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Exercises Doctrine queries and IDENTITY(...) ownership filters on AccountRepository.
 * Requires a migrated test DB (see composer scripts test:db / test flow in composer.json).
 */
final class AccountRepositoryTest extends KernelTestCase
{
    private AccountRepository $accounts;

    private User $userWithAccountA;

    private User $otherUser;

    private int $accountOwnedByUserAId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->reloadUserAndAccountFixtures();

        $this->accounts = $container->get(AccountRepository::class);

        $em = $container->get('doctrine')->getManager();
        $user1 = $em->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => 'mohangade08@gmail.com']);
        self::assertInstanceOf(User::class, $user1);
        self::assertInstanceOf(User::class, $user2);

        $owned = $this->accounts->findBy(['user' => $user1], ['id' => 'ASC']);
        self::assertNotEmpty($owned);

        $this->userWithAccountA = $user1;
        $this->otherUser = $user2;
        $this->accountOwnedByUserAId = $owned[0]->getId();
    }

    public function testFindOneOwnedByUserIdReturnsAccountForMatchingOwner(): void
    {
        $found = $this->accounts->findOneOwnedByUserId(
            $this->accountOwnedByUserAId,
            $this->userWithAccountA->getId()
        );

        self::assertNotNull($found);
        self::assertSame($this->accountOwnedByUserAId, $found->getId());
    }

    public function testFindOneOwnedByUserIdReturnsNullWhenUserDoesNotOwnAccount(): void
    {
        self::assertNull(
            $this->accounts->findOneOwnedByUserId(
                $this->accountOwnedByUserAId,
                $this->otherUser->getId()
            )
        );
    }

    public function testFindOneOwnedByUserIdReturnsNullForUnknownAccountId(): void
    {
        self::assertNull(
            $this->accounts->findOneOwnedByUserId(
                9_999_999,
                $this->userWithAccountA->getId()
            )
        );
    }

    public function testFindOneOwnedByUserIdForUpdateReturnsAccountForMatchingOwner(): void
    {
        $found = $this->accounts->findOneOwnedByUserIdForUpdate(
            $this->accountOwnedByUserAId,
            $this->userWithAccountA->getId()
        );

        self::assertNotNull($found);
        self::assertSame($this->accountOwnedByUserAId, $found->getId());
    }

    public function testFindOneOwnedByUserIdForUpdateReturnsNullWhenUserDoesNotOwnAccount(): void
    {
        self::assertNull(
            $this->accounts->findOneOwnedByUserIdForUpdate(
                $this->accountOwnedByUserAId,
                $this->otherUser->getId()
            )
        );
    }

    private function reloadUserAndAccountFixtures(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $purger = new ORMPurger($em);
        $purger->purge();

        $loader = new Loader();
        $loader->addFixture(new UserFixtures($container->get(UserPasswordHasherInterface::class)));
        $loader->addFixture(new AccountFixtures());

        $executor = new ORMExecutor($em);
        $executor->execute($loader->getFixtures(), true);
    }
}
