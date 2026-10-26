<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

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
}
