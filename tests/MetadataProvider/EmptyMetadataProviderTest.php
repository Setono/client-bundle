<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\MetadataProvider;

use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\MetadataProvider\EmptyMetadataProvider;

final class EmptyMetadataProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_empty_metadata(): void
    {
        $metadata = (new EmptyMetadataProvider())->getMetadata('client-id');

        self::assertCount(0, $metadata);
        self::assertFalse($metadata->isDirty());
    }
}
