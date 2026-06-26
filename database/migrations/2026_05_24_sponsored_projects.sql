-- Sponsored Projects Workflow
-- Adds sponsored columns to projects table + sponsored_tokens table for one-time-use application links

USE koinrex;

-- Add sponsored columns to projects table
ALTER TABLE projects
    ADD COLUMN sponsored_status VARCHAR(30) NOT NULL DEFAULT 'none' AFTER is_sponsored,
    ADD COLUMN sponsored_requested_at DATETIME NULL AFTER sponsored_status,
    ADD COLUMN sponsored_starts_at DATETIME NULL AFTER sponsored_requested_at,
    ADD COLUMN sponsored_ends_at DATETIME NULL AFTER sponsored_starts_at;

ALTER TABLE projects
    ADD KEY idx_projects_sponsored_status (sponsored_status);

-- Create sponsored_tokens table for one-time-use application links
CREATE TABLE IF NOT EXISTS sponsored_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL,
    project_id INT UNSIGNED NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sponsored_tokens_token (token),
    KEY idx_sponsored_tokens_project_id (project_id),
    KEY idx_sponsored_tokens_used (used),
    KEY idx_sponsored_tokens_expires_at (expires_at),
    CONSTRAINT fk_sponsored_tokens_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
