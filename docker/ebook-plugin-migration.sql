-- E-Book Plugin Migration
-- Run this script to enable the e-book plugin in an existing Bookoholik instance

-- Plugin settings table (generic key-value store for plugin config)
CREATE TABLE IF NOT EXISTS plugin_settings (
    id SERIAL PRIMARY KEY,
    plugin_name VARCHAR(100) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(plugin_name, setting_key)
);

-- Insert default e-book plugin setting (disabled by default — admin must enable)
INSERT INTO plugin_settings (plugin_name, setting_key, setting_value)
VALUES ('ebooks', 'enabled', 'false')
ON CONFLICT (plugin_name, setting_key) DO NOTHING;

-- E-Books table
CREATE TABLE IF NOT EXISTS ebooks (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    -- Link to existing book record (optional — ebook can be standalone)
    book_id UUID REFERENCES books(id) ON DELETE SET NULL,
    -- Basic metadata (auto-extracted or user-provided)
    title VARCHAR(500) NOT NULL,
    author VARCHAR(500),
    -- File info
    file_name VARCHAR(500) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    file_size BIGINT DEFAULT 0,
    file_format VARCHAR(10) NOT NULL CHECK (file_format IN ('pdf', 'epub', 'mobi')),
    -- Cover image: 'extracted' | 'fetched' | 'default'
    cover_source VARCHAR(20) DEFAULT 'default' CHECK (cover_source IN ('extracted', 'fetched', 'custom', 'default')),
    cover_path VARCHAR(1000),
    -- Reading progress
    total_pages INTEGER DEFAULT 0,
    current_page INTEGER DEFAULT 0,
    read_percentage NUMERIC(5,2) DEFAULT 0.00,
    -- Location type (local by default, reserved for future remote/cloud)
    location_type VARCHAR(20) DEFAULT 'local' CHECK (location_type IN ('local', 'remote')),
    -- Metadata state
    metadata_complete BOOLEAN DEFAULT FALSE,
    -- Who added it
    owner_id UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_ebooks_book_id ON ebooks(book_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_owner ON ebooks(owner_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_format ON ebooks(file_format);
CREATE INDEX IF NOT EXISTS idx_ebooks_title ON ebooks USING gin(to_tsvector('simple', title));
