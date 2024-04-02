<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Controller;

use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ClientValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    /**
     * @return list<Client>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        $argumentType = $argument->getType();
        if (!is_string($argumentType) || !is_a($argumentType, Client::class, true)) {
            return [];
        }

        return [$this->clientContext->getClient()];
    }
}
