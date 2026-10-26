<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\Tests\Functional\App\Entity\CustomMetadata;
use Setono\ClientBundle\Tests\Functional\App\Kernel;

final class CustomMetadataClassTest extends FunctionalTestCase
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
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::assertResponseIsSuccessful();
        self::assertSame('app_client_metadata', self::getEntityManager()->getClassMetadata(CustomMetadata::class)->getTableName());
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_loads_the_metadata_from_the_configured_entity(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        $this->browser->request('GET', '/metadata');

        self::assertSame('{"foo":"bar"}', $this->browser->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_keeps_the_bundles_metadata_a_mapped_superclass(): void
    {
        self::assertTrue(self::getEntityManager()->getClassMetadata(Metadata::class)->isMappedSuperclass);
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
