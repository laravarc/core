<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

final class AuthorizationTree
{
    /**
     * @param  array<string, array<string, list<list<AbilityRef>>>>  $controllers
     */
    public function __construct(
        private readonly array $controllers,
    ) {}

    /**
     * @return array<string, array<string, list<list<AbilityRef>>>>
     */
    public function controllers(): array
    {
        return $this->controllers;
    }
}
