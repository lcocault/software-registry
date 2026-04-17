<?php

declare(strict_types=1);

final class OsvClient
{
    private const OSV_API_URL = 'https://api.osv.dev/v1/query';

    /** Maps the application's language labels to OSV ecosystem identifiers. */
    private const ECOSYSTEM_MAP = [
        'Java'       => 'Maven',
        'Python'     => 'PyPI',
        'JavaScript' => 'npm',
    ];

    /**
     * Returns the OSV ecosystem identifier for the given language, or null if unsupported.
     */
    public static function ecosystemForLanguage(string $language): ?string
    {
        return self::ECOSYSTEM_MAP[$language] ?? null;
    }

    /**
     * Queries the OSV API and returns known CVEs for the given package, version and language.
     *
     * @return Cve[]
     */
    public function getVulnerabilities(string $packageName, string $version, string $language): array
    {
        $ecosystem = self::ecosystemForLanguage($language);
        if ($ecosystem === null) {
            return [];
        }

        $payload = json_encode([
            'package' => [
                'name'      => $packageName,
                'ecosystem' => $ecosystem,
            ],
            'version' => $version,
        ]);

        if ($payload === false) {
            return [];
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents(self::OSV_API_URL, false, $context);
        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['vulns']) || !is_array($data['vulns'])) {
            return [];
        }

        $cves = [];
        foreach ($data['vulns'] as $vuln) {
            if (!is_array($vuln)) {
                continue;
            }

            // Prefer the canonical CVE alias when available, fall back to the OSV ID.
            $id = is_string($vuln['id'] ?? null) ? $vuln['id'] : '';
            foreach ($vuln['aliases'] ?? [] as $alias) {
                if (is_string($alias) && str_starts_with($alias, 'CVE-')) {
                    $id = $alias;
                    break;
                }
            }

            if ($id === '') {
                continue;
            }

            $description = is_string($vuln['summary'] ?? null) ? $vuln['summary'] : '';
            $severity    = '';
            if (is_array($vuln['database_specific'] ?? null) && is_string($vuln['database_specific']['severity'] ?? null)) {
                $severity = $vuln['database_specific']['severity'];
            }

            $cves[] = new Cve($id, $description, $severity);
        }

        return $cves;
    }
}
