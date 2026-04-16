<?php

declare(strict_types=1);

final class Component
{
    /**
     * @param Dependency[] $dependencies
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $owner,
        public readonly string $language,
        public readonly string $projectName,
        public readonly array $dependencies = [],
    ) {}
}
