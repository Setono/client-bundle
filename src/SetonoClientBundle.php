<?php

declare(strict_types=1);

namespace Setono\ClientBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SetonoClientBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Registers two mappings of the bundle's Metadata, each enabled by a parameter. SetonoClientExtension sets one of them
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(DoctrineOrmMappingsPass::createXmlMappingDriver(
            [__DIR__ . '/../config/doctrine-mapping/entity' => 'Setono\ClientBundle\Entity'],
            [],
            'setono_client.doctrine_mapping.entity',
        ));

        $container->addCompilerPass(DoctrineOrmMappingsPass::createXmlMappingDriver(
            [__DIR__ . '/../config/doctrine-mapping/mapped-superclass' => 'Setono\ClientBundle\Entity'],
            [],
            'setono_client.doctrine_mapping.mapped_superclass',
        ));
    }
}
