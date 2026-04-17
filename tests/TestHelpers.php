<?php

declare(strict_types=1);

function assertTestSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
}

function assertTestTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertTestNull(mixed $value, string $message): void
{
    if ($value !== null) {
        throw new RuntimeException($message . PHP_EOL . 'Expected null, got: ' . var_export($value, true));
    }
}
