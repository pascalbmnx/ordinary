<?php
/**
 * Document (PDF library) controller.
 */
final class DocumentController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = min(100, max(1, $limit));

        $where = 'WHERE d.is_published = 1';
        if (Auth::isAuthenticated() && (int) (Auth::user()['is_staff'] ?? 0) === 1) {
            $where = '';
        }
        if (isset($_GET['category_id']) && (int) $_GET['category_id'] > 0) {
            $where .= ' AND d.category_id = ' . (int) $_GET['category_id'];
        }
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where .= ' AND d.title LIKE ' . $this->db->quote('%' . $search . '%');
        }

        $total = (int) $this->db->query("SELECT COUNT(*) FROM documents d $where")->fetchColumn();

        $sql = "SELECT d.*, c.name AS category_name
                FROM documents d LEFT JOIN document_categories c ON c.id = d.category_id
                $where ORDER BY d.created_at DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $rows = $this->db->query($sql)->fetchAll();

        Response::success($rows, 'Documents retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->fetch($id);
        if (!$row) Response::notFound('Document not found.');
        Response::success($row);
    }

    /**
     * GET /api/v1/documents/:id/download — stream file to browser.
     */
    public function download(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->fetch($id);
        if (!$row) Response::notFound('Document not found.');
        if ((int) $row['is_published'] !== 1 && (int) (Auth::user()['is_staff'] ?? 0) !== 1) {
            Response::forbidden();
        }

        $path = $row['file_path'];
        if (!is_file($path)) Response::notFound('File missing on server.');

        // Increment download count
        $this->db->prepare('UPDATE documents SET download_count = download_count + 1 WHERE id = ?')
                 ->execute([$id]);

        header('Content-Type: ' . $row['mime_type'] ?: 'application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($row['title'] . '.' . pathinfo($row['original_name'], PATHINFO_EXTENSION)) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function store(): void
    {
        Auth::requirePermission('content.manage');
        $input = Helper::input();
        $errors = [];
        $title = trim($input['title'] ?? '');
        if ($title === '') $errors[] = 'Title is required.';
        if (empty($_FILES['file'])) $errors[] = 'A PDF file is required.';
        if ($errors) Response::badRequest('Validation failed.', $errors);

        $upload = new Upload();
        try {
            $stored = $upload->process($_FILES['file'], 'documents');
        } catch (Exception $e) {
            Response::badRequest($e->getMessage());
        }

        $stmt = $this->db->prepare(
            'INSERT INTO documents (category_id, title, description, original_name, stored_name, file_path, mime_type, file_size, version, is_published, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) ($input['category_id'] ?? 0) ?: null,
            $title,
            trim($input['description'] ?? ''),
            $stored['original_name'],
            $stored['stored_name'],
            $stored['path'],
            $stored['mime'],
            $stored['size'],
            trim($input['version'] ?? '1.0'),
            (int) ($input['is_published'] ?? 1),
            Auth::id(),
        ]);
        $id = (int) $this->db->lastInsertId();
        ActivityLog::log(Auth::id(), 'Document uploaded', 'documents', $id);
        Response::created($this->fetch($id), 'Document uploaded.');
    }

    public function update(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $current = $this->fetch($id);
        if (!$current) Response::notFound('Document not found.');

        $input = Helper::input();
        $fields = [];
        $vals = [];

        // Metadata update
        $meta = ['title', 'description', 'category_id', 'version', 'is_published'];
        foreach ($meta as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }

        // Optional file replacement
        if (!empty($_FILES['file'])) {
            $upload = new Upload();
            try {
                $stored = $upload->process($_FILES['file'], 'documents');
                $fields[] = 'original_name = ?';
                $vals[] = $stored['original_name'];
                $fields[] = 'stored_name = ?';
                $vals[] = $stored['stored_name'];
                $fields[] = 'file_path = ?';
                $vals[] = $stored['path'];
                $fields[] = 'mime_type = ?';
                $vals[] = $stored['mime'];
                $fields[] = 'file_size = ?';
                $vals[] = $stored['size'];
                // Remove old file
                if (is_file($current['file_path'])) @unlink($current['file_path']);
            } catch (Exception $e) {
                Response::badRequest($e->getMessage());
            }
        }

        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE documents SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }
        ActivityLog::log(Auth::id(), 'Document updated', 'documents', $id);
        Response::success($this->fetch($id), 'Document updated.');
    }

    public function destroy(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->fetch($id);
        if (!$row) Response::notFound('Document not found.');
        if (is_file($row['file_path'])) @unlink($row['file_path']);
        $this->db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
        ActivityLog::log(Auth::id(), 'Document deleted', 'documents', $id);
        Response::success(null, 'Document deleted.');
    }

    private function fetch(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, c.name AS category_name FROM documents d
             LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
