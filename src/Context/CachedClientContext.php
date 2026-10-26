<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Symfony\Contracts\Service\ResetInterface;

final class CachedClientContext implements ClientContextInterface, ResetInterface
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

    public function reset(): void
    {
        $this->client = null;
    }
}
