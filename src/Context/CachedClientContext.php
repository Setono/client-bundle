<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gives a request one client. It decorates ClientContextInterface with the lowest priority, so it wraps the decorators
 * of the application too, and a decorator that creates a client on every call still gives the request one client.
 * Without a request, every call gets a client of its own
 */
final class CachedClientContext implements ClientContextInterface
{
    /**
     * Keyed by the request object, so an entry goes away with its request, and a new request never gets the client of
     * an earlier one. That's why this service needs no reset between requests in long-running runtimes
     *
     * @var \WeakMap<Request, Client>
     */
    private readonly \WeakMap $clients;

    public function __construct(
        private readonly ClientContextInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
        $this->clients = new \WeakMap();
    }

    public function getClient(): Client
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return $this->decorated->getClient();
        }

        return $this->clients[$request] ??= $this->decorated->getClient();
    }
}
