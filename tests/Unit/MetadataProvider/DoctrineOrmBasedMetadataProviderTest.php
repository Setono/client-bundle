<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\MetadataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
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
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::cetera())->shouldNotBeCalled();

        $decorated = $this->prophesize(MetadataProviderInterface::class);

        $metadata = $this->createProvider($managerRegistry, $decorated)->getMetadata('some-client-id');

        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isLazyObjectInitialized());
    }

    /**
     * @test
     */
    public function it_initializes_metadata_when_accessed(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);

        $decorated = $this->prophesize(MetadataProviderInterface::class);
        $decorated->getMetadata('some-client-id')->willReturn(new Metadata());

        $metadata = $this->createProvider($this->createManagerRegistry($manager), $decorated)->getMetadata('some-client-id');
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

        $decorated = $this->prophesize(MetadataProviderInterface::class);
        $decorated->getMetadata(Argument::any())->shouldNotBeCalled();

        $metadata = $this->createProvider($this->createManagerRegistry($manager), $decorated)->getMetadata('some-client-id');

        self::assertSame('bar', $metadata->get('foo'));
        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_decorated_provider_when_the_client_has_no_entity(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'some-client-id')->willReturn(null);

        $decorated = $this->prophesize(MetadataProviderInterface::class);
        $decorated->getMetadata('some-client-id')->willReturn(new Metadata(['foo' => 'bar']));

        $metadata = $this->createProvider($this->createManagerRegistry($manager), $decorated)->getMetadata('some-client-id');

        self::assertSame('bar', $metadata->get('foo'));
        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isDirty());
    }

    /**
     * @param ObjectProphecy<EntityManagerInterface> $manager
     *
     * @return ObjectProphecy<ManagerRegistry>
     */
    private function createManagerRegistry(ObjectProphecy $manager): ObjectProphecy
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(MetadataEntity::class)->willReturn($manager);

        return $managerRegistry;
    }

    /**
     * @param ObjectProphecy<ManagerRegistry> $managerRegistry
     * @param ObjectProphecy<MetadataProviderInterface> $decorated
     */
    private function createProvider(ObjectProphecy $managerRegistry, ObjectProphecy $decorated): DoctrineOrmBasedMetadataProvider
    {
        return new DoctrineOrmBasedMetadataProvider($decorated->reveal(), $managerRegistry->reveal(), MetadataEntity::class);
    }
}
