<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;
use Setono\ClientBundle\Entity\Metadata;

final class DoctrineMappingTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_maps_the_metadata_as_an_entity(): void
    {
        $classMetadata = self::getEntityManager()->getClassMetadata(Metadata::class);

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
    public function its_mapping_is_valid_and_matches_the_database_schema(): void
    {
        $validator = new SchemaValidator(self::getEntityManager());

        self::assertSame([], $validator->validateMapping());
        self::assertTrue($validator->schemaInSyncWithMetadata());
    }
}
