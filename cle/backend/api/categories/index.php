<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $defaults = ['Bibles', 'Journals', 'Devotionals', 'Christian Books', 'Children & Teens', 'Stationery', 'School Supplies', 'Business Books', 'African Authors', 'Bundle'];
    $exists = $pdo->prepare('SELECT 1 FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
    foreach ($defaults as $name) {
        $exists->execute([$name]);
        if (!$exists->fetchColumn()) $insert->execute([$name]);
    }

    $stmt = $pdo->query('SELECT id, name, parent_id FROM categories ORDER BY name');
    Response::success($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::requireAuth();
    RBAC::requirePermission($user, 'products.manage');

    $body = requestBody();
    $v = new Validator($body);
    $v->required('name');
    if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

    $name = trim((string)$body['name']);
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) Response::error('Category already exists', 409);

    $pdo->prepare('INSERT INTO categories (name, parent_id) VALUES (?, ?)')
        ->execute([$name, $body['parent_id'] ?? null]);
    $id = (int)$pdo->lastInsertId();
    AuditLogger::log($user, 'CATEGORY_CREATED', 'category', $id, null, $body);
    Response::success(['id' => $id], 'Category created', 201);
}

Response::error('Method not allowed', 405);
