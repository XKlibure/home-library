-- Per-user e-book reading progress
-- Each user has their own reading position for every e-book.

CREATE TABLE IF NOT EXISTS ebook_reading_progress (
    user_id         UUID NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    ebook_id        UUID NOT NULL REFERENCES ebooks(id) ON DELETE CASCADE,
    current_page    INTEGER        DEFAULT 0,
    read_percentage NUMERIC(5,2)   DEFAULT 0.00,
    last_read_at    TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (user_id, ebook_id)
);

CREATE INDEX IF NOT EXISTS idx_ebook_progress_user  ON ebook_reading_progress(user_id);
CREATE INDEX IF NOT EXISTS idx_ebook_progress_ebook ON ebook_reading_progress(ebook_id);

-- Migrate any existing progress from the ebooks table
INSERT INTO ebook_reading_progress (user_id, ebook_id, current_page, read_percentage)
SELECT owner_id, id, current_page, read_percentage
FROM   ebooks
WHERE  owner_id IS NOT NULL
  AND  (current_page > 0 OR read_percentage > 0)
ON CONFLICT (user_id, ebook_id) DO NOTHING;
