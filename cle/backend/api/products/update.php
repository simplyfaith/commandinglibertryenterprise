<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
if ($user['role_name'] === 'STAFF') {
    RBAC::requireRole($user, ['STAFF']);
} else {
    RBAC::requirePermission($user, 'products.manage');
}

$body = $_POST ?: requestBody();
// Optional SQL DATE fields must be NULL, not an empty string from a form.
if (array_key_exists('preorder_expected_date', $body) && $body['preorder_expected_date'] === '') {
    $body['preorder_expected_date'] = null;
}
if (array_key_exists('preorder_quantity_limit', $body) && $body['preorder_quantity_limit'] === '') {
    $body['preorder_quantity_limit'] = null;
}
if (isset($_FILES['image'])) {
    try {
        $body['image_url'] = ImageUploader::store($_FILES['image'], $config);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        error_log('Product image upload failed: ' . $e->getMessage());
        Response::error('The server could not save the book image. Please try again.', 500);
    }
}
$v = new Validator($body);
$v->required('id')->positiveInt('id')->numeric('discount_percent')->nonNegative('discount_percent');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$body['id']]);
$product = $stmt->fetch();
if (!$product) Response::notFound('Product not found');

if (array_key_exists('discount_percent', $body)) {
    $discountPercent = $body['discount_percent'] === '' ? null : (float)$body['discount_percent'];
    if ($discountPercent !== null && $discountPercent > 100) {
        Response::error('Discount percentage cannot exceed 100', 422);
    }
    $sellingPrice = array_key_exists('selling_price', $body) ? (float)$body['selling_price'] : (float)$product['selling_price'];
    $body['discount_price'] = $discountPercent === null
        ? null
        : round($sellingPrice * (1 - $discountPercent / 100), 2);
}

// Price fields require an explicit reason and, for non-company-wide roles,
// go through an approval request rather than applying immediately.
$priceFields = ['cost_price', 'selling_price', 'discount_price'];
$changingPrice = array_intersect(array_keys($body), $priceFields);

if (!empty($changingPrice) && !RBAC::isCompanyWide($user)) {
    if (empty($body['reason'])) {
        Response::error('A reason is required when requesting a price change', 422);
    }
    $pdo->beginTransaction();
    try {
        foreach ($changingPrice as $field) {
            $logStmt = $pdo->prepare('
                INSERT INTO price_change_log (product_id, field_changed, old_value, new_value, changed_by, reason, approval_status)
                VALUES (?,?,?,?,?,?,\'PENDING\')
            ');
            $logStmt->execute([$product['id'], $field, $product[$field], $body[$field], $user['id'], $body['reason']]);
            $logId = (int)$pdo->lastInsertId();

            $pdo->prepare('
                INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
                VALUES (\'PRICE_CHANGE\', ?, ?, ?, ?)
            ')->execute([$logId, $user['location_id'], $user['id'], $body['reason']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    AuditLogger::log($user, 'PRICE_CHANGE_REQUESTED', 'product', $product['id'], $product, $body, $body['reason'], 'PENDING');
    Response::success(null, 'Price change submitted for approval');
}

// Company-wide roles (or non-price fields) apply immediately.
$fields = [];
$params = [];
$allowed = array_merge($priceFields, [
    'name', 'category_id', 'subcategory_id', 'description', 'author', 'publisher', 'isbn',
    'image_url', 'status', 'is_preorder', 'preorder_expected_date', 'preorder_quantity_limit', 'reorder_level',
]);
foreach ($allowed as $f) {
    if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
}
if (empty($fields)) Response::error('No updatable fields supplied', 422);

$params[] = $product['id'];
$pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

foreach ($changingPrice as $field) {
    $pdo->prepare('
        INSERT INTO price_change_log (product_id, field_changed, old_value, new_value, changed_by, reason, approval_status, approved_by)
        VALUES (?,?,?,?,?,?,\'NOT_REQUIRED\',?)
    ')->execute([$product['id'], $field, $product[$field], $body[$field], $user['id'], $body['reason'] ?? 'Direct admin update', $user['id']]);
}

AuditLogger::log($user, 'PRODUCT_EDITED', 'product', $product['id'], $product, $body);
Response::success(null, 'Product updated');
