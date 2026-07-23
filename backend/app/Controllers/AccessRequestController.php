<?php

namespace App\Controllers;

use App\Config\Database;
use App\Services\MailService;

/**
 * Access Request Controller
 * Non-members can request an account; admin reviews and approves.
 */
class AccessRequestController extends BaseController
{
    // =========================================================
    // POST /api/auth/request-access   (public)
    // Body: { "email": "...", "message": "..." }
    // =========================================================
    public function store(array $params): void
    {
        $data    = $this->getRequestBody();
        $email   = trim($data['email'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'A valid email address is required.'], 422);
            return;
        }

        // Rate limiting: 3 requests per hour per IP to prevent admin inbox flooding
        $rateLimiter = new \App\Middleware\RateLimiter();
        $clientIp    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->attempt('access-request:' . $clientIp, 3, 3600)) {
            $this->json(['error' => 'Too many requests. Please try again later.'], 429);
            return;
        }

        $db = Database::getConnection();

        // Reject if already a user
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            // Don't reveal account existence — respond generically
            $this->json(['message' => 'Your request has been submitted.']);
            return;
        }

        // Reject duplicate pending request
        $stmt = $db->prepare("SELECT id FROM access_requests WHERE email = :email AND status = 'pending'");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $this->json(['error' => 'A request from this email address is already pending review. You will be contacted once it is processed.'], 409);
            return;
        }

        // Save request
        $stmt = $db->prepare("
            INSERT INTO access_requests (email, message)
            VALUES (:email, :message)
        ");
        $stmt->execute([
            'email'   => $this->sanitize($email),
            'message' => $message ? $this->sanitize($message) : null,
        ]);

        // Notify admin by email (best-effort)
        try {
            $adminStmt = $db->query("SELECT email FROM users WHERE role = 'admin' AND is_active = TRUE LIMIT 1");
            $admin     = $adminStmt->fetch();
            if ($admin) {
                MailService::sendAccessRequestNotification($admin['email'], $email, $message ?: null);
            }
        } catch (\Exception $e) {
            error_log('Access request notification error: ' . $e->getMessage());
        }

        $this->json(['message' => 'Your request has been submitted. An admin will review it shortly.'], 201);
    }

    // =========================================================
    // GET /api/users/access-requests   (admin)
    // =========================================================
    public function index(array $params): void
    {
        $db   = Database::getConnection();
        $q    = $this->getQueryParams();
        $status = in_array($q['status'] ?? 'pending', ['pending', 'approved', 'rejected', 'all'], true)
                  ? ($q['status'] ?? 'pending')
                  : 'pending';

        if ($status === 'all') {
            $stmt = $db->query("SELECT * FROM access_requests ORDER BY created_at DESC LIMIT 100");
        } else {
            $stmt = $db->prepare("SELECT * FROM access_requests WHERE status = :s ORDER BY created_at DESC LIMIT 100");
            $stmt->execute(['s' => $status]);
        }

        $this->json(['data' => $stmt->fetchAll()]);
    }

    // =========================================================
    // POST /api/users/access-requests/{id}/approve   (admin)
    // Body: { "full_name": "...", "username": "...", "role": "user" }
    // Creates a user account and sends credentials by email.
    // =========================================================
    public function approve(array $params): void
    {
        $data = $this->getRequestBody();
        $db   = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM access_requests WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $request = $stmt->fetch();

        if (!$request) {
            $this->json(['error' => 'Access request not found.'], 404);
            return;
        }
        if ($request['status'] !== 'pending') {
            $this->json(['error' => 'This request has already been processed.'], 409);
            return;
        }

        $fullName = !empty($data['full_name']) ? $this->sanitize($data['full_name']) : '';
        $username = !empty($data['username'])  ? $this->sanitize($data['username'])  : '';
        $role     = in_array($data['role'] ?? 'user', ['admin', 'user', 'viewer'], true)
                    ? ($data['role'] ?? 'user') : 'user';

        if (!$fullName || !$username) {
            $this->json(['error' => 'full_name and username are required.'], 422);
            return;
        }

        // Check username uniqueness
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
        $stmt->execute(['u' => $username, 'e' => $request['email']]);
        if ($stmt->fetch()) {
            $this->json(['error' => 'Username or email already exists.'], 409);
            return;
        }

        // Generate temporary password
        $tempPassword = $this->generateTempPassword();
        $hash         = password_hash($tempPassword, PASSWORD_ARGON2ID);

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, must_change_password)
            VALUES (:username, :email, :hash, :full_name, :role, TRUE)
            RETURNING id, username, email, full_name, role
        ");
        $stmt->execute([
            'username'  => $username,
            'email'     => $request['email'],
            'hash'      => $hash,
            'full_name' => $fullName,
            'role'      => $role,
        ]);
        $newUser = $stmt->fetch();

        // Mark request as approved
        $stmt = $db->prepare("UPDATE access_requests SET status = 'approved', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);

        // Send credentials email
        try {
            MailService::sendCredentials($request['email'], $fullName, $username, $tempPassword);
        } catch (\Exception $e) {
            error_log('Credentials email error: ' . $e->getMessage());
        }

        $this->json(['message' => 'Account created and credentials sent.', 'data' => $newUser], 201);
    }

    // =========================================================
    // DELETE /api/users/access-requests/{id}   (admin — reject)
    // =========================================================
    public function reject(array $params): void
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("UPDATE access_requests SET status = 'rejected', updated_at = NOW() WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'Request not found or already processed.'], 404);
            return;
        }

        $this->json(['message' => 'Request rejected.']);
    }

    // ── Helpers ────────────────────────────────────────────────

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
