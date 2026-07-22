-- E-Book Plugin: add publisher_id FK to ebooks
-- Run this on existing deployments (fresh installs already get it via init.sql)

ALTER TABLE ebooks
    ADD COLUMN IF NOT EXISTS publisher_id UUID REFERENCES publishers(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_ebooks_publisher ON ebooks(publisher_id);
