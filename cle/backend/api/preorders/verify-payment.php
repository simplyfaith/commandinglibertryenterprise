<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$body = requestBody();
$v = new Validator($body);
$v->required('preorder_id')->positiveInt('preorder_id')->required('provider_reference');
if ($v->fails()) Response::error('Validation failed', 422, $v->errors());

$config = require __DIR__ . '/../../config/config.php';
$provider = strtolower((string)($body['provider'] ?? $config['payment']['provider'] ?? 'paystack'));
if ($provider !== 'paystack') Response::error('Unsupported payment provider', 400);

$pdo = Database::connection();
$preorderStmt = $pdo->prepare('SELECT * FROM preorders WHERE id = ?');
$preorderStmt->execute([$body['preorder_id']]);
$preorder = $preorderStmt->fetch();
if (!$preorder) Response::notFound('Preorder not found');

$paymentStmt = $pdo->prepare("SELECT * FROM payments WHERE reference_type = 'PREORDER' AND reference_id = ? ORDER BY id DESC LIMIT 1");
$paymentStmt->execute([$preorder['id']]);
$payment = $paymentStmt->fetch();
if (!$payment) Response::error('No online payment is pending for this preorder', 422);
if ($payment['status'] === 'PAID') Response::success(null, 'Payment already verified');

$secretKey = getenv('PAYSTACK_SECRET_KEY') ?: ($config['payment']['paystack']['secret_key'] ?? null);
if (!$secretKey) Response::error('Paystack secret key is not configured', 500);

$verifyUrl = rtrim((string)($config['payment']['paystack']['verify_url'] ?? 'https://api.paystack.co/transaction/verify'), '/');
$ch = curl_init($verifyUrl . '/' . rawurlencode((string)$body['provider_reference']));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey, 'Content-Type: application/json'],
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$payload = is_string($response) ? json_decode($response, true) : null;
$amount = round((float)($payload['data']['amount'] ?? 0) / 100, 2);
$currency = strtoupper((string)($payload['data']['currency'] ?? ''));
$isValid = !$curlError && $httpCode >= 200 && $httpCode < 300
    && ($payload['status'] ?? null) === 'success'
    && ($payload['data']['status'] ?? null) === 'success'
    && $currency === 'NGN'
    && abs($amount - (float)$payment['amount']) <= 0.01;

if (!$isValid) {
    $pdo->prepare("UPDATE payments SET status='FAILED', provider_reference=? WHERE id=?")
        ->execute([$body['provider_reference'], $payment['id']]);
    Response::error($curlError ? 'Unable to contact the payment provider' : 'Payment could not be verified', $curlError ? 502 : 402);
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE payments SET status='PAID', provider_reference=?, verified_at=NOW(), verified_by_system=1, raw_provider_payload=? WHERE id=?")
        ->execute([$body['provider_reference'], json_encode($payload), $payment['id']]);
    $newAmountPaid = min((float)$preorder['total_amount'], (float)$preorder['amount_paid'] + $amount);
    $newPaymentStatus = $newAmountPaid >= (float)$preorder['total_amount'] ? 'PAID' : 'PARTIALLY_PAID';
    $pdo->prepare('UPDATE preorders SET amount_paid=?, payment_status=?, status="CONFIRMED" WHERE id=?')
        ->execute([$newAmountPaid, $newPaymentStatus, $preorder['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

AuditLogger::log(null, 'PREORDER_PAYMENT_CONFIRMED', 'preorder', (int)$preorder['id'], null, ['provider_reference' => $body['provider_reference'], 'provider' => $provider]);
Response::success(null, 'Payment verified and preorder confirmed');
