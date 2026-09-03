<?php
/**
 * JSON Response Helper
 */

class Response {
    public static function json($data = null, int $statusCode = 200, string $message = ''): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => $message,
            'data'    => $data,
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void {
        self::json($data, $statusCode, $message);
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, $data = null): void {
        self::json($data, $statusCode, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized access'): void {
        self::json(null, 401, $message);
    }

    public static function forbidden(string $message = 'Access forbidden'): void {
        self::json(null, 403, $message);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::json(null, 404, $message);
    }
}
