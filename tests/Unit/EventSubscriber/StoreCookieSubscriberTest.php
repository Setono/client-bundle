<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Cookie\ClientCookieJar;
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
    public function it_stores_the_client_cookie_of_the_current_client(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('current-client-id'));

        $request = new Request([], [], [], [self::COOKIE_NAME => (new Cookie('cookie-client-id'))->toString()]);
        $response = new Response();
        $this->createSubscriber($clientContext->reveal())->store($this->createResponseEvent($request, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(self::COOKIE_NAME, $cookies[0]->getName());
        self::assertSame('current-client-id', Cookie::fromString((string) $cookies[0]->getValue())->clientId);
    }

    /**
     * @test
     */
    public function it_does_not_store_a_cookie_for_sub_requests(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->shouldNotBeCalled();

        $response = new Response();
        $this->createSubscriber($clientContext->reveal())->store(
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

        $request = new Request();
        $response = new Response();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(PreStoreCookieEvent::class, static function (PreStoreCookieEvent $event) use ($request, $response): void {
            self::assertSame($request, $event->request);
            self::assertSame($response, $event->response);

            $event->store = false;
        });

        $this->createSubscriber($clientContext->reveal(), $eventDispatcher)->store($this->createResponseEvent($request, $response));

        self::assertCount(0, $response->headers->getCookies());
    }

    private function createSubscriber(
        ClientContextInterface $clientContext,
        EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
    ): StoreCookieSubscriber {
        return new StoreCookieSubscriber($clientContext, new ClientCookieJar(self::COOKIE_NAME, '+365 days'), $eventDispatcher);
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
