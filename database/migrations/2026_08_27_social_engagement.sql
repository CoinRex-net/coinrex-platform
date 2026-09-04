USE koinrex;

CREATE TABLE IF NOT EXISTS social_gate_campaigns (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(140) NOT NULL, platform ENUM('x','telegram') NOT NULL,
 modal_title VARCHAR(180) NOT NULL, modal_message TEXT NOT NULL, cta_label VARCHAR(80) NOT NULL, cta_url VARCHAR(500) NOT NULL,
 max_strikes INT UNSIGNED NOT NULL DEFAULT 3, is_active TINYINT(1) NOT NULL DEFAULT 0, activated_at DATETIME NULL,
 created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_sgc_active (is_active,activated_at), FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_gate_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, campaign_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 status ENUM('required','pending','approved','waived') NOT NULL DEFAULT 'required', strike_count INT UNSIGNED NOT NULL DEFAULT 0,
 cta_clicked_at DATETIME NULL, approved_at DATETIME NULL, waived_at DATETIME NULL, reviewed_by INT UNSIGNED NULL, admin_note TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sga_user_campaign (user_id,campaign_id), KEY idx_sga_status (status,campaign_id),
 FOREIGN KEY (campaign_id) REFERENCES social_gate_campaigns(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_gate_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, assignment_id BIGINT UNSIGNED NOT NULL, attempt_number INT UNSIGNED NOT NULL,
 handle VARCHAR(120) NOT NULL, profile_url VARCHAR(500) NOT NULL, screenshot_url VARCHAR(500) NOT NULL,
 status ENUM('pending','approved','returned') NOT NULL DEFAULT 'pending', review_note TEXT NULL, reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL, submitted_ip VARCHAR(45) NULL, submitted_user_agent VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sge_attempt (assignment_id,attempt_number), KEY idx_sge_queue (status,created_at),
 FOREIGN KEY (assignment_id) REFERENCES social_gate_assignments(id) ON DELETE CASCADE, FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engagement_announcements (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, message TEXT NOT NULL, cta_label VARCHAR(80) NULL, cta_url VARCHAR(500) NULL,
 audience ENUM('all','registered_after') NOT NULL DEFAULT 'all', audience_after DATETIME NULL, starts_at DATETIME NULL, ends_at DATETIME NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_ea_active (is_active,starts_at,ends_at), FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engagement_announcement_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, announcement_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 view_count INT UNSIGNED NOT NULL DEFAULT 0, first_viewed_at DATETIME NULL, last_viewed_at DATETIME NULL, dismissed_forever_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_eae_user (announcement_id,user_id), FOREIGN KEY (announcement_id) REFERENCES engagement_announcements(id) ON DELETE CASCADE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name,description) SELECT 'manage_engagement','Manage social gates and announcements'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_engagement');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.name='manage_engagement' WHERE r.name='super_admin';
