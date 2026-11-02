<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\ClientBundle\Context\CachedClientContext;
use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CachedClientContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_a_request_one_client(): void
    {
        $client = new Client('client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($client)->shouldBeCalledOnce();

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $context = new CachedClientContext($decorated->reveal(), $requestStack);

        self::assertSame($client, $context->getClient());
        self::assertSame($client, $context->getClient());
    }

    /**
     * @test
     */
    public function it_gives_a_sub_request_the_client_of_the_main_request(): void
    {
        $client = new Client('client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($client)->shouldBeCalledOnce();

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $context = new CachedClientContext($decorated->reveal(), $requestStack);
        $context->getClient();

        $requestStack->push(new Request());

        self::assertSame($client, $context->getClient());
    }

    /**
     * @test
     */
    public function it_gives_the_next_request_a_client_of_its_own(): void
    {
        $firstClient = new Client('first-client-id');
        $secondClient = new Client('second-client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($firstClient, $secondClient);

        // Like a long-running runtime, which handles many requests with the same service
        $requestStack = new RequestStack();
        $context = new CachedClientContext($decorated->reveal(), $requestStack);

        $requestStack->push(new Request());
        self::assertSame($firstClient, $context->getClient());
        $requestStack->pop();

        $requestStack->push(new Request());
        self::assertSame($secondClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_passes_every_call_without_a_request_on(): void
    {
        $firstClient = new Client('first-client-id');
        $secondClient = new Client('second-client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($firstClient, $secondClient);

        $context = new CachedClientContext($decorated->reveal(), new RequestStack());

        self::assertSame($firstClient, $context->getClient());
        self::assertSame($secondClient, $context->getClient());
    }
}
