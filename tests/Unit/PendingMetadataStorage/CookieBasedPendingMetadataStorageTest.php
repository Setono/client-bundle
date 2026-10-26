<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\PendingMetadataStorage;

use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\PendingMetadataStorage\CookieBasedPendingMetadataStorage;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CookieBasedPendingMetadataStorageTest extends TestCase
{
    private const COOKIE_NAME = 'setono_client_id_pending_metadata';

    private const SECRET = 's3cr3t';

    /**
     * @test
     */
    public function it_stores_the_metadata_as_signed_json(): void
    {
        $response = new Response();

        self::assertTrue($this->createStorage()->store(new Request(), $response, 'client-id', [
            'url' => 'https://example.com/æ',
            'amount' => 1.0,
        ]));

        $json = '{"url":"https://example.com/æ","amount":1.0}';
        $signature = hash_hmac('sha256', 'setono_client_pending_metadata.client-id.' . $json, self::SECRET);

        $cookie = self::getOnlyCookie($response);
        self::assertSame(self::COOKIE_NAME, $cookie->getName());
        self::assertSame($json . '.' . $signature, $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertFalse($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertEqualsWithDelta(strtotime('+365 days'), $cookie->getExpiresTime(), 5);
    }

    /**
     * @test
     */
    public function it_stores_a_secure_cookie_on_secure_requests(): void
    {
        $response = new Response();

        $this->createStorage()->store(Request::create('https://example.com/'), $response, 'client-id', ['foo' => 'bar']);

        self::assertTrue(self::getOnlyCookie($response)->isSecure());
    }

    /**
     * @test
     */
    public function it_returns_the_stored_metadata(): void
    {
        $metadata = ['foo' => 'bar', 'amount' => 1.0, '__expires' => ['foo' => 1893456000]];

        $storage = $this->createStorage();
        $response = new Response();
        $storage->store(new Request(), $response, 'client-id', $metadata);

        self::assertSame($metadata, $storage->get(self::createRequestFromResponse($response), 'client-id'));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_request_has_no_cookie(): void
    {
        self::assertNull($this->createStorage()->get(new Request(), 'client-id'));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_cookie_is_not_signed(): void
    {
        $request = new Request(cookies: [self::COOKIE_NAME => '{"foo":"bar"}']);

        self::assertNull($this->createStorage()->get($request, 'client-id'));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_metadata_has_been_tampered_with(): void
    {
        $response = new Response();
        $this->createStorage()->store(new Request(), $response, 'client-id', ['foo' => 'bar']);

        $value = str_replace('"bar"', '"baz"', (string) self::getOnlyCookie($response)->getValue());
        $request = new Request(cookies: [self::COOKIE_NAME => $value]);

        self::assertNull($this->createStorage()->get($request, 'client-id'));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_metadata_belongs_to_another_client(): void
    {
        $storage = $this->createStorage();
        $response = new Response();
        $storage->store(new Request(), $response, 'another-client-id', ['foo' => 'bar']);

        self::assertNull($storage->get(self::createRequestFromResponse($response), 'client-id'));
    }

    /**
     * @test
     */
    public function it_returns_null_when_the_metadata_was_signed_with_another_secret(): void
    {
        $response = new Response();
        (new CookieBasedPendingMetadataStorage(self::COOKIE_NAME, '+365 days', 'another-secret'))
            ->store(new Request(), $response, 'client-id', ['foo' => 'bar']);

        self::assertNull($this->createStorage()->get(self::createRequestFromResponse($response), 'client-id'));
    }

    /**
     * @test
     */
    public function it_does_not_store_metadata_that_cannot_be_encoded(): void
    {
        $response = new Response();

        self::assertFalse($this->createStorage()->store(new Request(), $response, 'client-id', ['foo' => "\xB1\x31"]));
        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_stores_metadata_up_to_the_size_limit(): void
    {
        $response = new Response();

        self::assertTrue($this->createStorage()->store(new Request(), $response, 'client-id', self::createMetadataOfLength(2048)));
        self::assertSame(2048, strlen(rawurlencode((string) self::getOnlyCookie($response)->getValue())));
    }

    /**
     * @test
     */
    public function it_does_not_store_metadata_exceeding_the_size_limit(): void
    {
        $response = new Response();

        self::assertFalse($this->createStorage()->store(new Request(), $response, 'client-id', self::createMetadataOfLength(2049)));
        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function it_removes_the_cookie(): void
    {
        $response = new Response();

        $this->createStorage()->remove(new Request(cookies: [self::COOKIE_NAME => 'value']), $response);

        $cookie = self::getOnlyCookie($response);
        self::assertSame(self::COOKIE_NAME, $cookie->getName());
        self::assertTrue($cookie->isCleared());
    }

    /**
     * @test
     */
    public function it_does_not_remove_the_cookie_when_the_request_has_none(): void
    {
        $response = new Response();

        $this->createStorage()->remove(new Request(), $response);

        self::assertCount(0, $response->headers->getCookies());
    }

    /**
     * @test
     */
    public function storing_after_removing_replaces_the_removal(): void
    {
        $storage = $this->createStorage();
        $response = new Response();

        $storage->remove(new Request(cookies: [self::COOKIE_NAME => 'value']), $response);
        $storage->store(new Request(), $response, 'client-id', ['foo' => 'bar']);

        self::assertFalse(self::getOnlyCookie($response)->isCleared());
    }

    private function createStorage(): CookieBasedPendingMetadataStorage
    {
        return new CookieBasedPendingMetadataStorage(self::COOKIE_NAME, '+365 days', self::SECRET);
    }

    /**
     * Returns metadata whose URL encoded cookie value has the given length
     *
     * @return array<string, string>
     */
    private static function createMetadataOfLength(int $length): array
    {
        $signatureLength = 64;

        return ['k' => str_repeat('a', $length - strlen(rawurlencode('{"k":""}.')) - $signatureLength)];
    }

    private static function getOnlyCookie(Response $response): Cookie
    {
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }

    private static function createRequestFromResponse(Response $response): Request
    {
        return new Request(cookies: [self::COOKIE_NAME => self::getOnlyCookie($response)->getValue()]);
    }
}
