<?php
/**
 * Conversations / messaging controller.
 *   GET    /api/v1/conversations
 *   POST   /api/v1/conversations
 *   GET    /api/v1/conversations/{id}
 *   PATCH  /api/v1/conversations/{id}      (staff: status / assignment)
 *   GET    /api/v1/conversations/{id}/messages
 *   POST   /api/v1/conversations/{id}/messages
 */
final class ConversationController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * List conversations. Staff see all; regular users only see the
     * conversations they participate in.
     */
    public function index(): void
    {
        Auth::requireAuth();
        $user = Auth::user();
        $isStaff = (int) ($user['is_staff'] ?? 0) === 1;

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;

        $where = [];
        $params = [];
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where[] = 'c.status = ?';
            $params[] = $_GET['status'];
        }
        if (!$isStaff) {
            $where[] = 'EXISTS (SELECT 1 FROM conversation_participants cp
                        WHERE cp.conversation_id = c.id AND cp.user_id = ?)';
            $params[] = (int) $user['id'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM conversations c $whereSql");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $sql = "SELECT c.*, u.full_name AS owner_name
                FROM conversations c LEFT JOIN users u ON u.id = c.user_id
                $whereSql ORDER BY c.updated_at DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $rows = array_map(fn($r) => $this->withUnread($r), $rows);
        Response::success($rows, 'Conversations retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    /**
     * Create a conversation. Optionally open with an initial message.
     */
    public function store(): void
    {
        Auth::requireAuth();
        $user = Auth::user();

        $input = Helper::input();
        $errors = [];
        $subject = trim($input['subject'] ?? '');
        if ($subject === '') $errors[] = 'Subject is required.';
        if (!empty($errors)) Response::badRequest('Validation failed.', $errors);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO conversations (reference_no, subject, status, user_id)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([null, $subject, 'Open', (int) $user['id']]);
            $id = (int) $this->db->lastInsertId();

            $ref = Helper::generateReference('C', $id);
            $this->db->prepare('UPDATE conversations SET reference_no = ? WHERE id = ?')->execute([$ref, $id]);

            // Creator participates in the thread.
            $this->db->prepare('INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)')
                     ->execute([$id, (int) $user['id']]);

            // Optional opening message.
            $body = trim($input['body'] ?? '');
            if ($body !== '') {
                $senderType = $isStaff = (int) ($user['is_staff'] ?? 0) === 1 ? 'admin' : 'user';
                $this->db->prepare('INSERT INTO messages (conversation_id, sender_id, sender_type, body) VALUES (?, ?, ?, ?)')
                         ->execute([$id, (int) $user['id'], $senderType, $body]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            Database::logError('Conversation store: ' . $e->getMessage());
            Response::serverError();
        }

        Response::created($this->find($id), 'Conversation created.');
    }

    public function show(): void
    {
        Auth::requireAuth();
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->accessible($id);
        if (!$row) Response::notFound('Conversation not found.');
        Response::success($row);
    }

    /**
     * Staff: change status / assign. Users can only close their own thread.
     */
    public function update(): void
    {
        Auth::requireAuth();
        $user = Auth::user();
        $isStaff = (int) ($user['is_staff'] ?? 0) === 1;
        if (!$isStaff) Auth::requirePermission('conversations.manage');

        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->accessible($id);
        if (!$row) Response::notFound('Conversation not found.');

        $input = Helper::input();
        $fields = [];
        $vals = [];

        $allowed = ['status', 'assigned_to', 'subject'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'status' && !$isStaff) {
                    // A user may only close their own open conversation.
                    if ($input[$f] !== 'Closed') Response::forbidden('Only staff can change status.');
                    if ((int) $row['user_id'] !== (int) $user['id']) Response::forbidden();
                }
                $fields[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }
        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE conversations SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }
        ActivityLog::log(Auth::id(), 'Conversation updated', 'conversations', $id);
        Response::success($this->find($id), 'Conversation updated.');
    }

    /**
     * Messages: GET lists, POST sends.
     */
    public function messages(): void
    {
        Auth::requireAuth();
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->accessible($id);
        if (!$row) Response::notFound('Conversation not found.');

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') {
            $stmt = $this->db->prepare(
                'SELECT m.*, u.full_name AS sender_name FROM messages m
                 LEFT JOIN users u ON u.id = m.sender_id
                 WHERE m.conversation_id = ? ORDER BY m.created_at ASC'
            );
            $stmt->execute([$id]);
            $messages = $stmt->fetchAll();

            // Mark all as read for this viewer.
            $user = Auth::user();
            $this->db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ?')
                     ->execute([$id, (int) $user['id']]);

            Response::success($messages, 'Messages retrieved.');
        } else {
            $user = Auth::user();
            $input = Helper::input();
            $body = trim($input['body'] ?? '');
            if ($body === '') Response::badRequest('Message body is required.');

            $senderType = (int) ($user['is_staff'] ?? 0) === 1 ? 'admin' : 'user';
            $this->db->prepare('INSERT INTO messages (conversation_id, sender_id, sender_type, body) VALUES (?, ?, ?, ?)')
                     ->execute([$id, (int) $user['id'], $senderType, $body]);
            $mid = (int) $this->db->lastInsertId();

            // Notify the counterpart (owner for staff replies, staff for user reply).
            if ($senderType === 'admin' && $row['user_id']) {
                $this->db->prepare('INSERT INTO notifications (user_id, title, message, type, reference_no)
                                    VALUES (?, ?, ?, ?, ?)')
                         ->execute([(int) $row['user_id'], 'New Reply', 'You have a new reply on ' . $row['reference_no'], 'conversation', $row['reference_no']]);
            }

            $stmt = $this->db->prepare(
                'SELECT m.*, u.full_name AS sender_name FROM messages m
                 LEFT JOIN users u ON u.id = m.sender_id WHERE m.id = ?'
            );
            $stmt->execute([$mid]);
            Response::created($stmt->fetch(), 'Message sent.');
        }
    }

    // ---------- helpers ----------

    private function accessible(int $id): ?array
    {
        $row = $this->find($id);
        if (!$row) return null;
        $user = Auth::user();
        $isStaff = (int) ($user['is_staff'] ?? 0) === 1;
        if ($isStaff) return $this->withUnread($row);

        $stmt = $this->db->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ?');
        $stmt->execute([$id, (int) $user['id']]);
        return $stmt->fetchColumn() ? $this->withUnread($row) : null;
    }

    private function withUnread(array $row): array
    {
        $user = Auth::user();
        if (!$user) return $row;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0'
        );
        $stmt->execute([(int) $row['id'], (int) $user['id']]);
        $row['unread_count'] = (int) $stmt->fetchColumn();
        return $row;
    }

    private function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.full_name AS owner_name FROM conversations c
             LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
