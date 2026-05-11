-- CoinRex anti-abuse security signals for registration/device checks

CREATE TABLE IF NOT EXISTS user_security_signals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    signal_type ENUM('signup', 'login', 'taskhub', 'reward') NOT NULL DEFAULT 'signup',
    ip_hash CHAR(64) NULL,
    raw_ip VARCHAR(64) NULL,
    fingerprint_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    meta_json JSON NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uss_user_id (user_id),
    INDEX idx_uss_ip_hash (ip_hash),
    INDEX idx_uss_fingerprint_hash (fingerprint_hash),
    INDEX idx_uss_signal_type (signal_type),
    CONSTRAINT fk_uss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fraud_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    user_id INT NULL,
    email VARCHAR(255) NULL,
    ip_hash CHAR(64) NULL,
    fingerprint_hash CHAR(64) NULL,
    details_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fraud_event_type (event_type),
    INDEX idx_fraud_user_id (user_id),
    INDEX idx_fraud_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS security_flagged TINYINT(1) NOT NULL DEFAULT 0 AFTER reward_frozen,
    ADD COLUMN IF NOT EXISTS security_flag_reason VARCHAR(255) NULL AFTER security_flagged,
    ADD COLUMN IF NOT EXISTS security_warning_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER security_flag_reason,
    ADD COLUMN IF NOT EXISTS security_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER security_warning_count,
    ADD COLUMN IF NOT EXISTS taskhub_blocked_until DATETIME NULL AFTER security_suspended,
    ADD COLUMN IF NOT EXISTS boosthub_blocked_until DATETIME NULL AFTER taskhub_blocked_until,
    ADD COLUMN IF NOT EXISTS review_blocked_until DATETIME NULL AFTER boosthub_blocked_until;
