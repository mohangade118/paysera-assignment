<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


class AccountFixtures extends Fixture implements DependentFixtureInterface
{


    public function load(ObjectManager $manager): void
    {
        $user1 = $this->getReference(UserFixtures::user1, User::class);
        $account1 = new Account();
        $account1->setUser($user1)
            ->setBalance(100)
            ->setCurrencyType('INR')
            ->setStatus(1);
        $manager->persist($account1);

        $user2 = $this->getReference(UserFixtures::user2, User::class);

        $account2 = new Account();
        $account2->setUser($user2)
            ->setBalance(100)
            ->setCurrencyType('INR')
            ->setStatus(1);
        $manager->persist($account2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
