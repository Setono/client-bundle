<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;
use Setono\ClientBundle\Cookie\ClientIdCookie;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieBasedClientFactory implements ClientFactoryInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $decorated,
        private readonly RequestStack $requestStack,
        private readonly MetadataProviderInterface $metadataProvider,
        private readonly string $cookieName,
    ) {
    }

    public function create(): Client
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return $this->decorated->create();
        }

        $clientId = $request->cookies->get($this->cookieName);
        if (!is_string($clientId) || '' === $clientId) {
            return $this->decorated->create();
        }

        try {
            $cookie = ClientIdCookie::fromString($clientId);
        } catch (\InvalidArgumentException) {
            return $this->decorated->create();
        }

        $metadata = $this->metadataProvider->getMetadata($cookie->clientId);
        $metadata->set('created_at', $cookie->createdAt);
        $metadata->set('updated_at', $cookie->updatedAt);

        return new Client($clientId, $metadata);
    }
}
