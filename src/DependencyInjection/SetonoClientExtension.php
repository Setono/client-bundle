<?php

declare(strict_types=1);

namespace Setono\ClientBundle\DependencyInjection;

use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\Entity\MetadataInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoClientExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{cookie: array{name: string, expiration: string}, metadata_class: class-string} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        if (!is_a($config['metadata_class'], MetadataInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The metadata class must implement %s',
                MetadataInterface::class,
            ));
        }

        // Doctrine only knows the class by its declared name, e.g. without a leading backslash
        $metadataClass = (new \ReflectionClass($config['metadata_class']))->getName();

        $container->setParameter('setono_client.cookie.name', $config['cookie']['name']);
        $container->setParameter('setono_client.cookie.expiration', $config['cookie']['expiration']);
        $container->setParameter('setono_client.metadata_class', $metadataClass);

        $loader->load('services.xml');

        // An application entity that extends the bundle's Metadata inherits its fields, but only while the bundle's class
        // stays a mapped superclass. Doctrine would otherwise see two entities without an inheritance mapping
        if (is_subclass_of($metadataClass, Metadata::class)) {
            $container->removeDefinition('setono_client.event_listener_doctrine.convert_to_entity');
        }
    }
}
