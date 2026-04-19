<?php

declare(strict_types=1);

final class CveRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Returns the stored CVEs for this dependency+version, or null if CVEs have never been
     * fetched from the OSV API for this combination. An empty array means the API was
     * queried and returned no vulnerabilities.
     *
     * @return Cve[]|null
     */
    public function findByDependency(string $name, string $version): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fetched_at FROM dependency_cve_fetches
             WHERE dependency_name = :name AND dependency_version = :version'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);

        if ($stmt->fetch() === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT cve_id, description, severity FROM dependency_cves
             WHERE dependency_name = :name AND dependency_version = :version
             ORDER BY cve_id'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);

        return array_map(
            static fn (array $row): Cve => new Cve($row['cve_id'], $row['description'], $row['severity']),
            $stmt->fetchAll(),
        );
    }

    /**
     * Returns the number of stored CVEs for this dependency+version, or null if CVEs have
     * never been fetched from the OSV API for this combination.
     */
    public function countByDependency(string $name, string $version): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(dc.id) AS cve_count
             FROM dependency_cve_fetches dcf
             LEFT JOIN dependency_cves dc
                 ON dc.dependency_name  = dcf.dependency_name
                AND dc.dependency_version = dcf.dependency_version
             WHERE dcf.dependency_name = :name AND dcf.dependency_version = :version
             GROUP BY dcf.dependency_name, dcf.dependency_version'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);

        $row = $stmt->fetch();

        return $row !== false ? (int) $row['cve_count'] : null;
    }

    /**
     * Returns CVE counts for all fetched dependency+version pairs, indexed by
     * dependency name and then by version.  Only entries that have been fetched
     * (i.e. recorded in dependency_cve_fetches) appear in the result.
     *
     * Usage: $count = $result[$name][$version] ?? null;
     *
     * @return array<string, array<string, int>>
     */
    public function getAllCounts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT dcf.dependency_name, dcf.dependency_version, COUNT(dc.id) AS cve_count
             FROM dependency_cve_fetches dcf
             LEFT JOIN dependency_cves dc
                 ON dc.dependency_name  = dcf.dependency_name
                AND dc.dependency_version = dcf.dependency_version
             GROUP BY dcf.dependency_name, dcf.dependency_version'
        );

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['dependency_name']][$row['dependency_version']] = (int) $row['cve_count'];
        }

        return $result;
    }

    /**
     * Stores the given CVEs for a dependency+version in the database, replacing any
     * previously stored data. Also records the fetch timestamp so that subsequent
     * requests do not hit the OSV API again.
     *
     * @param Cve[] $cves
     */
    public function store(string $name, string $version, array $cves): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                'INSERT INTO dependency_cve_fetches(dependency_name, dependency_version, fetched_at)
                 VALUES(:name, :version, CURRENT_TIMESTAMP)
                 ON CONFLICT(dependency_name, dependency_version) DO UPDATE SET fetched_at = CURRENT_TIMESTAMP'
            )->execute(['name' => $name, 'version' => $version]);

            $this->pdo->prepare(
                'DELETE FROM dependency_cves
                 WHERE dependency_name = :name AND dependency_version = :version'
            )->execute(['name' => $name, 'version' => $version]);

            $insertStmt = $this->pdo->prepare(
                'INSERT INTO dependency_cves(dependency_name, dependency_version, cve_id, description, severity)
                 VALUES(:name, :version, :cve_id, :description, :severity)'
            );
            foreach ($cves as $cve) {
                $insertStmt->execute([
                    'name'        => $name,
                    'version'     => $version,
                    'cve_id'      => $cve->id,
                    'description' => $cve->description,
                    'severity'    => $cve->severity,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
