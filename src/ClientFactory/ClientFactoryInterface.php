<?php

declare(strict_types=1);

namespace Setono\ClientBundle\ClientFactory;

use Setono\Client\Client;

interface ClientFactoryInterface
{
    public function create(): Client;
}
