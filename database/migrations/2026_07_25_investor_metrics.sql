USE koinrex;

CREATE TABLE IF NOT EXISTS user_activity_days (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    activity_date DATE NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'web',
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    activity_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_activity_day (user_id, activity_date, source),
    KEY idx_user_activity_date (activity_date),
    KEY idx_user_activity_user_date (user_id, activity_date),
    CONSTRAINT fk_user_activity_days_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    session_key_hash CHAR(64) NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'web',
    started_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_sessions_key (session_key_hash),
    KEY idx_user_sessions_user_seen (user_id, last_seen_at),
    KEY idx_user_sessions_started (started_at),
    KEY idx_user_sessions_source (source),
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_metric_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_code VARCHAR(32) NULL,
    token_hash CHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT 'Investor link',
    status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    created_by_admin_id INT UNSIGNED NULL,
    last_accessed_at DATETIME NULL,
    access_count INT UNSIGNED NOT NULL DEFAULT 0,
    revoked_at DATETIME NULL,
    revoked_by_admin_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_investor_metric_tokens_code (token_code),
    UNIQUE KEY uq_investor_metric_tokens_hash (token_hash),
    KEY idx_investor_metric_tokens_status (status),
    KEY idx_investor_metric_tokens_created_at (created_at),
    CONSTRAINT fk_investor_metric_tokens_created_by
        FOREIGN KEY (created_by_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_investor_metric_tokens_revoked_by
        FOREIGN KEY (revoked_by_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
