<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('category')->in('category', ['TRANSPORT', 'PRINTING', 'PACKAGING', 'DELIVERY', 'UTILITIES', 'STOCK_PURCHASE', 'OTHER'])
  ->required('amount')->numeric('amount')->nonNegative('amount')
  ->required('location_id')->positiveInt('location_id')
  ->required('spent_at');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

RBAC::requireLocation($user, (int)$body['location_id']);

$LARGE_EXPENSE_THRESHOLD = 50000; // ₦ — configurable in a real settings table
$needsApproval = (float)$body['amount'] > $LARGE_EXPENSE_THRESHOLD && !RBAC::isCompanyWide($user);

$pdo = Database::connection();
$stmt = $pdo->prepare('
    INSERT INTO expenses (category, amount, description, location_id, recorded_by, receipt_url, approval_status, spent_at)
    VALUES (?,?,?,?,?,?,?,?)
');
$stmt->execute([
    $body['category'], $body['amount'], $body['description'] ?? null, $body['location_id'],
    $user['id'], $body['receipt_url'] ?? null, $needsApproval ? 'PENDING' : 'APPROVED', $body['spent_at'],
]);
$id = (int)$pdo->lastInsertId();

if ($needsApproval) {
    $pdo->prepare('
        INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
        VALUES (\'EXPENSE\', ?, ?, ?, ?)
    ')->execute([$id, $body['location_id'], $user['id'], $body['description'] ?? null]);
}

AuditLogger::log($user, 'EXPENSE_CREATED', 'expense', $id, null, $body, null, $needsApproval ? 'PENDING' : 'APPROVED');
Response::success(['id' => $id], $needsApproval ? 'Expense submitted for approval' : 'Expense recorded', 201);
