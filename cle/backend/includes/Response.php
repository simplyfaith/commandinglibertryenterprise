<?php

class Response
{
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    public static function success($data = null, string $message = 'OK', int $statusCode = 200): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, $errors = null): void
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }
}
