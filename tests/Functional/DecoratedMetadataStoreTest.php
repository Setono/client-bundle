<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\ClientBundle\MetadataStore\MetadataStoreInterface;
use Setono\ClientBundle\Tests\Functional\App\MetadataStore\TraceableMetadataStore;

/**
 * The test application decorates the metadata store with TraceableMetadataStore, which records the calls it passes on
 */
final class DecoratedMetadataStoreTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_loads_and_stores_the_metadata_through_a_decorator_of_the_interface(): void
    {
        $this->browser->request('GET', '/');
        $clientId = $this->getClientId();

        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        self::assertResponseIsSuccessful();

        // The decorator of the request that just ran
        $metadataStore = self::getContainer()->get(MetadataStoreInterface::class);
        self::assertInstanceOf(TraceableMetadataStore::class, $metadataStore);

        self::assertSame([$clientId], $metadataStore->loadedClientIds);
        self::assertSame([$clientId], $metadataStore->storedClientIds);
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($clientId));
    }
}
