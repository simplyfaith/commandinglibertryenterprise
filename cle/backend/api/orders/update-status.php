<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PATCH'], true)) \Response::error('Method not allowed', 405);

$user = \Auth::requireAuth();
$body = requestBody();
$v = new \Validator($body);
$v->required('id')->positiveInt('id')->required('status')
  ->in('status', ['CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'DELIVERED', 'CANCELLED']);
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

$pdo = \Database::connection();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $stmt->execute([$body['id']]);
    $order = $stmt->fetch();
    if (!$order) { $pdo->rollBack(); \Response::notFound('Order not found'); }

    \RBAC::requireLocation($user, (int)$order['location_id']);

    if ($body['status'] === 'CANCELLED') {
        if ($order['status'] === 'CANCELLED') { $pdo->rollBack(); \Response::error('Order already cancelled', 422); }

        // Paid order cancellations require approval (section 52).
        $paymentStmt = $pdo->prepare("SELECT status FROM payments WHERE reference_type='ORDER' AND reference_id = ? ORDER BY id DESC LIMIT 1");
        $paymentStmt->execute([$order['id']]);
        $paymentStatus = $paymentStmt->fetchColumn();

        if ($paymentStatus === 'PAID' && !\RBAC::hasPermission($user, 'sales.cancel.approve') && !\RBAC::isCompanyWide($user)) {
            if (empty($body['reason'])) { $pdo->rollBack(); \Response::error('A cancellation reason is required', 422); }
            $pdo->prepare('
                INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
                VALUES (\'ORDER_CANCELLATION\', ?, ?, ?, ?)
            ')->execute([$order['id'], $order['location_id'], $user['id'], $body['reason']]);
            $pdo->commit();
            \AuditLogger::log($user, 'ORDER_CANCELLATION_REQUESTED', 'order', $order['id'], $order, $body, $body['reason'], 'PENDING');
            \Response::success(null, 'Cancellation submitted for approval', 202);
        }

        // Restore stock for every item.
        $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$order['id']]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND location_id = ?')
                ->execute([$item['quantity'], $item['product_id'], $order['location_id']]);
            $pdo->prepare('
                INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, reason, performed_by)
                VALUES (?,?,\'RETURN\',?,\'order_cancellation\',?,?,?)
            ')->execute([$item['product_id'], $order['location_id'], $item['quantity'], $order['id'], $body['reason'] ?? null, $user['id']]);
        }

        $pdo->prepare('UPDATE orders SET status = "CANCELLED", cancelled_reason = ?, cancelled_by = ? WHERE id = ?')
            ->execute([$body['reason'] ?? null, $user['id'], $order['id']]);
        $pdo->prepare('UPDATE sales SET status = "CANCELLED", payment_status = "CANCELLED", cancelled_reason = ?, cancelled_by = ? WHERE order_id = ? AND status <> "CANCELLED"')
            ->execute([$body['reason'] ?? null, $user['id'], $order['id']]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$body['status'], $order['id']]);
    }

    $pdo->commit();
    \AuditLogger::log($user, 'ORDER_UPDATED', 'order', $order['id'], $order, $body);
    \Response::success(null, 'Order updated');
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
