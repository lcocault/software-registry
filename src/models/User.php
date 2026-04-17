<?php

declare(strict_types=1);

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstname,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public function fullName(): string
    {
        return $this->firstname . ' ' . $this->name;
    }
}
