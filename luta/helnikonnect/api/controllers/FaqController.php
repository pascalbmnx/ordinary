<?php
/**
 * FAQ controller.
 */
final class FaqController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = min(200, max(1, $limit));

        $where = 'WHERE f.is_published = 1';
        if (Auth::isAuthenticated() && (int) (Auth::user()['is_staff'] ?? 0) === 1) {
            $where = '';
        }
        if (isset($_GET['category_id']) && (int) $_GET['category_id'] > 0) {
            $where .= ' AND f.category_id = ' . (int) $_GET['category_id'];
        }
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where .= ' AND (f.question LIKE ' . $this->db->quote('%' . $search . '%')
                    . ' OR f.answer LIKE ' . $this->db->quote('%' . $search . '%') . ')';
        }

        $total = (int) $this->db->query("SELECT COUNT(*) FROM faqs f $where")->fetchColumn();

        $sql = "SELECT f.*, c.name AS category_name
                FROM faqs f LEFT JOIN faq_categories c ON c.id = f.category_id
                $where ORDER BY f.sort_order ASC, f.created_at DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $rows = $this->db->query($sql)->fetchAll();

        Response::success($rows, 'FAQs retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->fetch($id);
        if (!$row) Response::notFound('FAQ not found.');
        Response::success($row);
    }

    public function store(): void
    {
        Auth::requirePermission('content.manage');
        $input = Helper::input();
        $errors = [];
        $question = trim($input['question'] ?? '');
        $answer = trim($input['answer'] ?? '');
        if ($question === '') $errors[] = 'Question is required.';
        if ($answer === '') $errors[] = 'Answer is required.';
        if ($errors) Response::badRequest('Validation failed.', $errors);

        $stmt = $this->db->prepare(
            'INSERT INTO faqs (category_id, question, answer, is_published, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) ($input['category_id'] ?? 0) ?: null,
            $question,
            $answer,
            (int) ($input['is_published'] ?? 1),
            (int) ($input['sort_order'] ?? 0),
        ]);
        $id = (int) $this->db->lastInsertId();
        ActivityLog::log(Auth::id(), 'FAQ created', 'faqs', $id);
        Response::created($this->fetch($id), 'FAQ created.');
    }

    public function update(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('FAQ not found.');

        $input = Helper::input();
        $fields = [];
        $vals = [];
        $allowed = ['question', 'answer', 'category_id', 'is_published', 'sort_order'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }
        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE faqs SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }
        ActivityLog::log(Auth::id(), 'FAQ updated', 'faqs', $id);
        Response::success($this->fetch($id), 'FAQ updated.');
    }

    public function destroy(): void
    {
        Auth::requirePermission('content.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->fetch($id)) Response::notFound('FAQ not found.');
        $this->db->prepare('DELETE FROM faqs WHERE id = ?')->execute([$id]);
        ActivityLog::log(Auth::id(), 'FAQ deleted', 'faqs', $id);
        Response::success(null, 'FAQ deleted.');
    }

    private function fetch(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, c.name AS category_name FROM faqs f
             LEFT JOIN faq_categories c ON c.id = f.category_id WHERE f.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
