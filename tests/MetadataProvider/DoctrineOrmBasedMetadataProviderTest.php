<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\MetadataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
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
        $manager = $this->prophesize(EntityManagerInterface::class);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager);

        $decorated = $this->prophesize(MetadataProviderInterface::class);

        $provider = new DoctrineOrmBasedMetadataProvider(
            $decorated->reveal(),
            $managerRegistry->reveal(),
            MetadataEntity::class,
        );

        $metadata = $provider->getMetadata('some-client-id');

        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isLazyObjectInitialized());
    }

    /**
     * @test
     */
    public function it_initializes_metadata_when_accessed(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);

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

        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertTrue($metadata->isLazyObjectInitialized());
        self::assertTrue($metadata->isDirty());
        self::assertSame('some-value', $metadata->get('some-key'));
    }

    /**
     * @test
     */
    public function it_loads_the_metadata_from_an_existing_entity(): void
    {
        $entity = new MetadataEntity();
        $entity->setClientId('some-client-id');
        $entity->setMetadata(['foo' => 'bar']);

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'some-client-id')->willReturn($entity);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager);

        $decorated = $this->prophesize(MetadataProviderInterface::class);
        $decorated->getMetadata(Argument::any())->shouldNotBeCalled();

        $provider = new DoctrineOrmBasedMetadataProvider(
            $decorated->reveal(),
            $managerRegistry->reveal(),
            MetadataEntity::class,
        );

        $metadata = $provider->getMetadata('some-client-id');

        self::assertSame('bar', $metadata->get('foo'));
    }
}
