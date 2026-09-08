<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);

// Public checkout endpoint — a logged-in customer or a guest (customer_id supplied
// after account creation on the frontend) can call this. Staff can also create an
// in-store order on a customer's behalf.
$user = \Auth::optionalAuth();
$body = requestBody();

$v = new \Validator($body);
$v->required('customer_id')->positiveInt('customer_id')
  ->required('location_id')->positiveInt('location_id')
  ->required('items');
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

$items = $body['items'];
    if (!is_array($items) || empty($items)) \Response::error('Order must contain at least one item', 422);

$pdo = \Database::connection();
$pdo->beginTransaction();
try {
    $subtotal = 0;
    $lineData = [];

    foreach ($items as $item) {
        if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
            $pdo->rollBack();
            \Response::error('Each item requires a valid product_id and positive quantity', 422);
        }

        $pStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
        $pStmt->execute([$item['product_id']]);
        $product = $pStmt->fetch();
        if (!$product) { $pdo->rollBack(); \Response::notFound("Product {$item['product_id']} not found"); }

        if ($product['is_preorder'] || $product['status'] === 'PREORDER') {
            $pdo->rollBack();
            \Response::error("Product '{$product['name']}' is a preorder item — submit it via /api/preorders/create.php separately, not this cart", 422);
        }

        $invStmt = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
        $invStmt->execute([$product['id'], $body['location_id']]);
        $onHand = (int)($invStmt->fetchColumn() ?: 0);
        if ($onHand < (int)$item['quantity']) {
            $pdo->rollBack();
            \Response::error("Insufficient stock for '{$product['name']}'. Available: $onHand", 422);
        }

        $unitPrice = $product['discount_price'] !== null && (float)$product['discount_price'] < (float)$product['selling_price']
            ? (float)$product['discount_price']
            : (float)$product['selling_price'];
        $lineTotal = round($unitPrice * (int)$item['quantity'], 2);
        $subtotal += $lineTotal;
        $lineData[] = [
            'product' => $product,
            'quantity' => (int)$item['quantity'],
            'unit_selling_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    $customerPaidTransport = (float)($body['customer_paid_transport'] ?? 0);
    $enterpriseTransport = (float)($body['enterprise_transport_cost'] ?? 0);
    $discountTotal = max(0, min((float)($body['discount_total'] ?? 0), $subtotal));
    $total = max(0, round($subtotal - $discountTotal + $customerPaidTransport, 2));

    $orderCode = 'CL-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    $oStmt = $pdo->prepare('
        INSERT INTO orders
            (order_code, order_kind, channel, customer_id, location_id, status, subtotal,
             discount_total, customer_paid_transport, enterprise_transport_cost, total,
             delivery_address, staff_id)
        VALUES (?,\'AVAILABLE\',?,?,?,\'PENDING\',?,?,?,?,?,?,?)
    ');
    $oStmt->execute([
        $orderCode, $user ? 'IN_STORE' : 'ONLINE', $body['customer_id'], $body['location_id'],
        $subtotal, $discountTotal, $customerPaidTransport, $enterpriseTransport, $total,
        $body['delivery_address'] ?? null, $user['id'] ?? null,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    foreach ($lineData as $line) {
        $pdo->prepare('
            INSERT INTO order_items (order_id, product_id, quantity, unit_cost_price, unit_selling_price, line_total)
            VALUES (?,?,?,?,?,?)
        ')->execute([
            $orderId, $line['product']['id'], $line['quantity'], $line['product']['cost_price'],
            $line['unit_selling_price'], $line['line_total'],
        ]);
        // Stock is reserved/deducted at order creation to prevent overselling;
        // if payment fails, cancelling the order (see cancel endpoint) restores it.
        $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND location_id = ?')
            ->execute([$line['quantity'], $line['product']['id'], $body['location_id']]);
        if ($user && isset($user['id'])) {
            $pdo->prepare('
                INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, performed_by)
                VALUES (?,?,\'SALE\',?,\'order\',?,?)
            ')->execute([$line['product']['id'], $body['location_id'], $line['quantity'], $orderId, $user['id']]);
        }
    }

    $pdo->prepare('
        INSERT INTO payments (reference_type, reference_id, provider, amount, status)
        VALUES (\'ORDER\', ?, ?, ?, \'PENDING\')
    ')->execute([$orderId, strtolower((string)($body['payment_provider'] ?? 'paystack')), $total]);

    $pdo->commit();
    \AuditLogger::log($user, 'ORDER_CREATED', 'order', $orderId, null, ['order_code' => $orderCode, 'total' => $total]);

    \Response::success(['id' => $orderId, 'order_code' => $orderCode, 'total' => $total], 'Order created — awaiting payment confirmation', 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
