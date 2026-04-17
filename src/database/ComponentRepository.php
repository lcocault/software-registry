<?php

declare(strict_types=1);

final class ComponentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM components WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?Component
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.version, c.owner, c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new Component(
            (int) $row['id'],
            $row['name'],
            $row['version'],
            $row['owner'],
            $row['language'],
            $row['project_name'],
        );
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     */
    public function save(
        string $name,
        string $version,
        string $owner,
        string $project,
        string $language,
        array $dependencies,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $projectId = $this->upsertProject($project);

            $stmt = $this->pdo->prepare(
                'INSERT INTO components(name, version, owner, language, project_id)
                 VALUES(:name, :version, :owner, :language, :project_id) RETURNING id'
            );
            $stmt->execute([
                'name' => $name,
                'version' => $version,
                'owner' => $owner,
                'language' => $language,
                'project_id' => $projectId,
            ]);
            $componentId = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($dependencies !== []) {
                $this->insertDependencies($componentId, $dependencies);
            }

            $this->pdo->commit();

            return $componentId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, array{name: string, version: string}>|null $dependencies null means no change
     */
    public function update(
        int $id,
        string $name,
        string $version,
        string $owner,
        string $project,
        string $language,
        ?array $dependencies,
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $projectId = $this->upsertProject($project);

            $stmt = $this->pdo->prepare(
                'UPDATE components
                 SET name = :name, version = :version, owner = :owner,
                     language = :language, project_id = :project_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'version' => $version,
                'owner' => $owner,
                'language' => $language,
                'project_id' => $projectId,
                'id' => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();

                return false;
            }

            if ($dependencies !== null) {
                $deleteStmt = $this->pdo->prepare('DELETE FROM dependencies WHERE component_id = :component_id');
                $deleteStmt->execute(['component_id' => $id]);

                if ($dependencies !== []) {
                    $this->insertDependencies($id, $dependencies);
                }
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return Component[]
     */
    public function listAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT c.id, c.name, c.version, c.owner, c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             ORDER BY c.id DESC
             LIMIT 200'
        )->fetchAll();

        if ($rows === []) {
            return [];
        }

        $componentIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $dependenciesByComponentId = $this->fetchDependencies($componentIds);

        return array_map(
            static fn (array $row): Component => new Component(
                (int) $row['id'],
                $row['name'],
                $row['version'],
                $row['owner'],
                $row['language'],
                $row['project_name'],
                $dependenciesByComponentId[(int) $row['id']] ?? [],
            ),
            $rows,
        );
    }

    private function upsertProject(string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO projects(name) VALUES(:name)
             ON CONFLICT(name) DO UPDATE SET name = EXCLUDED.name
             RETURNING id'
        );
        $stmt->execute(['name' => $name]);
        $id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $id;
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     */
    private function insertDependencies(int $componentId, array $dependencies): void
    {
        $valueClauses = [];
        $insertParams = [];

        foreach ($dependencies as $index => $dependency) {
            $valueClauses[] = '(:component_id_' . $index . ', :name_' . $index . ', :version_' . $index . ')';
            $insertParams['component_id_' . $index] = $componentId;
            $insertParams['name_' . $index] = $dependency['name'];
            $insertParams['version_' . $index] = $dependency['version'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO dependencies(component_id, name, version) VALUES ' . implode(', ', $valueClauses)
        );
        $stmt->execute($insertParams);
    }

    /**
     * @param int[] $componentIds
     * @return array<int, Dependency[]>
     */
    private function fetchDependencies(array $componentIds): array
    {
        $placeholderTokens = array_map(
            static fn (int $index): string => ':id_' . $index,
            array_keys($componentIds),
        );
        $stmt = $this->pdo->prepare(
            'SELECT component_id, name, version
             FROM dependencies
             WHERE component_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY name'
        );

        $params = [];
        foreach ($componentIds as $index => $componentId) {
            $params['id_' . $index] = $componentId;
        }
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $componentId = (int) $row['component_id'];
            $result[$componentId][] = new Dependency($row['name'], $row['version']);
        }

        return $result;
    }
}
