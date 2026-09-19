<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = \Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If request includes all=1 and user is admin, return all including inactive
    $user = \Auth::optionalAuth();
    $showAll = isset($_GET['all']) && $user && \RBAC::isCompanyWide($user);

    if ($showAll) {
        $stmt = $pdo->query('SELECT * FROM hero_sliders ORDER BY display_order ASC, id DESC');
    } else {
        $stmt = $pdo->query('SELECT * FROM hero_sliders WHERE is_active = 1 ORDER BY display_order ASC, id DESC');
    }

    \Response::success($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = \Auth::requireAuth();
    \RBAC::requirePermission($user, 'products.manage');

    $body = $_POST ?: requestBody();
    if (isset($_FILES['image'])) {
        try {
            $body['image_url'] = \ImageUploader::store($_FILES['image'], $config);
        } catch (InvalidArgumentException $e) {
            \Response::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            error_log('Hero slider upload failed: ' . $e->getMessage());
            \Response::error('The server could not save the image. Please try again.', 500);
        }
    }
    $body['title'] = $body['title'] ?? 'Promotional Banner';
    $v = new \Validator($body);
    $v->required('image_url');
    if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

    $stmt = $pdo->prepare('
        INSERT INTO hero_sliders (title, subtitle, description, cta_text, cta_link, image_url, display_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $body['title'],
        $body['subtitle'] ?? null,
        $body['description'] ?? null,
        $body['cta_text'] ?? 'Explore Catalog',
        $body['cta_link'] ?? '/shop',
        $body['image_url'] ?? null,
        (int)($body['display_order'] ?? 1),
        isset($body['is_active']) ? (int)$body['is_active'] : 1
    ]);

    $newId = (int)$pdo->lastInsertId();
    \AuditLogger::log($user, 'HERO_SLIDE_CREATED', 'hero_sliders', $newId, null, $body);

    \Response::success(['id' => $newId], 'Hero slide created', 201);
}

\Response::error('Method not allowed', 405);
