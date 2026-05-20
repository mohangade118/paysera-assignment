<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable as DateTimeImmutableAlias;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const user1 = 'user1';
    public const user2 = 'user2';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** @return list<string> */
    public static function getGroups(): array
    {
        return ['user_group'];
    }

    public function load(ObjectManager $manager): void
    {
        $plainPassword = 'password';

        $user1 = new User();
        $user1->setFirstName('Amit')
            ->setLastName('Sharma')
            ->setEmail('mohangade118@gmail.com')
            ->setContactNo('8793281988')
            ->setRoles([])
            ->setPassword($this->passwordHasher->hashPassword($user1, $plainPassword))
            ->setCreatedAt(new DateTimeImmutableAlias())
            ->setUpdatedAt(new DateTimeImmutableAlias());

        $manager->persist($user1);

        $user2 = new User();
        $user2->setFirstName('Priya')
            ->setLastName('Kulkarni')
            ->setEmail('mohangade08@gmail.com')
            ->setContactNo('8459206766')
            ->setRoles([])
            ->setPassword($this->passwordHasher->hashPassword($user2, $plainPassword))
            ->setCreatedAt(new DateTimeImmutableAlias())
            ->setUpdatedAt(new DateTimeImmutableAlias());

        $manager->persist($user2);
        $manager->flush();
        $this->addReference(self::user1, $user1);
        $this->addReference(self::user2, $user2);
    }
}
