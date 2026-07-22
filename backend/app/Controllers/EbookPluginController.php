<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * EbookPlugin Controller
 * Manages the e-book plugin enable/disable state (admin only)
 */
class EbookPluginController extends BaseController
{
    /**
     * GET /api/ebook-plugin/status
     * Returns whether the e-book plugin is enabled
     */
    public function status(array $params): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT setting_value FROM plugin_settings
            WHERE plugin_name = 'ebooks' AND setting_key = 'enabled'
        ");
        $stmt->execute();
        $row = $stmt->fetch();

        $enabled = $row && $row['setting_value'] === 'true';
        $this->json(['enabled' => $enabled]);
    }

    /**
     * POST /api/ebook-plugin/enable
     * Enable the e-book plugin (admin only)
     */
    public function enable(array $params): void
    {
        $this->setPluginState('true');
        $authUser = $this->getAuthUser();
        $this->logPluginAction('enable', $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin enabled.', 'enabled' => true]);
    }

    /**
     * POST /api/ebook-plugin/disable
     * Disable the e-book plugin (admin only)
     */
    public function disable(array $params): void
    {
        $this->setPluginState('false');
        $authUser = $this->getAuthUser();
        $this->logPluginAction('disable', $authUser['id'] ?? null);
        $this->json(['message' => 'E-book plugin disabled.', 'enabled' => false]);
    }

    // ---- Private helpers ----

    private function setPluginState(string $value): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO plugin_settings (plugin_name, setting_key, setting_value, updated_at)
            VALUES ('ebooks', 'enabled', :value, NOW())
            ON CONFLICT (plugin_name, setting_key)
            DO UPDATE SET setting_value = :value, updated_at = NOW()
        ");
        $stmt->execute(['value' => $value]);
    }

    private function logPluginAction(string $action, ?string $userId): void
    {
        $db = Database::getConnection();

        // Verify the user actually exists in this DB instance before inserting.
        // After a full rebuild the JWT may contain a UUID from a previous database.
        if ($userId !== null) {
            $check = $db->prepare("SELECT 1 FROM users WHERE id = :id");
            $check->execute(['id' => $userId]);
            if (!$check->fetch()) {
                $userId = null; // ghost user — log without user reference
            }
        }

        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details)
            VALUES (:user_id, :action, 'plugin', NULL, :details)
        ");
        $stmt->execute([
            'user_id'  => $userId,
            'action'   => $action,
            'details'  => json_encode(['plugin' => 'ebooks']),
        ]);
    }
}
