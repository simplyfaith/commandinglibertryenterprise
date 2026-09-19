<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = \Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Public endpoint: works for anonymous shoppers AND logged-in staff.
    // The response shape differs by role — this is enforced here, server-side,
    // never left to the frontend to hide fields.
    $user = \Auth::optionalAuth();
    $isStaff = $user !== null;

    $where = ['1=1'];
    $params = [];

    if (!empty($_GET['category_id'])) { $where[] = 'p.category_id = ?'; $params[] = (int)$_GET['category_id']; }
    if (!empty($_GET['category'])) { $where[] = 'LOWER(c.name) = LOWER(?)'; $params[] = trim($_GET['category']); }
    if (!empty($_GET['status']))      { $where[] = 'p.status = ?';      $params[] = $_GET['status']; }
    if (!empty($_GET['search'])) {
        $where[] = '(p.name LIKE ? OR p.author LIKE ? OR p.isbn LIKE ? OR p.sku LIKE ?)';
        $term = '%' . $_GET['search'] . '%';
        array_push($params, $term, $term, $term, $term);
    }
    // Anonymous customers never see discontinued items.
    if (!$isStaff) { $where[] = "p.status != 'DISCONTINUED'"; }

    $locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;

    $sql = '
        SELECT p.id, p.sku, p.name, p.category_id, p.subcategory_id, p.description,
               p.author, p.publisher, p.isbn, p.image_url, p.selling_price, p.discount_price,
               p.status, p.is_preorder, p.preorder_expected_date, p.preorder_quantity_limit,
               c.name AS category,
               ' . ($isStaff ? 'p.cost_price, p.reorder_level,' : '') . '
               ' . ($locationId ? '(SELECT quantity_on_hand FROM inventory i WHERE i.product_id = p.id AND i.location_id = ' . (int)$locationId . ') AS location_stock,' : '') . '
               (SELECT SUM(quantity_on_hand) FROM inventory i WHERE i.product_id = p.id) AS total_stock
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.created_at DESC
        LIMIT 200
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Belt-and-suspenders: strip sensitive fields again in case a future
    // edit to the SELECT above accidentally includes them.
    if (!$isStaff) {
        foreach ($products as &$p) {
            unset($p['cost_price'], $p['reorder_level']);
        }
    }

    \Response::success($products);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = \Auth::requireAuth();
    if ($user['role_name'] === 'STAFF') {
        \RBAC::requireRole($user, ['STAFF']);
    } else {
        \RBAC::requirePermission($user, 'products.manage');
    }

    $body = $_POST ?: requestBody();
    if (isset($_FILES['image'])) {
        try {
            $body['image_url'] = \ImageUploader::store($_FILES['image'], $config);
        } catch (InvalidArgumentException $e) {
            \Response::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            error_log('Product image upload failed: ' . $e->getMessage());
            \Response::error('The server could not save the book image. Please try again.', 500);
        }
    }
    $v = new \Validator($body);
    $v->required('sku')->required('name')->required('category_id')->required('selling_price')
            ->required('initial_quantity')->numeric('cost_price')->numeric('selling_price')->numeric('initial_quantity')
            ->numeric('discount_percent')
      ->nonNegative('cost_price')->nonNegative('selling_price')
            ->nonNegative('initial_quantity')->nonNegative('discount_percent')
      ->in('status', ['AVAILABLE', 'PREORDER', 'OUT_OF_STOCK', 'DISCONTINUED', 'COMING_SOON']);
    if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

    $isPreorder = ($body['status'] ?? '') === 'PREORDER' ? 1 : 0;
    $discountPercent = ($body['discount_percent'] ?? '') === '' ? null : (float)$body['discount_percent'];
    if ($discountPercent !== null && $discountPercent > 100) {
        \Response::error('Discount percentage cannot exceed 100', 422);
    }
    $discountPrice = $discountPercent === null
        ? null
        : round((float)$body['selling_price'] * (1 - $discountPercent / 100), 2);

    $stmt = $pdo->prepare('
        INSERT INTO products
            (sku, name, category_id, subcategory_id, description, author, publisher, isbn,
             image_url, cost_price, selling_price, discount_price, status, is_preorder,
             preorder_expected_date, preorder_quantity_limit, reorder_level, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $body['sku'], $body['name'], $body['category_id'] ?? null, $body['subcategory_id'] ?? null,
        $body['description'] ?? null, $body['author'] ?? null, $body['publisher'] ?? null, $body['isbn'] ?? null,
        $body['image_url'] ?? null, $body['cost_price'] ?? 0, $body['selling_price'],
        $discountPrice, $body['status'] ?? 'AVAILABLE', $isPreorder,
        $body['preorder_expected_date'] ?? null, $body['preorder_quantity_limit'] ?? null,
        $body['reorder_level'] ?? 5, $user['id'],
    ]);
    $newId = (int)$pdo->lastInsertId();

    $inventoryStmt = $pdo->prepare('
        INSERT INTO inventory (product_id, location_id, quantity_on_hand, reorder_level)
        SELECT ?, id,
               CASE WHEN ? IN (\'ADMIN\', \'SUPER_ADMIN\') OR id = ? THEN ? ELSE 0 END,
               ? FROM locations WHERE is_active = 1
        ON DUPLICATE KEY UPDATE reorder_level = VALUES(reorder_level)
    ');
    $inventoryStmt->execute([
        $newId,
        $user['role_name'],
        (int)($user['location_id'] ?? 0),
        (int)$body['initial_quantity'],
        (int)($body['reorder_level'] ?? 5),
    ]);

    \AuditLogger::log($user, 'PRODUCT_CREATED', 'product', $newId, null, $body);
    \Response::success(['id' => $newId], 'Product created', 201);
}

\Response::error('Method not allowed', 405);
