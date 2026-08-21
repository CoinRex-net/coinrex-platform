-- RexLink SDK v1 application identity and fast pairing status support.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rex_signer_apps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    app_id VARCHAR(64) NOT NULL,
    app_name VARCHAR(128) NOT NULL,
    app_url VARCHAR(512) NULL,
    public_key VARCHAR(256) NULL,
    callback_url VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rex_signer_apps_app_id (app_id),
    KEY idx_rex_signer_apps_active (is_active, app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rex_signer_apps (app_id, app_name, app_url, is_active)
VALUES ('coinrex', 'CoinRex', NULL, 1)
ON DUPLICATE KEY UPDATE app_name = VALUES(app_name), is_active = VALUES(is_active);

ALTER TABLE rex_signer_pairing_codes
    ADD COLUMN IF NOT EXISTS app_id VARCHAR(64) NOT NULL DEFAULT 'coinrex' AFTER user_id,
    ADD COLUMN IF NOT EXISTS poll_token_hash CHAR(64) NULL AFTER code_hash,
    ADD INDEX IF NOT EXISTS idx_rex_signer_pairing_app_status (app_id, status, expires_at);

ALTER TABLE rex_signer_sessions
    ADD COLUMN IF NOT EXISTS app_id VARCHAR(64) NOT NULL DEFAULT 'coinrex' AFTER user_id,
    ADD INDEX IF NOT EXISTS idx_rex_signer_sessions_app_status (app_id, status, expires_at);

ALTER TABLE rex_signer_approval_requests
    ADD COLUMN IF NOT EXISTS app_id VARCHAR(64) NOT NULL DEFAULT 'coinrex' AFTER user_id,
    ADD INDEX IF NOT EXISTS idx_rex_signer_approvals_app_status (app_id, status, expires_at);
