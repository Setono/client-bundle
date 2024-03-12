<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly string $cookieName,
        private readonly string $cookieExpiration,
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
        $client = $this->clientFactory->create();
        $event->getResponse()->headers->setCookie(
            Cookie::create($this->cookieName, $client->id, $this->cookieExpiration),
        );
    }
}
