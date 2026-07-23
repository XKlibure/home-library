-- v0.3.0 migrations: auth features + per-user plugin settings

-- Force password change on first login
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT FALSE;

-- Password reset tokens (time-limited, one-use)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    used_at    TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_reset_tokens_token ON password_reset_tokens(token);
CREATE INDEX IF NOT EXISTS idx_reset_tokens_user  ON password_reset_tokens(user_id);

-- Access requests (non-members asking for an account)
CREATE TABLE IF NOT EXISTS access_requests (
    id         UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email      VARCHAR(255) NOT NULL,
    message    TEXT,
    status     VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_access_requests_status ON access_requests(status);
CREATE INDEX IF NOT EXISTS idx_access_requests_email  ON access_requests(email);

-- Per-user plugin overrides (admin can disable for specific users)
CREATE TABLE IF NOT EXISTS user_plugin_settings (
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plugin_name VARCHAR(100) NOT NULL,
    enabled     BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (user_id, plugin_name)
);

-- Enable ebook plugin by default for all (was previously 'false')
UPDATE plugin_settings
SET    setting_value = 'true'
WHERE  plugin_name = 'ebooks' AND setting_key = 'enabled';

-- Admin user must change password on first login (if still using default password)
-- Only set if the admin's password is still the shipped default hash
UPDATE users
SET    must_change_password = TRUE
WHERE  username = 'admin'
  AND  password_hash = '$2y$12$/COW4ljVYn.YoMpWPrxtgu4dyeNo73abitkJiqab8LEUn.qqq3D3e';
