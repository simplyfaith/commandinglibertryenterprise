<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/CustomerAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);
$body = requestBody();
$v = new \Validator($body);
$v->required('email')->email('email')->required('password');
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

$pdo = \Database::connection();
$stmt = $pdo->prepare('SELECT * FROM customers WHERE email = ?');
$stmt->execute([strtolower(trim((string)$body['email']))]);
$customer = $stmt->fetch();
if (!$customer || empty($customer['password_hash']) || !password_verify($body['password'], $customer['password_hash'])) {
    \Response::unauthorized('Invalid customer email or password');
}
$token = \CustomerAuth::issue($customer);
unset($customer['password_hash']);
\Response::success(['token' => $token, 'customer' => $customer], 'Login successful');
