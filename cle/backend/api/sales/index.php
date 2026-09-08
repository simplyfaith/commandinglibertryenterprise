<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/RBAC.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') \Response::error('Method not allowed', 405);

$user = \Auth::requireAuth();
$pdo = \Database::connection();

[$scopeClause, $scopeParams] = \RBAC::locationScopeSql($user, 's.location_id');

$where = ['1=1'];
$params = [];
if (!empty($_GET['location_id'])) { \RBAC::requireLocation($user, (int)$_GET['location_id']); $where[] = 's.location_id = ?'; $params[] = (int)$_GET['location_id']; }
if (!empty($_GET['date_from']))   { $where[] = 's.sold_at >= ?'; $params[] = $_GET['date_from'] . ' 00:00:00'; }
if (!empty($_GET['date_to']))     { $where[] = 's.sold_at <= ?'; $params[] = $_GET['date_to'] . ' 23:59:59'; }
if (!empty($_GET['staff_id']))    { $where[] = 's.staff_id = ?'; $params[] = (int)$_GET['staff_id']; }

// Staff see their own sales plus online orders assigned to their branch.
if ($user['role_name'] === 'STAFF') { $where[] = '(s.staff_id = ? OR (s.staff_id IS NULL AND s.order_type = \'ONLINE\'))'; $params[] = $user['id']; }

$sql = "
    SELECT s.id, s.sale_code, s.product_id, p.name AS product_name, s.location_id, l.name AS location_name,
           s.staff_id, u.full_name AS staff_name, s.customer_id, s.quantity,
           s.selling_price, s.actual_unit_price, s.line_revenue,
           d.type AS discount_type, d.value AS discount_value, d.discount_amount,
           " . (\RBAC::isCompanyWide($user) || $user['role_name'] === 'MANAGER' ? 's.cost_price, s.line_profit, s.profit_percent,' : '') . "
           s.order_type, s.destination, s.customer_paid_transport, s.enterprise_transport_cost,
           s.payment_status, s.status, s.sold_at
    FROM sales s
    JOIN products p ON p.id = s.product_id
    LEFT JOIN discounts d ON d.id = s.discount_id
    JOIN locations l ON l.id = s.location_id
    LEFT JOIN users u ON u.id = s.staff_id
    WHERE " . implode(' AND ', $where) . " $scopeClause
    ORDER BY s.sold_at DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $scopeParams));
\Response::success($stmt->fetchAll());
