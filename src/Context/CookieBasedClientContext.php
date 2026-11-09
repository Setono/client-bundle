<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;
use Setono\ClientBundle\Request\MainRequestProvider;

/**
 * Recognizes the client by the client cookie of the main request. A request without a valid client cookie gets a new
 * client. Without a request, every call gets an anonymous client. CachedClientContext gives a request one client
 */
final class CookieBasedClientContext implements ClientContextInterface
{
    public function __construct(
        private readonly MainRequestProvider $mainRequestProvider,
        private readonly ClientCookieJar $cookieJar,
        private readonly MetadataProviderInterface $metadataProvider,
    ) {
    }

    public function getClient(): Client
    {
        $request = $this->mainRequestProvider->getMainRequest();
        $cookie = null === $request ? null : $this->cookieJar->read($request);

        if (null === $cookie) {
            return new Client(null, new ChangeAwareMetadata());
        }

        return new Client($cookie->clientId, $this->metadataProvider->getMetadata($cookie->clientId));
    }
}
