<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function save(string $firstname, string $name, string $email): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users(firstname, name, email)
             VALUES(:firstname, :name, :email) RETURNING id'
        );
        $stmt->execute([
            'firstname' => $firstname,
            'name'      => $name,
            'email'     => $email,
        ]);
        $id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $id;
    }

    public function update(int $id, string $firstname, string $name, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET firstname = :firstname, name = :name, email = :email
             WHERE id = :id'
        );
        $stmt->execute([
            'firstname' => $firstname,
            'name'      => $name,
            'email'     => $email,
            'id'        => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, firstname, name, email FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new User((int) $row['id'], $row['firstname'], $row['name'], $row['email']);
    }

    /**
     * @return User[]
     */
    public function listAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, firstname, name, email FROM users ORDER BY name, firstname'
        )->fetchAll();

        return array_map(
            static fn (array $row): User => new User(
                (int) $row['id'],
                $row['firstname'],
                $row['name'],
                $row['email'],
            ),
            $rows,
        );
    }
}
