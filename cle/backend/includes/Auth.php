<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

/**
 * Lightweight HS256 JWT implementation to avoid an external dependency.
 * For a larger production deployment, swap this for firebase/php-jwt via
 * composer — the interface below (encode/decode) is compatible in spirit.
 *
 * Session revocation is enforced by cross-checking the `sessions` table,
 * so a logout / forced-expiry immediately invalidates a token even though
 * JWTs are normally stateless.
 */
class Auth
{
    private static function secret(): string
    {
        $config = require __DIR__ . '/../config/config.php';
        if (empty($config['jwt_secret'])) {
            error_log('FATAL: JWT_SECRET is not configured.');
            Response::error('Server misconfiguration', 500);
        }
        return $config['jwt_secret'];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /** @param array $claims Custom claims, e.g. ['sub' => userId, 'role' => 'STAFF', 'sid' => sessionId] */
    public static function issueToken(array $claims): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + ($config['jwt_ttl_minutes'] * 60),
        ]);

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);
        return implode('.', $segments);
    }

    /** Returns the decoded payload array, or null if invalid/expired. */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $expectedSig = hash_hmac('sha256', "$headerB64.$payloadB64", self::secret(), true);
        $actualSig = self::base64UrlDecode($sigB64);

        if (!hash_equals($expectedSig, $actualSig)) return null;

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) return null;

        return $payload;
    }

    /**
     * Reads the Authorization: Bearer header, validates the JWT, confirms the
     * session hasn't been revoked, and returns the full user record (with role
     * name and permission set) or halts the request with 401.
     */
    public static function requireAuth(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
            Response::unauthorized('Missing or malformed Authorization header');
        }

        $payload = self::verifyToken($m[1]);
        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }

        $pdo = Database::connection();

        // Confirm the session is still active (enables server-side logout / forced expiry).
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND expires_at > NOW()');
        $stmt->execute([$payload['sid']]);
        if (!$stmt->fetch()) {
            Response::unauthorized('Session expired or revoked');
        }

        $stmt = $pdo->prepare('
            SELECT u.*, r.name AS role_name, r.max_discount_percent
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND u.is_active = 1
        ');
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized('Account not found or deactivated');
        }

        // Load permission keys for this role.
        $permStmt = $pdo->prepare('
            SELECT p.`key` FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ');
        $permStmt->execute([$user['role_id']]);
        $user['permissions'] = array_column($permStmt->fetchAll(), 'key');

        // Also load any additional assigned locations (multi-branch staff).
        $locStmt = $pdo->prepare('SELECT location_id FROM user_locations WHERE user_id = ?');
        $locStmt->execute([$user['id']]);
        $user['additional_location_ids'] = array_column($locStmt->fetchAll(), 'location_id');
        if (!empty($payload['location_id']) && !in_array($user['role_name'], ['SUPER_ADMIN', 'ADMIN'], true)) {
            $user['location_id'] = (int)$payload['location_id'];
        }

        unset($user['password_hash'], $user['mfa_secret']);
        return $user;
    }

    /**
     * Like requireAuth(), but returns null instead of halting when no valid
     * token is present. Used by public endpoints (e.g. the storefront) that
     * behave differently for logged-in staff vs anonymous customers, without
     * forcing every visitor to authenticate.
     */
    public static function optionalAuth(): ?array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) return null;

        $payload = self::verifyToken($m[1]);
        if (!$payload) return null;

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND expires_at > NOW()');
            $stmt->execute([$payload['sid']]);
            if (!$stmt->fetch()) return null;

            $stmt = $pdo->prepare('
                SELECT u.*, r.name AS role_name, r.max_discount_percent
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND u.is_active = 1
            ');
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();
            if (!$user) return null;

            $permStmt = $pdo->prepare('
                SELECT p.`key` FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ');
            $permStmt->execute([$user['role_id']]);
            $user['permissions'] = array_column($permStmt->fetchAll(), 'key');

            $locStmt = $pdo->prepare('SELECT location_id FROM user_locations WHERE user_id = ?');
            $locStmt->execute([$user['id']]);
            $user['additional_location_ids'] = array_column($locStmt->fetchAll(), 'location_id');
            if (!empty($payload['location_id']) && !in_array($user['role_name'], ['SUPER_ADMIN', 'ADMIN'], true)) {
                $user['location_id'] = (int)$payload['location_id'];
            }

            unset($user['password_hash'], $user['mfa_secret']);
            return $user;
        } catch (Throwable $e) {
            return null;
        }
    }
}
