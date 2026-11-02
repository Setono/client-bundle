<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Cookie;

use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClientCookieJarTest extends TestCase
{
    private const COOKIE_NAME = 'setono_client_id';

    /**
     * @test
     */
    public function it_reads_the_client_cookie_of_the_request(): void
    {
        $cookie = self::createJar()->read(self::createRequest((new Cookie('client-id', 2, 1_000, 2_000))->toString()));

        self::assertNotNull($cookie);
        self::assertSame('client-id', $cookie->clientId);
        self::assertSame(1_000, $cookie->firstSeenAt);
        self::assertSame(2_000, $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_reads_a_version_1_cookie(): void
    {
        self::assertSame('client-id', self::createJar()->read(self::createRequest('client-id'))?->clientId);
    }

    /**
     * @test
     */
    public function it_reads_the_cookie_with_the_configured_name(): void
    {
        $request = new Request([], [], [], ['custom_name' => (new Cookie('client-id'))->toString()]);

        self::assertNull(self::createJar()->read($request));
        self::assertSame('client-id', self::createJar('custom_name')->read($request)?->clientId);
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_request_has_no_client_cookie(): void
    {
        self::assertNull(self::createJar()->read(new Request()));
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_cookie_is_empty(): void
    {
        self::assertNull(self::createJar()->read(self::createRequest('')));
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_cookie_is_not_a_string(): void
    {
        self::assertNull(self::createJar()->read(new Request([], [], [], [self::COOKIE_NAME => 1])));
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_cookie_is_invalid(): void
    {
        self::assertNull(self::createJar()->read(self::createRequest('1.2.3')));
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_client_id_is_empty(): void
    {
        self::assertNull(self::createJar()->read(self::createRequest('2.1.1.')));
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_the_client_id_is_longer_than_255_characters(): void
    {
        self::assertNull(self::createJar()->read(self::createRequest((new Cookie(str_repeat('a', 256)))->toString())));
    }

    /**
     * @test
     */
    public function it_reads_a_client_id_of_255_characters(): void
    {
        $clientId = str_repeat('a', 255);

        self::assertSame($clientId, self::createJar()->read(self::createRequest((new Cookie($clientId))->toString()))?->clientId);
    }

    /**
     * @test
     */
    public function it_writes_a_cookie_that_javascript_can_read(): void
    {
        $response = new Response();
        self::createJar()->write(new Request(), $response, new Client('client-id'));

        $httpCookie = self::getWrittenCookie($response);
        self::assertSame(self::COOKIE_NAME, $httpCookie->getName());
        self::assertFalse($httpCookie->isHttpOnly());
    }

    /**
     * @test
     */
    public function it_writes_the_cookie_with_the_configured_name_and_expiration(): void
    {
        $before = time();
        $response = new Response();
        self::createJar('custom_name', '+30 days')->write(new Request(), $response, new Client('client-id'));

        $httpCookie = self::getWrittenCookie($response);
        self::assertSame('custom_name', $httpCookie->getName());
        self::assertGreaterThanOrEqual(strtotime('+30 days', $before), $httpCookie->getExpiresTime());
        self::assertLessThanOrEqual(strtotime('+30 days'), $httpCookie->getExpiresTime());
    }

    /**
     * @test
     */
    public function it_gives_a_new_client_fresh_timestamps(): void
    {
        $before = time();
        $response = new Response();
        self::createJar()->write(new Request(), $response, new Client('client-id'));

        $cookie = Cookie::fromString((string) self::getWrittenCookie($response)->getValue());
        self::assertSame('client-id', $cookie->clientId);
        self::assertGreaterThanOrEqual($before, $cookie->firstSeenAt);
        self::assertGreaterThanOrEqual($before, $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_keeps_the_first_seen_timestamp_and_refreshes_the_last_seen_timestamp_of_the_same_client_id(): void
    {
        $request = self::createRequest((new Cookie('client-id', 2, 1_000, 2_000))->toString());

        $before = time();
        $response = new Response();
        self::createJar()->write($request, $response, new Client('client-id'));

        $cookie = Cookie::fromString((string) self::getWrittenCookie($response)->getValue());
        self::assertSame('client-id', $cookie->clientId);
        self::assertSame(1_000, $cookie->firstSeenAt);
        self::assertGreaterThanOrEqual($before, $cookie->lastSeenAt);
        self::assertLessThanOrEqual(time(), $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_writes_the_client_id_of_the_client_and_starts_over_the_timestamps_when_it_differs_from_the_cookie(): void
    {
        $request = self::createRequest((new Cookie('cookie-client-id', 2, 1_000, 2_000))->toString());

        $before = time();
        $response = new Response();
        self::createJar()->write($request, $response, new Client('current-client-id'));

        $cookie = Cookie::fromString((string) self::getWrittenCookie($response)->getValue());
        self::assertSame('current-client-id', $cookie->clientId);
        self::assertGreaterThanOrEqual($before, $cookie->firstSeenAt);
    }

    /**
     * @test
     */
    public function it_writes_a_client_id_of_255_characters(): void
    {
        $clientId = str_repeat('a', 255);

        $response = new Response();
        self::createJar()->write(new Request(), $response, new Client($clientId));

        self::assertSame($clientId, Cookie::fromString((string) self::getWrittenCookie($response)->getValue())->clientId);
    }

    /**
     * @test
     */
    public function it_refuses_to_write_an_empty_client_id(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The client id must be a non-empty string of at most 255 bytes to be stored in the client cookie, but the current client has a client id of 0 bytes');

        self::createJar()->write(new Request(), new Response(), new Client(''));
    }

    /**
     * @test
     */
    public function it_refuses_to_write_a_client_id_longer_than_255_characters(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('client id of 256 bytes');

        self::createJar()->write(new Request(), new Response(), new Client(str_repeat('a', 256)));
    }

    private static function createJar(string $name = self::COOKIE_NAME, string $expiration = '+365 days'): ClientCookieJar
    {
        return new ClientCookieJar($name, $expiration);
    }

    private static function createRequest(string $cookieValue): Request
    {
        return new Request([], [], [], [self::COOKIE_NAME => $cookieValue]);
    }

    private static function getWrittenCookie(Response $response): HttpCookie
    {
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }
}
