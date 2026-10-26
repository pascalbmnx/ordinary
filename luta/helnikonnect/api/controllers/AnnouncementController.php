<?php
/**
 * Announcements controller.
 */
final class AnnouncementController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * GET /api/v1/announcements  (public: published only)
     */
    public function index(): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = min(50, max(1, $limit));

        // Only published for client; if authenticated staff sees all
        $where = 'WHERE a.is_published = 1';
        if (Auth::isAuthenticated() && (int) (Auth::user()['is_staff'] ?? 0) === 1) {
            $where = '';
        }
        if (isset($_GET['category']) && $_GET['category'] !== '') {
            $where .= ($where ? ' AND ' : 'WHERE ') . 'a.category = ' . $this->db->quote($_GET['category']);
        }
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where .= ($where ? ' AND ' : 'WHERE ') . '(a.title LIKE ' . $this->db->quote('%' . $search . '%')
                   . ' OR a.content LIKE ' . $this->db->quote('%' . $search . '%') . ')';
        }

        $total = (int) $this->db->query("SELECT COUNT(*) FROM announcements a $where")->fetchColumn();

        $sql = "SELECT a.* FROM announcements a $where
                ORDER BY COALESCE(a.published_at, a.created_at) DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $rows = $this->db->query($sql)->fetchAll();

        Response::success($rows, 'Announcements retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    /**
     * GET /api/v1/announcements/:id
     */
    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? $_GET['id'] ?? 0);
        $stmt = $this->db->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Response::notFound('Announcement not found.');
        Response::success($row);
    }

    /**
     * POST /api/v1/announcements  (admin)
     */
    public function store(): void
    {
        Auth::requirePermission('content.manage');
        $input = Helper::input();
        $errors = [];
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';
        if ($errors) Response::badRequest('Validation failed.', $errors);

        $stmt = $this->db->prepare(
            'INSERT INTO announcements (title, content, category, priority, is_published, published_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $title,
            $content,
            trim($input['category'] ?? 'Notice'),
            trim($input['priority'] ?? 'Normal'),
            (int) ($input['is_published'] ?? 1),
            ($input['is_published'] ?? 1) ? date('Y-m-d H:i:s') : null,
            Auth::id(),
        ]);
        $id = (int) $this->db->lastInsertId();
        ActivityLog::log(Auth::id(), 'Announcement published', 'announcements', $id);
        Response::created($this->fetch($id), 'Announcement created.');
    }

    /**
     * PATCH /api/v1/announcements/:id  (admin)
     */
    public function update(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? $_GET['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('Announcement not found.');

        $input = Helper::input();
        $fields = [];
        $vals = [];
        $allowed = ['title', 'content', 'category', 'priority', 'is_published'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }
        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE announcements SET ' . implode(', ', $fields) . ' WHERE id = ?')
                     ->execute($vals);
        }
        ActivityLog::log(Auth::id(), 'Announcement updated', 'announcements', $id);
        Response::success($this->fetch($id), 'Announcement updated.');
    }

    /**
     * DELETE /api/v1/announcements/:id  (admin)
     */
    public function destroy(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? $_GET['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('Announcement not found.');
        $this->db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        ActivityLog::log(Auth::id(), 'Announcement deleted', 'announcements', $id);
        Response::success(null, 'Announcement deleted.');
    }

    private function fetch(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
