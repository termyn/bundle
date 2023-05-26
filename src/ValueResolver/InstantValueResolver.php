<?php

declare(strict_types=1);

namespace Termyn\Symfony\Bundle\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as ValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Termyn\DateTime\Clock;
use Termyn\DateTime\Instant;

final readonly class InstantValueResolver implements ValueResolver
{
    public function __construct(
        private Clock $clock,
    ) {

    }

    public function resolve(
        Request $request,
        ArgumentMetadata $argument,
    ): iterable {
        if (! $this->supports($argument)) {
            return [];
        }

        return [
            $this->clock->measure(),
        ];
    }

    private function supports(ArgumentMetadata $argument): bool
    {
        return $argument->getType() && is_a($argument->getType(), Instant::class, true);
    }
}
