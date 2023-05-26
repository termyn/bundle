<?php

declare(strict_types=1);

namespace Termyn\Symfony\Bundle\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as ValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Termyn\Uuid;
use Termyn\Uuid\UuidFactory;
use Termyn\Uuid\UuidValidator;

final readonly class NullableUuidValueResolver implements ValueResolver
{
    public function __construct(
        private UuidValidator $uuidValidator,
        private UuidFactory $uuidFactory,
    ) {

    }

    public function resolve(
        Request $request,
        ArgumentMetadata $argument
    ): iterable {
        if (! $this->supports($argument)) {
            return [];
        }

        $parameter = sprintf('%s', $request->get($argument->getName()));
        if (! $this->isValidUuid($parameter)) {
            return [];
        }

        return [
            $this->uuidFactory->create($parameter),
        ];
    }

    private function supports(ArgumentMetadata $argument): bool
    {
        return $argument->getType()
            && is_a($argument->getType(), Uuid::class, true)
            && ! $argument->isVariadic()
            && ($argument->isNullable() || $argument->hasDefaultValue());
    }

    private function isValidUuid(string $parameter): bool
    {
        return $this->uuidValidator->validate($parameter);
    }
}
