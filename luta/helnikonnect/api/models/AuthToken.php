<?php
/**
 * Auth token model (stateless bearer tokens in DB).
 */
final class AuthToken
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $userId, string $token, int $ttl): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auth_tokens (user_id, token, expires_at, user_agent, ip_address)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)'
        );
        $stmt->execute([
            $userId,
            $token,
            $ttl,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            Helper::clientIp(),
        ]);
    }

    public function findValid(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM auth_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        $t = $stmt->fetch();
        return $t ?: null;
    }

    public function revoke(string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_tokens WHERE token = ?');
        $stmt->execute([$token]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
