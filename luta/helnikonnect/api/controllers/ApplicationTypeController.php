<?php
/**
 * Application types controller.
 */
final class ApplicationTypeController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(): void
    {
        // Client sees active only; staff sees all
        if (Auth::isAuthenticated() && (int) (Auth::user()['is_staff'] ?? 0) === 1) {
            $rows = $this->db->query('SELECT * FROM application_types ORDER BY sort_order ASC')->fetchAll();
        } else {
            $rows = $this->db->query('SELECT * FROM application_types WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        }
        Response::success($rows);
    }

    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $stmt = $this->db->prepare('SELECT * FROM application_types WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Response::notFound('Application type not found.');
        Response::success($row);
    }

    public function store(): void
    {
        Auth::requirePermission('application_types.manage');
        $input = Helper::input();
        $name = trim($input['name'] ?? '');
        if ($name === '') Response::badRequest('Name is required.');

        $stmt = $this->db->prepare(
            'INSERT INTO application_types (name, description, is_active, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            trim($input['description'] ?? ''),
            (int) ($input['is_active'] ?? 1),
            (int) ($input['sort_order'] ?? 0),
        ]);
        $id = (int) $this->db->lastInsertId();
        ActivityLog::log(Auth::id(), 'Application type created', 'application_types', $id);
        Response::created($this->fetch($id), 'Application type created.');
    }

    public function update(): void
    {
        Auth::requirePermission('application_types.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('Application type not found.');

        $input = Helper::input();
        $fields = [];
        $vals = [];
        foreach (['name', 'description', 'is_active', 'sort_order'] as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }
        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE application_types SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }
        ActivityLog::log(Auth::id(), 'Application type updated', 'application_types', $id);
        Response::success($this->fetch($id), 'Application type updated.');
    }

    public function destroy(): void
    {
        Auth::requirePermission('application_types.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('Application type not found.');
        $this->db->prepare('DELETE FROM application_types WHERE id = ?')->execute([$id]);
        ActivityLog::log(Auth::id(), 'Application type deleted', 'application_types', $id);
        Response::success(null, 'Application type deleted.');
    }

    private function fetch(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM application_types WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
