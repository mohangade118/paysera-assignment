<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Tests\Support\ReloadsDoctrineFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRepositoryTest extends KernelTestCase
{
    use ReloadsDoctrineFixtures;

    #[Test]
    public function findOneOwnedByUserIdReturnsAccountForOwner(): void
    {
        self::bootKernel();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $owner = $entityManager->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        self::assertInstanceOf(User::class, $owner);

        $account = $entityManager->getRepository(Account::class)->findOneBy(['user' => $owner]);
        self::assertInstanceOf(Account::class, $account);

        $repository = static::getContainer()->get(AccountRepository::class);
        $found = $repository->findOneOwnedByUserId($account->getId(), $owner->getId());

        self::assertNotNull($found);
        self::assertSame($account->getId(), $found->getId());
    }

    #[Test]
    public function findOneOwnedByUserIdReturnsNullForNonOwner(): void
    {
        self::bootKernel();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $owner = $entityManager->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        $other = $entityManager->getRepository(User::class)->findOneBy(['email' => 'mohangade08@gmail.com']);
        self::assertInstanceOf(User::class, $owner);
        self::assertInstanceOf(User::class, $other);

        $otherAccount = $entityManager->getRepository(Account::class)->findOneBy(['user' => $other]);
        self::assertInstanceOf(Account::class, $otherAccount);

        $repository = static::getContainer()->get(AccountRepository::class);
        $found = $repository->findOneOwnedByUserId($otherAccount->getId(), $owner->getId());

        self::assertNull($found);
    }

    #[Test]
    public function findByUserIdReturnsAllAccountsForUser(): void
    {
        self::bootKernel();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $owner = $entityManager->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        self::assertInstanceOf(User::class, $owner);

        $repository = static::getContainer()->get(AccountRepository::class);
        $accounts = $repository->findByUserId($owner->getId());

        self::assertCount(2, $accounts);
        foreach ($accounts as $account) {
            self::assertSame($owner->getId(), $account->getUser()?->getId());
        }
    }
}
