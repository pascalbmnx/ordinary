<?php
/**
 * Admin activity logging.
 */
final class ActivityLog
{
    public static function log(?int $adminId, string $action, string $resource = '', $resourceId = null, string $details = ''): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO admin_activity_logs (admin_id, action, resource, resource_id, ip_address, details)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $adminId,
                $action,
                $resource,
                $resourceId !== null ? (int) $resourceId : null,
                Helper::clientIp(),
                $details,
            ]);
        } catch (Throwable $e) {
            Database::logError('ActivityLog: ' . $e->getMessage());
        }
    }
}
