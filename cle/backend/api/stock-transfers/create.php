<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('product_id')->positiveInt('product_id')
  ->required('quantity')->positiveInt('quantity')
  ->required('source_location_id')->positiveInt('source_location_id')
  ->required('destination_location_id')->positiveInt('destination_location_id');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

if ((int)$body['source_location_id'] === (int)$body['destination_location_id']) {
    Response::error('Source and destination locations must differ', 422);
}

// A staff member may only request a transfer OUT of a location they belong to.
RBAC::requireLocation($user, (int)$body['source_location_id']);

$pdo = Database::connection();

$check = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE product_id = ? AND location_id = ?');
$check->execute([$body['product_id'], $body['source_location_id']]);
$available = (int)($check->fetchColumn() ?: 0);
if ($available < (int)$body['quantity']) {
    Response::error("Insufficient stock at source location. Available: $available", 422);
}

$stmt = $pdo->prepare('
    INSERT INTO stock_transfers (product_id, quantity, source_location_id, destination_location_id, reason, requested_by, status)
    VALUES (?,?,?,?,?,?,\'REQUESTED\')
');
$stmt->execute([
    $body['product_id'], $body['quantity'], $body['source_location_id'],
    $body['destination_location_id'], $body['reason'] ?? null, $user['id'],
]);
$id = (int)$pdo->lastInsertId();

AuditLogger::log($user, 'STOCK_TRANSFER_REQUESTED', 'stock_transfer', $id, null, $body);
Response::success(['id' => $id], 'Transfer requested', 201);
