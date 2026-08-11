-- REX Signer session/pairing foundation
-- Testnet-first MVP backend for extension-free CoinRex signing flows.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS rex_signer_networks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    chain_id INT UNSIGNED NULL,
    native_symbol VARCHAR(20) NOT NULL,
    rpc_url VARCHAR(500) NULL,
    explorer_url VARCHAR(500) NULL,
    environment ENUM('staging','testnet','mainnet','stub') NOT NULL DEFAULT 'testnet',
    chain_family VARCHAR(20) NOT NULL DEFAULT 'evm',
    claim_enabled TINYINT(1) NOT NULL DEFAULT 0,
    token_support_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rex_signer_networks_slug (slug),
    KEY idx_rex_signer_networks_enabled (is_enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rex_signer_activity_cache (
    wallet_address VARCHAR(42) NOT NULL,
    network_slug VARCHAR(50) NOT NULL,
    history_json LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (wallet_address, network_slug),
    KEY idx_rex_signer_activity_cache_fetched (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rex_signer_networks
    (slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, chain_family, claim_enabled, token_support_enabled, is_enabled, sort_order)
VALUES
    ('polygon', 'Polygon', 137, 'POL', 'https://polygon-rpc.com', 'https://polygonscan.com', 'mainnet', 'evm', 0, 1, 1, 10),
    ('base', 'Base', 8453, 'ETH', 'https://mainnet.base.org', 'https://basescan.org', 'mainnet', 'evm', 0, 1, 1, 20),
    ('plasma', 'Plasma', NULL, 'XPL', NULL, NULL, 'mainnet', 'evm', 0, 0, 1, 30),
    ('polygon-amoy', 'Polygon Amoy', 80002, 'POL', 'https://rpc-amoy.polygon.technology', 'https://amoy.polygonscan.com', 'staging', 'evm', 1, 1, 0, 90)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    chain_id = VALUES(chain_id),
    native_symbol = VALUES(native_symbol),
    rpc_url = VALUES(rpc_url),
    explorer_url = VALUES(explorer_url),
    environment = VALUES(environment),
    chain_family = VALUES(chain_family),
    claim_enabled = VALUES(claim_enabled),
    token_support_enabled = VALUES(token_support_enabled),
    is_enabled = VALUES(is_enabled),
    sort_order = VALUES(sort_order);

UPDATE rex_signer_networks SET is_enabled = 0, environment = 'stub' WHERE slug = 'plasma-testnet';
UPDATE rex_signer_networks SET is_enabled = 0 WHERE slug = 'polygon-amoy' AND environment = 'staging';

CREATE TABLE IF NOT EXISTS rex_signer_pairing_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    code_hash CHAR(64) NOT NULL,
    display_code VARCHAR(32) NOT NULL,
    pairing_purpose VARCHAR(30) NOT NULL DEFAULT 'claim',
    referral_code VARCHAR(32) NULL,
    status ENUM('pending','completed','expired','revoked') NOT NULL DEFAULT 'pending',
    requested_duration_minutes INT UNSIGNED NOT NULL DEFAULT 10,
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    completed_session_id INT UNSIGNED NULL,
    device_fingerprint VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rex_signer_pairing_code_hash (code_hash),
    KEY idx_rex_signer_pairing_user_status (user_id, status),
    KEY idx_rex_signer_pairing_expires (expires_at),
    CONSTRAINT fk_rex_signer_pairing_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rex_signer_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    pairing_code_id INT UNSIGNED NULL,
    session_token_hash CHAR(64) NOT NULL,
    device_name VARCHAR(120) NULL,
    status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoke_reason VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rex_signer_session_token_hash (session_token_hash),
    KEY idx_rex_signer_sessions_user_status (user_id, status, expires_at),
    KEY idx_rex_signer_sessions_pairing (pairing_code_id),
    CONSTRAINT fk_rex_signer_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_rex_signer_sessions_pairing
        FOREIGN KEY (pairing_code_id) REFERENCES rex_signer_pairing_codes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rex_signer_approval_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    network_slug VARCHAR(50) NOT NULL,
    request_type ENUM('claim','send','message') NOT NULL DEFAULT 'claim',
    title VARCHAR(160) NOT NULL,
    summary VARCHAR(255) NULL,
    amount VARCHAR(80) NULL,
    fee_estimate VARCHAR(80) NULL,
    payload_json JSON NULL,
    status ENUM('pending','approved','rejected','expired','cancelled') NOT NULL DEFAULT 'pending',
    decision_note VARCHAR(255) NULL,
    decided_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rex_signer_approvals_user_status (user_id, status, expires_at),
    KEY idx_rex_signer_approvals_session (session_id),
    KEY idx_rex_signer_approvals_network (network_slug),
    CONSTRAINT fk_rex_signer_approvals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_rex_signer_approvals_session
        FOREIGN KEY (session_id) REFERENCES rex_signer_sessions(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
