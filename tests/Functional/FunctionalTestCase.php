<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Cookie;
use Setono\ClientBundle\Tests\Functional\App\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Boots the test application with a fresh database. Like PHP-FPM, every request is handled by a new container
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const COOKIE_NAME = 'setono_client_id';

    protected KernelBrowser $browser;

    private static bool $cacheCleared = false;

    /**
     * Infection runs every mutant in a new process, but swaps in the mutated code without changing the modification time of the file.
     * A container compiled by an earlier process would look fresh, and could lack the mutant or contain the mutant of that process.
     * So every process compiles the container again
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$cacheCleared) {
            (new Filesystem())->remove(Kernel::getVarDir() . '/cache');
            self::$cacheCleared = true;
        }
    }

    protected function setUp(): void
    {
        // Fixed, so APP_ENV and APP_DEBUG in the environment, e.g. from the Symfony CLI, don't change the application
        $this->browser = static::createClient(['environment' => 'test', 'debug' => true]);

        // An exception fails the test instead of becoming an error response
        $this->browser->catchExceptions(false);

        $manager = self::getEntityManager();
        $classes = $manager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($manager);
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
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
