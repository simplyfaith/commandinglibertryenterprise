<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Writes to the append-only audit_logs table. The application layer never
 * issues UPDATE or DELETE against this table — enforce that additionally
 * with a DB user that only has INSERT+SELECT on audit_logs in production.
 */
class AuditLogger
{
    public static function log(
        ?array $user,
        string $action,
        ?string $recordType = null,
        ?int $recordId = null,
        $previousValue = null,
        $newValue = null,
        ?string $reason = null,
        ?string $approvalStatus = null
    ): void {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO audit_logs
                (user_id, role_name, location_id, action, record_type, record_id,
                 previous_value, new_value, reason, ip_address, user_agent, approval_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $user['id'] ?? null,
            $user['role_name'] ?? null,
            $user['location_id'] ?? null,
            $action,
            $recordType,
            $recordId,
            $previousValue !== null ? json_encode($previousValue) : null,
            $newValue !== null ? json_encode($newValue) : null,
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $approvalStatus,
        ]);
    }
}
