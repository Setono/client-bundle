<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\ClientBundle\DependencyInjection\Configuration;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\Tests\Unit\DependencyInjection\Fixture\CustomMetadata;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function it_has_default_values(): void
    {
        $this->assertProcessedConfigurationEquals([], [
            'cookie' => [
                'name' => 'setono_client_id',
                'expiration' => '+365 days',
            ],
            'metadata_class' => Metadata::class,
        ]);
    }

    /**
     * @test
     */
    public function it_allows_overriding_the_values(): void
    {
        $this->assertProcessedConfigurationEquals([
            [
                'cookie' => [
                    'name' => 'client_id',
                    'expiration' => '+30 days',
                ],
                'metadata_class' => CustomMetadata::class,
            ],
        ], [
            'cookie' => [
                'name' => 'client_id',
                'expiration' => '+30 days',
            ],
            'metadata_class' => CustomMetadata::class,
        ]);
    }

    /**
     * @test
     */
    public function it_keeps_the_default_of_a_cookie_option_that_is_not_overridden(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['cookie' => ['name' => 'client_id']],
        ], [
            'cookie' => [
                'name' => 'client_id',
                'expiration' => '+365 days',
            ],
            'metadata_class' => Metadata::class,
        ]);
    }
}
