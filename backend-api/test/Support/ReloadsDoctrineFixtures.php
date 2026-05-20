<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

trait ReloadsDoctrineFixtures
{
    /**
     * @param list<class-string<FixtureInterface>> $fixtureClasses
     */
    protected function purgeAndLoadFixtures(array $fixtureClasses): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $loader = new ContainerAwareFixtureLoader($container);
        foreach ($fixtureClasses as $fixtureClass) {
            $loader->addFixture($container->get($fixtureClass));
        }

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures(), true);
    }
}
