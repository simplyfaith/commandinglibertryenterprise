<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Response.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);

$body = requestBody();
$body['email'] = strtolower(trim((string)($body['email'] ?? '')));
$v = new \Validator($body);
$v->required('email')->email('email')->required('password');
if ($v->fails()) \Response::error('Validation failed', 422, $v->errors());

$pdo = \Database::connection();
$stmt = $pdo->prepare('
    SELECT u.*, r.name AS role_name FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.email = ?
');
$stmt->execute([$body['email']]);
$user = $stmt->fetch();

// Account lockout after repeated failures (brute-force protection).
if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
    \Response::forbidden('Account temporarily locked due to repeated failed logins. Try again later.');
}

if (!$user || !password_verify($body['password'], $user['password_hash']) || !$user['is_active']) {
    if ($user) {
        $attempts = $user['failed_login_attempts'] + 1;
        $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null; // 15 min lock after 5 fails
        $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?')
            ->execute([$attempts, $lockUntil, $user['id']]);
        \AuditLogger::log($user, 'LOGIN_FAILED', 'user', $user['id']);
    }
    \Response::unauthorized('Invalid email or password');
}

$companyWideRoles = ['SUPER_ADMIN', 'ADMIN'];
if (!in_array($user['role_name'], $companyWideRoles, true)) {
    $locationInput = trim((string)($body['location'] ?? ''));
    if ($locationInput === '') {
        \Response::error('Enter your branch location', 422);
    }

    $locationStmt = $pdo->prepare('SELECT id FROM locations WHERE is_active = 1 AND LOWER(name) = LOWER(?) LIMIT 1');
    $locationStmt->execute([$locationInput]);
    $locationId = (int)$locationStmt->fetchColumn();

    if (!$locationId) {
        $locationCode = 'BR-' . strtoupper(substr(hash('sha256', strtolower($locationInput)), 0, 17));
        $pdo->prepare('INSERT INTO locations (code, name, is_active) VALUES (?, ?, 1)')
            ->execute([$locationCode, $locationInput]);
        $locationId = (int)$pdo->lastInsertId();
    }

    $user['location_id'] = $locationId;
} else {
    $locationId = null;
}

// TODO: if $user['mfa_enabled'], require a second-factor verification step here
// before issuing the session — omitted in this scaffold for brevity.

$pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
    ->execute([$user['id']]);

$sessionId = bin2hex(random_bytes(32));
$config = require __DIR__ . '/../../config/config.php';
$expiresAt = date('Y-m-d H:i:s', time() + $config['jwt_ttl_minutes'] * 60);

$pdo->prepare('INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)')
    ->execute([$sessionId, $user['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $expiresAt]);

$token = \Auth::issueToken([
    'sub' => $user['id'],
    'sid' => $sessionId,
    'role' => $user['role_name'],
    'location_id' => $locationId,
]);

\AuditLogger::log($user, 'LOGIN', 'user', $user['id']);

unset($user['password_hash'], $user['mfa_secret']);

\Response::success([
    'token' => $token,
    'user'  => $user,
], 'Login successful');
