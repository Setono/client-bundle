<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Cookie;
use Setono\ClientBundle\Tests\Functional\App\Kernel;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Boots the test application. tests/bootstrap.php creates the schema once, and DAMA DoctrineTestBundle rolls back
 * what each test writes. Like PHP-FPM, every request is handled by a new container
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const COOKIE_NAME = 'setono_client_id';

    protected KernelBrowser $browser;

    protected function setUp(): void
    {
        // Fixed, so APP_ENV and APP_DEBUG in the environment, e.g. from the Symfony CLI, don't change the application
        $this->browser = static::createClient(['environment' => static::getEnvironment(), 'debug' => true]);

        // An exception fails the test instead of becoming an error response
        $this->browser->catchExceptions(false);
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected static function getEnvironment(): string
    {
        return 'test';
    }

    /**
     * Gives the browser a client id, so from the next request on it's a returning visitor, whose metadata is persisted
     * right away. The metadata of a new visitor isn't persisted until it comes back (see NewClientMetadataTest)
     */
    protected function becomeReturningVisitor(): void
    {
        $this->browser->request('GET', '/');
    }

    /**
     * Returns the cookie stored in the browser
     */
    protected function getCookie(): Cookie
    {
        $cookie = $this->browser->getCookieJar()->get(self::COOKIE_NAME);
        self::assertNotNull($cookie, 'The browser does not have the client id cookie');

        return Cookie::fromString($cookie->getValue());
    }

    /**
     * Returns the client id stored in the browser's cookie
     */
    protected function getClientId(): string
    {
        return $this->getCookie()->clientId;
    }

    /**
     * Returns the metadata stored in the database for the given client, or null if the client has no row
     *
     * @return array<array-key, mixed>|null
     */
    protected static function fetchStoredMetadata(string $clientId): ?array
    {
        $classMetadata = self::getMetadataClassMetadata();

        $metadata = self::getConnection()->fetchOne(sprintf(
            'SELECT %s FROM %s WHERE %s = ?',
            $classMetadata->getColumnName('metadata'),
            $classMetadata->getTableName(),
            $classMetadata->getColumnName('clientId'),
        ), [$clientId]);

        if (false === $metadata) {
            return null;
        }

        if (null === $metadata) {
            return [];
        }

        self::assertIsString($metadata);
        $metadata = json_decode($metadata, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);

        return $metadata;
    }

    protected static function countStoredMetadata(): int
    {
        $count = self::getConnection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s', self::getMetadataClassMetadata()->getTableName()));
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    protected static function storeMetadata(string $clientId, array $metadata): void
    {
        $classMetadata = self::getMetadataClassMetadata();

        self::getConnection()->update($classMetadata->getTableName(), [
            $classMetadata->getColumnName('metadata') => json_encode($metadata, \JSON_THROW_ON_ERROR),
        ], [
            $classMetadata->getColumnName('clientId') => $clientId,
        ]);
    }

    /**
     * Returns the SQL of the queries that the last request executed, as recorded by DoctrineBundle in debug mode
     *
     * @return list<string>
     */
    protected static function getExecutedQueries(): array
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

    protected static function getConnection(): Connection
    {
        return self::getEntityManager()->getConnection();
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        $managerRegistry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $managerRegistry);

        // The bundle uses the manager of the metadata class too
        $manager = $managerRegistry->getManagerForClass(self::getMetadataClass());
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @return ClassMetadata<object>
     */
    protected static function getMetadataClassMetadata(): ClassMetadata
    {
        return self::getEntityManager()->getClassMetadata(self::getMetadataClass());
    }

    /**
     * @return class-string
     */
    private static function getMetadataClass(): string
    {
        $metadataClass = self::getContainer()->getParameter('setono_client.metadata_class');
        self::assertIsString($metadataClass);
        self::assertTrue(class_exists($metadataClass));

        return $metadataClass;
    }
}
