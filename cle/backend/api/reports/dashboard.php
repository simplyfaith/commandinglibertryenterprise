<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/RBAC.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') \Response::error('Method not allowed', 405);

$user = \Auth::requireAuth();
$pdo = \Database::connection();

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

[$saleScope, $saleParams] = \RBAC::locationScopeSql($user, 'location_id');

// Staff dashboards intentionally stay narrow: their own activity only, no company/branch totals.
if ($user['role_name'] === 'STAFF') {
    $today = date('Y-m-d');
    $salesToday = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(line_revenue),0) AS revenue FROM sales WHERE location_id = ? AND order_id IS NULL AND DATE(sold_at) = ? AND status='COMPLETED' AND payment_status='PAID'");
    $salesToday->execute([$user['location_id'], $today]);
    $salesTodayRow = $salesToday->fetch();
    $ordersToday = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(o.total),0) AS revenue FROM orders o WHERE o.location_id = ? AND o.status IN ('CONFIRMED','PROCESSING','READY','DISPATCHED','DELIVERED') AND DATE(o.updated_at) = ?");
    $ordersToday->execute([$user['location_id'], $today]);
    $ordersTodayRow = $ordersToday->fetch();
    $ordersPending = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE location_id = ? AND status IN ('PENDING','CONFIRMED','PROCESSING','READY','DISPATCHED')");
    $ordersPending->execute([$user['location_id']]);
    $preordersPending = $pdo->prepare("SELECT COUNT(*) FROM preorders WHERE created_by = ? AND status IN ('OPEN','PAYMENT_PENDING')");
    $preordersPending->execute([$user['id']]);
    $lowStock = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE location_id = ? AND quantity_on_hand <= reorder_level");
    $lowStock->execute([$user['location_id']]);

    \Response::success([
        'scope' => 'STAFF',
        'todays_sales_count' => (int)$salesTodayRow['cnt'] + (int)$ordersTodayRow['cnt'],
        'todays_sales_revenue' => (float)$salesTodayRow['revenue'] + (float)$ordersTodayRow['revenue'],
        'pending_orders' => (int)$ordersPending->fetchColumn(),
        'pending_preorders' => (int)$preordersPending->fetchColumn(),
        'low_stock_alerts' => (int)$lowStock->fetchColumn(),
    ]);
}

// Manager / Admin / Super Admin — location-scoped (managers) or company-wide (admins) financials.
$salesAgg = $pdo->prepare("
    SELECT COALESCE(SUM(line_revenue),0) AS revenue, COALESCE(SUM(line_profit),0) AS profit,
           COUNT(*) AS sale_count
    FROM sales WHERE status='COMPLETED' AND payment_status='PAID' AND order_id IS NULL AND DATE(sold_at) BETWEEN ? AND ? $saleScope
");
$salesAgg->execute(array_merge([$dateFrom, $dateTo], $saleParams));
$salesRow = $salesAgg->fetch();

$confirmedOrdersAgg = $pdo->prepare("SELECT COALESCE(SUM(o.total),0) AS revenue, COUNT(*) AS order_count FROM orders o WHERE o.status IN ('CONFIRMED','PROCESSING','READY','DISPATCHED','DELIVERED') AND DATE(o.updated_at) BETWEEN ? AND ? " . str_replace('location_id', 'o.location_id', $saleScope));
$confirmedOrdersAgg->execute(array_merge([$dateFrom, $dateTo], $saleParams));
$confirmedOrdersRow = $confirmedOrdersAgg->fetch();

$todaySalesAgg = $pdo->prepare("SELECT COALESCE(SUM(line_revenue),0) AS revenue, COUNT(*) AS sale_count FROM sales WHERE status='COMPLETED' AND payment_status='PAID' AND order_id IS NULL AND DATE(sold_at) = ? $saleScope");
$todaySalesAgg->execute(array_merge([date('Y-m-d')], $saleParams));
$todaySalesRow = $todaySalesAgg->fetch();

$todayOrdersAgg = $pdo->prepare("SELECT COALESCE(SUM(o.total),0) AS revenue, COUNT(*) AS order_count FROM orders o WHERE o.status IN ('CONFIRMED','PROCESSING','READY','DISPATCHED','DELIVERED') AND DATE(o.updated_at) = ? " . str_replace('location_id', 'o.location_id', $saleScope));
$todayOrdersAgg->execute(array_merge([date('Y-m-d')], $saleParams));
$todayOrdersRow = $todayOrdersAgg->fetch();

$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$periodSalesAgg = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN DATE(sold_at) >= ? THEN line_revenue ELSE 0 END),0) AS week_revenue,
    COALESCE(SUM(CASE WHEN DATE(sold_at) >= ? THEN line_revenue ELSE 0 END),0) AS month_revenue,
    SUM(CASE WHEN DATE(sold_at) >= ? THEN 1 ELSE 0 END) AS week_count,
    SUM(CASE WHEN DATE(sold_at) >= ? THEN 1 ELSE 0 END) AS month_count
    FROM sales WHERE status='COMPLETED' AND payment_status='PAID' AND order_id IS NULL AND DATE(sold_at) <= ? $saleScope");
