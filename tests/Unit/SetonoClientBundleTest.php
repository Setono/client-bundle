<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\SetonoClientBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoClientBundleTest extends TestCase
{
    /**
     * @test
     */
    public function its_path_points_to_the_package_root(): void
    {
        self::assertSame(\dirname(__DIR__, 2), (new SetonoClientBundle())->getPath());
    }

    /**
     * @test
     */
    public function it_maps_the_metadata_as_an_entity_when_the_entity_mapping_is_enabled(): void
    {
        $classMetadata = self::loadMetadataMapping('setono_client.doctrine_mapping.entity');

        self::assertFalse($classMetadata->isMappedSuperclass);
        self::assertSame('setono_client__metadata', $classMetadata->getTableName());
        self::assertSame(['clientId'], $classMetadata->getIdentifierFieldNames());
        self::assertSame('string', $classMetadata->getTypeOfField('clientId'));
        self::assertSame('json', $classMetadata->getTypeOfField('metadata'));
        self::assertTrue($classMetadata->isNullable('metadata'));
    }

    /**
     * @test
     */
    public function it_maps_the_metadata_as_a_mapped_superclass_when_the_mapped_superclass_mapping_is_enabled(): void
    {
        $classMetadata = self::loadMetadataMapping('setono_client.doctrine_mapping.mapped_superclass');

        self::assertTrue($classMetadata->isMappedSuperclass);
        self::assertSame(['clientId'], $classMetadata->getIdentifierFieldNames());
        self::assertSame('string', $classMetadata->getTypeOfField('clientId'));
        self::assertSame('json', $classMetadata->getTypeOfField('metadata'));
        self::assertTrue($classMetadata->isNullable('metadata'));
    }

    /**
     * Compiles a container with the bundle's compiler passes and the mapping parameter that SetonoClientExtension would set,
     * and loads the mapping of the bundle's Metadata from the mapping driver that DoctrineBundle would give the entity manager
     *
     * @return ClassMetadata<Metadata>
     */
    private static function loadMetadataMapping(string $enabledParameter): ClassMetadata
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->setParameter($enabledParameter, true);
        $container->register('doctrine.orm.default_metadata_driver', MappingDriverChain::class)->setPublic(true);

        (new SetonoClientBundle())->build($container);
        $container->compile();

        $driver = $container->get('doctrine.orm.default_metadata_driver');
        self::assertInstanceOf(MappingDriver::class, $driver);

        $classMetadata = new ClassMetadata(Metadata::class);
        $driver->loadMetadataForClass(Metadata::class, $classMetadata);

        return $classMetadata;
    }
}
