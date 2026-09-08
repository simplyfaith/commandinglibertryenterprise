<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed', 405);

$user = Auth::optionalAuth();
$pdo = Database::connection();

$where = ['1=1'];
$params = [];
$scopeClause = '';
$scopeParams = [];

if ($user) {
    // Staff/admin view — scoped by their permitted locations.
    [$scopeClause, $scopeParams] = RBAC::locationScopeSql($user, 'o.location_id');
    if (!empty($_GET['location_id'])) { RBAC::requireLocation($user, (int)$_GET['location_id']); $where[] = 'o.location_id = ?'; $params[] = (int)$_GET['location_id']; }
} elseif (!empty($_GET['customer_id'])) {
    // Customer's own order history — a customer may only ever see their own orders.
    $where[] = 'o.customer_id = ?';
    $params[] = (int)$_GET['customer_id'];
} else {
    Response::unauthorized('Authentication or customer_id is required');
}

if (!empty($_GET['status'])) { $where[] = 'o.status = ?'; $params[] = $_GET['status']; }

$sql = "
        SELECT o.*, c.full_name AS customer_name, c.phone AS customer_phone,
            c.email AS customer_email, l.name AS location_name,
            p.provider AS payment_provider, p.status AS payment_status,
            p.amount AS payment_amount
    FROM orders o
    JOIN customers c ON c.id = o.customer_id
    JOIN locations l ON l.id = o.location_id
        LEFT JOIN payments p ON p.reference_type = 'ORDER' AND p.reference_id = o.id
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY o.created_at DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));
$orders = $stmt->fetchAll();

foreach ($orders as &$order) {
    $itemStmt = $pdo->prepare('
        SELECT oi.*, p.name AS product_name FROM order_items oi
        JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?
    ');
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll();
    // Customers never see cost price.
    if (!$user) {
        foreach ($order['items'] as &$it) unset($it['unit_cost_price']);
    }
}

Response::success($orders);
