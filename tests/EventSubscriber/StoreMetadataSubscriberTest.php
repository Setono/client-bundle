<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\EventSubscriber\StoreMetadataSubscriber;
use Setono\ClientBundle\MetadataPersister\MetadataPersisterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreMetadataSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_subscribes_to_the_finish_request_event(): void
    {
        self::assertSame([KernelEvents::FINISH_REQUEST => 'store'], StoreMetadataSubscriber::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_persists_the_metadata_of_the_current_client(): void
    {
        $client = new Client('client-id');

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn($client);

        $persister = $this->prophesize(MetadataPersisterInterface::class);
        $persister->persist($client)->shouldBeCalledOnce();

        $subscriber = new StoreMetadataSubscriber($clientContext->reveal(), $persister->reveal());
        $subscriber->store($this->createFinishRequestEvent(HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @test
     */
    public function it_does_nothing_for_sub_requests(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->shouldNotBeCalled();

        $persister = $this->prophesize(MetadataPersisterInterface::class);
        $persister->persist(Argument::cetera())->shouldNotBeCalled();

        $subscriber = new StoreMetadataSubscriber($clientContext->reveal(), $persister->reveal());
        $subscriber->store($this->createFinishRequestEvent(HttpKernelInterface::SUB_REQUEST));
    }

    private function createFinishRequestEvent(int $requestType): FinishRequestEvent
    {
        return new FinishRequestEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request(),
            $requestType,
        );
    }
}
