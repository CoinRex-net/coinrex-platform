USE koinrex;

ALTER TABLE projects
    ADD COLUMN feature_status VARCHAR(30) NOT NULL DEFAULT 'none' AFTER is_featured,
    ADD COLUMN feature_requested_at DATETIME NULL AFTER feature_status,
    ADD COLUMN feature_reviewed_at DATETIME NULL AFTER feature_requested_at,
    ADD COLUMN feature_reviewed_by INT UNSIGNED NULL AFTER feature_reviewed_at,
    ADD COLUMN featured_at DATETIME NULL AFTER feature_reviewed_by;

ALTER TABLE projects
    ADD KEY idx_projects_feature_status (feature_status);

ALTER TABLE projects
    ADD CONSTRAINT fk_projects_feature_reviewed_by_admin
    FOREIGN KEY (feature_reviewed_by) REFERENCES admins(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

UPDATE projects
SET feature_status = 'pending_review',
    feature_requested_at = COALESCE(feature_requested_at, NOW()),
    updated_at = NOW()
WHERE approval_status = 'approved'
  AND COALESCE(is_featured, 0) = 0
  AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'none'
  AND COALESCE(avg_rating, 0) >= 4.0
  AND COALESCE(total_reviews, 0) >= 100;
