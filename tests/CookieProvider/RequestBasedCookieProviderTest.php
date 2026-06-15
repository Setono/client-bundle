<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\CookieProvider;

use PHPUnit\Framework\TestCase;
use Setono\Client\Cookie;
use Setono\ClientBundle\CookieProvider\RequestBasedCookieProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestBasedCookieProviderTest extends TestCase
{
    private const COOKIE_NAME = 'setono_client_id';

    /**
     * @test
     */
    public function it_returns_null_when_there_is_no_request(): void
    {
        $provider = new RequestBasedCookieProvider(new RequestStack(), self::COOKIE_NAME);

        self::assertNull($provider->getCookie());
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_cookie_is_not_present(): void
    {
        $provider = new RequestBasedCookieProvider(new RequestStack(), self::COOKIE_NAME);

        self::assertNull($provider->getCookie(new Request()));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_cookie_value_is_invalid(): void
    {
        $provider = new RequestBasedCookieProvider(new RequestStack(), self::COOKIE_NAME);
        $request = new Request([], [], [], [self::COOKIE_NAME => '1.2.3']);

        self::assertNull($provider->getCookie($request));
    }

    /**
     * @test
     */
    public function it_returns_null_for_an_empty_cookie_value(): void
    {
        $provider = new RequestBasedCookieProvider(new RequestStack(), self::COOKIE_NAME);
        $request = new Request([], [], [], [self::COOKIE_NAME => '']);

        self::assertNull($provider->getCookie($request));
    }

    /**
     * @test
     */
    public function it_prefers_the_given_request_over_the_main_request(): void
    {
        $mainRequest = new Request([], [], [], [self::COOKIE_NAME => (new Cookie('main-id'))->toString()]);
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $given = new Request([], [], [], [self::COOKIE_NAME => (new Cookie('given-id'))->toString()]);

        $provider = new RequestBasedCookieProvider($requestStack, self::COOKIE_NAME);
        $cookie = $provider->getCookie($given);

        self::assertNotNull($cookie);
        self::assertSame('given-id', $cookie->clientId);
    }

    /**
     * @test
     */
    public function it_parses_the_cookie_from_the_given_request(): void
    {
        $provider = new RequestBasedCookieProvider(new RequestStack(), self::COOKIE_NAME);
        $request = new Request([], [], [], [self::COOKIE_NAME => (new Cookie('client-id'))->toString()]);

        $cookie = $provider->getCookie($request);

        self::assertNotNull($cookie);
        self::assertSame('client-id', $cookie->clientId);
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_main_request(): void
    {
        $request = new Request([], [], [], [self::COOKIE_NAME => (new Cookie('client-id'))->toString()]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new RequestBasedCookieProvider($requestStack, self::COOKIE_NAME);

        $cookie = $provider->getCookie();

        self::assertNotNull($cookie);
        self::assertSame('client-id', $cookie->clientId);
    }
}
