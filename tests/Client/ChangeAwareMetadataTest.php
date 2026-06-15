<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Client;

use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\Client\ChangeAwareMetadata;

final class ChangeAwareMetadataTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_not_dirty_initially(): void
    {
        self::assertFalse((new ChangeAwareMetadata())->isDirty());
        self::assertFalse((new ChangeAwareMetadata(['foo' => 'bar']))->isDirty());
    }

    /**
     * @test
     */
    public function it_becomes_dirty_when_setting_a_value(): void
    {
        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        self::assertTrue($metadata->isDirty());
        self::assertSame('bar', $metadata->get('foo'));
    }

    /**
     * @test
     */
    public function it_becomes_dirty_when_removing_a_value(): void
    {
        $metadata = new ChangeAwareMetadata(['foo' => 'bar']);
        self::assertFalse($metadata->isDirty());

        $metadata->remove('foo');

        self::assertTrue($metadata->isDirty());
        self::assertFalse($metadata->has('foo'));
    }
}
