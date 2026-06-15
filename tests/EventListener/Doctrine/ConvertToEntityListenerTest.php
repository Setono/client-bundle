<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\ClientBundle\Entity\Metadata;
use Setono\ClientBundle\EventListener\Doctrine\ConvertToEntityListener;

final class ConvertToEntityListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_turns_the_metadata_mapped_superclass_into_an_entity(): void
    {
        $classMetadata = new ClassMetadata(Metadata::class);
        $classMetadata->isMappedSuperclass = true;

        (new ConvertToEntityListener())->loadClassMetadata($this->createEventArgs($classMetadata));

        self::assertFalse($classMetadata->isMappedSuperclass);
    }

    /**
     * @test
     */
    public function it_leaves_other_classes_untouched(): void
    {
        $classMetadata = new ClassMetadata(\stdClass::class);
        $classMetadata->isMappedSuperclass = true;

        (new ConvertToEntityListener())->loadClassMetadata($this->createEventArgs($classMetadata));

        self::assertTrue($classMetadata->isMappedSuperclass);
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     *
     * @return LoadClassMetadataEventArgs<ClassMetadata<object>, EntityManagerInterface>
     */
    private function createEventArgs(ClassMetadata $classMetadata): LoadClassMetadataEventArgs
    {
        return new LoadClassMetadataEventArgs($classMetadata, $this->prophesize(EntityManagerInterface::class)->reveal());
    }
}
