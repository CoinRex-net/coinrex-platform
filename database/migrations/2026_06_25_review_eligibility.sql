USE koinrex;

CREATE TABLE IF NOT EXISTS project_contracts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    network_name VARCHAR(100) NOT NULL,
    network_slug VARCHAR(80) NULL,
    chain_id INT UNSIGNED NOT NULL,
    contract_address VARCHAR(100) NULL,
    token_type VARCHAR(20) NOT NULL DEFAULT 'ERC20',
    token_name VARCHAR(120) NULL,
    token_symbol VARCHAR(40) NULL,
    decimals TINYINT UNSIGNED NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    verification_status VARCHAR(30) NOT NULL DEFAULT 'needs_check',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_contract_chain_address (chain_id, contract_address),
    KEY idx_project_contracts_project (project_id, is_active),
    KEY idx_project_contracts_primary (project_id, is_primary),
    CONSTRAINT fk_project_contracts_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_eligibility_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    wallet_address VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'not_eligible',
    matched_project_contract_id INT UNSIGNED NULL,
    matched_chain_id INT UNSIGNED NULL,
    balance_raw VARCHAR(120) NULL,
    balance_display VARCHAR(120) NULL,
    reason TEXT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    raw_result_json JSON NULL,
    PRIMARY KEY (id),
    KEY idx_review_eligibility_lookup (user_id, project_id, wallet_address, expires_at),
    KEY idx_review_eligibility_status (status, checked_at),
    CONSTRAINT fk_review_eligibility_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_eligibility_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_eligibility_contract
        FOREIGN KEY (matched_project_contract_id) REFERENCES project_contracts(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reviews
    MODIFY tx_hash VARCHAR(255) NULL;

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS eligibility_check_id INT UNSIGNED NULL AFTER proof_status,
    ADD COLUMN IF NOT EXISTS eligibility_status VARCHAR(30) NULL AFTER eligibility_check_id,
    ADD COLUMN IF NOT EXISTS eligibility_wallet_address VARCHAR(100) NULL AFTER eligibility_status,
    ADD COLUMN IF NOT EXISTS eligibility_chain_id INT UNSIGNED NULL AFTER eligibility_wallet_address,
    ADD COLUMN IF NOT EXISTS eligibility_contract_address VARCHAR(100) NULL AFTER eligibility_chain_id;

UPDATE project_contracts SET contract_address = NULL WHERE token_type = 'NATIVE' AND TRIM(COALESCE(contract_address, '')) = '';

ALTER TABLE project_contracts
    MODIFY contract_address VARCHAR(100) NULL;
