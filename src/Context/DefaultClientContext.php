<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;

final class DefaultClientContext implements ClientContextInterface
{
    public function getClient(): Client
    {
        return new Client();
    }
}
