<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;

final class CookieBasedClientContext implements ClientContextInterface
{
    public function __construct(
        private readonly ClientContextInterface $decorated,
        private readonly MetadataProviderInterface $metadataProvider,
        private readonly CookieProviderInterface $clientCookieProvider,
    ) {
    }

    public function getClient(): Client
    {
        $cookie = $this->clientCookieProvider->getCookie();
        if (null === $cookie) {
            return $this->decorated->getClient();
        }

        return new Client($cookie->clientId, $this->metadataProvider->getMetadata($cookie->clientId));
    }
}
