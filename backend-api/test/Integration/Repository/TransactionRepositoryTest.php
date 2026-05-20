<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AccountFixtures;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Tests\Support\ReloadsDoctrineFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * TransactionRepository has no custom finder methods yet; tests cover container wiring,
 * baseline empty transactions after account fixtures, and ORM persistence via the repository aggregate.
 *
 * Requires a migrated test DB (composer test:db / full test composer script).
 */
final class TransactionRepositoryTest extends KernelTestCase
{
    use ReloadsDoctrineFixtures;

    private TransactionRepository $transactions;

    private EntityManagerInterface $em;

    private Account $fromAccountForUser1;

    private Account $toAccountForUser2;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->purgeAndLoadFixtures($container->get(AccountFixtures::class));

        $this->transactions = $container->get(TransactionRepository::class);
        $this->em = $container->get('doctrine')->getManager();

        $accounts = $container->get(AccountRepository::class);
        $user1 = $this->em->getRepository(User::class)->findOneBy(['email' => 'mohangade118@gmail.com']);
        $user2 = $this->em->getRepository(User::class)->findOneBy(['email' => 'mohangade08@gmail.com']);
        self::assertInstanceOf(User::class, $user1);
        self::assertInstanceOf(User::class, $user2);

        $u1accounts = $accounts->findBy(['user' => $user1], ['id' => 'ASC']);
        $u2accounts = $accounts->findBy(['user' => $user2], ['id' => 'ASC']);
        self::assertNotEmpty($u1accounts);
        self::assertNotEmpty($u2accounts);

        $this->fromAccountForUser1 = $u1accounts[0];
        $this->toAccountForUser2 = $u2accounts[0];
    }

    public function testRepositoryResolvedFromContainer(): void
    {
        self::assertInstanceOf(TransactionRepository::class, $this->transactions);
    }

    public function testNoTransactionsAfterAccountFixtures(): void
    {
        self::assertSame([], $this->transactions->findAll());
    }

    public function testPersistFlushAndFindLoadsTransaction(): void
    {
        $tx = new Transaction();
        $tx->setAmount(25.5)
            ->setStatus('SUCCESS')
            ->setNote('integration-note')
            ->setReceipt('receipt-int-1')
            ->setFromAccount($this->fromAccountForUser1)
            ->setToAccount($this->toAccountForUser2);

        $this->em->persist($tx);
        $this->em->flush();

        $id = $tx->getId();
        self::assertNotNull($id);

        $this->em->clear();

        $loaded = $this->transactions->find($id);
        self::assertInstanceOf(Transaction::class, $loaded);
        self::assertSame(25.5, $loaded->getAmount());
        self::assertSame('SUCCESS', $loaded->getStatus());
        self::assertSame('integration-note', $loaded->getNote());
        self::assertSame('receipt-int-1', $loaded->getReceipt());
        self::assertSame($this->fromAccountForUser1->getId(), $loaded->getFromAccount()?->getId());
        self::assertSame($this->toAccountForUser2->getId(), $loaded->getToAccount()?->getId());
    }
}
