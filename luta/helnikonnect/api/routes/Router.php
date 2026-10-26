<?php
/**
 * REST router. Parses path + method and dispatches to controllers.
 */
final class Router
{
    private static array $routes = [];
    public static array $routeParams = [];

    public static function add(string $method, string $pattern, callable $handler, array $permission = null): void
    {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#',
            'handler' => $handler,
            'permission' => $permission,
        ];
    }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = self::resolvePath();
        $parts = explode('/', trim($path, '/'));
        $entity = $parts[0] ?? '';
        $id = $parts[1] ?? null;
        $sub = $parts[2] ?? null;

        self::$routeParams = ['id' => is_numeric($id) ? (int) $id : 0, 'sub' => $sub];

        // Special-case: auth sub-routes (auth/register, auth/login, ...)
        if ($entity === 'auth' && $id !== null) {
            $map = [
                'register' => 'register',
                'login' => 'login',
                'logout' => 'logout',
                'me' => 'me',
                'forgot-password' => 'forgotPassword',
                'reset-password' => 'resetPassword',
            ];
            $methodName = $map[$id] ?? null;
            if (!$methodName) { Response::notFound('Unknown auth endpoint.'); return; }
            $auth = new AuthController();
            $auth->$methodName();
            return;
        }

        $controller = self::controllerFor($entity);
        if (!$controller) {
            Response::notFound('Unknown endpoint: /' . $entity);
        }

        $action = self::actionFor($method, $id, $sub);

        $className = $controller['class'];
        $methodName = $controller['methods'][$action] ?? null;
        if (!$methodName) {
            Response::notFound('Method not supported for this resource.');
        }

        $instance = new $className();
        $instance->$methodName();
    }

    private static function resolvePath(): string
    {
        $route = trim($_GET['route'] ?? '', '/');
        if ($route !== '') {
            return $route;
        }
        // Fallback: parse from REQUEST_URI (e.g. /helpkonnect/api/v1/applications/5)
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        // Find the "v1" segment
        if (preg_match('#v1/(.+)$#', $uri, $m)) {
            return trim($m[1], '/');
        }
        return '';
    }

    private static function controllerFor(string $entity): ?array
    {
        $map = [
            'auth' => ['class' => 'AuthController', 'methods' => [
                'register' => 'register',
                'login' => 'login',
                'logout' => 'logout',
                'me' => 'me',
                'forgot-password' => 'forgotPassword',
                'reset-password' => 'resetPassword',
            ]],
            'announcements' => ['class' => 'AnnouncementController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'delete' => 'destroy',
            ]],
            'faqs' => ['class' => 'FaqController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'delete' => 'destroy',
            ]],
            'documents' => ['class' => 'DocumentController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'delete' => 'destroy', 'download' => 'download',
            ]],
            'application-types' => ['class' => 'ApplicationTypeController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'delete' => 'destroy',
            ]],
            'settings' => ['class' => 'SettingController', 'methods' => [
                'list' => 'index', 'update' => 'update',
            ]],
            'applications' => ['class' => 'ApplicationController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'history' => 'history',
            ]],
            'drawings' => ['class' => 'DrawingController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'history' => 'history',
            ]],
            'conversations' => ['class' => 'ConversationController', 'methods' => [
                'list' => 'index', 'create' => 'store', 'one' => 'show',
                'update' => 'update', 'messages' => 'messages',
            ]],
        ];
        return $map[$entity] ?? null;
    }

    private static function actionFor(string $method, ?string $id, ?string $sub): string
    {
        switch ($method) {
            case 'GET':
                if ($id && $sub === 'download') return 'download';
                if ($id && $sub === 'history') return 'history';
                if ($id && $sub === 'messages') return 'messages';
                if ($id) return 'one';
                return 'list';
            case 'POST':
                if ($sub === 'messages') return 'messages';
                return 'create';
            case 'PATCH':
            case 'PUT':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return 'none';
        }
    }
}
