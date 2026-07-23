<?php

namespace App\Controllers;

use App\Config\Database;
use App\Services\MailService;

/**
 * Users Controller
 * Admin-level user management
 */
class UsersController extends BaseController
{
    /**
     * GET /api/users
     */
    public function index(array $params): void
    {
        $db   = Database::getConnection();
        $stmt = $db->query('
            SELECT id, username, email, full_name, role, is_active,
                   must_change_password, created_at, updated_at
            FROM users
            ORDER BY created_at DESC
        ');
        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * POST /api/users   (Admin creates a user and optionally emails credentials)
     */
    public function store(array $params): void
    {
        $data = $this->getRequestBody();

        $errors = $this->validateRequired($data, ['full_name', 'username', 'email']);
        if ($errors) { $this->json(['errors' => $errors], 422); return; }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Invalid email format.'], 422);
            return;
        }

        $db = Database::getConnection();

        // Check uniqueness
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
        $stmt->execute(['u' => $data['username'], 'e' => $data['email']]);
        if ($stmt->fetch()) {
            $this->json(['error' => 'Username or email already exists.'], 409);
            return;
        }

        $role = in_array($data['role'] ?? 'user', ['admin', 'user', 'viewer'], true)
                ? ($data['role'] ?? 'user') : 'user';

        // Use provided password OR generate a temporary one
        $providedPassword = $data['password'] ?? '';
        $tempPassword     = $providedPassword ?: $this->generateTempPassword();
        $hash             = password_hash($tempPassword, PASSWORD_ARGON2ID);
        $mustChange       = empty($providedPassword); // force change if auto-generated

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, must_change_password)
            VALUES (:username, :email, :hash, :full_name, :role, :must_change)
            RETURNING id, username, email, full_name, role, is_active, must_change_password, created_at
        ");
        $stmt->execute([
            'username'    => $this->sanitize($data['username']),
            'email'       => $data['email'],
            'hash'        => $hash,
            'full_name'   => $this->sanitize($data['full_name']),
            'role'        => $role,
            'must_change' => $mustChange ? 't' : 'f',
        ]);
        $newUser = $stmt->fetch();

        // Send credentials by email if requested or if password was auto-generated
        $sendEmail = filter_var($data['send_email'] ?? $mustChange, FILTER_VALIDATE_BOOLEAN);
        if ($sendEmail) {
            try {
                MailService::sendCredentials(
                    $data['email'],
                    $this->sanitize($data['full_name']),
                    $this->sanitize($data['username']),
                    $tempPassword
                );
            } catch (\Exception $e) {
                error_log('Credentials email error: ' . $e->getMessage());
            }
        }

        $this->json([
            'message'       => 'User created successfully.',
            'data'          => $newUser,
            'temp_password' => $mustChange ? $tempPassword : null,
            'email_sent'    => $sendEmail,
        ], 201);
    }

    /**
     * GET /api/users/{id}
     */
    public function show(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT id, username, email, full_name, role, is_active, created_at, updated_at
            FROM users WHERE id = :id
        ');
        $stmt->execute(['id' => $params['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->json(['error' => 'User not found.'], 404);
            return;
        }

        // Get user's book count
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE owner_id = :id');
        $stmt->execute(['id' => $params['id']]);
        $user['book_count'] = (int)$stmt->fetch()['count'];

        $this->json(['data' => $user]);
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(array $params): void
    {
        $data = $this->getRequestBody();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id FROM users WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        if (!$stmt->fetch()) {
            $this->json(['error' => 'User not found.'], 404);
            return;
        }

        $updates = [];
        $bindings = ['id' => $params['id']];

        if (!empty($data['full_name'])) {
            $updates[] = "full_name = :full_name";
            $bindings['full_name'] = $this->sanitize($data['full_name']);
        }
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->json(['error' => 'Invalid email format.'], 422);
                return;
            }
            $updates[] = "email = :email";
            $bindings['email'] = $data['email'];
        }
        if (!empty($data['role'])) {
            $allowedRoles = ['admin', 'user', 'viewer'];
            if (!in_array($data['role'], $allowedRoles)) {
                $this->json(['error' => 'Invalid role.'], 422);
                return;
            }
            $updates[] = "role = :role";
            $bindings['role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $updates[] = "is_active = :is_active";
            $bindings['is_active'] = (bool)$data['is_active'];
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id RETURNING id, username, email, full_name, role, is_active";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $user = $stmt->fetch();

        $this->json(['message' => 'User updated successfully.', 'data' => $user]);
    }

    /**
     * DELETE /api/users/{id}
     */
    public function destroy(array $params): void
    {
        $authUser = $this->getAuthUser();

        // Prevent self-deletion
        if ($params['id'] === $authUser['id']) {
            $this->json(['error' => 'Cannot delete your own account.'], 403);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'User not found.'], 404);
            return;
        }

        $this->json(['message' => 'User deleted successfully.']);
    }

    // ── Helper ─────────────────────────────────────────────────
    private function generateTempPassword(): string
    {
        // Cryptographically secure random password: 3 upper + 5 lower + 4 digits (12 chars)
        $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower  = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';

        $password = '';
        // Pick characters using random_int (CSPRNG-backed) instead of str_shuffle
        for ($i = 0; $i < 3; $i++) { $password .= $upper[random_int(0, strlen($upper) - 1)]; }
        for ($i = 0; $i < 5; $i++) { $password .= $lower[random_int(0, strlen($lower) - 1)]; }
        for ($i = 0; $i < 4; $i++) { $password .= $digits[random_int(0, strlen($digits) - 1)]; }

        // Fisher-Yates shuffle using random_int
        $chars = str_split($password);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }
}
