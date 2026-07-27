-- RexRank review feed upgrade

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS rexrank_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER total_rex_earned,
    ADD COLUMN IF NOT EXISTS rexrank_total_earned DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER rexrank_balance,
    ADD COLUMN IF NOT EXISTS rexrank_converted_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER rexrank_total_earned;

CREATE TABLE IF NOT EXISTS rexrank_ledger (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    balance_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    reference_type VARCHAR(40) NULL,
    reference_id VARCHAR(100) NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rexrank_user_created (user_id, created_at),
    KEY idx_rexrank_action (action_type),
    KEY idx_rexrank_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_priority_slots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    slot_group VARCHAR(20) NOT NULL,
    rexrank_cost DECIMAL(18,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_review_priority_active (status, expires_at, slot_group),
    KEY idx_review_priority_review (review_id),
    KEY idx_review_priority_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
