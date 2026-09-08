<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$pdo = Database::connection();

[$scopeClause, $scopeParams] = RBAC::locationScopeSql($user, 'e.location_id');

$where = ['1=1'];
$params = [];
if (!empty($_GET['location_id'])) { RBAC::requireLocation($user, (int)$_GET['location_id']); $where[] = 'e.location_id = ?'; $params[] = (int)$_GET['location_id']; }
if (!empty($_GET['category'])) { $where[] = 'e.category = ?'; $params[] = $_GET['category']; }
if (!empty($_GET['date_from'])) { $where[] = 'e.spent_at >= ?'; $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to']))   { $where[] = 'e.spent_at <= ?'; $params[] = $_GET['date_to']; }

$sql = "
    SELECT e.*, l.name AS location_name, u.full_name AS recorded_by_name
    FROM expenses e
    JOIN locations l ON l.id = e.location_id
    JOIN users u ON u.id = e.recorded_by
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY e.spent_at DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));
Response::success($stmt->fetchAll());
