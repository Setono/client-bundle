<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\ClientBundle\Context\CachedClientContext;
use Setono\ClientBundle\Context\ClientContextInterface;

final class CachedClientContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_the_decorated_client(): void
    {
        $client = new Client('id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($client);

        self::assertSame($client, (new CachedClientContext($decorated->reveal()))->getClient());
    }

    /**
     * @test
     */
    public function it_only_resolves_the_client_once(): void
    {
        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn(new Client('id'))->shouldBeCalledOnce();

        $context = new CachedClientContext($decorated->reveal());

        self::assertSame($context->getClient(), $context->getClient());
    }
}
