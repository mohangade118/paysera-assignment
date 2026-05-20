<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Tests\Support\ReloadsDoctrineFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransactionRepositoryTest extends KernelTestCase
{
    use ReloadsDoctrineFixtures;

    #[Test]
    public function persistsAndFindsTransaction(): void
    {
        self::bootKernel();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $userRepo = $entityManager->getRepository(User::class);
        $accountRepo = $entityManager->getRepository(Account::class);

        $user1 = $userRepo->findOneBy(['email' => 'mohangade118@gmail.com']);
        $accounts = $accountRepo->findBy(['user' => $user1]);
        self::assertCount(2, $accounts);

        $from = $accounts[0];
        $to = $accounts[1];

        $transaction = new Transaction();
        $transaction->setFromAccount($from)
            ->setToAccount($to)
            ->setAmount(15.0)
            ->setStatus('SUCCESS')
            ->setNote('integration')
            ->setReceipt('');

        $entityManager->persist($transaction);
        $entityManager->flush();
        $id = $transaction->getId();
        self::assertNotNull($id);

        $entityManager->clear();

        $repository = $container->get(TransactionRepository::class);
        $found = $repository->find($id);

        self::assertNotNull($found);
        self::assertSame(15.0, $found->getAmount());
        self::assertSame('SUCCESS', $found->getStatus());
    }
}
