USE koinrex;

ALTER TABLE project_contracts
    ADD COLUMN IF NOT EXISTS eligibility_min_amount VARCHAR(120) NULL AFTER decimals,
    ADD COLUMN IF NOT EXISTS eligibility_holding_minutes INT UNSIGNED NULL AFTER eligibility_min_amount;

CREATE TABLE IF NOT EXISTS review_eligibility_monitoring_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    project_contract_id INT UNSIGNED NOT NULL,
    wallet_address VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    reason_code VARCHAR(80) NOT NULL DEFAULT 'monitoring_active',
    reason TEXT NULL,
    token_type VARCHAR(20) NOT NULL,
    token_symbol VARCHAR(40) NOT NULL,
    token_decimals TINYINT UNSIGNED NOT NULL DEFAULT 18,
    required_amount VARCHAR(120) NOT NULL,
    required_amount_raw VARCHAR(120) NOT NULL,
    start_balance_raw VARCHAR(120) NOT NULL,
    last_balance_raw VARCHAR(120) NOT NULL,
    qualifying_tx_hash VARCHAR(100) NULL,
    start_block BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_checked_block BIGINT UNSIGNED NOT NULL DEFAULT 0,
    failure_tx_hash VARCHAR(100) NULL,
    ownership_verified_at DATETIME NOT NULL,
    started_at DATETIME NOT NULL,
    eligible_at DATETIME NOT NULL,
    next_check_at DATETIME NOT NULL,
    last_checked_at DATETIME NULL,
    completed_at DATETIME NULL,
    disqualified_at DATETIME NULL,
    expires_at DATETIME NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_review_monitor_due (status, next_check_at),
    KEY idx_review_monitor_lookup (user_id, project_id, wallet_address, status),
    KEY idx_review_monitor_contract (project_contract_id, status),
    CONSTRAINT fk_review_monitor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_monitor_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_monitor_contract FOREIGN KEY (project_contract_id) REFERENCES project_contracts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_eligibility_monitoring_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitoring_session_id BIGINT UNSIGNED NOT NULL,
    tx_hash VARCHAR(100) NOT NULL,
    log_index INT UNSIGNED NOT NULL DEFAULT 0,
    block_number BIGINT UNSIGNED NOT NULL,
    block_hash VARCHAR(100) NULL,
    event_at DATETIME NOT NULL,
    direction VARCHAR(20) NOT NULL,
    amount_raw VARCHAR(120) NOT NULL,
    balance_after_raw VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_monitor_event (monitoring_session_id, tx_hash, log_index),
    KEY idx_review_monitor_event_block (monitoring_session_id, block_number),
    CONSTRAINT fk_review_monitor_event_session FOREIGN KEY (monitoring_session_id) REFERENCES review_eligibility_monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_eligibility_notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitoring_session_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(255) NULL,
    in_app_delivered_at DATETIME NULL,
    email_delivered_at DATETIME NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_monitor_notification (monitoring_session_id, event_key),
    KEY idx_review_monitor_notification_due (next_attempt_at, in_app_delivered_at, email_delivered_at),
    CONSTRAINT fk_review_monitor_notification_session FOREIGN KEY (monitoring_session_id) REFERENCES review_eligibility_monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS eligibility_monitoring_session_id BIGINT UNSIGNED NULL AFTER eligibility_check_id;

INSERT INTO notification_templates (template_key, recipient_type, title_template, message_template, default_priority, default_action_url, is_active)
VALUES
('review.eligibility.started', 'user', 'Holding verification started', '{{message}}', 'normal', '/public/submit-review.php', 1),
('review.eligibility.stopped', 'user', 'Holding verification stopped', '{{message}}', 'high', '/public/submit-review.php', 1),
('review.eligibility.completed', 'user', 'You are eligible to review {{project_name}}', '{{message}}', 'high', '/public/submit-review.php', 1),
('review.eligibility.delayed', 'user', 'Blockchain monitoring delayed', '{{message}}', 'normal', '/public/submit-review.php', 1)
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    message_template = VALUES(message_template),
    default_priority = VALUES(default_priority),
    default_action_url = VALUES(default_action_url),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;
