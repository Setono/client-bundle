<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Client;

use Symfony\Component\VarExporter\LazyGhostTrait;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LazyChangeAwareMetadata extends ChangeAwareMetadata
{
    use LazyGhostTrait;
}
