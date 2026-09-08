<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$pdo = Database::connection();

[$scopeClause, $scopeParams] = RBAC::locationScopeSql($user, 'i.location_id');

$where = ['1=1'];
$params = [];
if (!empty($_GET['location_id'])) {
    RBAC::requireLocation($user, (int)$_GET['location_id']);
    $where[] = 'i.location_id = ?';
    $params[] = (int)$_GET['location_id'];
}
if (!empty($_GET['low_stock'])) {
    $where[] = 'i.quantity_on_hand <= i.reorder_level';
}

$sql = "
    SELECT i.*, p.name AS product_name, p.sku, l.name AS location_name
    FROM inventory i
    JOIN products p ON p.id = i.product_id
    JOIN locations l ON l.id = i.location_id
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY p.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));
Response::success($stmt->fetchAll());
