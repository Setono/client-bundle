<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;

final class CachedClientFactory implements ClientFactoryInterface
{
    private ?Client $client = null;

    public function __construct(private readonly ClientFactoryInterface $decorated)
    {
    }

    public function create(): Client
    {
        if (null === $this->client) {
            $this->client = $this->decorated->create();
        }

        return $this->client;
    }
}
