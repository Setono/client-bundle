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

        // SetonoClientBundle registers two mappings of the bundle's Metadata, and Doctrine uses the one whose parameter exists.
        // It's an entity only when it's the metadata class. Otherwise it's a mapped superclass: an application entity that
        // extends it inherits its fields, and one that doesn't leaves the bundle's table out of the schema
        $container->setParameter(
            Metadata::class === $metadataClass ? 'setono_client.doctrine_mapping.entity' : 'setono_client.doctrine_mapping.mapped_superclass',
            true,
        );

        $loader->load('services.xml');
    }
}
