<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Outside a request, e.g. in a console command or a worker, there is nothing to recognize a client by
 */
final class AnonymousClientTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_gives_every_call_outside_a_request_an_anonymous_client(): void
    {
        $clientContext = self::getContainer()->get(ClientContextInterface::class);
        self::assertInstanceOf(ClientContextInterface::class, $clientContext);

        $client = $clientContext->getClient();

        self::assertTrue(Uuid::isValid($client->id));
        self::assertNotSame($client->id, $clientContext->getClient()->id);
    }
}
