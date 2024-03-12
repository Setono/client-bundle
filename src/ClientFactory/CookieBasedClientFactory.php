<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;
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

        return new Client($clientId, $this->metadataProvider->getMetadata($clientId));
    }
}
