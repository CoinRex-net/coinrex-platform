CREATE TABLE IF NOT EXISTS feature_flags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feature_key VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    feature_group VARCHAR(80) NOT NULL DEFAULT 'General',
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    is_accessible TINYINT(1) NOT NULL DEFAULT 1,
    fallback_title VARCHAR(180) NOT NULL DEFAULT '',
    fallback_message TEXT NULL,
    fallback_cta_label VARCHAR(80) NOT NULL DEFAULT '',
    fallback_cta_url VARCHAR(500) NOT NULL DEFAULT '',
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feature_flags_key (feature_key),
    KEY idx_feature_flags_group (feature_group),
    KEY idx_feature_flags_visible (is_visible),
    KEY idx_feature_flags_accessible (is_accessible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description)
SELECT 'manage_launch_controls', 'Manage MVP launch feature visibility and access'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'manage_launch_controls');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name = 'manage_launch_controls'
WHERE r.name = 'super_admin';
