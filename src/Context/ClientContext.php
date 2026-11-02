<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Recognizes the client by the client cookie of the main request. A request without a usable client cookie gets a new
 * client, and so does every call without a request (an anonymous client). CachedClientContext makes sure a request gets one client
 */
final class ClientContext implements ClientContextInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClientCookieJar $cookieJar,
        private readonly MetadataProviderInterface $metadataProvider,
    ) {
    }

    public function getClient(): Client
    {
        $request = $this->requestStack->getMainRequest();
        $cookie = null === $request ? null : $this->cookieJar->read($request);

        if (null === $cookie) {
            return new Client(null, new ChangeAwareMetadata());
        }

        return new Client($cookie->clientId, $this->metadataProvider->getMetadata($cookie->clientId));
    }
}
