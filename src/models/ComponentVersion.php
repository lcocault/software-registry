<?php

declare(strict_types=1);

final class ComponentVersion
{
    /**
     * @param Dependency[] $dependencies
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly array $dependencies = [],
    ) {}
}
