<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Client;

use Symfony\Component\VarExporter\LazyGhostTrait;

class LazyChangeAwareMetadata extends ChangeAwareMetadata
{
    use LazyGhostTrait;
}
