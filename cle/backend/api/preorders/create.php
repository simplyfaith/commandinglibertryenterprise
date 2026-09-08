<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

// Public: a customer can create their own preorder from the storefront, OR
// a staff member can create one on behalf of a walk-in customer.
$user = Auth::optionalAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('product_id')->positiveInt('product_id')
  ->required('location_id')->positiveInt('location_id')
  ->required('quantity')->positiveInt('quantity')
  ->required('customer_id')->positiveInt('customer_id')
  ->required('payment_option')->in('payment_option', ['FULL', 'DEPOSIT', 'ON_ARRIVAL']);
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

if (empty($body['terms_acknowledged'])) {
    Response::error('Customer must acknowledge the preorder terms before proceeding', 422);
}

$pdo = Database::connection();

$pStmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$pStmt->execute([$body['product_id']]);
$product = $pStmt->fetch();
if (!$product) Response::notFound('Product not found');

if (!empty($product['preorder_quantity_limit'])) {
    $countStmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity),0) FROM preorders
        WHERE product_id = ? AND status NOT IN ('CANCELLED')
    ");
    $countStmt->execute([$product['id']]);
    $already = (int)$countStmt->fetchColumn();
    if ($already + (int)$body['quantity'] > $product['preorder_quantity_limit']) {
        Response::error('This preorder has reached its quantity limit', 422);
    }
}

$unitPrice = (float)($product['discount_price'] ?? $product['selling_price']);
$total = round($unitPrice * (int)$body['quantity'], 2);
// A payment is only recorded after the provider has verified it. This is the
// amount checkout must collect, not money that has already been received.
$paymentAmount = $body['payment_option'] === 'FULL' ? $total
    : ($body['payment_option'] === 'DEPOSIT' ? min((float)($body['deposit_amount'] ?? 0), $total) : 0);
$amountPaid = 0;
$paymentStatus = 'PENDING';
$status = $paymentAmount > 0 ? 'PAYMENT_PENDING' : 'OPEN';

$code = 'CL-PO-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare('
    INSERT INTO preorders
        (preorder_code, customer_id, product_id, location_id, quantity, unit_price, total_amount,
         amount_paid, payment_option, payment_status, status, expected_arrival_date,
         terms_acknowledged, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)
');
$stmt->execute([
    $code, $body['customer_id'], $product['id'], $body['location_id'], $body['quantity'],
    $unitPrice, $total, $amountPaid, $body['payment_option'], $paymentStatus, $status,
    $product['preorder_expected_date'], $user['id'] ?? null,
]);
$id = (int)$pdo->lastInsertId();

if ($paymentAmount > 0) {
    $paymentStmt = $pdo->prepare("INSERT INTO payments (reference_type, reference_id, provider, amount, status) VALUES ('PREORDER', ?, ?, ?, 'PENDING')");
    $paymentStmt->execute([$id, strtolower((string)($body['payment_provider'] ?? 'paystack')), $paymentAmount]);
}

AuditLogger::log($user, 'PREORDER_CREATED', 'preorder', $id, null, $body);

// NOTE: preorder quantity is intentionally NOT deducted from `inventory` —
// it must never be counted as currently available stock (spec section 9).

Response::success(['id' => $id, 'preorder_code' => $code, 'total' => $total, 'amount_paid' => $amountPaid, 'payment_amount' => $paymentAmount], 'Preorder created', 201);
