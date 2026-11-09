<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Request;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns the main request, also while kernel.terminate runs. Symfony removes the request from the request stack before
 * it dispatches kernel.terminate, but the listeners of that event still handle the request, and the current client belongs to it
 *
 * @internal
 */
final class MainRequestProvider implements EventSubscriberInterface
{
    /**
     * Weak, so the request goes away with the runtime's last reference to it, even if a failing terminate listener
     * keeps finishTerminating() from running
     *
     * @var \WeakReference<Request>|null
     */
    private ?\WeakReference $terminatingRequest = null;

    public function __construct(private readonly RequestStack $requestStack)
    {
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

    public function getMainRequest(): ?Request
    {
        return $this->requestStack->getMainRequest() ?? $this->terminatingRequest?->get();
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
