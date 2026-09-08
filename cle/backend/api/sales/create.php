<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user = Auth::requireAuth();
$body = requestBody();

$v = new Validator($body);
$v->required('product_id')->positiveInt('product_id')
  ->required('location_id')->positiveInt('location_id')
  ->required('quantity')->positiveInt('quantity')
  ->required('order_type')->in('order_type', ['WALK_IN', 'ONLINE', 'PHONE']);
if (isset($body['discount_percent'])) $v->numeric('discount_percent')->nonNegative('discount_percent');
if (isset($body['discount_amount']))  $v->numeric('discount_amount')->nonNegative('discount_amount');
$v->numeric('customer_paid_transport')->nonNegative('customer_paid_transport');
$v->numeric('enterprise_transport_cost')->nonNegative('enterprise_transport_cost');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

RBAC::requireLocation($user, (int)$body['location_id']);

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    // Lock the product + inventory row for the duration of this sale.
    $pStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
    $pStmt->execute([$body['product_id']]);
    $product = $pStmt->fetch();
    if (!$product) { $pdo->rollBack(); Response::notFound('Product not found'); }

    if ($product['is_preorder'] || $product['status'] === 'PREORDER') {
        $pdo->rollBack();
        Response::error('This product is preorder-only; use the preorders endpoint instead', 422);
    }

    $invStmt = $pdo->prepare('SELECT * FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
    $invStmt->execute([$body['product_id'], $body['location_id']]);
    $inventory = $invStmt->fetch();
    $onHand = (int)($inventory['quantity_on_hand'] ?? 0);

    if ($onHand < (int)$body['quantity']) {
        $pdo->rollBack();
        Response::error("Insufficient stock. Available: $onHand", 422);
    }

    $qty = (int)$body['quantity'];
    $unitCost = (float)$product['cost_price'];
    $unitSelling = (float)$product['selling_price'];
    $originalTotal = $unitSelling * $qty;

    // --- Discount calculation (server-authoritative; never trust a client-sent final price) ---
    $discountPercent = (float)($body['discount_percent'] ?? 0);
    $discountAmount = (float)($body['discount_amount'] ?? 0);
    if ($discountPercent > 0) {
        $discountAmount = round($originalTotal * ($discountPercent / 100), 2);
    } elseif ($discountAmount > 0 && $originalTotal > 0) {
        $discountPercent = round(($discountAmount / $originalTotal) * 100, 2);
    }
    // Discount can never exceed the sale total (final price floor of zero).
    $discountAmount = min($discountAmount, $originalTotal);
    $finalTotal = max(0, round($originalTotal - $discountAmount, 2));
    $actualUnitPrice = $qty > 0 ? round($finalTotal / $qty, 2) : 0;

    $discountId = null;
    $approvalStatus = 'AUTO_APPROVED';
    $needsApproval = $discountAmount > 0 && !RBAC::discountWithinOwnAuthority($user, $discountPercent);

    if ($discountAmount > 0) {
        if (($body['discount_reason'] ?? '') === 'OTHER' && empty($body['discount_note'])) {
            $pdo->rollBack();
            Response::error('An explanation is required when discount reason is OTHER', 422);
        }
        $approvalStatus = $needsApproval ? 'PENDING' : 'AUTO_APPROVED';

        $dStmt = $pdo->prepare('
            INSERT INTO discounts
                (scope, type, value, reason_code, reason_note, requested_by, location_id,
                 approval_status, reference_type, original_price, discount_amount, final_price)
            VALUES (\'ORDER\', ?, ?, ?, ?, ?, ?, ?, \'SALE\', ?, ?, ?)
        ');
        $dStmt->execute([
            $discountPercent > 0 ? 'PERCENTAGE' : 'FIXED',
            $discountPercent > 0 ? $discountPercent : $discountAmount,
            $body['discount_reason'] ?? 'OTHER',
            $body['discount_note'] ?? null,
            $user['id'], $body['location_id'], $approvalStatus,
            $originalTotal, $discountAmount, $finalTotal,
        ]);
        $discountId = (int)$pdo->lastInsertId();
    }

    if ($needsApproval) {
        // The sale is held pending approval — inventory is NOT deducted yet,
        // and staff cannot approve their own discount request.
        $pdo->prepare('
            INSERT INTO approval_requests (type, reference_id, location_id, requested_by, notes)
            VALUES (\'DISCOUNT\', ?, ?, ?, ?)
        ')->execute([$discountId, $body['location_id'], $user['id'], $body['discount_reason'] ?? null]);

        $pdo->commit();
        AuditLogger::log($user, 'DISCOUNT_APPLIED', 'discount', $discountId, null, $body, $body['discount_reason'] ?? null, 'PENDING');
        Response::success([
            'status' => 'PENDING_APPROVAL',
            'discount_id' => $discountId,
        ], 'Discount exceeds your authority; sale is pending manager approval', 202);
    }

    // --- Profit calculation happens on the ACTUAL discounted price, never the list price ---
    $lineProfit = round(($actualUnitPrice - $unitCost) * $qty, 2);
    $profitPercent = $actualUnitPrice > 0 ? round((($actualUnitPrice - $unitCost) / $actualUnitPrice) * 100, 2) : 0;

    $saleCode = 'CL-S-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    $sStmt = $pdo->prepare('
        INSERT INTO sales
            (sale_code, product_id, location_id, staff_id, customer_id, quantity, cost_price,
             selling_price, discount_id, actual_unit_price, line_revenue, line_profit, profit_percent,
             order_type, destination, customer_paid_transport, enterprise_transport_cost, payment_status, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'COMPLETED\')
    ');
    $sStmt->execute([
        $saleCode, $product['id'], $body['location_id'], $user['id'], $body['customer_id'] ?? null,
        $qty, $unitCost, $unitSelling, $discountId, $actualUnitPrice, $finalTotal, $lineProfit, $profitPercent,
        $body['order_type'], $body['destination'] ?? null,
        $body['customer_paid_transport'] ?? 0, $body['enterprise_transport_cost'] ?? 0,
        $body['payment_status'] ?? 'PENDING',
    ]);
    $saleId = (int)$pdo->lastInsertId();

    if ($discountId) {
        $pdo->prepare('UPDATE discounts SET reference_id = ? WHERE id = ?')->execute([$saleId, $discountId]);
    }

    // Deduct stock atomically alongside the sale (never one without the other).
    $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND location_id = ?')
        ->execute([$qty, $product['id'], $body['location_id']]);
    $pdo->prepare('
        INSERT INTO inventory_transactions (product_id, location_id, type, quantity, reference_type, reference_id, performed_by)
        VALUES (?,?,\'SALE\',?,\'sale\',?,?)
    ')->execute([$product['id'], $body['location_id'], $qty, $saleId, $user['id']]);

    $pdo->commit();

    AuditLogger::log($user, 'SALE_CREATED', 'sale', $saleId, null, [
        'sale_code' => $saleCode, 'total' => $finalTotal, 'profit' => $lineProfit,
    ]);

    Response::success([
        'id' => $saleId,
        'sale_code' => $saleCode,
        'total' => $finalTotal,
        'discount_amount' => $discountAmount,
        'profit' => $lineProfit,
    ], 'Sale recorded', 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
