<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();

// Only company-wide roles get unrestricted audit access. Managers may see
// logs for their own location only. Plain staff cannot view audit logs at all.
if ($user['role_name'] === 'STAFF') Response::forbidden('Staff cannot view audit logs');

$pdo = Database::connection();

$where = ['1=1'];
$params = [];

if (!RBAC::isCompanyWide($user)) {
    $where[] = 'location_id = ?';
    $params[] = $user['location_id'];
}
if (!empty($_GET['user_id']))   { $where[] = 'user_id = ?'; $params[] = (int)$_GET['user_id']; }
if (!empty($_GET['action']))    { $where[] = 'action = ?'; $params[] = $_GET['action']; }
if (!empty($_GET['location_id'])) {
    RBAC::requireLocation($user, (int)$_GET['location_id']);
    $where[] = 'location_id = ?';
    $params[] = (int)$_GET['location_id'];
}
if (!empty($_GET['date_from'])) { $where[] = 'created_at >= ?'; $params[] = $_GET['date_from'] . ' 00:00:00'; }
if (!empty($_GET['date_to']))   { $where[] = 'created_at <= ?'; $params[] = $_GET['date_to'] . ' 23:59:59'; }

$sql = '
    SELECT al.*, u.full_name AS user_name
    FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY al.created_at DESC
    LIMIT 500
';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll());
