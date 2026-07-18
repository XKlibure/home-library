<?php

namespace App\Middleware;

/**
 * User-level middleware — allows Admin and User roles, blocks Viewer
 */
class UserMiddleware
{
    public function handle(): bool
    {
        // First check auth
        $authMiddleware = new AuthMiddleware();
        $result = $authMiddleware->handle();

        if ($result === false) {
            return false;
        }

        $user = $_REQUEST['auth_user'] ?? null;
        $role = $user['role'] ?? '';

        if (!in_array($role, ['admin', 'user'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. This feature requires user or admin privileges.']);
            return false;
        }

        return true;
    }
}
