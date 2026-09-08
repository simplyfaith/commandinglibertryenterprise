<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::optionalAuth(); // staff creating a walk-in record, or null for self-registration
$body = requestBody();

$v = new Validator($body);
$v->required('full_name')->required('phone');
if (!empty($body['email'])) $v->email('email');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();

$passwordHash = null;
if (!empty($body['password'])) {
    if (strlen($body['password']) < 8) Response::error('Password must be at least 8 characters', 422);
    $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT);
}

$stmt = $pdo->prepare('
    INSERT INTO customers (full_name, email, phone, gender, password_hash, default_address, created_by_user_id)
    VALUES (?,?,?,?,?,?,?)
');
$stmt->execute([
    $body['full_name'], $body['email'] ?? null, $body['phone'], $body['gender'] ?? null,
    $passwordHash, $body['default_address'] ?? null, $user['id'] ?? null,
]);
$id = (int)$pdo->lastInsertId();

AuditLogger::log($user, 'CUSTOMER_CREATED', 'customer', $id, null, ['full_name' => $body['full_name'], 'phone' => $body['phone']]);
Response::success(['id' => $id], 'Customer created', 201);
