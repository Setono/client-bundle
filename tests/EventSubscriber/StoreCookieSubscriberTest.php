<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
        $subscriber = new StoreCookieSubscriber(
            $clientContext->reveal(),
            $cookieProvider->reveal(),
            $eventDispatcher,
            self::COOKIE_NAME,
            '+365 days',
        );
        $subscriber->store($this->createResponseEvent(new Request(), $response));

        self::assertCount(0, $response->headers->getCookies());
    }

    private function createSubscriber(
        ClientContextInterface $clientContext,
        CookieProviderInterface $cookieProvider,
    ): StoreCookieSubscriber {
        return new StoreCookieSubscriber(
            $clientContext,
            $cookieProvider,
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
}
