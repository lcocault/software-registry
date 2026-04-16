<?php

declare(strict_types=1);

final class Dependency
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {}
}
