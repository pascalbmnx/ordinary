<?php
final class SystemSetting
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function get(string $key, $default = null): mixed
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? ($row['setting_value'] ?? $default) : $default;
    }

    public function set(string $key, $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }
}
