<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('product_id')->positiveInt('product_id')
  ->required('location_id')->positiveInt('location_id')
  ->required('quantity')->positiveInt('quantity')
  ->required('reason_type')
  ->in('reason_type', ['DAMAGED', 'LOST', 'COUNTING_ERROR', 'EXPIRED', 'RETURNED', 'CORRECTION'])
  ->required('direction')->in('direction', ['ADD', 'REMOVE']);
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

RBAC::requireLocation($user, (int)$body['location_id']);

// Large adjustments require manager/admin approval rather than applying instantly.
$APPROVAL_THRESHOLD = 20;
$needsApproval = !RBAC::isCompanyWide($user) && $user['role_name'] === 'STAFF' && (int)$body['quantity'] > $APPROVAL_THRESHOLD;

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $txType = $body['direction'] === 'ADD' ? 'ADJUSTMENT_ADD' : 'ADJUSTMENT_REMOVE';

    if ($needsApproval) {
        // Record the request but do not touch inventory until approved.
        $pdo->prepare('
            INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
            VALUES (\'STOCK_ADJUSTMENT\', ?, ?, ?, ?)
        ')->execute([$body['product_id'], $body['location_id'], $user['id'], $body['reason_type'] . ': ' . ($body['note'] ?? '')]);

        AuditLogger::log($user, 'STOCK_ADJUSTMENT_REQUESTED', 'product', (int)$body['product_id'], null, $body, $body['reason_type'], 'PENDING');
        $pdo->commit();
        Response::success(null, 'Adjustment submitted for approval');
    }

    // Ensure an inventory row exists.
    $pdo->prepare('INSERT IGNORE INTO inventory (product_id, location_id, quantity_on_hand) VALUES (?,?,0)')
        ->execute([$body['product_id'], $body['location_id']]);

    if ($body['direction'] === 'REMOVE') {
        // Never allow stock to go negative.
        $check = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
        $check->execute([$body['product_id'], $body['location_id']]);
        $current = (int)($check->fetchColumn() ?: 0);
        if ($current < (int)$body['quantity']) {
            $pdo->rollBack();
            Response::error('Adjustment would result in negative stock', 422);
        }
        $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND location_id = ?')
            ->execute([$body['quantity'], $body['product_id'], $body['location_id']]);
    } else {
        $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND location_id = ?')
            ->execute([$body['quantity'], $body['product_id'], $body['location_id']]);
    }

    $pdo->prepare('
        INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reason, performed_by)
        VALUES (?,?,?,?,\'stock_adjustment\',?,?)
    ')->execute([$body['product_id'], $body['location_id'], $txType, $body['quantity'], $body['reason_type'] . ': ' . ($body['note'] ?? ''), $user['id']]);

    $pdo->commit();
    AuditLogger::log($user, 'STOCK_ADJUSTED', 'product', (int)$body['product_id'], null, $body, $body['reason_type']);
    Response::success(null, 'Stock adjusted');
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
