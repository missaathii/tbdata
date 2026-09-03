<?php
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    public static function authenticate(): ?array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::unauthorized('Missing or invalid Authorization token.');
            return null;
        }

        $token = $matches[1];
        $config = require __DIR__ . '/../config/app.php';
        $payload = JWT::decode($token, $config['jwt_secret']);

        if (!$payload) {
            Response::unauthorized('Invalid or expired authentication token.');
            return null;
        }

        return $payload;
    }
}
