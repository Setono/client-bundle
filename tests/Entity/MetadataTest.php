<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\Entity\Metadata;

final class MetadataTest extends TestCase
{
    /**
     * @test
     */
    public function it_handles_the_client_id(): void
    {
        $metadata = new Metadata();
        self::assertNull($metadata->getClientId());

        $metadata->setClientId('client-id');
        self::assertSame('client-id', $metadata->getClientId());
    }

    /**
     * @test
     */
    public function it_returns_an_empty_array_by_default(): void
    {
        self::assertSame([], (new Metadata())->getMetadata());
    }

    /**
     * @test
     */
    public function it_stores_metadata(): void
    {
        $metadata = new Metadata();
        $metadata->setMetadata(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $metadata->getMetadata());
    }

    /**
     * @test
     */
    public function it_normalizes_empty_metadata_to_an_empty_array(): void
    {
        $metadata = new Metadata();
        $metadata->setMetadata(['foo' => 'bar']);
        $metadata->setMetadata([]);

        self::assertSame([], $metadata->getMetadata());
    }
}
