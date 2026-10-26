<?php
/**
 * General utilities.
 */
final class Helper
{
    /**
     * Read request body as array.
     * Prefers JSON (application/json), but also merges multipart/form-data
     * fields from $_POST so file upload endpoints (e.g. documents) can read
     * their metadata alongside $_FILES.
     */
    public static function input(): array
    {
        $data = [];
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
        return $data;
    }

    public static function sanitize(?string $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate a Tanzanian phone number (+255XXXXXXXXX) loosely.
     */
    public static function validTzPhone(string $phone): bool
    {
        $p = preg_replace('/\s+/', '', $phone);
        return (bool) preg_match('/^(?:\+?255|0)\d{9}$/', $p);
    }

    public static function validEmail(?string $email): bool
    {
        return $email === null || filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate a unique reference number like NK-2026-000124.
     */
    public static function generateReference(string $prefix, int $id): string
    {
        $prefix = strtoupper($prefix ?: 'NK');
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        return sprintf('%s-%s-%06d', $prefix, date('Y'), $id);
    }

    /**
     * Create a random token.
     */
    public static function randomToken(int $length = 64): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Create a secure API session token.
     */
    public static function makeSessionToken(): string
    {
        $prefix = 'hnc_';
        return $prefix . self::randomToken(60);
    }

    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_-]+/', '-', $text);
        return trim($text, '-');
    }

    public static function applyPagination(int $page, int $limit): string
    {
        $offset = ($page - 1) * $limit;
        return "LIMIT $limit OFFSET $offset";
    }

    public static function buildPagination(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    public static function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
