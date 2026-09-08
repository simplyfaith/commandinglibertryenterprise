<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth();
RBAC::requirePermission($user, 'locations.manage');

$body = requestBody();
$v = new Validator($body);
$v->required('id')->positiveInt('id');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT * FROM locations WHERE id = ?');
$stmt->execute([$body['id']]);
$existing = $stmt->fetch();
if (!$existing) Response::notFound('Location not found');

$fields = [];
$params = [];
foreach (['name', 'address', 'state', 'phone', 'email', 'manager_id'] as $f) {
    if (array_key_exists($f, $body)) {
        $fields[] = "$f = ?";
        $params[] = $body[$f];
    }
}
if (array_key_exists('is_active', $body)) {
    $fields[] = 'is_active = ?';
    $params[] = (int)(bool)$body['is_active'];
}

if (empty($fields)) Response::error('No updatable fields supplied', 422);

$params[] = $body['id'];
$pdo->prepare('UPDATE locations SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

AuditLogger::log($user, 'LOCATION_UPDATED', 'location', (int)$body['id'], $existing, $body);
Response::success(null, 'Location updated');
