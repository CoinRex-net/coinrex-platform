-- One-time review correction metadata.

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS correction_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER auto_approved_by_level,
    ADD COLUMN IF NOT EXISTS correction_requested_at DATETIME NULL AFTER correction_count,
    ADD COLUMN IF NOT EXISTS correction_note TEXT NULL AFTER correction_requested_at;
