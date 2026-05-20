<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AccountFixtures extends Fixture implements DependentFixtureInterface
{
    public const user1_account1 = 'user1_account1';
    public const user1_account2 = 'user1_account2';
    public const user2_account1 = 'user2_account1';

    public function load(ObjectManager $manager): void
    {
        $user1 = $this->getReference(UserFixtures::user1, User::class);
        $account1 = new Account();
        $account1->setUser($user1)
            ->setBalance(100)
            ->setCurrencyType('INR')
            ->setStatus(1);
        $manager->persist($account1);

        $account1b = new Account();
        $account1b->setUser($user1)
            ->setBalance(100)
            ->setCurrencyType('INR')
            ->setStatus(1);
        $manager->persist($account1b);

        $user2 = $this->getReference(UserFixtures::user2, User::class);

        $account2 = new Account();
        $account2->setUser($user2)
            ->setBalance(100)
            ->setCurrencyType('INR')
            ->setStatus(1);
        $manager->persist($account2);

        $manager->flush();

        $this->addReference(self::user1_account1, $account1);
        $this->addReference(self::user1_account2, $account1b);
        $this->addReference(self::user2_account1, $account2);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
