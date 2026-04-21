<?php

declare(strict_types=1);

final class HighLevelDependencyParser
{
    private const ALLOWED_LICENSES = [
        '2-clause BSD License (free BSD)',
        '3-clause BSD License (Modified / new BSD)',
        'AGPL3',
        'Apache 2.0',
        'CDDL-1.0/CDDL1.1',
        'CPL/EPL',
        'GPL v2',
        'GPL v3',
        'LGPL v2.1',
        'LGPL v3',
        'MIT License',
        'MPL2.0/MPL1.1',
        'MS-PL',
        'Proprietary',
        'Other',
    ];

    /**
     * Parses a JSON file containing high-level dependencies.
     *
     * Expected JSON format:
     * {
     *   "highLevelDependencies": [
     *     {
     *       "name": "Logging",
     *       "license": "MIT License",
     *       "reuseJustification": "...",
     *       "integrationStrategy": "...",
     *       "validationStrategy": "...",
     *       "thirdPartyDependencies": ["dep1", "dep2"]
     *     }
     *   ]
     * }
     *
     * @return array<int, array{
     *   name: string,
     *   license: string,
     *   reuseJustification: string,
     *   integrationStrategy: string,
     *   validationStrategy: string,
     *   thirdPartyDependencies: string[]
     * }>
     * @throws RuntimeException if the JSON is invalid or missing required fields
     */
    public static function parse(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON: ' . $e->getMessage());
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('JSON root must be an object.');
        }

        if (!isset($decoded['highLevelDependencies']) || !is_array($decoded['highLevelDependencies'])) {
            throw new RuntimeException('Missing or invalid "highLevelDependencies" array in JSON.');
        }

        $result = [];
        $seenNames = [];

        foreach ($decoded['highLevelDependencies'] as $index => $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException(sprintf('Entry at index %d must be an object.', $index));
            }

            $name = isset($entry['name']) && is_string($entry['name']) ? trim($entry['name']) : '';
            if ($name === '') {
                throw new RuntimeException(sprintf('Entry at index %d is missing a non-empty "name".', $index));
            }
            if (strlen($name) > 255) {
                throw new RuntimeException(sprintf('Entry at index %d: name exceeds 255 characters.', $index));
            }

            $lowerName = strtolower($name);
            if (isset($seenNames[$lowerName])) {
                throw new RuntimeException(sprintf('Duplicate high-level dependency name: "%s".', $name));
            }
            $seenNames[$lowerName] = true;

            $license = isset($entry['license']) && is_string($entry['license']) ? trim($entry['license']) : '';
            if ($license !== '' && !in_array($license, self::ALLOWED_LICENSES, true)) {
                throw new RuntimeException(sprintf('Entry "%s": invalid license value "%s".', $name, $license));
            }

            $reuseJustification  = isset($entry['reuseJustification']) && is_string($entry['reuseJustification'])
                ? $entry['reuseJustification'] : '';
            $integrationStrategy = isset($entry['integrationStrategy']) && is_string($entry['integrationStrategy'])
                ? $entry['integrationStrategy'] : '';
            $validationStrategy  = isset($entry['validationStrategy']) && is_string($entry['validationStrategy'])
                ? $entry['validationStrategy'] : '';

            $thirdPartyDependencies = [];
            if (isset($entry['thirdPartyDependencies'])) {
                if (!is_array($entry['thirdPartyDependencies'])) {
                    throw new RuntimeException(sprintf('Entry "%s": "thirdPartyDependencies" must be an array.', $name));
                }
                foreach ($entry['thirdPartyDependencies'] as $depIndex => $dep) {
                    if (!is_string($dep) || trim($dep) === '') {
                        throw new RuntimeException(sprintf(
                            'Entry "%s": thirdPartyDependencies[%d] must be a non-empty string.',
                            $name,
                            $depIndex,
                        ));
                    }
                    if (strlen($dep) > 255) {
                        throw new RuntimeException(sprintf(
                            'Entry "%s": thirdPartyDependencies[%d] exceeds 255 characters.',
                            $name,
                            $depIndex,
                        ));
                    }
                    $thirdPartyDependencies[] = $dep;
                }
            }

            $result[] = [
                'name'                  => $name,
                'license'               => $license,
                'reuseJustification'    => $reuseJustification,
                'integrationStrategy'   => $integrationStrategy,
                'validationStrategy'    => $validationStrategy,
                'thirdPartyDependencies' => $thirdPartyDependencies,
            ];
        }

        return $result;
    }
}
