<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Laravarc\Core\Contracts\PresentationStack as PresentationStackContract;
use Laravarc\Core\Presentation\Exceptions\UnknownPresentationStackException;

final class PresentationStackRegistry
{
    /**
     * @param  array<string, PresentationStackContract>  $stacks
     */
    public function __construct(
        private readonly array $stacks,
        private readonly string $defaultKey,
    ) {
        if (! isset($this->stacks[$this->defaultKey])) {
            throw new InvalidArgumentException(sprintf(
                'Default presentation stack [%s] is not registered. Valid stacks: %s.',
                $this->defaultKey,
                implode(', ', array_keys($this->stacks)),
            ));
        }
    }

    /**
     * @param  list<class-string<PresentationStackContract>>  $stackClasses
     */
    public static function fromConfig(array $stackClasses, string $defaultKey, Container $container): self
    {
        $stacks = [];

        foreach ($stackClasses as $class) {
            if (! is_string($class) || $class === '') {
                throw new InvalidArgumentException('Each entry in arc.stacks must be a non-empty class name.');
            }

            if (! class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Presentation stack class [%s] does not exist.', $class));
            }

            if (! is_subclass_of($class, PresentationStackContract::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Presentation stack class [%s] must implement %s.',
                    $class,
                    PresentationStackContract::class,
                ));
            }

            $key = $class::key();

            if (isset($stacks[$key])) {
                throw new InvalidArgumentException(sprintf('Duplicate presentation stack key [%s].', $key));
            }

            $stacks[$key] = $container->make($class);
        }

        return new self($stacks, $defaultKey);
    }

    public function resolve(?string $key = null): PresentationStackContract
    {
        $resolvedKey = $key ?? $this->defaultKey;

        if (! isset($this->stacks[$resolvedKey])) {
            throw new UnknownPresentationStackException(sprintf(
                'Unknown presentation stack [%s]. Valid stacks: %s.',
                $resolvedKey,
                implode(', ', $this->keys()),
            ));
        }

        return $this->stacks[$resolvedKey];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->stacks);
    }

    public function defaultKey(): string
    {
        return $this->defaultKey;
    }
}
