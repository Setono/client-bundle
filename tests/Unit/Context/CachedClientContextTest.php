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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class CachedClientContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_wraps_the_listeners_of_kernel_terminate(): void
    {
        self::assertSame([
            KernelEvents::TERMINATE => [
                ['startTerminating', 2048],
                ['finishTerminating', -2048],
            ],
        ], CachedClientContext::getSubscribedEvents());
    }

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
    public function it_gives_the_listeners_of_kernel_terminate_the_client_of_the_request(): void
    {
        $client = new Client('client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($client)->shouldBeCalledOnce();

        $requestStack = new RequestStack();
        $context = new CachedClientContext($decorated->reveal(), $requestStack);

        $request = new Request();
        $requestStack->push($request);
        $context->getClient();

        // Like HttpKernel, which removes the request from the stack before it dispatches kernel.terminate
        $requestStack->pop();
        $context->startTerminating($this->createTerminateEvent($request));

        self::assertSame($client, $context->getClient());
    }

    /**
     * @test
     */
    public function it_prefers_the_main_request_of_the_request_stack_while_kernel_terminate_runs(): void
    {
        $terminatingClient = new Client('terminating-client-id');
        $mainClient = new Client('main-client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($terminatingClient, $mainClient);

        $requestStack = new RequestStack();
        $context = new CachedClientContext($decorated->reveal(), $requestStack);

        // Referenced, so the request is still alive while the context is asked
        $terminatingRequest = new Request();
        $context->startTerminating($this->createTerminateEvent($terminatingRequest));
        self::assertSame($terminatingClient, $context->getClient());

        $requestStack->push(new Request());
        self::assertSame($mainClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_passes_every_call_on_once_kernel_terminate_is_done(): void
    {
        $firstClient = new Client('first-client-id');
        $secondClient = new Client('second-client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($firstClient, $secondClient);

        $context = new CachedClientContext($decorated->reveal(), new RequestStack());

        $request = new Request();
        $context->startTerminating($this->createTerminateEvent($request));
        $context->finishTerminating();

        self::assertSame($firstClient, $context->getClient());
        self::assertSame($secondClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_does_not_keep_the_request_of_kernel_terminate_alive(): void
    {
        $firstClient = new Client('first-client-id');
        $secondClient = new Client('second-client-id');

        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($firstClient, $secondClient);

        $context = new CachedClientContext($decorated->reveal(), new RequestStack());

        // Nothing references the request any more, e.g. because a terminate listener failed and the runtime moved on
        $context->startTerminating($this->createTerminateEvent(new Request()));

        self::assertSame($firstClient, $context->getClient());
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

    private function createTerminateEvent(Request $request): TerminateEvent
    {
        return new TerminateEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, new Response());
    }
}
