<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Setono\ClientBundle\EventSubscriber\StoreCookieSubscriber;
use Setono\ClientBundle\PendingMetadataStorage\PendingMetadataStorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreCookieSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private const COOKIE_NAME = 'setono_client_id';

    /** @var ObjectProphecy<PendingMetadataStorageInterface> */
    private ObjectProphecy $pendingMetadataStorage;

    protected function setUp(): void
    {
        $this->pendingMetadataStorage = $this->prophesize(PendingMetadataStorageInterface::class);
    }

    /**
     * @test
     */
    public function it_subscribes_to_the_response_event(): void
    {
        self::assertSame([KernelEvents::RESPONSE => ['store', -64]], StoreCookieSubscriber::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_stores_a_new_cookie_when_none_exists(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', new ChangeAwareMetadata(['foo' => 'bar'])));

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldBeCalled();
        $this->pendingMetadataStorage->store(Argument::cetera())->shouldNotBeCalled();

        $response = $this->store($clientContext, null);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(self::COOKIE_NAME, $cookies[0]->getName());
        self::assertFalse($cookies[0]->isHttpOnly());
        self::assertSame('client-id', Cookie::fromString((string) $cookies[0]->getValue())->clientId);
    }

    /**
     * @test
     */
    public function it_reuses_the_client_id_from_an_existing_cookie(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->shouldNotBeCalled();

        $response = $this->store($clientContext, new Cookie('existing-id'));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('existing-id', Cookie::fromString((string) $cookies[0]->getValue())->clientId);
    }

    /**
     * @test
     */
    public function it_refreshes_the_last_seen_timestamp_and_keeps_the_first_seen_timestamp(): void
    {
        $before = time();
        $response = $this->store($this->prophesize(ClientContextInterface::class), new Cookie('existing-id', 2, 1_000, 2_000));

        $cookie = Cookie::fromString((string) $response->headers->getCookies()[0]->getValue());
        self::assertSame(1_000, $cookie->firstSeenAt);
        self::assertGreaterThanOrEqual($before, $cookie->lastSeenAt);
        self::assertLessThanOrEqual(time(), $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_uses_the_configured_cookie_name_and_expiration(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id'));

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::type(Request::class))->willReturn(null);

        $before = time();
        $response = new Response();
        $this->createSubscriber(
            $clientContext,
            $cookieProvider,
            cookieName: 'custom_name',
            cookieExpiration: '+30 days',
        )->store($this->createResponseEvent(new Request(), $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('custom_name', $cookies[0]->getName());
        self::assertGreaterThanOrEqual(strtotime('+30 days', $before), $cookies[0]->getExpiresTime());
        self::assertLessThanOrEqual(strtotime('+30 days'), $cookies[0]->getExpiresTime());
    }

    /**
     * @test
     */
    public function it_removes_the_pending_metadata(): void
    {
        $request = new Request();
        $response = new Response();

        $this->pendingMetadataStorage->remove($request, $response)->shouldBeCalledOnce();

        $this->store($this->prophesize(ClientContextInterface::class), new Cookie('existing-id'), $request, $response);
    }

    /**
     * @test
     */
    public function it_stores_the_changed_metadata_of_a_new_client_as_pending_metadata(): void
    {
        $request = new Request();
        $response = new Response();

        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', $metadata));

        $this->pendingMetadataStorage->remove($request, $response)->shouldBeCalled();
        $this->pendingMetadataStorage->store($request, $response, 'client-id', ['foo' => 'bar'])->willReturn(true)->shouldBeCalledOnce();

        $this->store($clientContext, null, $request, $response);

        self::assertFalse($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_keeps_the_metadata_dirty_when_it_cannot_be_stored_as_pending_metadata(): void
    {
        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', $metadata));

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldBeCalled();
        $this->pendingMetadataStorage->store(Argument::cetera())->willReturn(false)->shouldBeCalledOnce();

        $this->store($clientContext, null);

        self::assertTrue($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_does_not_store_empty_pending_metadata(): void
    {
        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');
        $metadata->remove('foo');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', $metadata));

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldBeCalled();
        $this->pendingMetadataStorage->store(Argument::cetera())->shouldNotBeCalled();

        $this->store($clientContext, null);

        self::assertFalse($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_does_not_store_metadata_that_is_not_change_aware_as_pending_metadata(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', new Metadata(['foo' => 'bar'])));

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldBeCalled();
        $this->pendingMetadataStorage->store(Argument::cetera())->shouldNotBeCalled();

        $response = $this->store($clientContext, null);

        self::assertCount(1, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_does_not_store_a_cookie_for_sub_requests(): void
    {
        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::cetera())->shouldNotBeCalled();

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldNotBeCalled();

        $response = new Response();
        $this->createSubscriber($this->prophesize(ClientContextInterface::class), $cookieProvider)->store(
            $this->createResponseEvent(new Request(), $response, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_does_not_store_a_cookie_when_the_pre_store_event_vetoes_it(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->shouldNotBeCalled();

        $this->pendingMetadataStorage->remove(Argument::cetera())->shouldNotBeCalled();

        $response = $this->store($clientContext, new Cookie('existing-id'), eventDispatcher: self::createVetoingEventDispatcher());

        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_discards_the_metadata_of_a_new_client_when_the_pre_store_event_vetoes_the_cookie(): void
    {
        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', $metadata));

        $this->pendingMetadataStorage->store(Argument::cetera())->shouldNotBeCalled();

        $response = $this->store($clientContext, null, eventDispatcher: self::createVetoingEventDispatcher());

        self::assertCount(0, $response->headers->getCookies());
        self::assertFalse($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_ignores_metadata_that_is_not_change_aware_when_the_pre_store_event_vetoes_the_cookie(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', new Metadata(['foo' => 'bar'])))->shouldBeCalled();

        $response = $this->store($clientContext, null, eventDispatcher: self::createVetoingEventDispatcher());

        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_triggers_a_deprecation_when_it_does_not_get_a_pending_metadata_storage(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            self::createSubscriberWithoutPendingMetadataStorage(
                $this->prophesize(ClientContextInterface::class),
                $this->prophesize(CookieProviderInterface::class),
            );
        } finally {
            restore_error_handler();
        }

        self::assertSame([sprintf(
            'Since setono/client-bundle 1.3: Not passing an instance of "%s" as "$pendingMetadataStorage" to "%s::__construct()" is deprecated, the argument will be required in 2.0.',
            PendingMetadataStorageInterface::class,
            StoreCookieSubscriber::class,
        )], $deprecations);
    }

    /**
     * @test
     */
    public function it_leaves_the_metadata_of_a_new_client_to_be_persisted_right_away_without_a_pending_metadata_storage(): void
    {
        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id', $metadata));

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::type(Request::class))->willReturn(null);

        $response = new Response();
        self::createSubscriberWithoutPendingMetadataStorage($clientContext, $cookieProvider)->store(
            $this->createResponseEvent(new Request(), $response),
        );

        self::assertCount(1, $response->headers->getCookies());
        self::assertTrue($metadata->isDirty());
    }

    /**
     * @param ObjectProphecy<ClientContextInterface> $clientContext
     */
    private function store(
        ObjectProphecy $clientContext,
        ?Cookie $cookie,
        Request $request = new Request(),
        Response $response = new Response(),
        EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
    ): Response {
        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie($request)->willReturn($cookie);

        $this->createSubscriber($clientContext, $cookieProvider, $eventDispatcher)->store(
            $this->createResponseEvent($request, $response),
        );

        return $response;
    }

    /**
     * @param ObjectProphecy<ClientContextInterface> $clientContext
     * @param ObjectProphecy<CookieProviderInterface> $cookieProvider
     */
    private function createSubscriber(
        ObjectProphecy $clientContext,
        ObjectProphecy $cookieProvider,
        EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
        string $cookieName = self::COOKIE_NAME,
        string $cookieExpiration = '+365 days',
    ): StoreCookieSubscriber {
        return new StoreCookieSubscriber(
            $clientContext->reveal(),
            $cookieProvider->reveal(),
            $eventDispatcher,
            $cookieName,
            $cookieExpiration,
            $this->pendingMetadataStorage->reveal(),
        );
    }

    /**
     * @param ObjectProphecy<ClientContextInterface> $clientContext
     * @param ObjectProphecy<CookieProviderInterface> $cookieProvider
     */
    private static function createSubscriberWithoutPendingMetadataStorage(
        ObjectProphecy $clientContext,
        ObjectProphecy $cookieProvider,
    ): StoreCookieSubscriber {
        return new StoreCookieSubscriber(
            $clientContext->reveal(),
            $cookieProvider->reveal(),
            new EventDispatcher(),
            self::COOKIE_NAME,
            '+365 days',
        );
    }

    private function createResponseEvent(
        Request $request,
        Response $response,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            $requestType,
            $response,
        );
    }

    private static function createVetoingEventDispatcher(): EventDispatcher
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(PreStoreCookieEvent::class, static function (PreStoreCookieEvent $event): void {
            $event->store = false;
        });

        return $eventDispatcher;
    }
}
