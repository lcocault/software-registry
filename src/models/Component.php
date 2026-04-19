<?php

declare(strict_types=1);

final class Component
{
    /**
     * @param ComponentVersion[]    $versions
     * @param HighLevelDependency[] $highLevelDependencies
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $ownerId,
        public readonly string $owner,
        public readonly string $language,
        public readonly string $projectName,
        public readonly array $versions = [],
        public readonly array $highLevelDependencies = [],
    ) {}
}
