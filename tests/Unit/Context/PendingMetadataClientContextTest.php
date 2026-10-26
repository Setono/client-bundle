<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Context\PendingMetadataClientContext;
use Setono\ClientBundle\PendingMetadataStorage\PendingMetadataStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PendingMetadataClientContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_the_decorated_client_when_there_is_no_main_request(): void
    {
        $decoratedClient = new Client('client-id');

        $storage = $this->prophesize(PendingMetadataStorageInterface::class);
        $storage->get(Argument::cetera())->shouldNotBeCalled();

        $context = $this->createContext($decoratedClient, $storage->reveal(), new RequestStack());

        self::assertSame($decoratedClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_returns_the_decorated_client_when_there_is_no_pending_metadata(): void
    {
        $decoratedClient = new Client('client-id');
        $request = new Request();

        $storage = $this->prophesize(PendingMetadataStorageInterface::class);
        $storage->get($request, 'client-id')->willReturn(null);

        $context = $this->createContext($decoratedClient, $storage->reveal(), self::createRequestStack($request));

        self::assertSame($decoratedClient, $context->getClient());
    }

    /**
     * @test
     */
    public function it_hands_the_pending_metadata_to_a_client_without_metadata(): void
    {
        $request = new Request();
        $pendingMetadata = ['foo' => 'bar', '__expires' => ['foo' => 1893456000]];

        $storage = $this->prophesize(PendingMetadataStorageInterface::class);
        $storage->get($request, 'client-id')->willReturn($pendingMetadata);

        $context = $this->createContext(
            new Client('client-id', new ChangeAwareMetadata()),
            $storage->reveal(),
            self::createRequestStack($request),
        );

        $client = $context->getClient();

        self::assertSame('client-id', $client->id);
        self::assertInstanceOf(ChangeAwareMetadata::class, $client->metadata);
        self::assertTrue($client->metadata->isDirty());
        self::assertSame($pendingMetadata, $client->metadata->toArray());
    }

    /**
     * @test
     */
    public function it_ignores_the_pending_metadata_when_the_client_has_metadata(): void
    {
        $decoratedClient = new Client('client-id', new Metadata(['foo' => 'persisted']));
        $request = new Request();

        $storage = $this->prophesize(PendingMetadataStorageInterface::class);
        $storage->get($request, 'client-id')->willReturn(['foo' => 'pending']);

        $context = $this->createContext($decoratedClient, $storage->reveal(), self::createRequestStack($request));

        self::assertSame($decoratedClient, $context->getClient());
        self::assertSame(['foo' => 'persisted'], $decoratedClient->metadata->toArray());
    }

    private function createContext(
        Client $decoratedClient,
        PendingMetadataStorageInterface $storage,
        RequestStack $requestStack,
    ): PendingMetadataClientContext {
        $decorated = $this->prophesize(ClientContextInterface::class);
        $decorated->getClient()->willReturn($decoratedClient);

        return new PendingMetadataClientContext($decorated->reveal(), $storage, $requestStack);
    }

    private static function createRequestStack(Request $request): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }
}
