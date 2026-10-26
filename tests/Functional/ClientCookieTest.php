<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\Client\Cookie;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\Uid\Uuid;

final class ClientCookieTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_gives_a_new_visitor_a_client_id(): void
    {
        $this->browser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHasCookie(self::COOKIE_NAME);

        $clientId = $this->getClientId();
        self::assertTrue(Uuid::isValid($clientId));
        self::assertSame($clientId, $this->browser->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_sets_a_cookie_that_javascript_can_read_and_that_expires_in_a_year(): void
    {
        $before = time();
        $this->browser->request('GET', '/');

        $cookies = $this->browser->getResponse()->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertFalse($cookies[0]->isHttpOnly());
        self::assertGreaterThanOrEqual(strtotime('+365 days', $before), $cookies[0]->getExpiresTime());
        self::assertLessThanOrEqual(strtotime('+365 days'), $cookies[0]->getExpiresTime());
    }

    /**
     * @test
     */
    public function it_recognizes_a_returning_visitor(): void
    {
        $this->browser->request('GET', '/');
        $clientId = $this->getClientId();

        $this->browser->request('GET', '/');

        self::assertSame($clientId, $this->browser->getResponse()->getContent());
        self::assertSame($clientId, $this->getClientId());
    }

    /**
     * @test
     */
    public function it_keeps_the_first_seen_timestamp_and_updates_the_last_seen_timestamp(): void
    {
        $this->setCookie((new Cookie('client-id', 2, 1_000, 2_000))->toString());

        $before = time();
        $this->browser->request('GET', '/');

        $cookie = $this->getCookie();
        self::assertSame('client-id', $cookie->clientId);
        self::assertSame(1_000, $cookie->firstSeenAt);
        self::assertGreaterThanOrEqual($before, $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_upgrades_a_version_1_cookie(): void
    {
        // Version 1 cookies only hold the client id
        $this->setCookie('client-id');

        $this->browser->request('GET', '/');

        self::assertSame('client-id', $this->browser->getResponse()->getContent());

        // Checks the raw value, because the version 1 cookie that was sent parses as a version 2 cookie too
        self::assertResponseHasCookie(self::COOKIE_NAME);
        self::assertMatchesRegularExpression('/^2\.\d+\.\d+\.client-id$/', (string) $this->browser->getCookieJar()->get(self::COOKIE_NAME)?->getValue());
    }

    /**
     * @test
     */
    public function it_treats_a_visitor_with_an_invalid_cookie_as_a_new_visitor(): void
    {
        $this->setCookie('1.2.3');

        $this->browser->request('GET', '/');

        $clientId = $this->getClientId();
        self::assertTrue(Uuid::isValid($clientId));
        self::assertSame($clientId, $this->browser->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_treats_a_visitor_with_an_empty_client_id_as_a_new_visitor(): void
    {
        $this->setCookie('2.1.1.');

        $this->browser->request('GET', '/');

        $clientId = $this->getClientId();
        self::assertTrue(Uuid::isValid($clientId));
        self::assertSame($clientId, $this->browser->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_does_not_set_the_cookie_when_a_listener_vetoes_it(): void
    {
        $this->browser->request('GET', '/?skip_cookie=1');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasCookie(self::COOKIE_NAME);
    }

    /**
     * Sets the cookie like the browser stores it after receiving it from the application
     */
    private function setCookie(string $value): void
    {
        $this->browser->getCookieJar()->set(new BrowserCookie(self::COOKIE_NAME, $value, null, '/', 'localhost'));
    }
}
