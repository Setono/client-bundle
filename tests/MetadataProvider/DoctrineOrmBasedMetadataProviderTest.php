<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\MetadataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyMetadata;
use Setono\ClientBundle\Entity\Metadata as MetadataEntity;
use Setono\ClientBundle\MetadataProvider\DoctrineOrmBasedMetadataProvider;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;

final class DoctrineOrmBasedMetadataProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_lazy_metadata(): void
    {
        $manager = $this->prophesize(ObjectManager::class);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager);

        $decorated = $this->prophesize(MetadataProviderInterface::class);

        $provider = new DoctrineOrmBasedMetadataProvider(
            $decorated->reveal(),
            $managerRegistry->reveal(),
            MetadataEntity::class,
        );

        $metadata = $provider->getMetadata('some-client-id');

        self::assertInstanceOf(LazyMetadata::class, $metadata);
    }

    /**
     * @test
     */
    public function it_initializes_metadata_when_accessed(): void
    {
        $manager = $this->prophesize(ObjectManager::class);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager);

        $decorated = $this->prophesize(MetadataProviderInterface::class);
        $decorated->getMetadata('some-client-id')->willReturn(new Metadata());

        $provider = new DoctrineOrmBasedMetadataProvider(
            $decorated->reveal(),
            $managerRegistry->reveal(),
            MetadataEntity::class,
        );

        $metadata = $provider->getMetadata('some-client-id');
        $metadata->set('some-key', 'some-value');

        self::assertInstanceOf(LazyMetadata::class, $metadata);
        self::assertSame('some-value', $metadata->get('some-key'));
    }
}
