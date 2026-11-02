<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\Client\Cookie;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;

/**
 * The test application decorates the client context with RequestedClientContext, which gives a request the client
 * that its "client_id" query parameter names
 */
final class DecoratedClientContextTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_gives_the_request_the_client_of_the_decorator(): void
    {
        $this->browser->request('GET', '/?client_id=decorated-client-id');

        self::assertSame('decorated-client-id', $this->browser->getResponse()->getContent());
        self::assertSame('decorated-client-id', $this->getClientId());
    }

    /**
     * @test
     */
    public function it_stores_the_client_id_of_the_decorator_with_fresh_timestamps_in_place_of_the_cookie(): void
    {
        $this->browser->getCookieJar()->set(new BrowserCookie(self::COOKIE_NAME, (new Cookie('cookie-client-id', 2, 1_000, 2_000))->toString(), null, '/', 'localhost'));

        $before = time();
        $this->browser->request('GET', '/?client_id=decorated-client-id');

        $cookie = $this->getCookie();
        self::assertSame('decorated-client-id', $cookie->clientId);
        self::assertGreaterThanOrEqual($before, $cookie->firstSeenAt);
    }

    /**
     * @test
     */
    public function it_stores_the_metadata_set_on_the_client_of_a_decorator_that_creates_a_client_on_every_call(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar&client_id=decorated-client-id');

        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata('decorated-client-id'));
    }

    /**
     * @test
     */
    public function it_refuses_to_store_a_client_id_that_the_cookie_cannot_hold(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The client id must be a non-empty string');

        $this->browser->request('GET', '/?client_id=');
    }
}