$periodSalesAgg->execute(array_merge([$weekStart, $monthStart, $weekStart, $monthStart, date('Y-m-d')], $saleParams));
$periodSalesRow = $periodSalesAgg->fetch();

$periodOrdersAgg = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN DATE(o.updated_at) >= ? THEN o.total ELSE 0 END),0) AS week_revenue,
    COALESCE(SUM(CASE WHEN DATE(o.updated_at) >= ? THEN o.total ELSE 0 END),0) AS month_revenue,
    SUM(CASE WHEN DATE(o.updated_at) >= ? THEN 1 ELSE 0 END) AS week_count,
    SUM(CASE WHEN DATE(o.updated_at) >= ? THEN 1 ELSE 0 END) AS month_count
    FROM orders o WHERE o.status IN ('CONFIRMED','PROCESSING','READY','DISPATCHED','DELIVERED') AND DATE(o.updated_at) <= ? " . str_replace('location_id', 'o.location_id', $saleScope));
$periodOrdersAgg->execute(array_merge([$weekStart, $monthStart, $weekStart, $monthStart, date('Y-m-d')], $saleParams));
$periodOrdersRow = $periodOrdersAgg->fetch();

$chartStart = new DateTimeImmutable($weekStart);
$chartEnd = new DateTimeImmutable(date('Y-m-d'));
$chartDays = [];
for ($day = $chartStart; $day <= $chartEnd; $day = $day->modify('+1 day')) {
    $chartDays[$day->format('Y-m-d')] = 0.0;
}

$chartSalesStmt = $pdo->prepare("SELECT DATE(sold_at) AS sale_date, COALESCE(SUM(line_revenue),0) AS revenue FROM sales WHERE status='COMPLETED' AND payment_status='PAID' AND order_id IS NULL AND DATE(sold_at) BETWEEN ? AND ? $saleScope GROUP BY DATE(sold_at)");
$chartSalesStmt->execute(array_merge([$weekStart, date('Y-m-d')], $saleParams));
foreach ($chartSalesStmt->fetchAll() as $row) {
    if (isset($chartDays[$row['sale_date']])) $chartDays[$row['sale_date']] += (float)$row['revenue'];
}

$chartOrdersStmt = $pdo->prepare("SELECT DATE(o.updated_at) AS sale_date, COALESCE(SUM(o.total),0) AS revenue FROM orders o WHERE o.status IN ('CONFIRMED','PROCESSING','READY','DISPATCHED','DELIVERED') AND DATE(o.updated_at) BETWEEN ? AND ? " . str_replace('location_id', 'o.location_id', $saleScope) . " GROUP BY DATE(o.updated_at)");
$chartOrdersStmt->execute(array_merge([$weekStart, date('Y-m-d')], $saleParams));
foreach ($chartOrdersStmt->fetchAll() as $row) {
    if (isset($chartDays[$row['sale_date']])) $chartDays[$row['sale_date']] += (float)$row['revenue'];
}

$salesChart = array_map(static function ($date, $revenue) {
    return ['date' => $date, 'label' => date('D', strtotime($date)), 'revenue' => $revenue];
}, array_keys($chartDays), array_values($chartDays));

$discountAgg = $pdo->prepare("
    SELECT COALESCE(SUM(discount_amount),0) AS total_discounts
    FROM discounts WHERE approval_status IN ('AUTO_APPROVED','APPROVED') AND DATE(created_at) BETWEEN ? AND ? $saleScope
");
$discountAgg->execute(array_merge([$dateFrom, $dateTo], $saleParams));

$expenseAgg = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS total_expenses
    FROM expenses WHERE approval_status = 'APPROVED' AND spent_at BETWEEN ? AND ? " . str_replace('location_id', 'location_id', $saleScope)
);
$expenseAgg->execute(array_merge([$dateFrom, $dateTo], $saleParams));

$stockValueAgg = $pdo->prepare("
    SELECT COALESCE(SUM(i.quantity_on_hand * p.cost_price),0) AS stock_value
    FROM inventory i JOIN products p ON p.id = i.product_id WHERE 1=1 " . str_replace('location_id', 'i.location_id', $saleScope)
);
$stockValueAgg->execute($saleParams);

$lowStockAgg = $pdo->prepare("SELECT COUNT(*) FROM inventory i WHERE i.quantity_on_hand <= i.reorder_level " . str_replace('location_id', 'i.location_id', $saleScope));
$lowStockAgg->execute($saleParams);

$pendingApprovalsAgg = $pdo->prepare("SELECT COUNT(*) FROM approval_requests WHERE status = 'PENDING'" . str_replace('location_id', 'location_id', $saleScope));
$pendingApprovalsAgg->execute($saleParams);

