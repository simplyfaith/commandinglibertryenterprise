<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();
$v = new Validator($body);
$v->required('id')->positiveInt('id')->required('reason');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
    $stmt->execute([$body['id']]);
    $sale = $stmt->fetch();
    if (!$sale) { $pdo->rollBack(); Response::notFound('Sale not found'); }
    if ($sale['status'] === 'CANCELLED') { $pdo->rollBack(); Response::error('Sale already cancelled', 422); }

    RBAC::requireLocation($user, (int)$sale['location_id']);

    $isPaidSale = $sale['payment_status'] === 'PAID';
    $isOwnSale = (int)$sale['staff_id'] === (int)$user['id'];
    $highRisk = $isPaidSale || $isOwnSale;

    if ($highRisk && !RBAC::hasPermission($user, 'sales.cancel.approve') && !RBAC::isCompanyWide($user)) {
        $pdo->prepare('
            INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
            VALUES (\'SALE_CANCELLATION\', ?, ?, ?, ?)
        ')->execute([$sale['id'], $sale['location_id'], $user['id'], $body['reason']]);

        $pdo->commit();
        AuditLogger::log($user, 'SALE_CANCELLATION_REQUESTED', 'sale', $sale['id'], $sale, $body, $body['reason'], 'PENDING');
        Response::success(null, 'Cancellation submitted for approval', 202);
    }

    // Never delete — mark cancelled and restore stock.
    $pdo->prepare('UPDATE sales SET status = "CANCELLED", cancelled_reason = ?, cancelled_by = ? WHERE id = ?')
        ->execute([$body['reason'], $user['id'], $sale['id']]);

    $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND location_id = ?')
        ->execute([$sale['quantity'], $sale['product_id'], $sale['location_id']]);
    $pdo->prepare('
        INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, reason, performed_by)
        VALUES (?,?,\'RETURN\',?,\'sale_cancellation\',?,?,?)
    ')->execute([$sale['product_id'], $sale['location_id'], $sale['quantity'], $sale['id'], $body['reason'], $user['id']]);

    $pdo->commit();
    AuditLogger::log($user, 'SALE_CANCELLED', 'sale', $sale['id'], $sale, $body, $body['reason']);
    Response::success(null, 'Sale cancelled');
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
