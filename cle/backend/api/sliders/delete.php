<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireAuth();
RBAC::requirePermission($user, 'products.manage');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = requestBody();
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) Response::error('Missing valid id', 422);

    $pdo = Database::connection();
    $stmt = $pdo->prepare('DELETE FROM hero_sliders WHERE id = ?');
    $stmt->execute([$id]);

    AuditLogger::log($user, 'HERO_SLIDE_DELETED', 'hero_sliders', $id);
    Response::success(null, 'Hero slide deleted');
}

Response::error('Method not allowed', 405);
