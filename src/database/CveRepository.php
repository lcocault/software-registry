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
