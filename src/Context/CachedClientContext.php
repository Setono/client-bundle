<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives a request one client, from the first call until its kernel.terminate listeners are done. It decorates
 * ClientContextInterface with the lowest priority, so it wraps the decorators of the application too, and a decorator
 * that creates a client on every call still gives the request one client. Without a request, every call gets a client of its own
 */
final class CachedClientContext implements ClientContextInterface, EventSubscriberInterface
{
    /**
     * Keyed by the request object, so an entry goes away with its request, and a new request never gets the client of
     * an earlier one. That's why this service needs no reset between requests in long-running runtimes
     *
     * @var \WeakMap<Request, Client>
     */
    private readonly \WeakMap $clients;

    /**
     * Symfony removes the request from the request stack before it dispatches kernel.terminate, so this keeps it for the
     * listeners of that event. They get the request's client from $clients, because StoreMetadataSubscriber gets the
     * current client on kernel.finish_request of every main request. Weak, so the request goes away with the runtime's
     * last reference to it, even if a failing terminate listener keeps finishTerminating() from running
     *
     * @var \WeakReference<Request>|null
     */
    private ?\WeakReference $terminatingRequest = null;

    public function __construct(
        private readonly ClientContextInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
        $this->clients = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [
                ['startTerminating', 2048],
                ['finishTerminating', -2048],
            ],
        ];
    }

    public function getClient(): Client
    {
        $request = $this->requestStack->getMainRequest() ?? $this->terminatingRequest?->get();
        if (null === $request) {
            return $this->decorated->getClient();
        }

        return $this->clients[$request] ??= $this->decorated->getClient();
    }

    public function startTerminating(TerminateEvent $event): void
    {
        $this->terminatingRequest = \WeakReference::create($event->getRequest());
    }

    public function finishTerminating(): void
    {
        $this->terminatingRequest = null;
    }
}
