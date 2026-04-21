<?php

declare(strict_types=1);

final class HighLevelDependency
{
    /**
     * @param string[] $thirdPartyDependencies
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $reuseJustification,
        public readonly string $integrationStrategy,
        public readonly string $validationStrategy,
        public readonly string $license = '',
        public readonly array $thirdPartyDependencies = [],
    ) {}
}
