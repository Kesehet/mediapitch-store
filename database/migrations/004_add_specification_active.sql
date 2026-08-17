ALTER TABLE specification_definitions
ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order;
