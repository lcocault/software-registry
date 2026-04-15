<?php

declare(strict_types=1);

final class DependencyParser
{
    /**
     * @return array<int, array{name: string, version: string}>
     */
    public static function parse(string $language, string $content): array
    {
        return match ($language) {
            'Java' => self::parseMavenDependencyTree($content),
            'Python' => self::parsePipList($content),
            'JavaScript' => self::parsePackageLock($content),
            default => [],
        };
    }

    /**
     * @return array<int, array{name: string, version: string}>
     */
    private static function parseMavenDependencyTree(string $content): array
    {
        $dependencies = [];
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $line) {
            if (!str_contains($line, '+-') && !str_contains($line, '\\-')) {
                continue;
            }

            $normalized = preg_replace('/^\[INFO\]\s*/', '', trim($line));
            if ($normalized === null) {
                continue;
            }

            $normalized = preg_replace('/^[|+\-\\\\\s]+/', '', $normalized);
            if ($normalized === null) {
                continue;
            }

            $parts = explode(':', $normalized);
            $count = count($parts);

            if ($count < 4) {
                continue;
            }

            $group = $parts[0];
            $artifact = $parts[1];
            $version = $count >= 6 ? $parts[4] : $parts[3];

            if ($version === '' || $group === '' || $artifact === '') {
                continue;
            }

            $dependencies[] = ['name' => $group . ':' . $artifact, 'version' => $version];
        }

        return self::deduplicate($dependencies);
    }

    /**
     * @return array<int, array{name: string, version: string}>
     */
    private static function parsePipList(string $content): array
    {
        $dependencies = [];
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (
                $line === '' ||
                stripos($line, 'package') === 0 ||
                str_starts_with($line, '---')
            ) {
                continue;
            }

            if (str_contains($line, '==')) {
                [$name, $version] = explode('==', $line, 2);
            } else {
                $parts = preg_split('/\s+/', $line) ?: [];
                if (count($parts) < 2) {
                    continue;
                }

                [$name, $version] = [$parts[0], $parts[1]];
            }

            if ($name === '' || $version === '') {
                continue;
            }

            $dependencies[] = ['name' => $name, 'version' => $version];
        }

        return self::deduplicate($dependencies);
    }

    /**
     * @return array<int, array{name: string, version: string}>
     */
    private static function parsePackageLock(string $content): array
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $dependencies = [];

        if (isset($decoded['packages']) && is_array($decoded['packages'])) {
            foreach ($decoded['packages'] as $packagePath => $packageData) {
                if (
                    !is_string($packagePath) ||
                    $packagePath === '' ||
                    $packagePath === '.' ||
                    !str_starts_with($packagePath, 'node_modules/') ||
                    !is_array($packageData)
                ) {
                    continue;
                }

                $version = $packageData['version'] ?? null;
                if (!is_string($version) || $version === '') {
                    continue;
                }

                $dependencies[] = [
                    'name' => substr($packagePath, strlen('node_modules/')),
                    'version' => $version,
                ];
            }
        } elseif (isset($decoded['dependencies']) && is_array($decoded['dependencies'])) {
            self::collectPackageLockV1Dependencies($decoded['dependencies'], $dependencies);
        }

        return self::deduplicate($dependencies);
    }

    /**
     * @param array<string, mixed> $dependenciesNode
     * @param array<int, array{name: string, version: string}> $collector
     */
    private static function collectPackageLockV1Dependencies(array $dependenciesNode, array &$collector): void
    {
        foreach ($dependenciesNode as $name => $info) {
            if (!is_string($name) || !is_array($info)) {
                continue;
            }

            $version = $info['version'] ?? null;
            if (is_string($version) && $version !== '') {
                $collector[] = ['name' => $name, 'version' => $version];
            }

            $subDependencies = $info['dependencies'] ?? null;
            if (is_array($subDependencies)) {
                self::collectPackageLockV1Dependencies($subDependencies, $collector);
            }
        }
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     * @return array<int, array{name: string, version: string}>
     */
    private static function deduplicate(array $dependencies): array
    {
        $unique = [];

        foreach ($dependencies as $dependency) {
            $key = $dependency['name'] . '@' . $dependency['version'];
            $unique[$key] = $dependency;
        }

        return array_values($unique);
    }
}