$activeOrdersAgg = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('PENDING','CONFIRMED','PROCESSING','READY','DISPATCHED') $saleScope");
$activeOrdersAgg->execute($saleParams);

$response = [
    'scope' => \RBAC::isCompanyWide($user) ? 'COMPANY_WIDE' : 'LOCATION',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'total_revenue' => (float)$salesRow['revenue'] + (float)$confirmedOrdersRow['revenue'],
    'total_profit' => (float)$salesRow['profit'],
    'total_sales' => (int)$salesRow['sale_count'],
    'total_discounts' => (float)$discountAgg->fetch()['total_discounts'],
    'total_expenses' => (float)$expenseAgg->fetch()['total_expenses'],
    'total_stock_value' => (float)$stockValueAgg->fetch()['stock_value'],
    'low_stock_products' => (int)$lowStockAgg->fetchColumn(),
    'pending_approvals' => (int)$pendingApprovalsAgg->fetchColumn(),
    'kpis' => [
        'today_sales_revenue' => (float)$todaySalesRow['revenue'] + (float)$todayOrdersRow['revenue'],
        'today_sales_count' => (int)$todaySalesRow['sale_count'] + (int)$todayOrdersRow['order_count'],
        'week_sales_revenue' => (float)$periodSalesRow['week_revenue'] + (float)$periodOrdersRow['week_revenue'],
        'week_sales_count' => (int)$periodSalesRow['week_count'] + (int)$periodOrdersRow['week_count'],
        'month_sales_revenue' => (float)$periodSalesRow['month_revenue'] + (float)$periodOrdersRow['month_revenue'],
        'month_sales_count' => (int)$periodSalesRow['month_count'] + (int)$periodOrdersRow['month_count'],
        'sales_chart' => $salesChart,
        'active_orders_count' => (int)$activeOrdersAgg->fetchColumn(),
    ],
];

// Admin/Super Admin also get a per-branch comparison.
if (\RBAC::isCompanyWide($user)) {
    $branchStmt = $pdo->prepare("
        SELECT l.id, l.name,
               COALESCE(SUM(s.line_revenue),0) AS revenue,
               COALESCE(SUM(s.line_profit),0) AS profit,
               COUNT(s.id) AS sale_count
        FROM locations l
        LEFT JOIN sales s ON s.location_id = l.id AND s.status='COMPLETED'
            AND DATE(s.sold_at) BETWEEN ? AND ?
        GROUP BY l.id, l.name
        ORDER BY revenue DESC
    ");
    $branchStmt->execute([$dateFrom, $dateTo]);
    $response['branch_performance'] = $branchStmt->fetchAll();

    $recentSales = $pdo->query('
        SELECT s.id, s.sale_code AS sale_number, s.quantity AS item_count,
               s.line_revenue AS total_amount, s.status, s.sold_at,
               l.name AS location_name, d.type AS discount_type,
               d.value AS discount_value, COALESCE(d.discount_amount, 0) AS discount_amount
        FROM sales s
        JOIN locations l ON l.id = s.location_id
        LEFT JOIN discounts d ON d.id = s.discount_id
        ORDER BY s.sold_at DESC, s.id DESC
        LIMIT 20
    ')->fetchAll();
    $response['recent_sales'] = $recentSales;

    $inventorySummary = $pdo->query('
        SELECT l.id, l.name,
               COALESCE(SUM(i.quantity_on_hand), 0) AS total_books,
               COUNT(DISTINCT CASE WHEN i.quantity_on_hand > 0 THEN i.product_id END) AS different_books
        FROM locations l
        LEFT JOIN inventory i ON i.location_id = l.id
        WHERE l.is_active = 1
        GROUP BY l.id, l.name
        ORDER BY l.name
    ')->fetchAll();
    $inventoryTotal = $pdo->query('
        SELECT COALESCE(SUM(quantity_on_hand), 0) AS total_books,
               COUNT(DISTINCT CASE WHEN quantity_on_hand > 0 THEN product_id END) AS different_books
        FROM inventory
    ')->fetch();
    $response['inventory_by_location'] = $inventorySummary;
    $response['main_inventory_total'] = $inventoryTotal;

    $expenseSummary = $pdo->query('
        SELECT e.id, e.category, e.amount, e.description, e.approval_status, e.spent_at,
               l.name AS location_name, u.full_name AS recorded_by_name
        FROM expenses e
        JOIN locations l ON l.id = e.location_id
        JOIN users u ON u.id = e.recorded_by
        ORDER BY e.spent_at DESC, e.id DESC
        LIMIT 20
    ')->fetchAll();
    $expenseTotal = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM expenses')->fetchColumn();
    $response['recent_expenses'] = $expenseSummary;
    $response['total_expenses_recorded'] = (float)$expenseTotal;
}

\Response::success($response);
