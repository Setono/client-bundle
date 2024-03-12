<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Client;

use Setono\Client\Metadata;
use Symfony\Component\VarExporter\LazyGhostTrait;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LazyMetadata extends Metadata
{
    use LazyGhostTrait;
}
