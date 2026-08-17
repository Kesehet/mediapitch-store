ALTER TABLE brands ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_url;
CREATE INDEX IF NOT EXISTS idx_brands_active_name ON brands (active,name);
