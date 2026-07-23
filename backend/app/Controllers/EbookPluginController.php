<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * EbookPlugin Controller
 * Global enable/disable + per-user override.
 *
 * Logic:
 *  - If global = false → disabled for everyone, no override possible
 *  - If global = true  → enabled by default; admin can disable for specific users
 */
class EbookPluginController extends BaseController
{
    // =========================================================
    // GET /api/ebook-plugin/status   (any authenticated user)
    // Returns whether the plugin is active FOR THE CURRENT USER
    // =========================================================
    public function status(array $params): void
    {
        $authUser = $this->getAuthUser();
        $userId   = $authUser['id'] ?? null;

        $enabled = $this->isEnabledFor($userId);
        $this->json(['enabled' => $enabled]);
    }

    // =========================================================
    // GET /api/ebook-plugin/global-status   (admin)
    // Returns ONLY the global toggle state — ignores per-user overrides.
    // Used by the Settings page so the admin sees the real global switch.
    // =========================================================
    public function globalStatus(array $params): void
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT setting_value FROM plugin_settings
            WHERE plugin_name = 'ebooks' AND setting_key = 'enabled'
        ");
        $stmt->execute();
        $row     = $stmt->fetch();
        $enabled = !$row || $row['setting_value'] === 'true';
        $this->json(['enabled' => $enabled]);
    }

    // =========================================================
    // POST /api/ebook-plugin/enable   (admin — global)
    // =========================================================
    public function enable(array $params): void
    {
        $this->setGlobal('true');
        $authUser = $this->getAuthUser();
        $this->logPluginAction('enable_global', $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin enabled globally.', 'enabled' => true]);
    }

    // =========================================================
    // POST /api/ebook-plugin/disable   (admin — global)
    // =========================================================
    public function disable(array $params): void
    {
        $this->setGlobal('false');
        $authUser = $this->getAuthUser();
        $this->logPluginAction('disable_global', $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin disabled globally.', 'enabled' => false]);
    }

    // =========================================================
    // POST /api/ebook-plugin/user/{userId}/enable   (admin)
    // Remove a per-user disable override → user inherits global
    // =========================================================
    public function enableForUser(array $params): void
    {
        $this->setUserOverride($params['userId'], true);
        $authUser = $this->getAuthUser();
        $this->logPluginAction('enable_for_user:' . $params['userId'], $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin enabled for user.', 'enabled' => true]);
    }

    // =========================================================
    // POST /api/ebook-plugin/user/{userId}/disable   (admin)
    // =========================================================
    public function disableForUser(array $params): void
    {
        $this->setUserOverride($params['userId'], false);
        $authUser = $this->getAuthUser();
        $this->logPluginAction('disable_for_user:' . $params['userId'], $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin disabled for user.', 'enabled' => false]);
    }

    // =========================================================
    // GET /api/ebook-plugin/users   (admin)
    // Returns per-user override list
    // =========================================================
    public function userOverrides(array $params): void
    {
        $db   = Database::getConnection();
        $stmt = $db->query("
            SELECT u.id, u.username, u.full_name,
                   COALESCE(ups.enabled, TRUE) AS ebook_enabled
            FROM users u
            LEFT JOIN user_plugin_settings ups
                   ON ups.user_id = u.id AND ups.plugin_name = 'ebooks'
            WHERE u.is_active = TRUE
            ORDER BY u.full_name
        ");
        $this->json(['data' => $stmt->fetchAll()]);
    }

    // =========================================================
    // Private helpers
    // =========================================================

    public function isEnabledFor(?string $userId): bool
    {
        $db = Database::getConnection();

        // 1. Check global setting
        $stmt = $db->prepare("
            SELECT setting_value FROM plugin_settings
            WHERE plugin_name = 'ebooks' AND setting_key = 'enabled'
        ");
        $stmt->execute();
        $row = $stmt->fetch();
        // Default to true if no setting exists
        $globalEnabled = !$row || $row['setting_value'] === 'true';

        if (!$globalEnabled) {
            return false; // Global off trumps everything
        }

        if (!$userId) {
            return true; // No user context → return global
        }

        // 2. Check per-user override (wrapped in try/catch in case table doesn't exist yet)
        try {
            $stmt = $db->prepare("
                SELECT enabled FROM user_plugin_settings
                WHERE user_id = :uid AND plugin_name = 'ebooks'
            ");
            $stmt->execute(['uid' => $userId]);
            $override = $stmt->fetch();

            if ($override !== false) {
                return (bool)$override['enabled'];
            }
        } catch (\Exception $e) {
            // Table may not exist yet on first boot — fall through to default
        }

        return true; // No override → inherit global (which is true at this point)
    }

    private function setGlobal(string $value): void
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO plugin_settings (plugin_name, setting_key, setting_value, updated_at)
            VALUES ('ebooks', 'enabled', :value, NOW())
            ON CONFLICT (plugin_name, setting_key)
            DO UPDATE SET setting_value = :value, updated_at = NOW()
        ");
        $stmt->execute(['value' => $value]);
    }

    private function setUserOverride(string $userId, bool $enabled): void
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO user_plugin_settings (user_id, plugin_name, enabled, updated_at)
            VALUES (:uid, 'ebooks', :enabled, NOW())
            ON CONFLICT (user_id, plugin_name)
            DO UPDATE SET enabled = :enabled, updated_at = NOW()
        ");
        $stmt->execute(['uid' => $userId, 'enabled' => $enabled ? 't' : 'f']);
    }

    private function logPluginAction(string $action, ?string $userId): void
    {
        $db = Database::getConnection();

        if ($userId !== null) {
            $check = $db->prepare("SELECT 1 FROM users WHERE id = :id");
            $check->execute(['id' => $userId]);
            if (!$check->fetch()) { $userId = null; }
        }

        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details)
            VALUES (:user_id, :action, 'plugin', NULL, :details)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'action'  => $action,
            'details' => json_encode(['plugin' => 'ebooks']),
        ]);
    }
}
