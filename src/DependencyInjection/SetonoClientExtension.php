<?php

declare(strict_types=1);

namespace Setono\ClientBundle\DependencyInjection;

use Setono\ClientBundle\Entity\ClientInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoClientExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{cookie: array{name: string, expiration: string}, client_class: class-string} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        if (!is_a($config['client_class'], ClientInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The metadata class must implement %s',
                ClientInterface::class,
            ));
        }

        $container->setParameter('setono_client.cookie.name', $config['cookie']['name']);
        $container->setParameter('setono_client.cookie.expiration', $config['cookie']['expiration']);
        $container->setParameter('setono_client.client_class', $config['client_class']);

        $loader->load('services.xml');
    }
}
