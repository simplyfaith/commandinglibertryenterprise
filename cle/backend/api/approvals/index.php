<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
RBAC::requireRole($user, ['SUPER_ADMIN', 'ADMIN', 'MANAGER']);

$pdo = Database::connection();
[$scopeClause, $scopeParams] = RBAC::locationScopeSql($user, 'ar.location_id');

$where = ["ar.status = 'PENDING'"];
$params = [];
if (!empty($_GET['type'])) { $where[] = 'ar.type = ?'; $params[] = $_GET['type']; }

$sql = "
    SELECT ar.*, u.full_name AS requested_by_name, l.name AS location_name
    FROM approval_requests ar
    JOIN users u ON u.id = ar.requested_by
    LEFT JOIN locations l ON l.id = ar.location_id
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY ar.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));

// Managers/staff can never approve their own request — filter those out here too
// (in addition to the enforcement inside each type-specific decide endpoint).
$rows = array_values(array_filter($stmt->fetchAll(), fn($r) => (int)$r['requested_by'] !== (int)$user['id']));

Response::success($rows);
