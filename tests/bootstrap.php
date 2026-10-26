<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Setono\ClientBundle\Tests\Functional\App\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Assert\Assert;

require dirname(__DIR__) . '/vendor/autoload.php';

// Infection runs every mutant in a new process, but swaps in the mutated code without changing the modification time of the file.
// A container compiled by an earlier process would look fresh, and could lack the mutant or contain the mutant of that process.
// So every process compiles the container again
(new Filesystem())->remove(Kernel::getVarDir() . '/cache');

// DAMA DoctrineTestBundle rolls back what each test writes, so the schema is created once, before it starts its transactions
foreach (['test', Kernel::ENVIRONMENT_CUSTOM_METADATA] as $environment) {
    $kernel = new Kernel($environment, true);
    $kernel->boot();

    $container = $kernel->getContainer();

    $metadataClass = $container->getParameter('setono_client.metadata_class');
    Assert::string($metadataClass);
    Assert::classExists($metadataClass);

    $managerRegistry = $container->get('doctrine');
    Assert::isInstanceOf($managerRegistry, ManagerRegistry::class);

    $manager = $managerRegistry->getManagerForClass($metadataClass);
    Assert::isInstanceOf($manager, EntityManagerInterface::class);

    $classes = $manager->getMetadataFactory()->getAllMetadata();
    $schemaTool = new SchemaTool($manager);
    $schemaTool->dropSchema($classes);
    $schemaTool->createSchema($classes);

    $kernel->shutdown();
}
