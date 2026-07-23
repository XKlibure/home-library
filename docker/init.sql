-- Bookoholik: Home Library Database Initialization
-- This script runs when the PostgreSQL container is first created

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Users table
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user', 'viewer')),
    is_active BOOLEAN DEFAULT TRUE,
    must_change_password BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Genres reference table
CREATE TABLE genres (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    name_ar VARCHAR(100),
    name_fr VARCHAR(100)
);

-- Writers table
CREATE TABLE writers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    name_fr VARCHAR(255),
    nationality VARCHAR(100),
    birth_year INTEGER,
    death_year INTEGER,
    biography TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_writers_name ON writers(name);
CREATE INDEX idx_writers_name_ar ON writers(name_ar);

-- Publishers table
CREATE TABLE publishers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    name_fr VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_publishers_name ON publishers(name);

-- Library Addresses (multiple physical locations)
CREATE TABLE addresses (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    street VARCHAR(255),
    city VARCHAR(100),
    state_province VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    is_primary BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Rooms within addresses
CREATE TABLE rooms (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    address_id UUID NOT NULL REFERENCES addresses(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    floor VARCHAR(50),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_rooms_address ON rooms(address_id);

-- Shelves within rooms
CREATE TABLE shelves (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    room_id UUID NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    capacity INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_shelves_room ON shelves(room_id);

-- Books table
CREATE TABLE books (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title VARCHAR(500) NOT NULL,
    author VARCHAR(500) NOT NULL,
    genre VARCHAR(100),
    publication_year INTEGER,
    language VARCHAR(50) DEFAULT 'arabic' CHECK (language IN ('arabic', 'english', 'french', 'other')),
    location_room VARCHAR(100),
    location_shelf VARCHAR(100),
    shelf_id UUID REFERENCES shelves(id) ON DELETE SET NULL,
    publisher_id UUID REFERENCES publishers(id) ON DELETE SET NULL,
    read_status BOOLEAN DEFAULT FALSE,
    isbn VARCHAR(20),
    edition_house VARCHAR(255),
    num_pages INTEGER,
    series_name VARCHAR(255),
    series_position INTEGER,
    notes TEXT,
    cover_image_url VARCHAR(500),
    owner_id UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Lending records table
CREATE TABLE lending_records (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    book_id UUID NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    borrower_name VARCHAR(255) NOT NULL,
    borrower_contact VARCHAR(255),
    lent_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE NOT NULL,
    returned_date DATE,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'returned', 'overdue')),
    notes TEXT,
    lent_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Audit log table
CREATE TABLE audit_log (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id UUID,
    details JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- =====================================================
-- E-Book Plugin (optional, disabled by default)
-- =====================================================

-- Plugin settings table
CREATE TABLE IF NOT EXISTS plugin_settings (
    id SERIAL PRIMARY KEY,
    plugin_name VARCHAR(100) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(plugin_name, setting_key)
);

INSERT INTO plugin_settings (plugin_name, setting_key, setting_value)
VALUES ('ebooks', 'enabled', 'true')
ON CONFLICT (plugin_name, setting_key) DO NOTHING;

-- E-Books table
CREATE TABLE IF NOT EXISTS ebooks (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    book_id UUID REFERENCES books(id) ON DELETE SET NULL,
    title VARCHAR(500) NOT NULL,
    author VARCHAR(500),
    file_name VARCHAR(500) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    file_size BIGINT DEFAULT 0,
    file_format VARCHAR(10) NOT NULL CHECK (file_format IN ('pdf', 'epub', 'mobi')),
    cover_source VARCHAR(20) DEFAULT 'default' CHECK (cover_source IN ('extracted', 'fetched', 'custom', 'default')),
    cover_path VARCHAR(1000),
    total_pages INTEGER DEFAULT 0,
    current_page INTEGER DEFAULT 0,
    read_percentage NUMERIC(5,2) DEFAULT 0.00,
    location_type VARCHAR(20) DEFAULT 'local' CHECK (location_type IN ('local', 'remote')),
    metadata_complete BOOLEAN DEFAULT FALSE,
    owner_id UUID REFERENCES users(id) ON DELETE SET NULL,
    publisher_id UUID REFERENCES publishers(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ebooks_book_id ON ebooks(book_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_owner   ON ebooks(owner_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_format  ON ebooks(file_format);
CREATE INDEX IF NOT EXISTS idx_ebooks_publisher ON ebooks(publisher_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_title   ON ebooks USING gin(to_tsvector('simple', title));

-- Per-user reading progress
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

-- Access requests (non-members requesting an account)
CREATE TABLE IF NOT EXISTS access_requests (
    id         UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email      VARCHAR(255) NOT NULL,
    message    TEXT,
    status     VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_access_requests_status ON access_requests(status);

-- Per-user plugin settings (admin can disable ebook plugin for specific users)
CREATE TABLE IF NOT EXISTS user_plugin_settings (
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plugin_name VARCHAR(100) NOT NULL,
    enabled     BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (user_id, plugin_name)
);

-- Indexes for performance
CREATE INDEX idx_books_title ON books USING gin(to_tsvector('simple', title));
CREATE INDEX idx_books_author ON books USING gin(to_tsvector('simple', author));
CREATE INDEX idx_books_genre ON books(genre);
CREATE INDEX idx_books_language ON books(language);
CREATE INDEX idx_books_publication_year ON books(publication_year);
CREATE INDEX idx_books_read_status ON books(read_status);
CREATE INDEX idx_books_isbn ON books(isbn);
CREATE INDEX idx_books_owner ON books(owner_id);
CREATE INDEX idx_books_shelf ON books(shelf_id);
CREATE INDEX idx_books_publisher ON books(publisher_id);
CREATE INDEX idx_lending_book ON lending_records(book_id);
CREATE INDEX idx_lending_status ON lending_records(status);
CREATE INDEX idx_lending_due_date ON lending_records(due_date);
CREATE INDEX idx_audit_user ON audit_log(user_id);
CREATE INDEX idx_audit_entity ON audit_log(entity_type, entity_id);

-- Insert default genres
INSERT INTO genres (name, name_ar, name_fr) VALUES
    ('Fiction', 'رواية', 'Fiction'),
    ('Non-Fiction', 'غير خيالي', 'Non-fiction'),
    ('Science', 'علوم', 'Sciences'),
    ('History', 'تاريخ', 'Histoire'),
    ('Philosophy', 'فلسفة', 'Philosophie'),
    ('Religion', 'دين', 'Religion'),
    ('Poetry', 'شعر', 'Poésie'),
    ('Biography', 'سيرة ذاتية', 'Biographie'),
    ('Technology', 'تكنولوجيا', 'Technologie'),
    ('Art', 'فن', 'Art'),
    ('Literature', 'أدب', 'Littérature'),
    ('Education', 'تعليم', 'Éducation'),
    ('Politics', 'سياسة', 'Politique'),
    ('Economics', 'اقتصاد', 'Économie'),
    ('Psychology', 'علم نفس', 'Psychologie'),
    ('Self-Help', 'تطوير ذاتي', 'Développement personnel'),
    ('Children', 'أطفال', 'Enfants'),
    ('Travel', 'رحلات', 'Voyages'),
    ('Cooking', 'طبخ', 'Cuisine'),
    ('Islamic Studies', 'دراسات إسلامية', 'Études islamiques');

-- Insert default admin user
-- Password: Admin1234! (bcrypt, cost 12). Must be changed on first login.
-- password_verify() handles both bcrypt and argon2id transparently.
INSERT INTO users (username, email, password_hash, full_name, role, must_change_password) VALUES
    ('admin', 'admin@homelibrary.local',
     '$2y$12$/COW4ljVYn.YoMpWPrxtgu4dyeNo73abitkJiqab8LEUn.qqq3D3e',
     'مدير المكتبة', 'admin', TRUE)
    ON CONFLICT (username) DO NOTHING;
