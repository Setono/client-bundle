<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Client;

use Setono\Client\Metadata;
use Symfony\Component\VarExporter\LazyGhostTrait;

/**
 * Metadata that DoctrineOrmBasedMetadataStore::load() returns as a lazy ghost, so the database is only queried when the
 * metadata is used
 *
 * @internal
 */
final class LazyMetadata extends Metadata
{
    use LazyGhostTrait;
}
