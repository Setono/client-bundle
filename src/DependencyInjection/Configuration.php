<?php

declare(strict_types=1);

namespace Setono\ClientBundle\DependencyInjection;

use Setono\ClientBundle\Entity\Client;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_client');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress MixedMethodCall, UndefinedInterfaceMethod, PossiblyNullReference */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('cookie')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('name')
                        ->defaultValue('setono_client_id')
                        ->info('The name of the cookie that holds the client id. NOTICE that if you change this value, all clients with a cookie with the old name will be considered new clients')
                    ->end()
                    ->scalarNode('expiration')
                        ->defaultValue('+365 days')
                        ->info('The expiration of the cookie. This is a string that can be parsed by strtotime')
                    ->end()
                ->end()
            ->end()
            ->scalarNode('client_class')
                ->defaultValue(Client::class)
        ;

        return $treeBuilder;
    }
}
