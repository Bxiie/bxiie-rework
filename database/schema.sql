CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tenant_domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    domain TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('owner','editor','viewer')),
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(tenant_id, email)
);

CREATE TABLE IF NOT EXISTS settings (
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (tenant_id, setting_key)
);

CREATE TABLE IF NOT EXISTS images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT,
    medium TEXT,
    year TEXT,
    dimensions TEXT,
    price TEXT,
    sale_status TEXT,
    location TEXT,
    tags TEXT,
    alt_text TEXT,
    sort_order INTEGER NOT NULL DEFAULT 100,
    storage_key TEXT NOT NULL,
    original_path TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    width INTEGER NOT NULL DEFAULT 0,
    height INTEGER NOT NULL DEFAULT 0,
    is_public INTEGER NOT NULL DEFAULT 1,
    is_draft INTEGER NOT NULL DEFAULT 0,
    featured_home INTEGER NOT NULL DEFAULT 0,
    featured_rotator INTEGER NOT NULL DEFAULT 0,
    featured_about INTEGER NOT NULL DEFAULT 0,
    featured_contact INTEGER NOT NULL DEFAULT 0,
    background_image INTEGER NOT NULL DEFAULT 0,
    watermarked INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS portfolio_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 100,
    UNIQUE(tenant_id, slug)
);

CREATE TABLE IF NOT EXISTS image_sections (
    image_id INTEGER NOT NULL REFERENCES images(id) ON DELETE CASCADE,
    section_id INTEGER NOT NULL REFERENCES portfolio_sections(id) ON DELETE CASCADE,
    PRIMARY KEY (image_id, section_id)
);

CREATE TABLE IF NOT EXISTS exhibitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    venue TEXT,
    city TEXT,
    state TEXT,
    event_date TEXT,
    display_date TEXT,
    url TEXT,
    description TEXT,
    event_type TEXT,
    work_name TEXT,
    additional_info TEXT,
    is_recent INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    email TEXT NOT NULL,
    name TEXT,
    source TEXT,
    created_at TEXT NOT NULL,
    UNIQUE(tenant_id, email)
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name TEXT,
    email TEXT,
    message TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS page_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    event_type TEXT NOT NULL,
    image_id INTEGER REFERENCES images(id) ON DELETE SET NULL,
    path TEXT,
    referrer TEXT,
    user_agent TEXT,
    ip_hash TEXT,
    country_code TEXT,
    created_at TEXT NOT NULL
);
