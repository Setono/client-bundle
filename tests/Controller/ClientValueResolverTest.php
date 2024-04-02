<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Controller\ClientValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ClientValueResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_resolves(): void
    {
        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client('id'));

        $resolver = new ClientValueResolver($clientContext->reveal());
        $resolved = $resolver->resolve(new Request(), new ArgumentMetadata('foo', Client::class, false, false, null));

        self::assertCount(1, $resolved);
        self::assertInstanceOf(Client::class, $resolved[0]);
    }
}
