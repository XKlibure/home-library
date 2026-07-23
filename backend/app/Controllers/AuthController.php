<?php

namespace App\Controllers;

use App\Config\Database;
use Firebase\JWT\JWT;

/**
 * Authentication Controller
 * Handles login, register, and token management
 */
class AuthController extends BaseController
{
    /**
     * POST /api/auth/login
     */
    public function login(array $params): void
    {
        $data = $this->getRequestBody();

        $errors = $this->validateRequired($data, ['username', 'password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        // Rate limiting: 5 attempts per 15 minutes per IP
        $rateLimiter = new \App\Middleware\RateLimiter();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey = 'login:' . $clientIp . ':' . strtolower($data['username']);

        if (!$rateLimiter->attempt($rateLimitKey, 5, 900)) {
            $this->json(['error' => 'Too many login attempts. Please try again in 15 minutes.'], 429);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE (username = :username OR email = :username) AND is_active = TRUE');
        $stmt->execute(['username' => $data['username']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            $this->json(['error' => 'Invalid credentials.'], 401);
            return;
        }

        // Clear rate limit on successful login
        $rateLimiter->clear($rateLimitKey);

        $token = $this->generateToken($user);

        $this->json([
            'message'             => 'Login successful',
            'token'               => $token,
            'must_change_password'=> (bool)$user['must_change_password'],
            'user' => [
                'id'                  => $user['id'],
                'username'            => $user['username'],
                'email'               => $user['email'],
                'full_name'           => $user['full_name'],
                'role'                => $user['role'],
                'must_change_password'=> (bool)$user['must_change_password'],
            ]
        ]);
    }

    /**
     * POST /api/auth/change-initial-password
     * Used on first login when must_change_password = true.
     * Enforces the same rules as changePassword but also clears the flag.
     */
    public function changeInitialPassword(array $params): void
    {
        $data     = $this->getRequestBody();
        $authUser = $this->getAuthUser();

        $errors = $this->validateRequired($data, ['new_password', 'confirm_password']);
        if ($errors) { $this->json(['errors' => $errors], 422); return; }

        if ($data['new_password'] !== $data['confirm_password']) {
            $this->json(['error' => 'Passwords do not match.'], 422);
            return;
        }
        if (strlen($data['new_password']) < 10) {
            $this->json(['error' => 'Password must be at least 10 characters.'], 422);
            return;
        }
        if (!preg_match('/[A-Z]/', $data['new_password']) ||
            !preg_match('/[a-z]/', $data['new_password']) ||
            !preg_match('/[0-9]/', $data['new_password'])) {
            $this->json(['error' => 'Password must contain uppercase, lowercase, and a number.'], 422);
            return;
        }

        $db   = Database::getConnection();
        $hash = password_hash($data['new_password'], PASSWORD_ARGON2ID);

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = :hash, must_change_password = FALSE, updated_at = NOW()
            WHERE id = :id
            RETURNING id, username, email, full_name, role, must_change_password
        ");
        $stmt->execute(['hash' => $hash, 'id' => $authUser['id']]);
        $updated = $stmt->fetch();

        $this->json(['message' => 'Password updated. Welcome!', 'user' => $updated]);
    }

    /**
     * POST /api/auth/register  — DISABLED
     * Self-registration is replaced by the access-request flow.
     */
    public function register(array $params): void
    {
        $this->json([
            'error' => 'Self-registration is disabled. Please use the \'Request Access\' form on the login page.'
        ], 403);
    }

    /**
     * GET /api/auth/me
     */
    public function me(array $params): void
    {
        $authUser = $this->getAuthUser();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id, username, email, full_name, role, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $authUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->json(['error' => 'User not found.'], 404);
            return;
        }

        $this->json(['user' => $user]);
    }

    /**
     * PUT /api/auth/profile
     * Update current user's profile information
     */
    public function updateProfile(array $params): void
    {
        $data = $this->getRequestBody();
        $authUser = $this->getAuthUser();
        $db = Database::getConnection();

        $updates = [];
        $bindings = ['id' => $authUser['id']];

        if (!empty($data['full_name'])) {
            $updates[] = "full_name = :full_name";
            $bindings['full_name'] = $this->sanitize($data['full_name']);
        }

        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->json(['error' => 'Invalid email format.'], 422);
                return;
            }
            // Check email uniqueness
            $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :check_id');
            $stmt->execute(['email' => $data['email'], 'check_id' => $authUser['id']]);
            if ($stmt->fetch()) {
                $this->json(['error' => 'Email already in use by another account.'], 409);
                return;
            }
            $updates[] = "email = :email";
            $bindings['email'] = $data['email'];
        }

        if (!empty($data['username'])) {
            $username = $this->sanitize($data['username']);
            if (strlen($username) < 3) {
                $this->json(['error' => 'Username must be at least 3 characters.'], 422);
                return;
            }
            // Check username uniqueness
            $stmt = $db->prepare('SELECT id FROM users WHERE username = :username AND id != :check_id');
            $stmt->execute(['username' => $username, 'check_id' => $authUser['id']]);
            if ($stmt->fetch()) {
                $this->json(['error' => 'Username already taken.'], 409);
                return;
            }
            $updates[] = "username = :username";
            $bindings['username'] = $username;
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id RETURNING id, username, email, full_name, role, created_at";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $updatedUser = $stmt->fetch();

        $this->json(['message' => 'Profile updated successfully.', 'user' => $updatedUser]);
    }

    /**
     * PUT /api/auth/password
     */
    public function changePassword(array $params): void
    {
        $data = $this->getRequestBody();
        $authUser = $this->getAuthUser();

        $errors = $this->validateRequired($data, ['current_password', 'new_password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        if (strlen($data['new_password']) < 10) {
            $this->json(['error' => 'New password must be at least 10 characters long.'], 422);
            return;
        }

        // Enforce same complexity as registration
        if (!preg_match('/[A-Z]/', $data['new_password']) ||
            !preg_match('/[a-z]/', $data['new_password']) ||
            !preg_match('/[0-9]/', $data['new_password'])) {
            $this->json(['error' => 'Password must contain uppercase, lowercase, and a number.'], 422);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $authUser['id']]);
        $user = $stmt->fetch();

        if (!password_verify($data['current_password'], $user['password_hash'])) {
            $this->json(['error' => 'Current password is incorrect.'], 401);
            return;
        }

        $newHash = password_hash($data['new_password'], PASSWORD_ARGON2ID);
        $stmt = $db->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $newHash, 'id' => $authUser['id']]);

        $this->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Generate JWT token
     */
    private function generateToken(array $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET environment variable is not set.');
        }
        $payload = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (8 * 60 * 60), // 8 hours
            'iss' => 'bookoholik',
            'aud' => 'bookoholik',
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}
