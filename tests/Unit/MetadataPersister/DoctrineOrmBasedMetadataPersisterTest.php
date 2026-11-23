<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\MetadataPersister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\Client\Client;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyMetadata;
use Setono\ClientBundle\Entity\Metadata as MetadataEntity;
use Setono\ClientBundle\MetadataPersister\DoctrineOrmBasedMetadataPersister;

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

        $metadata = self::createLazyMetadata(['foo' => 'bar']);

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertFalse($metadata->isLazyObjectInitialized());
    }

    /**
     * @test
     */
    public function it_does_nothing_when_the_lazy_metadata_was_only_read(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->shouldNotBeCalled();
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldNotBeCalled();

        $metadata = self::createLazyMetadata(['foo' => 'bar']);
        self::assertSame('bar', $metadata->get('foo'));

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));
    }

    /**
     * @test
     */
    public function it_updates_the_entity_when_the_lazy_metadata_was_changed(): void
    {
        $entity = new MetadataEntity();
        $entity->setClientId('client-id');
        $entity->setMetadata(['foo' => 'bar']);

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(MetadataEntity::class, 'client-id')->willReturn($entity);
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldBeCalledOnce();

        $metadata = self::createLazyMetadata(['foo' => 'bar']);
        $metadata->set('baz', 'qux');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertSame(['foo' => 'bar', 'baz' => 'qux'], $entity->getMetadata());
    }

    /**
     * @test
     */
    public function it_does_nothing_when_the_metadata_has_not_changed(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->shouldNotBeCalled();
        $manager->persist(Argument::cetera())->shouldNotBeCalled();
        $manager->flush()->shouldNotBeCalled();

        // Plain metadata, like a custom metadata provider may return
        $metadata = new Metadata(['foo' => 'bar']);
        self::assertSame('bar', $metadata->get('foo'));

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertFalse($metadata->hasChanged());
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

        $metadata = new Metadata();
        $metadata->set('foo', 'bar');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertInstanceOf(MetadataEntity::class, $persistedEntity);
        self::assertSame('client-id', $persistedEntity->getClientId());
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

        $metadata = new Metadata();
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

        $metadata = new Metadata();
        $metadata->set('foo', 'bar');

        $this->createPersister($this->createManagerRegistry($manager))->persist(new Client('client-id', $metadata));

        self::assertTrue($metadata->hasChanged());
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
     * Returns lazy metadata like the one DoctrineOrmBasedMetadataProvider returns, holding the given data once initialized
     *
     * @param array<string, mixed> $data
     */
    private static function createLazyMetadata(array $data): LazyMetadata
    {
        return LazyMetadata::createLazyGhost(static function (LazyMetadata $instance) use ($data): void {
            $instance->__construct($data);
        });
    }

    /**
     * @param ObjectProphecy<ManagerRegistry> $managerRegistry
     */
    private function createPersister(ObjectProphecy $managerRegistry): DoctrineOrmBasedMetadataPersister
    {
        return new DoctrineOrmBasedMetadataPersister($managerRegistry->reveal(), MetadataEntity::class);
    }
}
