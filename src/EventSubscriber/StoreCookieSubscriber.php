<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Setono\ClientBundle\Cookie\ClientIdCookie;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

        $createdAt = $client->metadata['created_at'] ?? null;
        Assert::nullOrInteger($createdAt);

        $updated = $client->metadata['updated_at'] ?? null;
        Assert::nullOrInteger($updated);

        unset($client->metadata['created_at'], $client->metadata['updated_at']);

        $cookie = new ClientIdCookie($client->id, $createdAt, $updated);

        $event->getResponse()->headers->setCookie(
            $cookie->asHttpCookie($this->cookieName, $this->cookieExpiration),
        );
    }
}
