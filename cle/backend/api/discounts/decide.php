<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
RBAC::requirePermission($user, 'discounts.approve');

$body = requestBody();
$v = new Validator($body);
$v->required('discount_id')->positiveInt('discount_id')
  ->required('decision')->in('decision', ['APPROVED', 'REJECTED']);
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM discounts WHERE id = ? FOR UPDATE');
    $stmt->execute([$body['discount_id']]);
    $discount = $stmt->fetch();
    if (!$discount) { $pdo->rollBack(); Response::notFound('Discount request not found'); }
    if ($discount['approval_status'] !== 'PENDING') { $pdo->rollBack(); Response::error('Discount already decided', 422); }

    // A staff member can never approve their own discount request.
    if ((int)$discount['requested_by'] === (int)$user['id']) {
        $pdo->rollBack();
        Response::forbidden('You cannot approve your own discount request');
    }

    RBAC::requireLocation($user, (int)$discount['location_id']);

    $pdo->prepare('UPDATE discounts SET approval_status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?')
        ->execute([$body['decision'], $user['id'], $discount['id']]);

    $pdo->prepare('
        UPDATE approval_requests SET status = ?, decided_by = ?, decided_at = NOW()
        WHERE type = "DISCOUNT" AND reference_id = ? AND status = "PENDING"
    ')->execute([$body['decision'], $user['id'], $discount['id']]);

    $pdo->commit();
    AuditLogger::log($user, 'DISCOUNT_' . $body['decision'], 'discount', $discount['id'], $discount, $body, null, $body['decision']);

    Response::success(null, "Discount {$body['decision']}. If approved, ask staff to re-submit the sale/order to finalize it with the approved discount.");
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
