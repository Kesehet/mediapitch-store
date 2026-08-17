ALTER TABLE specification_definitions
ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order;
