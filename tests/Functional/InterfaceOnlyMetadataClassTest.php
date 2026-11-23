<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\Tests\Functional\App\Kernel;

/**
 * Runs the metadata tests with an application entity that implements the metadata interface without extending the
 * bundle's Metadata class
 */
final class InterfaceOnlyMetadataClassTest extends MetadataTest
{
    protected static function getEnvironment(): string
    {
        return Kernel::ENVIRONMENT_INTERFACE_ONLY_METADATA;
    }

    /**
     * @test
     */
    public function it_maps_the_bundles_metadata_as_a_mapped_superclass(): void
    {
        self::assertTrue(self::getEntityManager()->getClassMetadata(Metadata::class)->isMappedSuperclass);
    }

    /**
     * @test
     */
    public function it_leaves_the_bundles_table_out_of_the_schema(): void
    {
        $entityManager = self::getEntityManager();
        $schema = (new SchemaTool($entityManager))->getSchemaFromMetadata($entityManager->getMetadataFactory()->getAllMetadata());

        self::assertTrue($schema->hasTable('app_interface_only_metadata'));
        self::assertFalse($schema->hasTable('setono_client__metadata'));
    }
}
