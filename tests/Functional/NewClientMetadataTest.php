<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Cookie;
use Setono\ClientBundle\Context\CachedClientContext;
use Setono\ClientBundle\Context\CookieBasedClientContext;
use Setono\ClientBundle\Context\DefaultClientContext;
use Setono\ClientBundle\Context\PendingMetadataClientContext;
use Setono\ClientBundle\CookieProvider\CachedCookieProvider;
use Setono\ClientBundle\CookieProvider\RequestBasedCookieProvider;
use Setono\ClientBundle\Entity\Metadata as MetadataEntity;
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Setono\ClientBundle\EventSubscriber\StoreCookieSubscriber;
use Setono\ClientBundle\EventSubscriber\StoreMetadataSubscriber;
use Setono\ClientBundle\MetadataPersister\DoctrineOrmBasedMetadataPersister;
use Setono\ClientBundle\MetadataProvider\DoctrineOrmBasedMetadataProvider;
use Setono\ClientBundle\MetadataProvider\EmptyMetadataProvider;
use Setono\ClientBundle\PendingMetadataStorage\CookieBasedPendingMetadataStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs the bundle's services through the HTTP kernel to show how the metadata of a new client travels
 */
final class NewClientMetadataTest extends TestCase
{
    use ProphecyTrait;

    private const COOKIE_NAME = 'setono_client_id';

    private const PENDING_METADATA_COOKIE_NAME = 'setono_client_id_pending_metadata';

    /** @var \ArrayObject<string, MetadataEntity> */
    private \ArrayObject $database;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $databaseCalls;

    private ManagerRegistry $managerRegistry;

    /**
     * The cookies of the browser used in the tests
     *
     * @var array<string, string>
     */
    private array $cookieJar = [];

