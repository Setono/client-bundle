<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;

interface ClientContextInterface
{
    public function getClient(): Client;
}
