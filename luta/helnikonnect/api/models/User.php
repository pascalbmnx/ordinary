<?php
/**
 * User model.
 */
final class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    public function roleOf(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.* FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function permissionsOf(int $roleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        return array_column($stmt->fetchAll(), 'code');
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (role_id, full_name, phone, email, password_hash, applicant_type, region, district, ward, street, is_staff)
             VALUES (:role, :full_name, :phone, :email, :pass, :applicant_type, :region, :district, :ward, :street, :is_staff)'
        );
        $stmt->execute([
            ':role' => $data['role_id'] ?? 5,
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':pass' => $data['password_hash'],
            ':applicant_type' => $data['applicant_type'] ?? null,
            ':region' => $data['region'] ?? null,
            ':district' => $data['district'] ?? null,
            ':ward' => $data['ward'] ?? null,
            ':street' => $data['street'] ?? null,
            ':is_staff' => $data['is_staff'] ?? 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $fields = [];
        $vals = [];
        $allowed = [
            'full_name', 'phone', 'email', 'applicant_type', 'region',
            'district', 'ward', 'street', 'profile_pic'
        ];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $vals[":$f"] = $data[$f];
            }
        }
        if (!$fields) return false;
        $vals[':id'] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($vals);
    }

    public function updatePassword(int $id, string $hash): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $id]);
    }

    public function publicUser(array $u): array
    {
        return [
            'id' => (int) $u['id'],
            'full_name' => $u['full_name'],
            'phone' => $u['phone'],
            'email' => $u['email'],
            'applicant_type' => $u['applicant_type'],
            'region' => $u['region'],
            'district' => $u['district'],
            'ward' => $u['ward'],
            'street' => $u['street'],
            'profile_pic' => $u['profile_pic'],
            'is_staff' => (int) $u['is_staff'],
            'created_at' => $u['created_at'],
        ];
    }
}
