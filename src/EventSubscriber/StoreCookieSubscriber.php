<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Client\Client;
use Setono\Client\Cookie;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Setono\ClientBundle\PendingMetadataStorage\PendingMetadataStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreCookieSubscriber implements EventSubscriberInterface
{
    /**
     * @param PendingMetadataStorageInterface|null $pendingMetadataStorage without it, the metadata of a new client is persisted right away
     */
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly CookieProviderInterface $cookieProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $cookieName,
        private readonly string $cookieExpiration,
        private readonly ?PendingMetadataStorageInterface $pendingMetadataStorage = null,
    ) {
        if (null === $pendingMetadataStorage) {
            trigger_deprecation(
                'setono/client-bundle',
                '1.3',
                'Not passing an instance of "%s" as "$pendingMetadataStorage" to "%s()" is deprecated, the argument will be required in 2.0.',
                PendingMetadataStorageInterface::class,
                __METHOD__,
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Runs after listeners with the default priority, so the metadata they set on a new client is included
            // in its pending metadata, and before the profiler (-100) collects the response
            KernelEvents::RESPONSE => ['store', -64],
        ];
    }

    public function store(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $preStoreEvent = new PreStoreCookieEvent($request, $response);
        $this->eventDispatcher->dispatch($preStoreEvent);

        $cookie = $this->cookieProvider->getCookie($request);

        if (!$preStoreEvent->store) {
            // A new client that doesn't get a cookie can't be recognized again, so its metadata would never be read
            if (null === $cookie) {
                self::markClean($this->clientContext->getClient()->metadata);
            }

            return;
        }

        // Pending metadata sent by the client is handed to the client when it's resolved, at the latest when its metadata
        // is persisted on kernel.finish_request (see PendingMetadataClientContext), so the client can forget it
        $this->pendingMetadataStorage?->remove($request, $response);

        if (null === $cookie) {
            $client = $this->clientContext->getClient();
            $cookie = new Cookie($client->id);

            $this->storePendingMetadata($request, $response, $client);
        }

        $response->headers->setCookie(
            HttpCookie::create($this->cookieName, $cookie->withLastSeenAt(time())->toString(), $this->cookieExpiration)
                ->withHttpOnly(false), // this allows javascript to read the cookie
        );
    }

    /**
     * The metadata of a new client is not persisted until the client comes back with its cookie
     */
    private function storePendingMetadata(Request $request, Response $response, Client $client): void
    {
        $metadata = $client->metadata;
        if (null === $this->pendingMetadataStorage || !$metadata instanceof ChangeAwareMetadata || !$metadata->isDirty()) {
            return;
        }

        $data = $metadata->toArray();

        // If the metadata can't be stored as pending metadata, it stays dirty and is persisted right away
        if ([] === $data || $this->pendingMetadataStorage->store($request, $response, $client->id, $data)) {
            $metadata->markClean();
        }
    }

    private static function markClean(Metadata $metadata): void
    {
        if ($metadata instanceof ChangeAwareMetadata) {
            $metadata->markClean();
        }
    }
}
