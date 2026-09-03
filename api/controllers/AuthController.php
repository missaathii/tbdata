<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/JWT.php';

class AuthController {
    public static function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            Response::error('Username and password are required.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, s.state_name, s.state_code 
            FROM users u
            LEFT JOIN states s ON u.state_id = s.id
            WHERE u.username = :username AND u.is_active = 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::unauthorized('Invalid username or password.');
        }

        // Update last_login
        $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $upd->execute(['id' => $user['id']]);

        $config = require __DIR__ . '/../config/app.php';
        $tokenPayload = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'state_id' => $user['state_id'] ? (int)$user['state_id'] : null,
            'state_name' => $user['state_name'],
            'state_code' => $user['state_code'],
        ];

        $token = JWT::encode($tokenPayload, $config['jwt_secret'], $config['jwt_expiry']);

        Response::success([
            'token' => $token,
            'user' => $tokenPayload
        ], 'Login successful');
    }

    public static function me(array $currentUser): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.display_name, u.role, u.state_id, u.last_login,
                   s.state_name, s.state_code 
            FROM users u
            LEFT JOIN states s ON u.state_id = s.id
            WHERE u.id = :id AND u.is_active = 1
        ");
        $stmt->execute(['id' => $currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found or inactive');
        }

        Response::success($user, 'User profile retrieved');
    }
}
