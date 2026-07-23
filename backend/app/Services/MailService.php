<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Mail Service — wraps PHPMailer with app SMTP config from .env
 */
class MailService
{
    private static function mailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST']       ?? 'localhost';
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME']   ?? '';
        $mail->Password   = $_ENV['MAIL_PASSWORD']   ?? '';

        $enc = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls');
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure  = '';
        }

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                // Explicit CA bundle path — required when open_basedir is set
                'cafile'            => '/etc/ssl/certs/ca-certificates.crt',
            ],
        ];

        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? 'noreply@bookoholik.local',
            $_ENV['MAIL_FROM_NAME']    ?? 'Bookoholik'
        );

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        return $mail;
    }

    // ── Helpers ────────────────────────────────────────────────

    private static function appUrl(): string
    {
        return rtrim($_ENV['APP_URL'] ?? 'http://localhost:3000', '/');
    }

    private static function appName(): string
    {
        return $_ENV['MAIL_FROM_NAME'] ?? 'Bookoholik';
    }

    private static function wrap(string $bodyHtml): string
    {
        $app = htmlspecialchars(self::appName(), ENT_QUOTES, 'UTF-8');
        return "
        <div style='font-family:sans-serif;max-width:560px;margin:40px auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden'>
          <div style='background:#0f172a;padding:24px;text-align:center'>
            <span style='color:#ffffff;font-size:20px;font-weight:700'>{$app}</span>
          </div>
          <div style='padding:32px;background:#ffffff;color:#374151;line-height:1.6'>
            {$bodyHtml}
          </div>
          <div style='background:#f9fafb;padding:16px;text-align:center;font-size:12px;color:#9ca3af'>
            {$app} &mdash; Home Library Management
          </div>
        </div>";
    }

    // ── Public send methods ────────────────────────────────────

    /**
     * Send password-reset link to a user.
     */
    public static function sendPasswordReset(string $toEmail, string $toName, string $token): void
    {
        $link = self::appUrl() . '/reset-password?token=' . urlencode($token);
        $name = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $app  = htmlspecialchars(self::appName(), ENT_QUOTES, 'UTF-8');

        $body = self::wrap("
            <p>Hi <strong>{$name}</strong>,</p>
            <p>We received a request to reset your <strong>{$app}</strong> password.</p>
            <p style='text-align:center;margin:32px 0'>
              <a href='{$link}'
                 style='background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600'>
                Reset Password
              </a>
            </p>
            <p style='font-size:13px;color:#6b7280'>
              This link expires in <strong>24 hours</strong>.<br>
              If you did not request a password reset, you can safely ignore this email.
            </p>
            <p style='font-size:12px;color:#9ca3af;word-break:break-all'>
              Can't click the button? Copy this URL:<br>{$link}
            </p>
        ");

        $mail = self::mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = '[' . self::appName() . '] Reset your password';
        $mail->Body    = $body;
        $mail->AltBody = "Reset your password: {$link}\n\nThis link expires in 24 hours.";
        $mail->send();
    }

    /**
     * Notify the admin of a new access request.
     */
    public static function sendAccessRequestNotification(string $adminEmail, string $requesterEmail, ?string $message): void
    {
        $email   = htmlspecialchars($requesterEmail, ENT_QUOTES, 'UTF-8');
        $msg     = $message ? '<p style="background:#f3f4f6;padding:12px;border-radius:6px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $appLink = self::appUrl() . '/users';

        $body = self::wrap("
            <p>A new <strong>access request</strong> has been received on " . htmlspecialchars(self::appName(), ENT_QUOTES, 'UTF-8') . ".</p>
            <p><strong>Email:</strong> {$email}</p>
            {$msg}
            <p style='text-align:center;margin:28px 0'>
              <a href='{$appLink}'
                 style='background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600'>
                Review in Admin Panel
              </a>
            </p>
        ");

        $mail = self::mailer();
        $mail->addAddress($adminEmail);
        $mail->Subject = '[' . self::appName() . '] New access request from ' . $requesterEmail;
        $mail->Body    = $body;
        $mail->AltBody = "New access request from: {$requesterEmail}\nReview at: {$appLink}";
        $mail->send();
    }

    /**
     * Send login credentials to a newly created user.
     */
    public static function sendCredentials(string $toEmail, string $toName, string $username, string $tempPassword): void
    {
        $name     = htmlspecialchars($toName,       ENT_QUOTES, 'UTF-8');
        $user     = htmlspecialchars($username,     ENT_QUOTES, 'UTF-8');
        $pass     = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
        $loginUrl = self::appUrl() . '/login';
        $app      = htmlspecialchars(self::appName(), ENT_QUOTES, 'UTF-8');

        $body = self::wrap("
            <p>Hi <strong>{$name}</strong>,</p>
            <p>Your account on <strong>{$app}</strong> has been created. Here are your credentials:</p>
            <table style='border-collapse:collapse;width:100%;margin:16px 0'>
              <tr><td style='padding:8px;color:#6b7280;width:120px'>Username</td>
                  <td style='padding:8px;font-weight:600'>{$user}</td></tr>
              <tr style='background:#f9fafb'>
                  <td style='padding:8px;color:#6b7280'>Password</td>
                  <td style='padding:8px;font-family:monospace;font-size:15px'>{$pass}</td></tr>
            </table>
            <p style='background:#fef3c7;border:1px solid #fbbf24;border-radius:6px;padding:12px;font-size:13px'>
              ⚠️ You will be asked to <strong>change your password</strong> on your first login.
            </p>
            <p style='text-align:center;margin:28px 0'>
              <a href='{$loginUrl}'
                 style='background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600'>
                Log In Now
              </a>
            </p>
        ");

        $mail = self::mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = '[' . self::appName() . '] Your account credentials';
        $mail->Body    = $body;
        $mail->AltBody = "Your {$app} credentials:\nUsername: {$username}\nPassword: {$tempPassword}\nLogin: {$loginUrl}\n\nYou must change your password on first login.";
        $mail->send();
    }
}
