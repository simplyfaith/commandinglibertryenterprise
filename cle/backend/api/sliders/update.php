<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = \Auth::requireAuth();
\RBAC::requirePermission($user, 'products.manage');

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $v->required('id');
    if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

    $pdo = \Database::connection();
    $stmt = $pdo->prepare('
        UPDATE hero_sliders
        SET title = ?, subtitle = ?, description = ?, cta_text = ?, cta_link = ?,
            image_url = COALESCE(?, image_url), display_order = ?, is_active = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $body['title'],
        $body['subtitle'] ?? null,
        $body['description'] ?? null,
        $body['cta_text'] ?? 'Explore Catalog',
        $body['cta_link'] ?? '/shop',
        $body['image_url'] ?? null,
        (int)($body['display_order'] ?? 1),
        isset($body['is_active']) ? (int)$body['is_active'] : 1,
        (int)$body['id']
    ]);

    \AuditLogger::log($user, 'HERO_SLIDE_UPDATED', 'hero_sliders', (int)$body['id'], null, $body);
    \Response::success(null, 'Hero slide updated');
}

\Response::error('Method not allowed', 405);
