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
            'SELECT c.id, c.name, c.owner_id,
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

        $versionsByComponentId = $this->fetchVersions([$id], false);

        return new Component(
            (int) $row['id'],
            $row['name'],
            (int) $row['owner_id'],
            $row['owner_name'],
            $row['language'],
            $row['project_name'],
            $versionsByComponentId[$id] ?? [],
        );
    }

    public function findByIdWithVersions(int $id): ?Component
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.owner_id,
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

        $versionsByComponentId = $this->fetchVersions([$id], true);

        return new Component(
            (int) $row['id'],
            $row['name'],
            (int) $row['owner_id'],
            $row['owner_name'],
            $row['language'],
            $row['project_name'],
            $versionsByComponentId[$id] ?? [],
        );
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     */
    public function save(
        string $name,
        string $versionLabel,
        int $ownerId,
        string $project,
        string $language,
        array $dependencies,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $projectId = $this->upsertProject($project);

            $stmt = $this->pdo->prepare(
                'INSERT INTO components(name, owner_id, language, project_id)
                 VALUES(:name, :owner_id, :language, :project_id) RETURNING id'
            );
            $stmt->execute([
                'name'       => $name,
                'owner_id'   => $ownerId,
                'language'   => $language,
                'project_id' => $projectId,
            ]);
            $componentId = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            $versionId = $this->upsertComponentVersion($componentId, $versionLabel);

            if ($dependencies !== []) {
                $this->insertDependencies($versionId, $dependencies);
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
        string $versionLabel,
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
                 SET name = :name, owner_id = :owner_id,
                     language = :language, project_id = :project_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'name'       => $name,
                'owner_id'   => $ownerId,
                'language'   => $language,
                'project_id' => $projectId,
                'id'         => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();

                return false;
            }

            $versionId = $this->upsertComponentVersion($id, $versionLabel);

            if ($dependencies !== null) {
                $deleteStmt = $this->pdo->prepare('DELETE FROM versioned_dependencies WHERE component_version_id = :version_id');
                $deleteStmt->execute(['version_id' => $versionId]);

                if ($dependencies !== []) {
                    $this->insertDependencies($versionId, $dependencies);
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
            'SELECT c.id, c.name, c.owner_id,
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
        $versionsByComponentId = $this->fetchVersions($componentIds, true);

        return array_map(
            static fn (array $row): Component => new Component(
                (int) $row['id'],
                $row['name'],
                (int) $row['owner_id'],
                $row['owner_name'],
                $row['language'],
                $row['project_name'],
                $versionsByComponentId[(int) $row['id']] ?? [],
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
            'SELECT name, MAX(usage_count) AS usage_count
             FROM (
                 SELECT d.name, COUNT(DISTINCT cv.component_id) AS usage_count
                 FROM dependencies d
                 JOIN versioned_dependencies vd ON vd.dependency_id = d.id
                 JOIN component_versions cv ON cv.id = vd.component_version_id
                 GROUP BY d.id, d.name
                 UNION ALL
                 SELECT DISTINCT name, 0
                 FROM catalog_entries
             ) merged
             GROUP BY name
             ORDER BY name'
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
            'SELECT version, MAX(usage_count) AS usage_count
             FROM (
                 SELECT vd.version, COUNT(DISTINCT cv.component_id) AS usage_count
                 FROM versioned_dependencies vd
                 JOIN dependencies d ON d.id = vd.dependency_id
                 JOIN component_versions cv ON cv.id = vd.component_version_id
                 WHERE d.name = :name
                 GROUP BY vd.version
                 UNION ALL
                 SELECT version, 0
                 FROM catalog_entries
                 WHERE name = :name
             ) merged
             GROUP BY version
             ORDER BY version'
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
     * Adds a standalone 3rd party entry (name + version) to the catalog.
     * Idempotent: silently ignores duplicates.
     */
    public function addCatalogEntry(string $name, string $version): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO catalog_entries(name, version) VALUES(:name, :version)
             ON CONFLICT(name, version) DO NOTHING'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);
    }

    /**
     * Returns components that have a version using the given dependency.
     * Each returned Component contains exactly the matching ComponentVersion
     * in its versions array.
     *
     * @return Component[]
     */
    public function listComponentsUsingDependency(string $name, string $version): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.owner_id,
                    u.firstname || \' \' || u.name AS owner_name,
                    c.language, p.name AS project_name,
                    cv.id AS version_id, cv.label AS version_label
             FROM components c
             JOIN projects p ON p.id = c.project_id
             JOIN users u ON u.id = c.owner_id
             JOIN component_versions cv ON cv.component_id = c.id
             JOIN versioned_dependencies vd ON vd.component_version_id = cv.id
             JOIN dependencies d ON d.id = vd.dependency_id
             WHERE d.name = :name AND vd.version = :version
             ORDER BY c.name, cv.label'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);

        return array_map(
            static fn (array $row): Component => new Component(
                (int) $row['id'],
                $row['name'],
                (int) $row['owner_id'],
                $row['owner_name'],
                $row['language'],
                $row['project_name'],
                [new ComponentVersion((int) $row['version_id'], $row['version_label'])],
            ),
            $stmt->fetchAll(),
        );
    }

    /**
     * Returns a Component with its high-level dependencies loaded.
     */
    public function findByIdWithHighLevelDeps(int $id): ?Component
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.owner_id,
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

        $highLevelDeps = $this->fetchHighLevelDeps([$id]);

        return new Component(
            (int) $row['id'],
            $row['name'],
            (int) $row['owner_id'],
            $row['owner_name'],
            $row['language'],
            $row['project_name'],
            [],
            $highLevelDeps[$id] ?? [],
        );
    }

    /**
     * Adds a high-level dependency to a component.
     * Returns the new high-level dependency ID, or false if the component does not exist.
     */
    public function addHighLevelDependency(
        int $componentId,
        string $name,
        string $reuseJustification,
        string $integrationStrategy,
        string $validationStrategy,
        string $license = '',
    ): int|false {
        $stmt = $this->pdo->prepare('SELECT id FROM components WHERE id = :id');
        $stmt->execute(['id' => $componentId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO component_high_level_deps
                 (component_id, name, reuse_justification, integration_strategy, validation_strategy, license)
             VALUES(:component_id, :name, :reuse_justification, :integration_strategy, :validation_strategy, :license)
             RETURNING id'
        );
        $stmt->execute([
            'component_id'         => $componentId,
            'name'                 => $name,
            'reuse_justification'  => $reuseJustification,
            'integration_strategy' => $integrationStrategy,
            'validation_strategy'  => $validationStrategy,
            'license'              => $license,
        ]);
        $id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $id;
    }

    /**
     * Deletes a high-level dependency from a component.
     * Returns false if the high-level dependency does not belong to the component.
     */
    public function deleteHighLevelDependency(int $componentId, int $highLevelDepId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM component_high_level_deps WHERE id = :id AND component_id = :component_id'
        );
        $stmt->execute(['id' => $highLevelDepId, 'component_id' => $componentId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Adds a 3rd party dependency name to a high-level dependency.
     * Returns false if the high-level dependency does not belong to the component.
     */
    public function addHighLevelDepThirdParty(int $componentId, int $highLevelDepId, string $depName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM component_high_level_deps WHERE id = :id AND component_id = :component_id'
        );
        $stmt->execute(['id' => $highLevelDepId, 'component_id' => $componentId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO high_level_dep_third_party(high_level_dep_id, dependency_name)
             VALUES(:high_level_dep_id, :dependency_name)
             ON CONFLICT(high_level_dep_id, dependency_name) DO NOTHING'
        );
        $stmt->execute([
            'high_level_dep_id' => $highLevelDepId,
            'dependency_name'   => $depName,
        ]);

        return true;
    }

    /**
     * Removes a 3rd party dependency name from a high-level dependency.
     * Returns false if the link does not exist or does not belong to the component.
     */
    public function deleteHighLevelDepThirdParty(int $componentId, int $highLevelDepId, string $depName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM component_high_level_deps WHERE id = :id AND component_id = :component_id'
        );
        $stmt->execute(['id' => $highLevelDepId, 'component_id' => $componentId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM high_level_dep_third_party
             WHERE high_level_dep_id = :high_level_dep_id AND dependency_name = :dependency_name'
        );
        $stmt->execute([
            'high_level_dep_id' => $highLevelDepId,
            'dependency_name'   => $depName,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Adds a new version label to an existing component.
     * Returns false if the component does not exist, true otherwise (idempotent).
     */
    public function addVersion(int $componentId, string $label): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM components WHERE id = :id');
        $stmt->execute(['id' => $componentId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $this->upsertComponentVersion($componentId, $label);

        return true;
    }

    /**
     * Adds a single dependency to a specific component version.
     * Returns false if the version does not belong to the given component, true otherwise.
     */
    public function addDependency(int $componentId, int $versionId, string $depName, string $depVersion): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM component_versions WHERE id = :id AND component_id = :component_id'
        );
        $stmt->execute(['id' => $versionId, 'component_id' => $componentId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $this->insertDependencies($versionId, [['name' => $depName, 'version' => $depVersion]]);

        return true;
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

    private function upsertComponentVersion(int $componentId, string $label): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO component_versions(component_id, label)
             VALUES(:component_id, :label)
             ON CONFLICT(component_id, label) DO UPDATE SET label = EXCLUDED.label
             RETURNING id'
        );
        $stmt->execute(['component_id' => $componentId, 'label' => $label]);
        $id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $id;
    }

    /**
     * @param array<int, array{name: string, version: string}> $dependencies
     */
    private function insertDependencies(int $versionId, array $dependencies): void
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
                'INSERT INTO versioned_dependencies(component_version_id, dependency_id, version)
                 VALUES(:version_id, :dependency_id, :version)
                 ON CONFLICT(component_version_id, dependency_id) DO UPDATE SET version = EXCLUDED.version'
            );
            $stmt->execute([
                'version_id'    => $versionId,
                'dependency_id' => $dependencyId,
                'version'       => $dependency['version'],
            ]);
        }
    }

    /**
     * @param int[] $componentIds
     * @return array<int, ComponentVersion[]>  keyed by component_id
     */
    private function fetchVersions(array $componentIds, bool $withDeps): array
    {
        if ($componentIds === []) {
            return [];
        }

        $placeholderTokens = array_map(
            static fn (int $index): string => ':id_' . $index,
            array_keys($componentIds),
        );
        $stmt = $this->pdo->prepare(
            'SELECT cv.id, cv.component_id, cv.label
             FROM component_versions cv
             WHERE cv.component_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY cv.label'
        );

        $params = [];
        foreach ($componentIds as $index => $componentId) {
            $params['id_' . $index] = $componentId;
        }
        $stmt->execute($params);

        $versionRows = $stmt->fetchAll();

        $versionIds = array_map(static fn (array $row): int => (int) $row['id'], $versionRows);

        $depsByVersionId = [];
        if ($withDeps && $versionIds !== []) {
            $depsByVersionId = $this->fetchDependencies($versionIds);
        }

        $result = [];
        foreach ($versionRows as $row) {
            $versionId   = (int) $row['id'];
            $componentId = (int) $row['component_id'];
            $result[$componentId][] = new ComponentVersion(
                $versionId,
                $row['label'],
                $depsByVersionId[$versionId] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param int[] $versionIds
     * @return array<int, Dependency[]>  keyed by component_version_id
     */
    private function fetchDependencies(array $versionIds): array
    {
        $placeholderTokens = array_map(
            static fn (int $index): string => ':id_' . $index,
            array_keys($versionIds),
        );
        $stmt = $this->pdo->prepare(
            'SELECT vd.component_version_id, d.name, vd.version
             FROM versioned_dependencies vd
             JOIN dependencies d ON d.id = vd.dependency_id
             WHERE vd.component_version_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY d.name'
        );

        $params = [];
        foreach ($versionIds as $index => $versionId) {
            $params['id_' . $index] = $versionId;
        }
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $versionId = (int) $row['component_version_id'];
            $result[$versionId][] = new Dependency($row['name'], $row['version']);
        }

        return $result;
    }

    /**
     * @param int[] $componentIds
     * @return array<int, HighLevelDependency[]>  keyed by component_id
     */
    private function fetchHighLevelDeps(array $componentIds): array
    {
        if ($componentIds === []) {
            return [];
        }

        $placeholderTokens = array_map(
            static fn (int $index): string => ':id_' . $index,
            array_keys($componentIds),
        );
        $stmt = $this->pdo->prepare(
            'SELECT hld.id, hld.component_id, hld.name, hld.reuse_justification,
                    hld.integration_strategy, hld.validation_strategy, hld.license
             FROM component_high_level_deps hld
             WHERE hld.component_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY hld.name'
        );

        $params = [];
        foreach ($componentIds as $index => $componentId) {
            $params['id_' . $index] = $componentId;
        }
        $stmt->execute($params);

        $hldRows = $stmt->fetchAll();

        if ($hldRows === []) {
            return [];
        }

        $hldIds = array_map(static fn (array $row): int => (int) $row['id'], $hldRows);
        $thirdPartyByHldId = $this->fetchHighLevelDepThirdParty($hldIds);

        $result = [];
        foreach ($hldRows as $row) {
            $hldId       = (int) $row['id'];
            $componentId = (int) $row['component_id'];
            $result[$componentId][] = new HighLevelDependency(
                $hldId,
                $row['name'],
                $row['reuse_justification'],
                $row['integration_strategy'],
                $row['validation_strategy'],
                $row['license'],
                $thirdPartyByHldId[$hldId] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param int[] $hldIds
     * @return array<int, string[]>  keyed by high_level_dep_id
     */
    private function fetchHighLevelDepThirdParty(array $hldIds): array
    {
        if ($hldIds === []) {
            return [];
        }

        $placeholderTokens = array_map(
            static fn (int $index): string => ':id_' . $index,
            array_keys($hldIds),
        );
        $stmt = $this->pdo->prepare(
            'SELECT hldtp.high_level_dep_id, hldtp.dependency_name
             FROM high_level_dep_third_party hldtp
             WHERE hldtp.high_level_dep_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY hldtp.dependency_name'
        );

        $params = [];
        foreach ($hldIds as $index => $hldId) {
            $params['id_' . $index] = $hldId;
        }
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $hldId = (int) $row['high_level_dep_id'];
            $result[$hldId][] = $row['dependency_name'];
        }

        return $result;
    }
}
