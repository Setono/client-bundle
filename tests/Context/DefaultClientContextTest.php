<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Context;

use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\DefaultClientContext;

final class DefaultClientContextTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_a_client_with_a_generated_id_and_change_aware_metadata(): void
    {
        $client = (new DefaultClientContext())->getClient();
        $metadata = $client->metadata;

        self::assertNotSame('', $client->id);
        self::assertInstanceOf(ChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isDirty());
    }
}
