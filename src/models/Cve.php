<?php

declare(strict_types=1);

final class Cve
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $severity,
    ) {}
}
