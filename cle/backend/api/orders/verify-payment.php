<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);

$body = requestBody();
$v = new \Validator($body);
$v->required('order_id')->positiveInt('order_id')->required('provider_reference');
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

$config = require __DIR__ . '/../../config/config.php';
$pdo = \Database::connection();

$orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$orderStmt->execute([$body['order_id']]);
$order = $orderStmt->fetch();
if (!$order) \Response::notFound('Order not found');

$verified = false;
$verifiedAmount = null;
$rawProviderPayload = null;
$provider = strtolower((string)($body['provider'] ?? $config['payment']['provider'] ?? 'paystack'));

if ($provider === 'paystack') {
    $paystackConfig = $config['payment']['paystack'] ?? [];
    $secretKey = getenv('PAYSTACK_SECRET_KEY') ?: ($paystackConfig['secret_key'] ?? null);
    if (!$secretKey) {
        $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
            ->execute([$body['provider_reference'], $order['id']]);
        \Response::error('Paystack secret key is not configured', 500);
    }

    $verifyUrl = rtrim((string)($paystackConfig['verify_url'] ?? 'https://api.paystack.co/transaction/verify'), '/');
    $ch = curl_init($verifyUrl . '/' . rawurlencode((string)$body['provider_reference']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
            ->execute([$body['provider_reference'], $order['id']]);
        \Response::error('Unable to contact the payment provider', 502);
    }

    $payload = json_decode($response, true);
    $providerStatus = $payload['data']['status'] ?? null;
    $amountInKobo = (float)($payload['data']['amount'] ?? 0);
    $providerCurrency = strtoupper((string)($payload['data']['currency'] ?? 'NGN'));
    $verifiedAmount = round($amountInKobo / 100, 2);
    $rawProviderPayload = $payload;

    if (($payload['status'] ?? null) !== 'success' || $providerStatus !== 'success') {
        $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
            ->execute([$body['provider_reference'], $order['id']]);
        \Response::error('Payment could not be verified with the provider', 402);
    }

    if (abs((float)$verifiedAmount - (float)$order['total']) > 0.01 || $providerCurrency !== 'NGN') {
        $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
            ->execute([$body['provider_reference'], $order['id']]);
        \Response::error('Payment amount or currency does not match the order', 402);
    }

    $verified = true;
} else {
    $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
        ->execute([$body['provider_reference'], $order['id']]);
    \Response::error('Unsupported payment provider', 400);
}

if (!$verified || abs((float)$verifiedAmount - (float)$order['total']) > 0.01) {
    $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE reference_type='ORDER' AND reference_id=?")
        ->execute([$body['provider_reference'], $order['id']]);
    \Response::error('Payment could not be verified with the provider', 402);
}

$pdo->beginTransaction();
try {
    $lockedOrderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $lockedOrderStmt->execute([$order['id']]);
    $order = $lockedOrderStmt->fetch();
    if (!$order) {
        $pdo->rollBack();
        \Response::notFound('Order not found');
    }

    $pdo->prepare("
        UPDATE payments SET status='PAID', verified_at = NOW(), verified_by_system = 1,
               provider_reference = ?, amount = ?, raw_provider_payload = ?
        WHERE reference_type='ORDER' AND reference_id = ?
    ")->execute([$body['provider_reference'], $verifiedAmount, json_encode($rawProviderPayload), $order['id']]);

    $pdo->prepare('UPDATE orders SET status = "CONFIRMED" WHERE id = ?')->execute([$order['id']]);

    $existingSalesStmt = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE order_id = ?');
    $existingSalesStmt->execute([$order['id']]);
    if ((int)$existingSalesStmt->fetchColumn() === 0) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$order['id']]);
        $saleStmt = $pdo->prepare("
            INSERT INTO sales
                (sale_code, order_id, product_id, location_id, staff_id, customer_id, quantity,
                 cost_price, selling_price, actual_unit_price, line_revenue, line_profit, profit_percent,
                 order_type, destination, customer_paid_transport, enterprise_transport_cost, payment_status, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'PAID','COMPLETED')
        ");

        foreach ($itemsStmt->fetchAll() as $item) {
            $actualUnitPrice = (float)$item['unit_selling_price'];
            $lineRevenue = (float)$item['line_total'];
            $lineProfit = round(($actualUnitPrice - (float)$item['unit_cost_price']) * (int)$item['quantity'], 2);
            $profitPercent = $actualUnitPrice > 0
                ? round((($actualUnitPrice - (float)$item['unit_cost_price']) / $actualUnitPrice) * 100, 2)
                : 0;
            $saleCode = 'CL-S-' . date('ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $saleStmt->execute([
                $saleCode, $order['id'], $item['product_id'], $order['location_id'], $order['staff_id'],
                $order['customer_id'], $item['quantity'], $item['unit_cost_price'], $item['unit_selling_price'],
                $actualUnitPrice, $lineRevenue, $lineProfit, $profitPercent, 'ONLINE', $order['delivery_address'],
                $order['customer_paid_transport'], $order['enterprise_transport_cost'], 'PAID',
            ]);
        }
    }

    $pdo->commit();

    \AuditLogger::log(null, 'PAYMENT_CONFIRMED', 'order', $order['id'], null, ['provider_reference' => $body['provider_reference'], 'provider' => $provider]);
    \Response::success(null, 'Payment verified and order confirmed');
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
