<?php
/**
 * Authentication context — resolves the current user + permissions.
 */
final class Auth
{
    private static ?array $context = null;

    /**
     * Resolve the bearer token from the Authorization header.
     */
    public static function resolveToken(): ?string
    {
        // Under Apache + CGI/FastCGI the Authorization header is not exposed
        // in $_SERVER['HTTP_AUTHORIZATION']; it may surface as
        // REDIRECT_HTTP_AUTHORIZATION (set by mod_rewrite) or 'Authorization'.
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['Authorization']
            ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return $_REQUEST['token'] ?? null;
    }

    /**
     * Load the authenticated user context (user + role + permissions).
     */
    public static function context(): ?array
    {
        if (self::$context !== null) {
            return self::$context;
        }
        $token = self::resolveToken();
        if (!$token) {
            return null;
        }
        $tokenModel = new AuthToken();
        $tokenRow = $tokenModel->findValid($token);
        if (!$tokenRow) {
            return null;
        }
        $userModel = new User();
        $user = $userModel->findById((int) $tokenRow['user_id']);
        if (!$user || (int) $user['is_active'] !== 1) {
            return null;
        }
        $role = $userModel->roleOf((int) $user['id']);
        $permissions = $userModel->permissionsOf((int) ($role['id'] ?? 0));

        self::$context = [
            'user' => $user,
            'token' => $tokenRow['token'],
            'role' => $role,
            'permissions' => $permissions,
        ];
        return self::$context;
    }

    public static function user(): ?array
    {
        return self::context()['user'] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function roleName(): string
    {
        return self::context()['role']['name'] ?? 'guest';
    }

    public static function isAuthenticated(): bool
    {
        return self::user() !== null;
    }

    public static function requireAuth(): array
    {
        $ctx = self::context();
        if (!$ctx) {
            Response::unauthorized('Authentication required.');
        }
        return $ctx;
    }

    /**
     * Require a specific permission code. Super admins have wildcard access.
     */
    public static function requirePermission(string $permission): void
    {
        $ctx = self::requireAuth();
        $perms = $ctx['permissions'];
        if (in_array('manage_all', $perms, true)) {
            return;
        }
        if (!in_array($permission, $perms, true)) {
            Response::forbidden('You do not have permission to perform this action.');
        }
    }
}