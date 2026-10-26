<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Context\DefaultClientContext;
use Setono\ClientBundle\CookieProvider\RequestBasedCookieProvider;
use Setono\ClientBundle\DependencyInjection\SetonoClientExtension;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\EventListener\Doctrine\ConvertToEntityListener;
use Setono\ClientBundle\Tests\Unit\DependencyInjection\Fixture\CustomMetadata;
use Setono\ClientBundle\Tests\Unit\DependencyInjection\Fixture\InterfaceOnlyMetadata;

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
    public function after_loading_with_custom_values_the_parameters_reflect_them(): void
    {
        $this->load([
            'cookie' => [
                'name' => 'client_id',
                'expiration' => '+30 days',
            ],
            'metadata_class' => CustomMetadata::class,
        ]);

        $this->assertContainerBuilderHasParameter('setono_client.cookie.name', 'client_id');
        $this->assertContainerBuilderHasParameter('setono_client.cookie.expiration', '+30 days');
        $this->assertContainerBuilderHasParameter('setono_client.metadata_class', CustomMetadata::class);
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
    public function after_loading_the_cached_client_context_is_reset_between_requests(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_client.client_context.cached', 'kernel.reset', ['method' => 'reset']);
    }

    /**
     * @test
     */
    public function after_loading_the_services_hooking_into_symfony_and_doctrine_are_tagged(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_client.event_subscriber.store_cookie', 'kernel.event_subscriber');
        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_client.event_subscriber.store_metadata', 'kernel.event_subscriber');
        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_client.controller.client_value_resolver', 'controller.argument_value_resolver');
        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_client.event_listener_doctrine.convert_to_entity', 'doctrine.event_listener', [
            'event' => 'loadClassMetadata',
        ]);
    }

    /**
     * @test
     */
    public function after_loading_with_a_metadata_class_that_extends_the_bundles_class_the_doctrine_listener_is_removed(): void
    {
        $this->load(['metadata_class' => CustomMetadata::class]);

        $this->assertContainerBuilderNotHasService('setono_client.event_listener_doctrine.convert_to_entity');
    }

    /**
     * @test
     */
    public function after_loading_with_a_metadata_class_that_only_implements_the_interface_the_doctrine_listener_is_kept(): void
    {
        $this->load(['metadata_class' => InterfaceOnlyMetadata::class]);

        $this->assertContainerBuilderHasService('setono_client.event_listener_doctrine.convert_to_entity', ConvertToEntityListener::class);
    }

    /**
     * @test
     */
    public function after_loading_the_metadata_class_parameter_holds_the_declared_class_name(): void
    {
        $this->load(['metadata_class' => '\\' . CustomMetadata::class]);

        $this->assertContainerBuilderHasParameter('setono_client.metadata_class', CustomMetadata::class);
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
