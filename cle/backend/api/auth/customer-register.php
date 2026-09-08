<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/CustomerAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);
$body = requestBody();
$v = new \Validator($body);
$v->required('full_name')->required('email')->email('email')->required('phone')->required('password');
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());
if (strlen((string)$body['password']) < 8) \Response::error('Password must be at least 8 characters', 422);

$pdo = \Database::connection();
$email = strtolower(trim((string)$body['email']));
$check = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) \Response::error('An account with this email already exists', 409);

$stmt = $pdo->prepare('INSERT INTO customers (full_name, email, phone, password_hash, default_address) VALUES (?,?,?,?,?)');
$stmt->execute([trim($body['full_name']), $email, trim($body['phone']), password_hash($body['password'], PASSWORD_BCRYPT), $body['default_address'] ?? null]);
$id = (int)$pdo->lastInsertId();
$customerStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$customerStmt->execute([$id]);
$customer = $customerStmt->fetch();
$token = \CustomerAuth::issue($customer);
unset($customer['password_hash']);
\Response::success(['token' => $token, 'customer' => $customer], 'Account created', 201);
