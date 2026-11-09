<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\ClientBundle\Request\MainRequestProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class MainRequestProviderTest extends TestCase
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
        ], MainRequestProvider::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_returns_the_main_request_of_the_request_stack(): void
    {
        $mainRequest = new Request();

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);
        $requestStack->push(new Request());

        self::assertSame($mainRequest, (new MainRequestProvider($requestStack))->getMainRequest());
    }

    /**
     * @test
     */
    public function it_returns_nothing_without_a_request(): void
    {
        self::assertNull((new MainRequestProvider(new RequestStack()))->getMainRequest());
    }

    /**
     * @test
     */
    public function it_returns_the_request_that_kernel_terminate_runs_for(): void
    {
        $request = new Request();

        $provider = new MainRequestProvider(new RequestStack());
        $provider->startTerminating($this->createTerminateEvent($request));

        self::assertSame($request, $provider->getMainRequest());
    }

    /**
     * @test
     */
    public function it_prefers_the_main_request_of_the_request_stack(): void
    {
        $mainRequest = new Request();

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        // Referenced, so the request is still alive while the provider is asked
        $terminatingRequest = new Request();

        $provider = new MainRequestProvider($requestStack);
        $provider->startTerminating($this->createTerminateEvent($terminatingRequest));

        self::assertSame($mainRequest, $provider->getMainRequest());
    }

    /**
     * @test
     */
    public function it_returns_nothing_once_kernel_terminate_is_done(): void
    {
        $request = new Request();

        $provider = new MainRequestProvider(new RequestStack());
        $provider->startTerminating($this->createTerminateEvent($request));
        $provider->finishTerminating();

        self::assertNull($provider->getMainRequest());
    }

    /**
     * @test
     */
    public function it_does_not_keep_the_request_alive(): void
    {
        $provider = new MainRequestProvider(new RequestStack());
        $provider->startTerminating($this->createTerminateEvent(new Request()));

        // Nothing references the request any more, e.g. because the runtime moved on to the next request
        self::assertNull($provider->getMainRequest());
    }

    private function createTerminateEvent(Request $request): TerminateEvent
    {
        return new TerminateEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, new Response());
    }
}
