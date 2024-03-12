<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;

final class DefaultClientFactory implements ClientFactoryInterface
{
    public function create(): Client
    {
        return new Client();
    }
}
