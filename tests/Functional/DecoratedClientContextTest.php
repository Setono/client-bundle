<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\Client\Cookie;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;

/**
 * The test application has two decorators that create a client on every call: QueryParameterClientContext decorates
 * the interface and reads the "client_id" query parameter, and HeaderClientContext decorates
 * setono_client.client_context.default and reads the "X-Client-Id" header
 */
final class DecoratedClientContextTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_lets_a_decorator_of_the_interface_choose_the_current_client(): void
    {
        $this->browser->request('GET', '/?client_id=decorated-client-id');

        self::assertSame('decorated-client-id', $this->browser->getResponse()->getContent());
        self::assertSame('decorated-client-id', $this->getClientId());
    }

    /**
     * @test
     */
    public function it_lets_a_decorator_of_the_service_id_choose_the_current_client(): void
    {
        $this->browser->request('GET', '/', server: ['HTTP_X_CLIENT_ID' => 'decorated-client-id']);

        self::assertSame('decorated-client-id', $this->browser->getResponse()->getContent());
        self::assertSame('decorated-client-id', $this->getClientId());
    }

    /**
     * @test
     */
    public function it_rewrites_the_client_cookie_with_fresh_timestamps_when_the_current_client_differs(): void
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
    public function it_stores_the_metadata_of_the_current_client_of_a_decorator_of_the_interface(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar&client_id=decorated-client-id');

        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata('decorated-client-id'));
    }

    /**
     * @test
     */
    public function it_stores_the_metadata_of_the_current_client_of_a_decorator_of_the_service_id(): void
    {
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar', server: ['HTTP_X_CLIENT_ID' => 'decorated-client-id']);

        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata('decorated-client-id'));
    }

    /**
     * @test
     */
    public function it_refuses_to_store_a_current_client_id_that_is_not_valid(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The client id must be a non-empty string');

        $this->browser->request('GET', '/?client_id=');
    }
}
