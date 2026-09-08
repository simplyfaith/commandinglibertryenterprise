<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::optionalAuth();
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Everyone can see the list of active locations (needed for storefront/branch picker),
    // but only company-wide roles see inactive ones and manager/staff-count details.
    if ($user && RBAC::isCompanyWide($user)) {
        $stmt = $pdo->query('
            SELECT l.*, u.full_name AS manager_name,
                   (SELECT COUNT(*) FROM users s WHERE s.location_id = l.id) AS staff_count
            FROM locations l LEFT JOIN users u ON u.id = l.manager_id
            ORDER BY l.name
        ');
    } else {
        $stmt = $pdo->prepare('SELECT id, code, name, address, state, phone, email, is_active FROM locations WHERE is_active = 1 ORDER BY name');
        $stmt->execute();
    }
    Response::success($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RBAC::requirePermission($user, 'locations.manage');

    $body = requestBody();
    $v = new Validator($body);
    $v->required('code')->required('name');
    if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

    $stmt = $pdo->prepare('
        INSERT INTO locations (code, name, address, state, phone, email, is_active)
        VALUES (?,?,?,?,?,?,1)
    ');
    $stmt->execute([
        $body['code'], $body['name'], $body['address'] ?? null,
        $body['state'] ?? null, $body['phone'] ?? null, $body['email'] ?? null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    AuditLogger::log($user, 'LOCATION_CREATED', 'location', $newId, null, $body);
    Response::success(['id' => $newId], 'Location created', 201);
}

Response::error('Method not allowed', 405);
