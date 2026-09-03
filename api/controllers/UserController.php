<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';

class UserController {

    public static function listUsers(array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.display_name, u.role, u.state_id, u.is_active, u.last_login, u.created_at,
                   s.state_name, s.state_code
            FROM users u
            LEFT JOIN states s ON u.state_id = s.id
            ORDER BY u.id ASC
        ");

        Response::success($stmt->fetchAll());
    }

    public static function createUser(array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);

        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '123456');
        $displayName = trim($input['display_name'] ?? '');
        $role = $input['role'] ?? 'state_meo';
        $stateId = !empty($input['state_id']) ? (int)$input['state_id'] : null;

        if (empty($username) || empty($displayName)) {
            Response::error('Username and Display Name are required.');
        }

        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, display_name, role, state_id, is_active)
                VALUES (:username, :hash, :display_name, :role, :state_id, 1)
            ");
            $stmt->execute([
                'username' => $username,
                'hash' => $hash,
                'display_name' => $displayName,
                'role' => $role,
                'state_id' => $stateId,
            ]);

            Response::success(['id' => $pdo->lastInsertId()], 'User created successfully', 201);
        } catch (PDOException $e) {
            Response::error('Username already exists or invalid data.');
        }
    }

    public static function resetPassword(int $id, array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);
        $newPassword = trim($input['password'] ?? '123456');

        $pdo = Database::getConnection();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $stmt->execute(['hash' => $hash, 'id' => $id]);

        Response::success(null, 'Password reset successfully');
    }
}
