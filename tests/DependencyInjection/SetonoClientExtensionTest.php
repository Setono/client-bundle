<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\ClientBundle\DependencyInjection\SetonoClientExtension;
use Setono\ClientBundle\Entity\Metadata;

final class SetonoClientExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoClientExtension(),
        ];
    }

    /**
     * @test
     */
    public function after_loading_the_correct_parameter_has_been_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_client.cookie.name', 'setono_client_id');
        $this->assertContainerBuilderHasParameter('setono_client.cookie.expiration', '+365 days');
        $this->assertContainerBuilderHasParameter('setono_client.metadata_class', Metadata::class);
    }
}
