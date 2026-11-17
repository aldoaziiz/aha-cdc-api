-- Run once per target database after backup and schema inspection.
-- Existing invoices receive a zero discount. Master prices are not modified.

ALTER TABLE billing_items
    ADD COLUMN discount_amount DECIMAL(15, 2) NOT NULL DEFAULT 0 AFTER subtotal;
