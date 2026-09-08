<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) Response::unauthorized();

$payload = Auth::verifyToken($m[1]);
if ($payload) {
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$payload['sid']]);
    AuditLogger::log(['id' => $payload['sub'], 'role_name' => $payload['role'] ?? null, 'location_id' => null], 'LOGOUT', 'user', $payload['sub']);
}

Response::success(null, 'Logged out');
