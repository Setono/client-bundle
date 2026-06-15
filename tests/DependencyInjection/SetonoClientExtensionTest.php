<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Context\DefaultClientContext;
use Setono\ClientBundle\CookieProvider\RequestBasedCookieProvider;
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

    /**
     * @test
     */
    public function after_loading_the_services_are_registered(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService('setono_client.client_context.default', DefaultClientContext::class);
        $this->assertContainerBuilderHasService('setono_client.cookie_provider.default', RequestBasedCookieProvider::class);
        $this->assertContainerBuilderHasAlias(ClientContextInterface::class, 'setono_client.client_context.default');
    }

    /**
     * @test
     */
    public function it_throws_when_the_metadata_class_does_not_implement_the_interface(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->load(['metadata_class' => \stdClass::class]);
    }
}
