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
            'SELECT c.id, c.name, c.version, c.owner_id,
                    u.firstname || \' \' || u.name AS owner_name,
                    c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             JOIN users u ON u.id = c.owner_id
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
            (int) $row['owner_id'],
            $row['owner_name'],
            $row['language'],
            $row['project_name'],
        );
    }

    public function findByIdWithDependencies(int $id): ?Component
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.version, c.owner_id,
                    u.firstname || \' \' || u.name AS owner_name,
                    c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             JOIN users u ON u.id = c.owner_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $dependenciesByComponentId = $this->fetchDependencies([$id]);

        return new Component(
            (int) $row['id'],
            $row['name'],
            $row['version'],
            (int) $row['owner_id'],
            $row['owner_name'],
            $row['language'],
            $row['project_name'],
            $dependenciesByComponentId[$id] ?? [],
        );
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     */
    public function save(
        string $name,
        string $version,
        int $ownerId,
        string $project,
        string $language,
        array $dependencies,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $projectId = $this->upsertProject($project);

            $stmt = $this->pdo->prepare(
                'INSERT INTO components(name, version, owner_id, language, project_id)
                 VALUES(:name, :version, :owner_id, :language, :project_id) RETURNING id'
            );
            $stmt->execute([
                'name'       => $name,
                'version'    => $version,
                'owner_id'   => $ownerId,
                'language'   => $language,
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
        int $ownerId,
        string $project,
        string $language,
        ?array $dependencies,
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $projectId = $this->upsertProject($project);

            $stmt = $this->pdo->prepare(
                'UPDATE components
                 SET name = :name, version = :version, owner_id = :owner_id,
                     language = :language, project_id = :project_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'name'       => $name,
                'version'    => $version,
                'owner_id'   => $ownerId,
                'language'   => $language,
                'project_id' => $projectId,
                'id'         => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();

                return false;
            }

            if ($dependencies !== null) {
                $deleteStmt = $this->pdo->prepare('DELETE FROM versioned_dependencies WHERE component_id = :component_id');
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
            'SELECT c.id, c.name, c.version, c.owner_id,
                    u.firstname || \' \' || u.name AS owner_name,
                    c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             JOIN users u ON u.id = c.owner_id
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
                (int) $row['owner_id'],
                $row['owner_name'],
                $row['language'],
                $row['project_name'],
                $dependenciesByComponentId[(int) $row['id']] ?? [],
            ),
            $rows,
        );
    }

    /**
     * @return array<array{name: string, usage_count: int}>
     */
    public function listDependencyNames(): array
    {
        $rows = $this->pdo->query(
            'SELECT d.name, COUNT(DISTINCT vd.component_id) AS usage_count
             FROM dependencies d
             JOIN versioned_dependencies vd ON vd.dependency_id = d.id
             GROUP BY d.id, d.name
             ORDER BY d.name'
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'name'        => $row['name'],
                'usage_count' => (int) $row['usage_count'],
            ],
            $rows,
        );
    }

    /**
     * @return array<array{version: string, usage_count: int}>
     */
    public function listDependencyVersions(string $name): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT vd.version, COUNT(DISTINCT vd.component_id) AS usage_count
             FROM versioned_dependencies vd
             JOIN dependencies d ON d.id = vd.dependency_id
             WHERE d.name = :name
             GROUP BY vd.version
             ORDER BY vd.version'
        );
        $stmt->execute(['name' => $name]);

        return array_map(
            static fn (array $row): array => [
                'version'     => $row['version'],
                'usage_count' => (int) $row['usage_count'],
            ],
            $stmt->fetchAll(),
        );
    }

    /**
     * @return Component[]
     */
    public function listComponentsUsingDependency(string $name, string $version): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.version, c.owner_id,
                    u.firstname || \' \' || u.name AS owner_name,
                    c.language, p.name AS project_name
             FROM components c
             JOIN projects p ON p.id = c.project_id
             JOIN users u ON u.id = c.owner_id
             JOIN versioned_dependencies vd ON vd.component_id = c.id
             JOIN dependencies d ON d.id = vd.dependency_id
             WHERE d.name = :name AND vd.version = :version
             ORDER BY c.name, c.version'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);

        return array_map(
            static fn (array $row): Component => new Component(
                (int) $row['id'],
                $row['name'],
                $row['version'],
                (int) $row['owner_id'],
                $row['owner_name'],
                $row['language'],
                $row['project_name'],
            ),
            $stmt->fetchAll(),
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
        foreach ($dependencies as $dependency) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO dependencies(name) VALUES(:name)
                 ON CONFLICT(name) DO UPDATE SET name = EXCLUDED.name
                 RETURNING id'
            );
            $stmt->execute(['name' => $dependency['name']]);
            $dependencyId = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            $stmt = $this->pdo->prepare(
                'INSERT INTO versioned_dependencies(component_id, dependency_id, version)
                 VALUES(:component_id, :dependency_id, :version)
                 ON CONFLICT(component_id, dependency_id) DO UPDATE SET version = EXCLUDED.version'
            );
            $stmt->execute([
                'component_id' => $componentId,
                'dependency_id' => $dependencyId,
                'version' => $dependency['version'],
            ]);
        }
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
            'SELECT vd.component_id, d.name, vd.version
             FROM versioned_dependencies vd
             JOIN dependencies d ON d.id = vd.dependency_id
             WHERE vd.component_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY d.name'
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
