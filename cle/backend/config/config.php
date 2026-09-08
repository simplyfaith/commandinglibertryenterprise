<?php
/**
 * Global configuration. All secrets are pulled from environment variables —
 * never hard-code credentials here.
 */
return [
    'app_env'         => getenv('APP_ENV') ?: 'production',
    'app_url'         => getenv('APP_URL') ?: 'http://localhost:8000',
    'jwt_secret'      => getenv('JWT_SECRET') ?: 'cle-local-development-secret-change-me',
    'jwt_ttl_minutes' => (int)(getenv('JWT_TTL_MINUTES') ?: 60),
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'commanding_liberty',
        'user'     => getenv('DB_USER') ?: 'root',
        'pass'     => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'cors_allowed_origins' => array_filter(explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')),
    'rate_limit' => [
        'window_seconds' => 60,
        'max_requests'   => 120,
    ],
    'payment' => [
        'provider' => getenv('PAYMENT_PROVIDER') ?: 'paystack',
        'paystack' => [
            'public_key' => getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_75cf617970048604fd90b790a3ea98f560c1e624',
            'secret_key' => getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_ab55b4cafd7eff8977694f4712c9bde00fccb790',
            'verify_url' => getenv('PAYSTACK_VERIFY_URL') ?: 'https://api.paystack.co/transaction/verify',
        ],
    ],
    'upload_dir'       => __DIR__ . '/../storage/uploads',
    'max_upload_bytes' => 5 * 1024 * 1024,
];
