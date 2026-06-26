CREATE TABLE IF NOT EXISTS roadmap_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_status ENUM('draft','published') NOT NULL,
    title VARCHAR(180) NOT NULL,
    title_gold_word VARCHAR(80) NOT NULL DEFAULT '',
    subtitle VARCHAR(300) NOT NULL DEFAULT '',
    eyebrow VARCHAR(120) NOT NULL DEFAULT '',
    progress_label VARCHAR(120) NOT NULL DEFAULT '',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    progress_note VARCHAR(300) NOT NULL DEFAULT '',
    bottom_statement VARCHAR(220) NOT NULL DEFAULT '',
    published_at DATETIME NULL,
    published_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roadmap_settings_status (version_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roadmap_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_status ENUM('draft','published') NOT NULL,
    stage_number VARCHAR(20) NOT NULL,
    title VARCHAR(160) NOT NULL,
    status_label VARCHAR(120) NOT NULL DEFAULT '',
    badge ENUM('CURRENT','NEXT','PLANNED','FUTURE') NOT NULL DEFAULT 'PLANNED',
    tone ENUM('current','next','planned','future') NOT NULL DEFAULT 'planned',
    icon VARCHAR(80) NOT NULL DEFAULT 'fa-circle-nodes',
    milestone_note TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_roadmap_stages_status_sort (version_status, sort_order),
    KEY idx_roadmap_stages_visible (is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roadmap_stage_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stage_id INT UNSIGNED NOT NULL,
    entry_type ENUM('item','goal') NOT NULL DEFAULT 'item',
    label VARCHAR(180) NOT NULL,
    icon VARCHAR(80) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_roadmap_entries_stage_sort (stage_id, sort_order),
    CONSTRAINT fk_roadmap_entries_stage
        FOREIGN KEY (stage_id) REFERENCES roadmap_stages(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description)
SELECT 'manage_roadmap', 'Manage public roadmap stages and publishing'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'manage_roadmap');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name = 'manage_roadmap'
WHERE r.name = 'super_admin';
