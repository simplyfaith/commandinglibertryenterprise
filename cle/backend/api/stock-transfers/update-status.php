<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PATCH'], true)) Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('id')->positiveInt('id')
  ->required('action')->in('action', ['APPROVE', 'DISPATCH', 'RECEIVE', 'CANCEL']);
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE');
    $stmt->execute([$body['id']]);
    $transfer = $stmt->fetch();
    if (!$transfer) { $pdo->rollBack(); Response::notFound('Transfer not found'); }

    $action = $body['action'];
    $validTransitions = [
        'APPROVE'  => ['from' => 'REQUESTED', 'to' => 'APPROVED'],
        'DISPATCH' => ['from' => 'APPROVED',  'to' => 'DISPATCHED'],
        'RECEIVE'  => ['from' => 'DISPATCHED','to' => 'RECEIVED'],
        'CANCEL'   => ['from' => null,        'to' => 'CANCELLED'],
    ];
    $rule = $validTransitions[$action];

    if ($action !== 'CANCEL' && $transfer['status'] !== $rule['from']) {
        $pdo->rollBack();
        Response::error("Cannot $action a transfer in status {$transfer['status']}", 422);
    }
    if ($action === 'CANCEL' && in_array($transfer['status'], ['RECEIVED', 'CANCELLED'], true)) {
        $pdo->rollBack();
        Response::error('Transfer already completed or cancelled', 422);
    }

    switch ($action) {
        case 'APPROVE':
            RBAC::requirePermission($user, 'stock.transfer.approve');
            RBAC::requireLocation($user, (int)$transfer['source_location_id']);
            $pdo->prepare('UPDATE stock_transfers SET status = "APPROVED", approved_by = ?, approved_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $transfer['id']]);
            break;

        case 'DISPATCH':
            RBAC::requireLocation($user, (int)$transfer['source_location_id']);
            // Deduct from source now; stock is "in transit" and not double-counted anywhere.
            $check = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
            $check->execute([$transfer['product_id'], $transfer['source_location_id']]);
            $available = (int)($check->fetchColumn() ?: 0);
            if ($available < $transfer['quantity']) {
                $pdo->rollBack();
                Response::error('Insufficient stock at source to dispatch', 422);
            }
            $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND location_id = ?')
                ->execute([$transfer['quantity'], $transfer['product_id'], $transfer['source_location_id']]);
            $pdo->prepare('
                INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, performed_by)
                VALUES (?,?,\'TRANSFER_OUT\',?,\'stock_transfer\',?,?)
            ')->execute([$transfer['product_id'], $transfer['source_location_id'], $transfer['quantity'], $transfer['id'], $user['id']]);

            $pdo->prepare('UPDATE stock_transfers SET status = "DISPATCHED", dispatched_by = ?, dispatched_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $transfer['id']]);
            break;

        case 'RECEIVE':
            RBAC::requireLocation($user, (int)$transfer['destination_location_id']);
            $pdo->prepare('INSERT IGNORE INTO inventory (product_id, location_id, quantity_on_hand) VALUES (?,?,0)')
                ->execute([$transfer['product_id'], $transfer['destination_location_id']]);
            $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND location_id = ?')
                ->execute([$transfer['quantity'], $transfer['product_id'], $transfer['destination_location_id']]);
            $pdo->prepare('
                INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, performed_by)
                VALUES (?,?,\'TRANSFER_IN\',?,\'stock_transfer\',?,?)
            ')->execute([$transfer['product_id'], $transfer['destination_location_id'], $transfer['quantity'], $transfer['id'], $user['id']]);

            $pdo->prepare('UPDATE stock_transfers SET status = "RECEIVED", received_by = ?, received_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $transfer['id']]);
            break;

        case 'CANCEL':
            // If already dispatched, the stock deducted from source must be restored.
            if ($transfer['status'] === 'DISPATCHED') {
                $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND location_id = ?')
                    ->execute([$transfer['quantity'], $transfer['product_id'], $transfer['source_location_id']]);
            }
            $pdo->prepare('UPDATE stock_transfers SET status = "CANCELLED" WHERE id = ?')->execute([$transfer['id']]);
            break;
    }

    $pdo->commit();
    AuditLogger::log($user, "STOCK_TRANSFER_$action", 'stock_transfer', $transfer['id'], $transfer, $body);
    Response::success(null, "Transfer $action successful");
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
