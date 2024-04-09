<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;

final class DefaultClientContext implements ClientContextInterface
{
    public function getClient(): Client
    {
        return new Client(null, new ChangeAwareMetadata());
    }
}
