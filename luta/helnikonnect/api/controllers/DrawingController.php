<?php
/**
 * Drawing requests controller.
 *   GET    /api/v1/drawings
 *   POST   /api/v1/drawings
 *   GET    /api/v1/drawings/{id}
 *   PATCH  /api/v1/drawings/{id}
 *   GET    /api/v1/drawings/{id}/history
 */
final class DrawingController
{
    private PDO $db;
    private SystemSetting $settings;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->settings = new SystemSetting();
    }

    public function index(): void
    {
        $user = Auth::user();
        $isStaff = $user && (int) $user['is_staff'] === 1;

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;

        $where = [];
        $params = [];
        if (!$isStaff) {
            if ($user) { $where[] = 'd.user_id = ?'; $params[] = (int) $user['id']; }
            else { Response::unauthorized('Authentication required to list drawing requests.'); }
        } else {
            if (isset($_GET['status']) && $_GET['status'] !== '') { $where[] = 'd.status = ?'; $params[] = $_GET['status']; }
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $where[] = '(d.reference_no LIKE ? OR d.applicant_name LIKE ? OR d.phone LIKE ?)';
                $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; $params[] = $like;
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM drawing_requests d $whereSql");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $sql = "SELECT d.* FROM drawing_requests d $whereSql ORDER BY d.submitted_at DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        Response::success($rows, 'Drawing requests retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    public function store(): void
    {
        $input = Helper::input();
        $errors = [];
        $name = trim($input['applicant_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        if ($name === '') $errors[] = 'Applicant name is required.';
        if ($phone === '') { $errors[] = 'Phone number is required.'; }
        elseif (!Helper::validTzPhone($phone)) { $errors[] = 'Invalid phone number.'; }
        if (!empty($errors)) Response::badRequest('Validation failed.', $errors);

        $user = Auth::user();
        $userId = $user ? (int) $user['id'] : null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO drawing_requests
                 (user_id, status, applicant_name, phone, email, location, property_type,
                  electrical_requirement, number_of_rooms, estimated_load, description, additional_instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                'Submitted',
                $name,
                $phone,
                trim($input['email'] ?? '') ?: null,
                trim($input['location'] ?? '') ?: null,
                trim($input['property_type'] ?? '') ?: null,
                trim($input['electrical_requirement'] ?? '') ?: null,
                isset($input['number_of_rooms']) && $input['number_of_rooms'] !== '' ? (int) $input['number_of_rooms'] : null,
                trim($input['estimated_load'] ?? '') ?: null,
                trim($input['description'] ?? '') ?: null,
                trim($input['additional_instructions'] ?? '') ?: null,
            ]);
            $id = (int) $this->db->lastInsertId();

            $prefix = 'DR';
            $ref = Helper::generateReference($prefix, $id);
            $this->db->prepare('UPDATE drawing_requests SET reference_no = ? WHERE id = ?')->execute([$ref, $id]);

            $this->db->prepare('INSERT INTO drawing_status_history (drawing_request_id, new_status) VALUES (?, ?)')
                     ->execute([$id, 'Submitted']);

            if ($userId) {
                $this->db->prepare('INSERT INTO notifications (user_id, title, message, type, reference_no) VALUES (?, ?, ?, ?, ?)')
                         ->execute([$userId, 'Drawing Request Submitted', 'Your drawing request ' . $ref . ' has been submitted.', 'drawing', $ref]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            Database::logError('Drawing store: ' . $e->getMessage());
            Response::serverError();
        }

        Response::created($this->find($id), 'Drawing request submitted.');
    }

    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->find($id);
        if (!$row) Response::notFound('Drawing request not found.');
        $user = Auth::user();
        $isStaff = $user && (int) $user['is_staff'] === 1;
        if (!$isStaff) {
            if (!($user && (int) $row['user_id'] === (int) $user['id'])) {
                Response::forbidden('You do not have access to this drawing request.');
            }
        }
        Response::success($row);
    }

    public function update(): void
    {
        Auth::requirePermission('drawings.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $current = $this->find($id);
        if (!$current) Response::notFound('Drawing request not found.');

        $input = Helper::input();

        if (isset($input['status']) && $input['status'] !== $current['status']) {
            $this->db->beginTransaction();
            try {
                $this->db->prepare('UPDATE drawing_requests SET status = ? WHERE id = ?')->execute([$input['status'], $id]);
                $this->db->prepare('INSERT INTO drawing_status_history (drawing_request_id, previous_status, new_status, changed_by) VALUES (?, ?, ?, ?)')
                         ->execute([$id, $current['status'], $input['status'], Auth::id()]);
                if ($current['user_id']) {
                    $this->db->prepare('INSERT INTO notifications (user_id, title, message, type, reference_no) VALUES (?, ?, ?, ?, ?)')
                             ->execute([(int) $current['user_id'], 'Drawing Status Updated',
                                        'Your drawing request ' . $current['reference_no'] . ' is now: ' . $input['status'],
                                        'drawing', $current['reference_no']]);
                }
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
                Database::logError('Drawing status update: ' . $e->getMessage());
                Response::serverError();
            }
            ActivityLog::log(Auth::id(), 'Drawing ' . $current['reference_no'] . ' status changed', 'drawings', $id);
            $this->notifyCustomer($current, $input['status']);
        }

        $allowed = ['assigned_to', 'additional_instructions'];
        $fields = []; $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $vals[] = $input[$f]; }
        }
        if ($fields) { $vals[] = $id; $this->db->prepare('UPDATE drawing_requests SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals); }

        Response::success($this->find($id), 'Drawing request updated.');
    }

    public function history(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->find($id)) Response::notFound('Drawing request not found.');
        $stmt = $this->db->prepare(
            'SELECT h.*, u.full_name AS changed_by_name FROM drawing_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.drawing_request_id = ? ORDER BY h.changed_at ASC'
        );
        $stmt->execute([$id]);
        Response::success($stmt->fetchAll(), 'Drawing history retrieved.');
    }

    private function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM drawing_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Notify the customer via their provided contact when an admin updates
     * the drawing request, regardless of whether the customer has an account.
     */
    private function notifyCustomer(array $current, string $newStatus): void
    {
        $phone = trim((string) ($current['phone'] ?? ''));
        if ($phone === '') {
            return;
        }
        $message = 'Hello ' . trim((string) ($current['applicant_name'] ?? 'Customer')) .
                   ', your drawing request ' . $current['reference_no'] .
                   ' is now: ' . $newStatus . '. — HelpKonnect';

        try {
            $messenger = new Messenger();
            $messenger->notify($phone, ['sms', 'whatsapp', 'call'], $message, [
                'subject'       => 'Drawing Status Updated',
                'resource_type' => 'drawing',
                'resource_id'   => (int) $current['id'],
                'reference_no'  => $current['reference_no'],
                'email'         => $current['email'] ?? null,
            ]);
        } catch (Throwable $e) {
            Database::logError('Drawing notifyCustomer: ' . $e->getMessage());
        }
    }
}
