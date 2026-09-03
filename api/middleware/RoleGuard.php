<?php
require_once __DIR__ . '/../helpers/Response.php';

class RoleGuard {
    public static function requireAdmin(array $user): void {
        if (!in_array($user['role'], ['super_admin', 'national_admin'])) {
            Response::forbidden('Admin privileges required to perform this action.');
        }
    }

    public static function enforceStateScope(array $user, ?int $requestedStateId = null): ?int {
        // If super_admin or national_admin, they can view any state or all states (null)
        if (in_array($user['role'], ['super_admin', 'national_admin'])) {
            return $requestedStateId;
        }

        // If state_meo, strictly limit to their assigned state_id
        if ($user['role'] === 'state_meo') {
            return (int)$user['state_id'];
        }

        return $requestedStateId;
    }
}
