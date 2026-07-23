<?php

namespace App\Controllers;

use App\Config\Database;
use App\Services\MailService;

/**
 * Password Reset Controller
 * Handles forgot-password / reset-password flow.
 */
class PasswordResetController extends BaseController
{
    // =========================================================
    // POST /api/auth/forgot-password
    // Body: { "email": "user@example.com" }
    // Always returns 200 (no user enumeration)
    // =========================================================
    public function forgotPassword(array $params): void
    {
        $data  = $this->getRequestBody();
        $email = trim($data['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'A valid email address is required.'], 422);
            return;
        }

        // Rate limiting: 5 attempts per hour per IP to prevent email flooding
        $rateLimiter = new \App\Middleware\RateLimiter();
        $clientIp    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->attempt('forgot-password:' . $clientIp, 5, 3600)) {
            // Return generic message — do NOT reveal rate limit to prevent enumeration
            $this->json(['message' => 'If that email exists, a reset link has been sent.']);
            return;
        }

        $db   = Database::getConnection();
        $stmt = $db->prepare('SELECT id, full_name, email FROM users WHERE email = :email AND is_active = TRUE');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always respond with success to prevent user enumeration
        if (!$user) {
            $this->json(['message' => 'If that email exists, a reset link has been sent.']);
            return;
        }

        // Invalidate any existing unused tokens for this user
        $stmt = $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL");
        $stmt->execute(['uid' => $user['id']]);

        // Generate a cryptographically secure token
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:sP', time() + 86400); // 24 hours

        $stmt = $db->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at)
            VALUES (:user_id, :token, :expires_at)
        ");
        $stmt->execute([
            'user_id'    => $user['id'],
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        // Send email (fire-and-forget; don't expose mail errors to the client)
        try {
            MailService::sendPasswordReset($user['email'], $user['full_name'], $token);
        } catch (\Exception $e) {
            error_log('Password reset mail error: ' . $e->getMessage());
        }

        $this->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    // =========================================================
    // GET /api/auth/reset-password/validate?token=xxx
    // Validates a reset token without consuming it.
    // =========================================================
    public function validateToken(array $params): void
    {
        $token = trim($_GET['token'] ?? '');

        if (!$token) {
            $this->json(['valid' => false, 'reason' => 'missing_token'], 422);
            return;
        }

        [$valid, $reason] = $this->checkToken($token);
        $this->json(['valid' => $valid, 'reason' => $reason]);
    }

    // =========================================================
    // POST /api/auth/reset-password
    // Body: { "token": "...", "password": "...", "password_confirmation": "..." }
    // =========================================================
    public function resetPassword(array $params): void
    {
        $data     = $this->getRequestBody();
        $token    = trim($data['token'] ?? '');
        $password = $data['password'] ?? '';
        $confirm  = $data['password_confirmation'] ?? '';

        if (!$token) {
            $this->json(['error' => 'Reset token is required.'], 422);
            return;
        }

        // Validate password
        if (strlen($password) < 10) {
            $this->json(['error' => 'Password must be at least 10 characters.'], 422);
            return;
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $this->json(['error' => 'Password must contain uppercase, lowercase, and a number.'], 422);
            return;
        }
        if ($password !== $confirm) {
            $this->json(['error' => 'Passwords do not match.'], 422);
            return;
        }

        [$valid, $reason] = $this->checkToken($token);
        if (!$valid) {
            $status = ($reason === 'expired') ? 410 : 422;
            $this->json(['error' => $reason === 'expired'
                ? 'This reset link has expired. Please request a new one.'
                : 'Invalid or already-used reset link.', 'reason' => $reason], $status);
            return;
        }

        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT prt.user_id FROM password_reset_tokens prt
            WHERE prt.token = :token AND prt.used_at IS NULL AND prt.expires_at > NOW()
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        // Hash new password
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        // Update password + clear must_change_password flag
        $stmt = $db->prepare("
            UPDATE users SET password_hash = :hash, must_change_password = FALSE, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['hash' => $hash, 'id' => $row['user_id']]);

        // Mark token as used
        $stmt = $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $this->json(['message' => 'Password reset successfully. You can now log in.']);
    }

    // ── Private helpers ────────────────────────────────────────

    private function checkToken(string $token): array
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT expires_at, used_at FROM password_reset_tokens WHERE token = :token
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            return [false, 'invalid'];
        }
        if ($row['used_at'] !== null) {
            return [false, 'used'];
        }
        if (strtotime($row['expires_at']) < time()) {
            return [false, 'expired'];
        }

        return [true, 'ok'];
    }
}
