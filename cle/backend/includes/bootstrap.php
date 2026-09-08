<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak stack traces to the client

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/RBAC.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/ImageUploader.php';

$config = require __DIR__ . '/../config/config.php';

// ---- CORS (allow-list only; never reflect arbitrary Origin) ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['cors_allowed_origins'], true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Security headers ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---- Rate limiting ----
$rl = $config['rate_limit'];
$clientKey = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . ($_SERVER['REQUEST_URI'] ?? '');
if (!RateLimiter::check($clientKey, $rl['max_requests'], $rl['window_seconds'])) {
    Response::error('Too many requests, please slow down', 429);
}

// ---- Global exception safety net ----
set_exception_handler(function (Throwable $e) {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Internal server error', 500);
});

/** Reads and JSON-decodes the request body (POST/PUT/PATCH). */
function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
