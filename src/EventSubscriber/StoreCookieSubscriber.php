<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Stores the client cookie of the current client, unless a listener of PreStoreCookieEvent vetoes it
 */
final class StoreCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly ClientCookieJar $cookieJar,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'store',
        ];
    }

    public function store(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $preStoreEvent = new PreStoreCookieEvent($event->getRequest(), $event->getResponse());
        $this->eventDispatcher->dispatch($preStoreEvent);

        if (!$preStoreEvent->store) {
            return;
        }

        $this->cookieJar->write($event->getRequest(), $event->getResponse(), $this->clientContext->getClient());
    }
}
