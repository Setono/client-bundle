<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the service id instead of the interface. It makes the client that the "X-Client-Id" header names the
 * current client, and it creates a client on every call
 */
#[AsDecorator('setono_client.client_context.default')]
final class HeaderClientContext implements ClientContextInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ClientContextInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getClient(): Client
    {
        $clientId = $this->requestStack->getMainRequest()?->headers->get('X-Client-Id');
        if (null === $clientId) {
            return $this->decorated->getClient();
        }

        return new Client($clientId, new ChangeAwareMetadata());
    }
}
