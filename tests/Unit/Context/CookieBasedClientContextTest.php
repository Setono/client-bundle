<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\Client\Cookie;
use Setono\Client\Metadata;
use Setono\ClientBundle\Context\CookieBasedClientContext;
use Setono\ClientBundle\Cookie\ClientCookieJar;
use Setono\ClientBundle\MetadataStore\MetadataStoreInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class CookieBasedClientContextTest extends TestCase
{
    use ProphecyTrait;

    private const COOKIE_NAME = 'setono_client_id';

    /**
     * @test
     */
    public function it_recognizes_the_client_by_its_client_cookie(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);

        $metadataStore = $this->prophesize(MetadataStoreInterface::class);
        $metadataStore->load('client-id')->willReturn($metadata);

        $client = $this->createContext($metadataStore, self::createRequest('client-id'))->getClient();

        self::assertSame('client-id', $client->id);
        self::assertSame($metadata, $client->metadata);
    }

    /**
     * @test
     */
    public function it_recognizes_the_client_by_the_client_cookie_of_the_main_request(): void
    {
        $metadataStore = $this->prophesize(MetadataStoreInterface::class);
        $metadataStore->load(Argument::type('string'))->willReturn(new Metadata());

        $context = $this->createContext($metadataStore, self::createRequest('main-client-id'), self::createRequest('sub-client-id'));

        self::assertSame('main-client-id', $context->getClient()->id);
    }

    /**
     * @test
     */
    public function it_gives_a_request_without_a_client_cookie_a_new_client(): void
    {
        $metadataStore = $this->prophesize(MetadataStoreInterface::class);
        $metadataStore->load(Argument::any())->shouldNotBeCalled();

        $client = $this->createContext($metadataStore, new Request())->getClient();

        self::assertTrue(Uuid::isValid($client->id));
        self::assertFalse($client->metadata->hasChanged());
        self::assertCount(0, $client->metadata);
    }

    /**
     * @test
     */
    public function it_gives_a_request_with_an_invalid_client_cookie_a_new_client(): void
    {
        $metadataStore = $this->prophesize(MetadataStoreInterface::class);
        $metadataStore->load(Argument::any())->shouldNotBeCalled();

        $client = $this->createContext($metadataStore, new Request([], [], [], [self::COOKIE_NAME => '1.2.3']))->getClient();

        self::assertTrue(Uuid::isValid($client->id));
    }

    /**
     * @test
     */
    public function it_gives_every_call_without_a_request_an_anonymous_client(): void
    {
        $metadataStore = $this->prophesize(MetadataStoreInterface::class);
        $metadataStore->load(Argument::any())->shouldNotBeCalled();

        $context = $this->createContext($metadataStore);
        $client = $context->getClient();

        self::assertTrue(Uuid::isValid($client->id));
        self::assertCount(0, $client->metadata);
        self::assertNotSame($client->id, $context->getClient()->id);
    }

    /**
     * @param ObjectProphecy<MetadataStoreInterface> $metadataStore
     * @param Request ...$requests the requests on the request stack, the main request first
     */
    private function createContext(ObjectProphecy $metadataStore, Request ...$requests): CookieBasedClientContext
    {
        $requestStack = new RequestStack();
        foreach ($requests as $request) {
            $requestStack->push($request);
        }

        return new CookieBasedClientContext(
            $requestStack,
            new ClientCookieJar(self::COOKIE_NAME, '+365 days'),
            $metadataStore->reveal(),
        );
    }

    private static function createRequest(string $clientId): Request
    {
        return new Request([], [], [], [self::COOKIE_NAME => (new Cookie($clientId))->toString()]);
    }
}
