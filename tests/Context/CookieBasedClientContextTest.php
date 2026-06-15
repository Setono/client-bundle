<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\Client\Metadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Context\CookieBasedClientContext;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;

final class CookieBasedClientContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_delegates_to_the_decorated_context_when_there_is_no_cookie(): void
    {
        $decoratedClient = new Client('decorated');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($decoratedClient);

        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata(Argument::any())->shouldNotBeCalled();

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie()->willReturn(null);

        $context = new CookieBasedClientContext(
            $decorated->reveal(),
            $metadataProvider->reveal(),
            $cookieProvider->reveal(),
        );

        self::assertSame($decoratedClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_builds_a_client_from_the_cookie(): void
    {
        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->shouldNotBeCalled();

        $metadata = new Metadata(['foo' => 'bar']);

        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata('client-id')->willReturn($metadata);

        $cookieProvider = $this->prophesize(CookieProviderInterface::class);
        $cookieProvider->getCookie()->willReturn(new Cookie('client-id'));

        $context = new CookieBasedClientContext(
            $decorated->reveal(),
            $metadataProvider->reveal(),
            $cookieProvider->reveal(),
        );

        $client = $context->getClient();

        self::assertSame('client-id', $client->id);
        self::assertSame($metadata, $client->metadata);
    }
}
