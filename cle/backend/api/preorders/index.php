<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$pdo = Database::connection();

[$scopeClause, $scopeParams] = RBAC::locationScopeSql($user, 'po.location_id');

$where = ['1=1'];
$params = [];
if (!empty($_GET['location_id'])) { RBAC::requireLocation($user, (int)$_GET['location_id']); $where[] = 'po.location_id = ?'; $params[] = (int)$_GET['location_id']; }
if (!empty($_GET['status'])) { $where[] = 'po.status = ?'; $params[] = $_GET['status']; }

$sql = "
    SELECT po.*, p.name AS product_name,
           c.full_name AS customer_name, c.phone AS customer_phone,
           c.email AS customer_email, c.default_address AS customer_address,
           l.name AS location_name
    FROM preorders po
    JOIN products p ON p.id = po.product_id
    JOIN customers c ON c.id = po.customer_id
    JOIN locations l ON l.id = po.location_id
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY po.created_at DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));
Response::success($stmt->fetchAll());
