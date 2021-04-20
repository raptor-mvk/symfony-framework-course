<?php

namespace UnitTests;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class FixturedTestCase extends WebTestCase
{
    /** @var ContainerAwareLoader */
    private $fixtureLoader;
    /** @var AbstractExecutor */
    private $fixtureExecutor;

    public function setUp(): void
    {
        self::bootKernel();
        $this->initFixtureExecutor();
    }

    public function tearDown(): void
    {
        $em = self::$kernel->getContainer()->get('doctrine')->getManager();
        $em->clear();
        $em->getConnection()->close();
        gc_collect_cycles();
        parent::tearDown();
    }

    protected function getContainer(): ContainerInterface
    {
        return self::$kernel->getContainer();
    }

    protected function initFixtureExecutor(): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('doctrine')->getManager();
        $this->fixtureExecutor = new ORMExecutor($entityManager, new ORMPurger($entityManager));
    }

    protected function addFixture(FixtureInterface $fixture): void
    {
        $this->getFixtureLoader()->addFixture($fixture);
    }

    protected function executeFixtures(): void
    {
        $this->fixtureExecutor->execute($this->getFixtureLoader()->getFixtures());
        $this->fixtureLoader = null;
    }

    protected function getReference(string $refName)
    {
        return $this->fixtureExecutor->getReferenceRepository()->getReference($refName);
    }

    private function getFixtureLoader(): ContainerAwareLoader
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader($this->getContainer());
        }

        return $this->fixtureLoader;
    }

    public function getDoctrineManager(): EntityManagerInterface
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }
}
