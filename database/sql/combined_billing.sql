-- Combined billing schema change for existing MySQL/MariaDB databases.
-- Run once after taking a database backup and confirming the target schema.
-- This leaves billings.registration_id in place for existing API compatibility.

ALTER TABLE registrations
    ADD COLUMN billing_id BIGINT UNSIGNED NULL AFTER clinic_id,
    ADD INDEX registrations_billing_id_index (billing_id),
    ADD CONSTRAINT registrations_billing_id_foreign
        FOREIGN KEY (billing_id) REFERENCES billings (id)
        ON DELETE SET NULL;

ALTER TABLE billing_items
    ADD COLUMN registration_id BIGINT UNSIGNED NULL AFTER billing_id,
    ADD INDEX billing_items_registration_id_index (registration_id),
    ADD CONSTRAINT billing_items_registration_id_foreign
        FOREIGN KEY (registration_id) REFERENCES registrations (id)
        ON DELETE SET NULL;

UPDATE registrations AS r
JOIN billings AS b ON b.registration_id = r.id
SET r.billing_id = b.id
WHERE r.billing_id IS NULL;

UPDATE billing_items AS bi
JOIN billings AS b ON b.id = bi.billing_id
SET bi.registration_id = b.registration_id
WHERE bi.registration_id IS NULL;
