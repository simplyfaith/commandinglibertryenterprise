<?php

/**
 * File-based sliding-window rate limiter, keyed by IP + route. Adequate for
 * a single-server deployment; swap the storage backend for Redis/Memcached
 * once the app runs on more than one node.
 */
class RateLimiter
{
    public static function check(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $dir = sys_get_temp_dir() . '/cle_rate_limit';
        if (!is_dir($dir)) mkdir($dir, 0700, true);

        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        $now = time();

        $hits = [];
        if (is_file($file)) {
            $hits = json_decode(file_get_contents($file), true) ?: [];
        }
        // Drop hits outside the window.
        $hits = array_values(array_filter($hits, fn($t) => $t > $now - $windowSeconds));

        if (count($hits) >= $maxRequests) {
            file_put_contents($file, json_encode($hits));
            return false;
        }

        $hits[] = $now;
        file_put_contents($file, json_encode($hits));
        return true;
    }
}
