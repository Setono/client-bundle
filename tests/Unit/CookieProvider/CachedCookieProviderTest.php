<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\CookieProvider;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Cookie;
use Setono\ClientBundle\CookieProvider\CachedCookieProvider;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CachedCookieProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_null_when_there_is_no_request(): void
    {
        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie(Argument::any())->shouldNotBeCalled();

        $provider = new CachedCookieProvider($decorated->reveal(), new RequestStack());

        self::assertNull($provider->getCookie());
    }

    /**
     * @test
     */
    public function it_resolves_the_cookie_once_per_request(): void
    {
        $request = new Request();
        $cookie = new Cookie('client-id');

        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie($request)->willReturn($cookie)->shouldBeCalledOnce();

        $provider = new CachedCookieProvider($decorated->reveal(), new RequestStack());

        self::assertSame($cookie, $provider->getCookie($request));
        self::assertSame($cookie, $provider->getCookie($request));
    }

    /**
     * @test
     */
    public function it_caches_a_missing_cookie(): void
    {
        $request = new Request();

        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie($request)->willReturn(null)->shouldBeCalledOnce();

        $provider = new CachedCookieProvider($decorated->reveal(), new RequestStack());

        self::assertNull($provider->getCookie($request));
        self::assertNull($provider->getCookie($request));
    }

    /**
     * @test
     */
    public function it_resolves_the_cookie_of_each_request_separately(): void
    {
        // The requests are equal, so only their identity tells them apart. Prophecy compares arguments with ==, so the stub checks the identity itself
        $firstRequest = new Request();
        $firstCookie = new Cookie('first-client-id');
        $secondRequest = new Request();
        $secondCookie = new Cookie('second-client-id');

        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie(Argument::type(Request::class))->will(
            static fn (array $arguments): Cookie => $arguments[0] === $firstRequest ? $firstCookie : $secondCookie,
        );

        $provider = new CachedCookieProvider($decorated->reveal(), new RequestStack());

        self::assertSame($firstCookie, $provider->getCookie($firstRequest));
        self::assertSame($secondCookie, $provider->getCookie($secondRequest));
    }

    /**
     * @test
     */
    public function it_does_not_give_a_new_request_the_cookie_of_a_freed_request(): void
    {
        // A double that doesn't hold on to the requests it receives, unlike a Prophecy double
        $decorated = new class() implements CookieProviderInterface {
            private int $calls = 0;

            public function getCookie(?Request $request = null): Cookie
            {
                return new Cookie(sprintf('client-id-%d', ++$this->calls));
            }
        };

        $provider = new CachedCookieProvider($decorated, new RequestStack());

        $firstRequest = new Request();
        $firstRequestId = spl_object_id($firstRequest);
        self::assertSame('client-id-1', $provider->getCookie($firstRequest)?->clientId);
        unset($firstRequest);

        // PHP reuses the freed object's handle, so this request gets the same spl_object_id() and spl_object_hash()
        $secondRequest = new Request();
        self::assertSame($firstRequestId, spl_object_id($secondRequest), 'The new request does not reuse the handle of the freed one, so this test proves nothing');
        self::assertSame('client-id-2', $provider->getCookie($secondRequest)?->clientId);
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_main_request(): void
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $cookie = new Cookie('client-id');

        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie($request)->willReturn($cookie);

        $provider = new CachedCookieProvider($decorated->reveal(), $requestStack);

        self::assertSame($cookie, $provider->getCookie());
    }
}
