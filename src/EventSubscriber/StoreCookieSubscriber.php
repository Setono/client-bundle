<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\Client\Cookie;
use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Webmozart\Assert\Assert;

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

        $firstSeenAt = $client->metadata['created_at'] ?? null;
        Assert::nullOrInteger($firstSeenAt);

        $lastSeenAt = $client->metadata['updated_at'] ?? null;
        Assert::nullOrInteger($lastSeenAt);

        unset($client->metadata['created_at'], $client->metadata['updated_at']);

        $cookie = new Cookie($client->id, firstSeenAt: $firstSeenAt, lastSeenAt: $lastSeenAt);

        $event->getResponse()->headers->setCookie(
            HttpCookie::create($this->cookieName, $cookie->toString(), $this->cookieExpiration),
        );
    }
}
