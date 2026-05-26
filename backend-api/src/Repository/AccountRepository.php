<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * @return list<Account>
     */
    public function findByUserId(int $userId): array
    {
        /** @var list<Account> $accounts */
        $accounts = $this->createQueryBuilder('a')
            ->andWhere('IDENTITY(a.user) = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $accounts;
    }

    public function findOneOwnedByUserId(int $accountId, int $userId): ?Account
    {
        /** @var Account|null $account */
        $account = $this->createQueryBuilder('a')
            ->andWhere('a.id = :accountId')
            ->andWhere('IDENTITY(a.user) = :userId')
            ->setParameter('accountId', $accountId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }

    public function findOneOwnedByUserIdForUpdate(int $accountId, int $userId): ?Account
    {
        /** @var Account|null $account */
        $account = $this->createQueryBuilder('a')
            ->andWhere('a.id = :accountId')
            ->andWhere('IDENTITY(a.user) = :userId')
            ->setParameter('accountId', $accountId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $account;
    }

    //    /**
    //     * @return Account[] Returns an array of Account objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Account
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
