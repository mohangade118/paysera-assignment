<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AccountFixtures;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Tests\Support\ReloadsDoctrineFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises Doctrine queries and IDENTITY(...) ownership filters on AccountRepository.
 * Requires a migrated test DB (see composer scripts test:db / test flow in composer.json).
 */
final class AccountRepositoryTest extends KernelTestCase
{
    use ReloadsDoctrineFixtures;

    private AccountRepository $accounts;

    private EntityManagerInterface $em;

    private User $userWithAccountA;

    private User $otherUser;

    private int $accountOwnedByUserAId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->purgeAndLoadFixtures($container->get(AccountFixtures::class));

        $this->accounts = $container->get(AccountRepository::class);
        $this->em = $container->get('doctrine')->getManager();

        $user1 = $this->em->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        $user2 = $this->em->getRepository(User::class)->findOneBy(['email' => 'mohangade08@gmail.com']);
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
        $found = $this->em->wrapInTransaction(
            fn (): ?Account => $this->accounts->findOneOwnedByUserIdForUpdate(
                $this->accountOwnedByUserAId,
                $this->userWithAccountA->getId()
            )
        );

        self::assertNotNull($found);
        self::assertSame($this->accountOwnedByUserAId, $found->getId());
    }

    public function testFindOneOwnedByUserIdForUpdateReturnsNullWhenUserDoesNotOwnAccount(): void
    {
        $found = $this->em->wrapInTransaction(
            fn (): ?Account => $this->accounts->findOneOwnedByUserIdForUpdate(
                $this->accountOwnedByUserAId,
                $this->otherUser->getId()
            )
        );

        self::assertNull($found);
    }
}
