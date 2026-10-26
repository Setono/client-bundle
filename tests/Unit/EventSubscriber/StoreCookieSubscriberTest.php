<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Setono\ClientBundle\EventSubscriber\StoreCookieSubscriber;
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

    /**
     * @test
     */
    public function it_subscribes_to_the_response_event(): void
    {
        self::assertSame([KernelEvents::RESPONSE => 'store'], StoreCookieSubscriber::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_stores_a_new_cookie_when_none_exists(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('client-id'));

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::type(Request::class))->willReturn(null);

        $response = new Response();
        $this->createSubscriber($clientContext->reveal(), $cookieProvider->reveal())->store(
            $this->createResponseEvent(new Request(), $response),
        );

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

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::type(Request::class))->willReturn(new Cookie('existing-id'));

        $response = new Response();
        $this->createSubscriber($clientContext->reveal(), $cookieProvider->reveal())->store(
            $this->createResponseEvent(new Request(), $response),
        );

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('existing-id', Cookie::fromString((string) $cookies[0]->getValue())->clientId);
    }

    /**
     * @test
     */
    public function it_refreshes_the_last_seen_timestamp_and_keeps_the_first_seen_timestamp(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::type(Request::class))->willReturn(new Cookie('existing-id', 2, 1_000, 2_000));

        $before = time();
        $response = new Response();
        $this->createSubscriber($clientContext->reveal(), $cookieProvider->reveal())->store(
            $this->createResponseEvent(new Request(), $response),
        );

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
            $clientContext->reveal(),
            $cookieProvider->reveal(),
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
    public function it_does_not_store_a_cookie_for_sub_requests(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::cetera())->shouldNotBeCalled();

        $response = new Response();
        $this->createSubscriber($clientContext->reveal(), $cookieProvider->reveal())->store(
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
        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie(Argument::cetera())->shouldNotBeCalled();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(PreStoreCookieEvent::class, static function (PreStoreCookieEvent $event): void {
            $event->store = false;
        });

        $response = new Response();
        $this->createSubscriber($clientContext->reveal(), $cookieProvider->reveal(), $eventDispatcher)->store(
            $this->createResponseEvent(new Request(), $response),
        );

        self::assertCount(0, $response->headers->getCookies());
    }

    private function createSubscriber(
        ClientContextInterface $clientContext,
        CookieProviderInterface $cookieProvider,
        EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
        string $cookieName = self::COOKIE_NAME,
        string $cookieExpiration = '+365 days',
    ): StoreCookieSubscriber {
        return new StoreCookieSubscriber($clientContext, $cookieProvider, $eventDispatcher, $cookieName, $cookieExpiration);
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
}
