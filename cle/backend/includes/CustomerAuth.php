<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class CustomerAuth
{
    private static function secret(): string
    {
        $config = require __DIR__ . '/../config/config.php';
        return (string)$config['jwt_secret'];
    }

    private static function encode(array $claims): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', "$header.$payload", self::secret(), true);
        return "$header.$payload." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $signature] = $parts;
        $expected = hash_hmac('sha256', "$header.$payload", self::secret(), true);
        $actual = base64_decode(strtr($signature, '-_', '+/') . str_repeat('=', (4 - strlen($signature) % 4) % 4));
        if (!hash_equals($expected, $actual)) return null;
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4)), true);
        return is_array($claims) && !empty($claims['exp']) && $claims['exp'] >= time() ? $claims : null;
    }

    public static function issue(array $customer): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $config = require __DIR__ . '/../config/config.php';
        $expiresAt = date('Y-m-d H:i:s', time() + ((int)$config['jwt_ttl_minutes'] * 60));
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO customer_sessions (id, customer_id, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)')
            ->execute([$sessionId, $customer['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $expiresAt]);
        return self::encode(['sub' => (int)$customer['id'], 'sid' => $sessionId, 'type' => 'CUSTOMER', 'exp' => time() + ((int)$config['jwt_ttl_minutes'] * 60)]);
    }

    public static function optionalAuth(): ?array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $match)) return null;
        $claims = self::decode($match[1]);
        if (!$claims || ($claims['type'] ?? null) !== 'CUSTOMER') return null;
        $pdo = Database::connection();
        $session = $pdo->prepare('SELECT id FROM customer_sessions WHERE id = ? AND expires_at > NOW()');
        $session->execute([$claims['sid']]);
        if (!$session->fetch()) return null;
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$claims['sub']]);
        $customer = $stmt->fetch();
        if (!$customer) return null;
        unset($customer['password_hash']);
        return $customer;
    }

    public static function requireAuth(): array
    {
        $customer = self::optionalAuth();
        if (!$customer) Response::unauthorized('Customer login required');
        return $customer;
    }

    public static function logout(): void
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $match)) {
            $claims = self::decode($match[1]);
            if ($claims && !empty($claims['sid'])) Database::connection()->prepare('DELETE FROM customer_sessions WHERE id = ?')->execute([$claims['sid']]);
        }
        Response::success(null, 'Logged out');
    }
}
