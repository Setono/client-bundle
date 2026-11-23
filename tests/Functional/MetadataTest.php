<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Metadata;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

/**
 * Not final, because CustomMetadataClassTest and InterfaceOnlyMetadataClassTest run these tests with an application's own metadata entity
 */
class MetadataTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_does_not_store_anything_for_a_visitor_that_does_not_use_metadata(): void
    {
        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(0, self::countStoredMetadata());
    }

    /**
     * @test
     */
    public function it_stores_the_metadata_of_a_new_visitor(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::assertResponseIsSuccessful();
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_loads_the_stored_metadata_of_a_returning_visitor(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        $this->browser->request('GET', '/metadata');

        self::assertResponseIsSuccessful();
        self::assertSame(['foo' => 'bar'], $this->getResponseData());
    }

    /**
     * @test
     */
    public function it_gives_a_returning_visitor_without_stored_metadata_empty_metadata(): void
    {
        $this->browser->request('GET', '/');

        $this->browser->request('GET', '/metadata');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->getResponseData());
    }

    /**
     * @test
     */
    public function it_stores_the_metadata_of_a_returning_visitor_without_stored_metadata(): void
    {
        $this->browser->request('GET', '/');

        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::assertResponseIsSuccessful();
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_updates_the_stored_metadata_of_a_returning_visitor(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        $this->browser->request('GET', '/metadata/set?key=baz&value=qux');
        $this->browser->request('GET', '/metadata/set?key=foo&value=changed');

        // assertEquals() because MySQL doesn't keep the order of the keys in a JSON column
        self::assertEquals(['foo' => 'changed', 'baz' => 'qux'], self::fetchStoredMetadata($this->getClientId()));
        self::assertSame(1, self::countStoredMetadata());
    }

    /**
     * @test
     */
    public function it_removes_metadata(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        $this->browser->request('GET', '/metadata/set?key=baz&value=qux');

        $this->browser->request('GET', '/metadata/remove?key=foo');
        self::assertSame(['baz' => 'qux'], self::fetchStoredMetadata($this->getClientId()));

        $this->browser->request('GET', '/metadata/remove?key=baz');
        self::assertSame([], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_keeps_the_metadata_of_each_visitor_apart(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=first');
        $firstClientId = $this->getClientId();

        $this->browser->getCookieJar()->clear();
        $this->browser->request('GET', '/metadata/set?key=foo&value=second');
        $secondClientId = $this->getClientId();

        self::assertNotSame($firstClientId, $secondClientId);
        self::assertSame(['foo' => 'first'], self::fetchStoredMetadata($firstClientId));
        self::assertSame(['foo' => 'second'], self::fetchStoredMetadata($secondClientId));
    }

    /**
     * @test
     */
    public function it_does_not_query_the_database_when_the_metadata_is_not_used(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame([], self::getExecutedQueries());

        // Proves that the queries of a request are recorded
        $this->browser->request('GET', '/metadata');
        self::assertNotSame([], self::getExecutedQueries());
    }

    /**
     * @test
     */
    public function it_does_not_query_the_database_for_a_new_visitor_that_does_not_use_metadata(): void
    {
        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame([], self::getExecutedQueries());
    }

    /**
     * @test
     */
    public function it_does_not_query_the_database_for_a_returning_visitor_without_stored_metadata_that_does_not_use_it(): void
    {
        $this->browser->request('GET', '/');

        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame([], self::getExecutedQueries());
        self::assertSame(0, self::countStoredMetadata());
    }

    /**
     * @test
     */
    public function it_does_not_start_the_entity_manager_when_the_metadata_is_not_used(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertFalse(self::hasStartedAnEntityManager());

        // Proves that a request that uses the metadata starts the entity manager
        $this->browser->request('GET', '/metadata');
        self::assertTrue(self::hasStartedAnEntityManager());
    }

    /**
     * @test
     */
    public function it_does_not_start_the_entity_manager_for_a_new_visitor_that_does_not_use_metadata(): void
    {
        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertFalse(self::hasStartedAnEntityManager());
    }

    /**
     * MySQL doesn't keep the order of the keys in a JSON column, so the stored metadata can come back in another order
     * than the metadata stores it in. Doctrine would see that as a change and update the row on every request
     *
     * @test
     */
    public function it_does_not_store_metadata_that_was_set_to_the_value_it_already_has(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        // The reserved expiry key first, as MySQL may return it, where the metadata itself puts it last
        self::storeMetadata($this->getClientId(), [
            Metadata::EXPIRES_KEY => ['baz' => time() + 3600],
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        self::assertResponseIsSuccessful();

        $queries = self::getExecutedQueries();
        self::assertNotSame([], preg_grep('/^\s*SELECT\b/i', $queries), 'The request loads the stored metadata');
        self::assertSame([], preg_grep('/^\s*(INSERT|UPDATE)\b/i', $queries));
    }

    /**
     * Stores the row with its keys in another order first, like it_does_not_store_metadata_that_was_set_to_the_value_it_already_has()
     *
     * @test
     */
    public function it_does_not_store_metadata_that_was_only_read(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::storeMetadata($this->getClientId(), [
            Metadata::EXPIRES_KEY => ['foo' => time() + 3600],
            'foo' => 'bar',
        ]);

        $this->browser->request('GET', '/metadata');
        self::assertResponseIsSuccessful();
        self::assertSame(['foo' => 'bar'], $this->getResponseData());

        $queries = self::getExecutedQueries();
        self::assertNotSame([], preg_grep('/^\s*SELECT\b/i', $queries), 'The request loads the stored metadata');
        self::assertSame([], preg_grep('/^\s*(INSERT|UPDATE)\b/i', $queries));
    }

    /**
     * @test
     */
    public function it_removes_the_expiry_when_a_value_is_set_without_a_ttl(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar&ttl=3600');
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');

        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_keeps_the_expiry_of_metadata_between_requests(): void
    {
        $before = time();
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar&ttl=3600');

        $stored = self::fetchStoredMetadata($this->getClientId());
        self::assertIsArray($stored);
        self::assertSame('bar', $stored['foo']);

        $expires = $stored[Metadata::EXPIRES_KEY];
        self::assertIsArray($expires);
        self::assertIsInt($expires['foo']);
        self::assertGreaterThanOrEqual($before + 3600, $expires['foo']);
        self::assertLessThanOrEqual(time() + 3600, $expires['foo']);

        $this->browser->request('GET', '/metadata');
        self::assertSame(['foo' => 'bar'], $this->getResponseData());
    }

    /**
     * @test
     */
    public function it_removes_expired_metadata(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        $clientId = $this->getClientId();

        self::storeMetadata($clientId, [
            'foo' => 'bar',
            'baz' => 'qux',
            Metadata::EXPIRES_KEY => ['foo' => time() - 1],
        ]);

        $this->browser->request('GET', '/metadata');

        self::assertSame(['baz' => 'qux'], $this->getResponseData());
        self::assertSame(['baz' => 'qux'], self::fetchStoredMetadata($clientId));
    }

    /**
     * @test
     */
    public function it_keeps_the_row_of_a_concurrent_request_that_stored_it_first(): void
    {
        $this->browser->request('GET', '/metadata/set-concurrently?key=foo&value=bar');

        self::assertResponseIsSuccessful();
        self::assertResponseHasCookie(self::COOKIE_NAME);
        self::assertSame(['set_by' => 'concurrent request'], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * The schema check fails if the mapping makes Doctrine see a difference to the schema it created itself, which would
     * make every doctrine:migrations:diff generate a migration
     *
     * @test
     */
    public function its_mapping_is_valid_and_matches_the_database_schema(): void
    {
        $validator = new SchemaValidator(self::getEntityManager());

        self::assertSame([], $validator->validateMapping());
        self::assertTrue($validator->schemaInSyncWithMetadata());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getResponseData(): array
    {
        $data = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Tells whether the last request started any entity manager
     */
    private static function hasStartedAnEntityManager(): bool
    {
        $managerRegistry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $managerRegistry);

        foreach ($managerRegistry->getManagerNames() as $serviceId) {
            if (self::getContainer()->initialized($serviceId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the SQL of the queries that the last request executed, as recorded by DoctrineBundle in debug mode
     *
     * @return list<string>
     */
    private static function getExecutedQueries(): array
    {
        $debugDataHolder = self::getContainer()->get('test.doctrine.debug_data_holder');
        self::assertInstanceOf(DebugDataHolder::class, $debugDataHolder);

        $queries = [];
        foreach ($debugDataHolder->getData() as $connectionQueries) {
            self::assertIsArray($connectionQueries);

            foreach ($connectionQueries as $query) {
                self::assertIsArray($query);
                self::assertIsString($query['sql']);

                $queries[] = $query['sql'];
            }
        }

        return $queries;
    }
}