    protected function setUp(): void
    {
        /** @var \ArrayObject<string, MetadataEntity> $database */
        $database = new \ArrayObject();
        /** @var \ArrayObject<int, string> $databaseCalls */
        $databaseCalls = new \ArrayObject();
        $this->database = $database;
        $this->databaseCalls = $databaseCalls;

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, Argument::type('string'))->will(
            static function (array $arguments) use ($database, $databaseCalls): ?MetadataEntity {
                $databaseCalls->append('find');

                /** @var string $clientId */
                $clientId = $arguments[1];

                return $database[$clientId] ?? null;
            },
        );
        $manager->persist(Argument::type(MetadataEntity::class))->will(
            static function (array $arguments) use ($database, $databaseCalls): void {
                $databaseCalls->append('persist');

                /** @var MetadataEntity $entity */
                $entity = $arguments[0];
                $database[(string) $entity->getClientId()] = $entity;
            },
        );
        $manager->flush()->will(static function () use ($databaseCalls): void {
            $databaseCalls->append('flush');
        });

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager->reveal());

        $this->managerRegistry = $managerRegistry->reveal();
    }

    /**
     * @test
     */
    public function it_does_not_touch_the_database_for_a_new_client_that_never_comes_back(): void
    {
        $response = $this->handle(Request::create('/?gclid=abc'));

        self::assertEqualsCanonicalizing(
            [self::COOKIE_NAME, self::PENDING_METADATA_COOKIE_NAME],
            array_map(static fn ($cookie) => $cookie->getName(), $response->headers->getCookies()),
        );
        self::assertSame([], $this->databaseCalls->getArrayCopy());
    }

    /**
     * @test
     */
    public function it_persists_the_metadata_when_the_new_client_comes_back(): void
    {
        $this->browse('/?gclid=abc');
        self::assertSame([], $this->databaseCalls->getArrayCopy());

        $response = $this->browse('/');

        $clientId = Cookie::fromString($this->cookieJar[self::COOKIE_NAME])->clientId;
        $entity = $this->database[$clientId] ?? null;
        self::assertNotNull($entity);

        $metadata = $entity->getMetadata();
        self::assertSame('abc', $metadata['gclid']);
        self::assertArrayHasKey('__expires', $metadata);

        $pendingMetadataCookies = array_values(array_filter(
            $response->headers->getCookies(),
            static fn ($cookie) => $cookie->getName() === self::PENDING_METADATA_COOKIE_NAME,
        ));
        self::assertCount(1, $pendingMetadataCookies);
        self::assertTrue($pendingMetadataCookies[0]->isCleared());
        self::assertArrayNotHasKey(self::PENDING_METADATA_COOKIE_NAME, $this->cookieJar);

        // from now on, the metadata is only loaded when it's used
        $databaseCalls = count($this->databaseCalls);
        $this->browse('/');
        self::assertCount($databaseCalls, $this->databaseCalls);
    }

    /**
     * @test
     */
    public function it_persists_the_metadata_right_away_when_it_does_not_fit_in_the_pending_metadata_cookie(): void
    {
        $this->browse('/?gclid=' . str_repeat('a', 2048));

        self::assertArrayNotHasKey(self::PENDING_METADATA_COOKIE_NAME, $this->cookieJar);
        self::assertCount(1, $this->database);
    }

    /**
     * @test
     */
    public function it_persists_metadata_set_after_the_cookie_was_stored_right_away(): void
    {
        $this->browse('/?gclid=abc', metadataListenerPriority: -128);

        self::assertCount(1, $this->database);
    }

    /**
     * @test
     */
    public function it_does_not_persist_the_metadata_of_a_new_client_that_does_not_get_a_cookie(): void
    {
        $response = $this->handle(Request::create('/?gclid=abc'), storeCookie: false);

        self::assertCount(0, $response->headers->getCookies());
        self::assertSame([], $this->databaseCalls->getArrayCopy());
    }

    /**
     * Sends a request with the cookies of the browser and updates them with the cookies of the response
     */
    private function browse(string $uri, int $metadataListenerPriority = 0): Response
    {
        $response = $this->handle(Request::create($uri, cookies: $this->cookieJar), $metadataListenerPriority);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                unset($this->cookieJar[$cookie->getName()]);
            } else {
                $this->cookieJar[$cookie->getName()] = (string) $cookie->getValue();
            }
        }

        return $response;
    }

    private function handle(Request $request, int $metadataListenerPriority = 0, bool $storeCookie = true): Response
    {
        $requestStack = new RequestStack();
        $eventDispatcher = new EventDispatcher();

        $cookieProvider = new CachedCookieProvider(
            new RequestBasedCookieProvider($requestStack, self::COOKIE_NAME),
            $requestStack,
        );
        $pendingMetadataStorage = new CookieBasedPendingMetadataStorage(
            self::PENDING_METADATA_COOKIE_NAME,
            '+365 days',
            'secret',
        );
        $clientContext = new CachedClientContext(new PendingMetadataClientContext(
            new CookieBasedClientContext(
                new DefaultClientContext(),
                new DoctrineOrmBasedMetadataProvider(new EmptyMetadataProvider(), $this->managerRegistry, MetadataEntity::class),
                $cookieProvider,
            ),
            $pendingMetadataStorage,
            $requestStack,
        ));

        $eventDispatcher->addSubscriber(new StoreCookieSubscriber(
            $clientContext,
            $cookieProvider,
            $pendingMetadataStorage,
            $eventDispatcher,
            self::COOKIE_NAME,
            '+365 days',
        ));
        $eventDispatcher->addSubscriber(new StoreMetadataSubscriber(
            $clientContext,
            new DoctrineOrmBasedMetadataPersister($this->managerRegistry, MetadataEntity::class),
        ));

        // Like Setono/SyliusGoogleAdsPlugin, the application stores the Google click id on the response
        $eventDispatcher->addListener(KernelEvents::RESPONSE, static function (ResponseEvent $event) use ($clientContext): void {
            $gclid = $event->getRequest()->query->get('gclid');
            if (null !== $gclid) {
                $clientContext->getClient()->metadata->set('gclid', $gclid, 7_776_000);
            }
        }, $metadataListenerPriority);

        $eventDispatcher->addListener(PreStoreCookieEvent::class, static function (PreStoreCookieEvent $event) use ($storeCookie): void {
            $event->store = $storeCookie;
        });

        $request->attributes->set('_controller', static fn (): Response => new Response());

        return (new HttpKernel($eventDispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver()))
            ->handle($request);
    }
}
