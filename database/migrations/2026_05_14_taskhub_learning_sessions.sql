USE koinrex;

-- ============================================================
-- TaskHub Learning Sessions Table
-- Server-side tracking of active reading sessions for Learn & Quiz
-- Ensures users spend real active time on learning pages.
-- ============================================================
CREATE TABLE IF NOT EXISTS taskhub_learning_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL COMMENT 'References users.id',
    task_id INT UNSIGNED NOT NULL COMMENT 'References mini_tasks.id',
    task_key VARCHAR(80) NOT NULL COMMENT 'References mini_tasks.task_key',
    session_token VARCHAR(128) NOT NULL COMMENT 'Cryptographically secure session token',
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the session was created',
    last_heartbeat TIMESTAMP NULL DEFAULT NULL COMMENT 'Last heartbeat received from frontend',
    active_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Server-calculated active time in seconds',
    required_seconds INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Required reading time in seconds',
    interruption_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of interruptions (tab switches, blurs)',
    max_scroll_depth INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Maximum scroll depth percentage reached',
    status ENUM('active', 'paused', 'invalid', 'completed') NOT NULL DEFAULT 'active' COMMENT 'Current session status',
    validation_failed_reason VARCHAR(255) DEFAULT NULL COMMENT 'Reason if validation failed',
    completed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the session was completed/validated',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_learning_sessions_user (user_id),
    KEY idx_learning_sessions_task (task_key),
    KEY idx_learning_sessions_token (session_token),
    KEY idx_learning_sessions_status (status),
    KEY idx_learning_sessions_user_task (user_id, task_key, status),
    KEY idx_learning_sessions_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
