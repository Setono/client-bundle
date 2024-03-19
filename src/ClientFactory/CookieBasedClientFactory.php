<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;

final class CookieBasedClientFactory implements ClientFactoryInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $decorated,
        private readonly MetadataProviderInterface $metadataProvider,
        private readonly CookieProviderInterface $clientCookieProvider,
    ) {
    }

    public function create(): Client
    {
        $cookie = $this->clientCookieProvider->getClientCookie();
        if (null === $cookie) {
            return $this->decorated->create();
        }

        $metadata = $this->metadataProvider->getMetadata($cookie->clientId);

        return new Client($cookie->clientId, $metadata);
    }
}
