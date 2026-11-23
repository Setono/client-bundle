<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\Tests\Functional\App\Entity\CustomMetadata;
use Setono\ClientBundle\Tests\Functional\App\Kernel;

/**
 * Runs the metadata tests with an application entity that extends the bundle's Metadata class
 */
final class CustomMetadataClassTest extends MetadataTest
{
    protected static function getEnvironment(): string
    {
        return Kernel::ENVIRONMENT_CUSTOM_METADATA;
    }

    /**
     * @test
     */
    public function it_stores_the_metadata_in_the_configured_entity(): void
    {
        $this->becomeReturningVisitor();
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::assertResponseIsSuccessful();
        self::assertSame('app_client_metadata', self::getEntityManager()->getClassMetadata(CustomMetadata::class)->getTableName());
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_keeps_the_bundles_metadata_a_mapped_superclass(): void
    {
        self::assertTrue(self::getEntityManager()->getClassMetadata(Metadata::class)->isMappedSuperclass);
    }
}
