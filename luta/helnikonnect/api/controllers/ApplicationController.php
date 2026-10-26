<?php
/**
 * Applications controller.
 *   GET    /api/v1/applications
 *   POST   /api/v1/applications
 *   GET    /api/v1/applications/{id}
 *   PATCH  /api/v1/applications/{id}
 *   GET    /api/v1/applications/{id}/history
 */
final class ApplicationController
{
    private PDO $db;
    private SystemSetting $settings;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->settings = new SystemSetting();
    }

    /**
     * GET list. Public/authenticated users see only their own; staff see all.
     */
    public function index(): void
    {
        $user = Auth::user();
        $isStaff = $user && (int) $user['is_staff'] === 1;

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;

        $where = [];
        $params = [];

        if (!$isStaff) {
            // Only authenticated user or phone-based tracking
            $phone = $user ? $user['phone'] : (isset($_GET['phone']) ? trim($_GET['phone']) : null);
            if ($user) {
                $where[] = 'a.user_id = ?'; $params[] = (int) $user['id'];
            } elseif ($phone) {
                $where[] = 'a.phone = ?'; $params[] = $phone;
            } else {
                Response::unauthorized('Authentication or a phone number is required to list applications.');
            }
        } else {
            if (isset($_GET['status']) && $_GET['status'] !== '') { $where[] = 'a.status = ?'; $params[] = $_GET['status']; }
            if (isset($_GET['type']) && (int) $_GET['type'] > 0) { $where[] = 'a.application_type_id = ?'; $params[] = (int) $_GET['type']; }
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $where[] = '(a.reference_no LIKE ? OR a.full_name LIKE ? OR a.phone LIKE ?)';
                $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; $params[] = $like;
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM applications a $whereSql");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $sql = "SELECT a.*, t.name AS type_name
                FROM applications a LEFT JOIN application_types t ON t.id = a.application_type_id
                $whereSql ORDER BY a.submitted_at DESC
                LIMIT $limit OFFSET " . (($page - 1) * $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        Response::success($rows, 'Applications retrieved.', Helper::buildPagination($page, $limit, $total));
    }

    /**
     * POST create — transactional: application + history + notification.
     */
    public function store(): void
    {
        $input = Helper::input();

        // Basic validation
        $errors = [];
        $fullName = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        if ($fullName === '') $errors[] = 'Full name is required.';
        if ($phone === '') { $errors[] = 'Phone number is required.'; }
        elseif (!Helper::validTzPhone($phone)) { $errors[] = 'Invalid phone number.'; }
        if (isset($input['application_type_id']) && (int) $input['application_type_id'] === 0) {
            $errors[] = 'Application type is required.';
        }
        if (!empty($errors)) Response::badRequest('Validation failed.', $errors);

        $user = Auth::user();
        $userId = $user ? (int) $user['id'] : null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO applications
                 (user_id, application_type_id, status, full_name, phone, email,
                  region, district, ward, street, applicant_type, service_required,
                  description, location, preferred_contact, additional_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                isset($input['application_type_id']) ? (int) $input['application_type_id'] : null,
                'Submitted',
                $fullName,
                $phone,
                trim($input['email'] ?? '') ?: null,
                trim($input['region'] ?? '') ?: null,
                trim($input['district'] ?? '') ?: null,
                trim($input['ward'] ?? '') ?: null,
                trim($input['street'] ?? '') ?: null,
                trim($input['applicant_type'] ?? '') ?: null,
                trim($input['service_required'] ?? '') ?: null,
                trim($input['description'] ?? '') ?: null,
                trim($input['location'] ?? '') ?: null,
                trim($input['preferred_contact'] ?? '') ?: null,
                trim($input['additional_notes'] ?? '') ?: null,
            ]);
            $id = (int) $this->db->lastInsertId();

            // Generate reference number NK-YYYY-######
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->settings->get('application_prefix', 'NK')));
            $ref = Helper::generateReference($prefix, $id);
            $this->db->prepare('UPDATE applications SET reference_no = ? WHERE id = ?')->execute([$ref, $id]);

            // Status history
            $this->db->prepare('INSERT INTO application_status_history (application_id, new_status) VALUES (?, ?)')
                     ->execute([$id, 'Submitted']);

            // Notification for the owner
            if ($userId) {
                $this->db->prepare('INSERT INTO notifications (user_id, title, message, type, reference_no) VALUES (?, ?, ?, ?, ?)')
                         ->execute([$userId, 'Application Submitted', 'Your application ' . $ref . ' has been submitted successfully.', 'application', $ref]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            Database::logError('Application store: ' . $e->getMessage());
            Response::serverError();
        }

        Response::created($this->find($id), 'Application submitted successfully.');
    }

    public function show(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $row = $this->find($id);
        if (!$row) Response::notFound('Application not found.');

        $user = Auth::user();
        $isStaff = $user && (int) $user['is_staff'] === 1;
        if (!$isStaff) {
            if ($user && (int) $row['user_id'] === (int) $user['id']) { /* owner */ }
            elseif ($user && $row['phone'] === $user['phone']) { /* same phone */ }
            else Response::forbidden('You do not have access to this application.');
        }

        Response::success($row);
    }

    /**
     * PATCH update status / fields (staff only).
     */
    public function update(): void
    {
        Auth::requirePermission('applications.manage');
        $id = (int) (Router::$routeParams['id'] ?? 0);
        $current = $this->find($id);
        if (!$current) Response::notFound('Application not found.');

        $input = Helper::input();

        // If status is being changed, record history (transactional).
        if (isset($input['status']) && $input['status'] !== $current['status']) {
            $this->db->beginTransaction();
            try {
                $this->db->prepare('UPDATE applications SET status = ? WHERE id = ?')
                         ->execute([$input['status'], $id]);
                $this->db->prepare('INSERT INTO application_status_history (application_id, previous_status, new_status, changed_by)
                                    VALUES (?, ?, ?, ?)')
                         ->execute([$id, $current['status'], $input['status'], Auth::id()]);
                if ($current['user_id']) {
                    $this->db->prepare('INSERT INTO notifications (user_id, title, message, type, reference_no)
                                        VALUES (?, ?, ?, ?, ?)')
                             ->execute([(int) $current['user_id'], 'Application Status Updated',
                                        'Your application ' . $current['reference_no'] . ' is now: ' . $input['status'],
                                        'application', $current['reference_no']]);
                }
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
                Database::logError('Application status update: ' . $e->getMessage());
                Response::serverError();
            }
            ActivityLog::log(Auth::id(), 'Application ' . $current['reference_no'] . ' status changed', 'applications', $id);
            $this->notifyCustomer($current, $input['status']);
        }

        // Other allowed fields
        $allowed = ['assigned_to', 'additional_notes'];
        $fields = []; $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $vals[] = $input[$f]; }
        }
        if ($fields) {
            $vals[] = $id;
            $this->db->prepare('UPDATE applications SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        }

        Response::success($this->find($id), 'Application updated.');
    }

    public function history(): void
    {
        $id = (int) (Router::$routeParams['id'] ?? 0);
        if (!$this->find($id)) Response::notFound('Application not found.');
        $stmt = $this->db->prepare(
            'SELECT h.*, u.full_name AS changed_by_name
             FROM application_status_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.application_id = ? ORDER BY h.changed_at ASC'
        );
        $stmt->execute([$id]);
        Response::success($stmt->fetchAll(), 'Application history retrieved.');
    }

    private function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, t.name AS type_name FROM applications a
             LEFT JOIN application_types t ON t.id = a.application_type_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Notify the customer via their provided contact when an admin updates
     * the application. Sends SMS / WhatsApp / call through the Messenger,
     * regardless of whether the customer has an account.
     */
    private function notifyCustomer(array $current, string $newStatus): void
    {
        $phone = trim((string) ($current['phone'] ?? ''));
        if ($phone === '') {
            return;
        }
        $preferred = strtolower(trim((string) ($current['preferred_contact'] ?? 'sms')));
        $channels = ['sms', 'whatsapp', 'call'];
        if (in_array($preferred, $channels, true)) {
            $channels = [$preferred, ...$channels];
        }
        $message = 'Hello ' . trim((string) ($current['full_name'] ?? 'Customer')) .
                   ', your application ' . $current['reference_no'] .
                   ' is now: ' . $newStatus . '. — HelpKonnect';

        try {
            $messenger = new Messenger();
            $messenger->notify($phone, $channels, $message, [
                'subject'       => 'Application Status Updated',
                'resource_type' => 'application',
                'resource_id'   => (int) $current['id'],
                'reference_no'  => $current['reference_no'],
                'email'         => $current['email'] ?? null,
            ]);
        } catch (Throwable $e) {
            Database::logError('Application notifyCustomer: ' . $e->getMessage());
        }
    }
}
