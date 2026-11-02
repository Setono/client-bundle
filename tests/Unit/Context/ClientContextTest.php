<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\Client\Cookie;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContext;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\MetadataProvider\MetadataProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class ClientContextTest extends TestCase
{
    use ProphecyTrait;

    private const COOKIE_NAME = 'setono_client_id';

    /**
     * @test
     */
    public function it_recognizes_the_client_by_its_client_cookie(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);

        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata('client-id')->willReturn($metadata);

        $client = $this->createContext(self::createRequest('client-id'), $metadataProvider)->getClient();

        self::assertSame('client-id', $client->id);
        self::assertSame($metadata, $client->metadata);
    }

    /**
     * @test
     */
    public function it_recognizes_the_client_by_the_client_cookie_of_the_main_request(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(self::createRequest('main-client-id'));
        $requestStack->push(self::createRequest('sub-client-id'));

        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata(Argument::type('string'))->willReturn(new Metadata());

        $context = new ClientContext($requestStack, new ClientCookieJar(self::COOKIE_NAME, '+365 days'), $metadataProvider->reveal());

        self::assertSame('main-client-id', $context->getClient()->id);
    }

    /**
     * @test
     */
    public function it_gives_a_request_without_a_client_cookie_a_new_client(): void
    {
        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata(Argument::any())->shouldNotBeCalled();

        $client = $this->createContext(new Request(), $metadataProvider)->getClient();

        self::assertTrue(Uuid::isValid($client->id));
        self::assertInstanceOf(ChangeAwareMetadata::class, $client->metadata);
        self::assertCount(0, $client->metadata);
    }

    /**
     * @test
     */
    public function it_gives_a_request_with_an_unusable_client_cookie_a_new_client(): void
    {
        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata(Argument::any())->shouldNotBeCalled();

        $client = $this->createContext(new Request([], [], [], [self::COOKIE_NAME => '1.2.3']), $metadataProvider)->getClient();

        self::assertTrue(Uuid::isValid($client->id));
    }

    /**
     * @test
     */
    public function it_gives_every_call_without_a_request_an_anonymous_client(): void
    {
        $metadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $metadataProvider->getMetadata(Argument::any())->shouldNotBeCalled();

        $context = $this->createContext(null, $metadataProvider);
        $client = $context->getClient();

        self::assertTrue(Uuid::isValid($client->id));
        self::assertInstanceOf(ChangeAwareMetadata::class, $client->metadata);
        self::assertNotSame($client->id, $context->getClient()->id);
    }

    /**
     * @param ObjectProphecy<MetadataProviderInterface> $metadataProvider
     */
    private function createContext(?Request $request, ObjectProphecy $metadataProvider): ClientContext
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new ClientContext($requestStack, new ClientCookieJar(self::COOKIE_NAME, '+365 days'), $metadataProvider->reveal());
    }

    private static function createRequest(string $clientId): Request
    {
        return new Request([], [], [], [self::COOKIE_NAME => (new Cookie($clientId))->toString()]);
    }
}
