<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\SetonoClientBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoClientBundleTest extends TestCase
{
    /**
     * @test
     */
    public function its_path_points_to_the_package_root(): void
    {
        self::assertSame(\dirname(__DIR__), (new SetonoClientBundle())->getPath());
    }

    /**
     * @test
     */
    public function it_registers_the_doctrine_mapping_for_the_metadata_entity(): void
    {
        $container = new ContainerBuilder();
        (new SetonoClientBundle())->build($container);

        $pass = null;
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $candidate) {
            if ($candidate instanceof DoctrineOrmMappingsPass) {
                $pass = $candidate;

                break;
            }
        }

        self::assertInstanceOf(DoctrineOrmMappingsPass::class, $pass);
        self::assertSame(
            [\dirname(__DIR__) . '/src/../config/doctrine-mapping' => 'Setono\ClientBundle\Entity'],
            (new \ReflectionProperty($pass, 'namespaces'))->getValue($pass),
        );
    }
}
