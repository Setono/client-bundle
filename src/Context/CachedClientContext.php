<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;

final class CachedClientContext implements ClientContextInterface
{
    private ?Client $client = null;

    public function __construct(private readonly ClientContextInterface $decorated)
    {
    }

    public function getClient(): Client
    {
        if (null === $this->client) {
            $this->client = $this->decorated->getClient();
        }

        return $this->client;
    }
}
