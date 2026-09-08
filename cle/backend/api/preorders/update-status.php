<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PATCH'], true)) Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();
$v = new Validator($body);
$v->required('id')->positiveInt('id')->required('status')
  ->in('status', ['PAYMENT_PENDING', 'CONFIRMED', 'AWAITING_STOCK', 'STOCK_RECEIVED', 'PROCESSING', 'READY', 'DISPATCHED', 'DELIVERED', 'CANCELLED']);
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT * FROM preorders WHERE id = ?');
$stmt->execute([$body['id']]);
$preorder = $stmt->fetch();
if (!$preorder) Response::notFound('Preorder not found');

RBAC::requireLocation($user, (int)$preorder['location_id']);

$pdo->beginTransaction();
try {
    $lockedStmt = $pdo->prepare('SELECT * FROM preorders WHERE id = ? FOR UPDATE');
    $lockedStmt->execute([$preorder['id']]);
    $preorder = $lockedStmt->fetch();
    if (!$preorder) {
        $pdo->rollBack();
        Response::notFound('Preorder not found');
    }

    $status = $body['status'];
    $saleDestination = 'Preorder ' . $preorder['preorder_code'];
    $saleCheck = $pdo->prepare("SELECT id FROM sales WHERE destination = ? AND order_type = 'ONLINE' LIMIT 1");
    $saleCheck->execute([$saleDestination]);
    $existingSale = $saleCheck->fetch();

    if ($status === 'PAYMENT_PENDING') {
        if ($existingSale) {
            $pdo->rollBack();
            Response::error('This preorder already has recorded sales revenue and cannot be marked unpaid', 422);
        }
        $pdo->prepare("UPDATE payments SET status = 'PENDING' WHERE reference_type = 'PREORDER' AND reference_id = ? AND status <> 'PAID'")
            ->execute([$preorder['id']]);
        $pdo->prepare("UPDATE preorders SET status = 'PAYMENT_PENDING', amount_paid = 0, payment_status = 'PENDING' WHERE id = ?")
            ->execute([$preorder['id']]);
    } elseif ($status === 'CONFIRMED') {
        // Confirming a preorder in the portal is the staff acknowledgement that
        // the full payment has been received. Record it once in payments and sales.
        $paymentStmt = $pdo->prepare("SELECT id FROM payments WHERE reference_type = 'PREORDER' AND reference_id = ? ORDER BY id DESC LIMIT 1");
        $paymentStmt->execute([$preorder['id']]);
        $payment = $paymentStmt->fetch();
        if ($payment) {
            $pdo->prepare("UPDATE payments SET amount = ?, status = 'PAID', verified_at = NOW(), verified_by_system = 0 WHERE id = ?")
                ->execute([$preorder['total_amount'], $payment['id']]);
        } else {
            $pdo->prepare("INSERT INTO payments (reference_type, reference_id, provider, amount, status, verified_at, verified_by_system) VALUES ('PREORDER', ?, 'manual', ?, 'PAID', NOW(), 0)")
                ->execute([$preorder['id'], $preorder['total_amount']]);
        }
        $pdo->prepare("UPDATE preorders SET status = 'CONFIRMED', amount_paid = total_amount, payment_status = 'PAID' WHERE id = ?")
            ->execute([$preorder['id']]);

        if (!$existingSale) {
            $productStmt = $pdo->prepare('SELECT cost_price, selling_price FROM products WHERE id = ?');
            $productStmt->execute([$preorder['product_id']]);
            $product = $productStmt->fetch();
            if (!$product) {
                $pdo->rollBack();
                Response::notFound('Product not found');
            }

            $quantity = (int)$preorder['quantity'];
            $lineRevenue = (float)$preorder['total_amount'];
            $actualUnitPrice = $quantity > 0 ? round($lineRevenue / $quantity, 2) : 0;
            $unitCost = (float)$product['cost_price'];
            $lineProfit = round($lineRevenue - ($unitCost * $quantity), 2);
            $profitPercent = $actualUnitPrice > 0 ? round((($actualUnitPrice - $unitCost) / $actualUnitPrice) * 100, 2) : 0;
            $saleCode = 'CL-S-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $saleStmt = $pdo->prepare("\n+                INSERT INTO sales\n+                    (sale_code, product_id, location_id, staff_id, customer_id, quantity, cost_price,\n+                     selling_price, actual_unit_price, line_revenue, line_profit, profit_percent,\n+                     order_type, destination, customer_paid_transport, enterprise_transport_cost, payment_status, status)\n+                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,? ,?, 0, 0, 'PAID', 'COMPLETED')\n+            ");
            $saleStmt->execute([
                $saleCode, $preorder['product_id'], $preorder['location_id'], $user['id'], $preorder['customer_id'],
                $quantity, $unitCost, $product['selling_price'], $actualUnitPrice, $lineRevenue, $lineProfit,
                $profitPercent, 'ONLINE', $saleDestination,
            ]);
        }
    } else {
        $pdo->prepare('UPDATE preorders SET status = ? WHERE id = ?')->execute([$status, $preorder['id']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

AuditLogger::log($user, 'PREORDER_STATUS_UPDATED', 'preorder', $preorder['id'], $preorder, $body);
Response::success(null, $body['status'] === 'CONFIRMED' ? 'Payment received and preorder revenue recorded' : 'Preorder status updated');
