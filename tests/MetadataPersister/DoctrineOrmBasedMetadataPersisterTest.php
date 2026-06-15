<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\MetadataPersister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\Client\Client;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
use Setono\ClientBundle\Entity\Metadata as MetadataEntity;
use Setono\ClientBundle\MetadataPersister\DoctrineOrmBasedMetadataPersister;
use Setono\ClientBundle\MetadataProvider\DoctrineOrmBasedMetadataProvider;
use Setono\ClientBundle\MetadataProvider\EmptyMetadataProvider;

final class DoctrineOrmBasedMetadataPersisterTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_does_nothing_when_the_lazy_metadata_is_not_initialized(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->shouldNotBeCalled();
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldNotBeCalled();

        $managerRegistry = $this->createManagerRegistry($manager);

        $metadata = (new DoctrineOrmBasedMetadataProvider(
            new EmptyMetadataProvider(),
            $managerRegistry->reveal(),
            MetadataEntity::class,
        ))->getMetadata('client-id');

        self::assertInstanceOf(LazyChangeAwareMetadata::class, $metadata);
        self::assertFalse($metadata->isLazyObjectInitialized());

        $this->createPersister($managerRegistry)->persist(new Client('client-id', $metadata));

        self::assertFalse($metadata->isLazyObjectInitialized());
    }

    /**
     * @test
     */
    public function it_does_nothing_when_the_metadata_is_not_dirty(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->shouldNotBeCalled();
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldNotBeCalled();

        $managerRegistry = $this->createManagerRegistry($manager);

        $metadata = new ChangeAwareMetadata();
        $this->createPersister($managerRegistry)->persist(new Client('client-id', $metadata));

        self::assertFalse($metadata->isDirty());
    }

    /**
     * @test
     */
    public function it_persists_a_new_entity(): void
    {
        $persistedEntity = null;

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'client-id')->willReturn(null);
        $manager->persist(Argument::type(MetadataEntity::class))->will(
            function (array $args) use (&$persistedEntity): void {
                $persistedEntity = $args[0];
            },
        )->shouldBeCalledOnce();
        $manager->flush()->shouldBeCalledOnce();

        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertInstanceOf(MetadataEntity::class, $persistedEntity);
        self::assertSame('client-id', $persistedEntity->getClientId());
        self::assertSame(['foo' => 'bar'], $persistedEntity->getMetadata());
    }

    /**
     * @test
     */
    public function it_persists_metadata_that_does_not_track_changes(): void
    {
        $persistedEntity = null;

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'client-id')->willReturn(null);
        $manager->persist(Argument::type(MetadataEntity::class))->will(
            function (array $args) use (&$persistedEntity): void {
                $persistedEntity = $args[0];
            },
        )->shouldBeCalledOnce();
        $manager->flush()->shouldBeCalledOnce();

        $client = new Client('client-id', new Metadata(['foo' => 'bar']));

        $this->createPersister($this->createManagerRegistry($manager))->persist($client);

        self::assertInstanceOf(MetadataEntity::class, $persistedEntity);
        self::assertSame(['foo' => 'bar'], $persistedEntity->getMetadata());
    }

    /**
     * @test
     */
    public function it_updates_an_existing_entity(): void
    {
        $existing = new MetadataEntity();
        $existing->setClientId('client-id');

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'client-id')->willReturn($existing);
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldBeCalledOnce();

        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertSame(['foo' => 'bar'], $existing->getMetadata());
    }

    /**
     * @test
     */
    public function it_swallows_unique_constraint_violations(): void
    {
        $exception = (new \ReflectionClass(UniqueConstraintViolationException::class))->newInstanceWithoutConstructor();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'client-id')->willReturn(null);
        $manager->persist(Argument::type(MetadataEntity::class))->shouldBeCalledOnce();
        $manager->flush()->willThrow($exception);

        $metadata = new ChangeAwareMetadata();
        $metadata->set('foo', 'bar');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertTrue($metadata->isDirty());
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
     */
    private function createPersister(ObjectProphecy $managerRegistry): DoctrineOrmBasedMetadataPersister
    {
        return new DoctrineOrmBasedMetadataPersister($managerRegistry->reveal(), MetadataEntity::class);
    }
}
