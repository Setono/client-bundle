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
        $firstRequest = Request::create('/first');
        $firstCookie = new Cookie('first-client-id');
        $secondRequest = Request::create('/second');
        $secondCookie = new Cookie('second-client-id');

        $decorated = $this->prophesize(CookieProviderInterface::class);
        $decorated->getCookie($firstRequest)->willReturn($firstCookie);
        $decorated->getCookie($secondRequest)->willReturn($secondCookie);

        $provider = new CachedCookieProvider($decorated->reveal(), new RequestStack());

        self::assertSame($firstCookie, $provider->getCookie($firstRequest));
        self::assertSame($secondCookie, $provider->getCookie($secondRequest));
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
