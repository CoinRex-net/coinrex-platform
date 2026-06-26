-- Early Adopter Airdrop System
-- 7% of 1B REX (70,000,000 REX) for first users
-- 1,000 REX signup bonus + 50 REX per valid referral
-- Pool-based: airdrop ends when pool is exhausted

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- early_airdrop_pool: Single-row table tracking the remaining pool
-- ============================================================
CREATE TABLE IF NOT EXISTS early_airdrop_pool (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    remaining_rex DECIMAL(18,8) NOT NULL DEFAULT 70000000.00000000,
    total_allocated_signup DECIMAL(18,8) NOT NULL DEFAULT 0,
    total_allocated_referral DECIMAL(18,8) NOT NULL DEFAULT 0,
    signup_count INT UNSIGNED NOT NULL DEFAULT 0,
    referral_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the pool if empty
INSERT IGNORE INTO early_airdrop_pool (id, remaining_rex) VALUES (1, 70000000);

-- ============================================================
-- early_airdrop_claims: Tracks who claimed what from the pool
-- ============================================================
CREATE TABLE IF NOT EXISTS early_airdrop_claims (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    claim_type ENUM('signup_bonus', 'referral_bonus') NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    reference_id VARCHAR(100) NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_early_claims_user (user_id),
    KEY idx_early_claims_type (claim_type),
    KEY idx_early_claims_claimed_at (claimed_at),
    CONSTRAINT fk_early_claims_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
