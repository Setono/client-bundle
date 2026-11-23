<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\MetadataStore\MetadataStoreInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Recognizes the client by the client cookie of the main request. A request without a valid client cookie gets a new
 * client. Without a request, every call gets an anonymous client. CachedClientContext gives a request one client
 */
final class CookieBasedClientContext implements ClientContextInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClientCookieJar $cookieJar,
        private readonly MetadataStoreInterface $metadataStore,
    ) {
    }

    public function getClient(): Client
    {
        $request = $this->requestStack->getMainRequest();
        $cookie = null === $request ? null : $this->cookieJar->read($request);

        if (null === $cookie) {
            return new Client();
        }

        return new Client($cookie->clientId, $this->metadataStore->load($cookie->clientId));
    }
}
