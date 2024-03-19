<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\Client\Cookie;
use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly CookieProviderInterface $cookieProvider,
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
        if (!$event->isMainRequest()) {
            return;
        }

        $cookie = $this->cookieProvider->getCookie($event->getRequest());

        if (null === $cookie) {
            $cookie = new Cookie($this->clientFactory->create()->id);
        }

        $event->getResponse()->headers->setCookie(
            HttpCookie::create($this->cookieName, $cookie->toString(), $this->cookieExpiration),
        );
    }
}
