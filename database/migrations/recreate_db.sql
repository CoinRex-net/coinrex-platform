-- CoinRex full schema rebuild
-- Recreated from live code audit on 2026-04-14
-- Warning: this script drops and recreates CoinRex tables.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS koinrex
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE koinrex;

DROP TABLE IF EXISTS content_flags;
DROP TABLE IF EXISTS review_reactions;
DROP TABLE IF EXISTS user_task_logs;
DROP TABLE IF EXISTS mini_tasks;
DROP TABLE IF EXISTS claim_snapshots;
DROP TABLE IF EXISTS reward_ledger;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS admin_activity_logs;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS developer_verification;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    referral_code VARCHAR(16) NOT NULL,
    referred_by INT UNSIGNED NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    level VARCHAR(20) NOT NULL DEFAULT 'beginner',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    rex_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_rex_earned DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    referral_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    signup_ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verified_at DATETIME NULL,
    otp_code CHAR(6) NULL,
    otp_expiry DATETIME NULL,
    otp_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_login DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    last_active DATETIME NULL,
    remember_token_hash VARCHAR(255) NULL,
    remember_token_expires_at DATETIME NULL,
    avatar VARCHAR(255) NULL,
    country VARCHAR(100) NULL,
    wallet_address VARCHAR(100) NULL,
    wallet_type VARCHAR(20) NOT NULL DEFAULT 'non_custodial',
    is_developer_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_expert TINYINT(1) NOT NULL DEFAULT 0,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_affiliate TINYINT(1) NOT NULL DEFAULT 0,
    has_verified_badge TINYINT(1) NOT NULL DEFAULT 0,
    expert_at DATETIME NULL,
    total_referrals INT UNSIGNED NOT NULL DEFAULT 0,
    valid_referrals INT UNSIGNED NOT NULL DEFAULT 0,
    referral_qualified_at DATETIME NULL,
    total_reviews INT UNSIGNED NOT NULL DEFAULT 0,
    approved_reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_referral_code (referral_code),
    KEY idx_users_status (status),
    KEY idx_users_role (role),
    KEY idx_users_level (level),
    KEY idx_users_referred_by (referred_by),
    KEY idx_users_created_at (created_at),
    CONSTRAINT fk_users_referred_by
        FOREIGN KEY (referred_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(100) NULL,
    name VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    UNIQUE KEY uq_admins_username (username),
    KEY idx_admins_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SECURITY NOTE:
-- No default admin account is inserted by this schema rebuild.
-- Create your own admin explicitly after import using a unique email,
-- a unique username, and a freshly generated bcrypt password hash.
-- See database/migrations/admin_seed.sql for a safe bootstrap template.

CREATE TABLE projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    logo VARCHAR(255) NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    telegram_url VARCHAR(255) NULL,
    twitter_url VARCHAR(255) NULL,
    contract_address VARCHAR(255) NOT NULL,
    github_url VARCHAR(255) NULL,
    discord_url VARCHAR(255) NULL,
    network VARCHAR(100) NULL,
    project_live_since DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
    min_holding_amount DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    max_reward_rex DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    required_holding_days INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    approval_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    feature_status VARCHAR(30) NOT NULL DEFAULT 'none',
    feature_requested_at DATETIME NULL,
    feature_reviewed_at DATETIME NULL,
    feature_reviewed_by INT UNSIGNED NULL,
    featured_at DATETIME NULL,
    project_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    total_reviews INT UNSIGNED NOT NULL DEFAULT 0,
    avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    UNIQUE KEY uq_projects_website_url (website_url),
    UNIQUE KEY uq_projects_contract_address (contract_address),
    KEY idx_projects_created_by (created_by),
    KEY idx_projects_approval_status (approval_status),
    KEY idx_projects_status (status),
    KEY idx_projects_featured (is_featured),
    KEY idx_projects_feature_status (feature_status),
    KEY idx_projects_verified (is_verified),
    KEY idx_projects_created_at (created_at),
    CONSTRAINT fk_projects_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_feature_reviewed_by_admin
        FOREIGN KEY (feature_reviewed_by) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE developer_verification (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    username VARCHAR(100) NULL,
    password_hash VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    verification_post_url VARCHAR(500) NULL,
    verification_url VARCHAR(500) NULL,
    verification_code TEXT NULL,
    has_verified_badge TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dev_verification_user_id (user_id),
    KEY idx_dev_verification_status (status),
    KEY idx_dev_verification_updated_at (updated_at),
    CONSTRAINT fk_dev_verification_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    review_title VARCHAR(255) NULL,
    review_content TEXT NOT NULL,
    rating DECIMAL(2,1) NOT NULL,
    pros TEXT NULL,
    cons TEXT NULL,
    holding_amount DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    holding_days INT UNSIGNED NOT NULL DEFAULT 0,
    wallet_type VARCHAR(20) NOT NULL DEFAULT 'non_custodial',
    tx_hash VARCHAR(255) NOT NULL,
    wallet_address VARCHAR(255) NOT NULL,
    screenshot_url VARCHAR(255) NULL,
    tokenomics_score TINYINT UNSIGNED NULL,
    team_score TINYINT UNSIGNED NULL,
    utility_score TINYINT UNSIGNED NULL,
    community_score TINYINT UNSIGNED NULL,
    risk_score TINYINT UNSIGNED NULL,
    calculated_rex DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    final_rex DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    review_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    proof_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    approval_note TEXT NULL,
    helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    proof_verified_by INT UNSIGNED NULL,
    proof_verified_at DATETIME NULL,
    proof_rejection_reason TEXT NULL,
    auto_approved_at DATETIME NULL,
    auto_approved_by_level TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_tx_hash (tx_hash),
    UNIQUE KEY uq_reviews_user_project (user_id, project_id),
    KEY idx_reviews_user_status (user_id, status),
    KEY idx_reviews_project_status (project_id, status),
    KEY idx_reviews_wallet_address (wallet_address),
    KEY idx_reviews_review_score (review_score),
    KEY idx_reviews_created_at (created_at),
    KEY idx_reviews_reviewed_by (reviewed_by),
    KEY idx_reviews_proof_verified_by (proof_verified_by),
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_reviewed_by_admin
        FOREIGN KEY (reviewed_by) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_proof_verified_by_admin
        FOREIGN KEY (proof_verified_by) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_reactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reaction_type VARCHAR(20) NOT NULL DEFAULT 'like',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_user_reaction (review_id, user_id, reaction_type),
    KEY idx_review_reaction_review (review_id),
    KEY idx_review_reaction_user (user_id),
    CONSTRAINT fk_review_reactions_review
        FOREIGN KEY (review_id) REFERENCES reviews(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_reactions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reward_ledger (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    source ENUM('mini_task','referral','review','bonus') NOT NULL,
    reward_phase ENUM('phase1','phase2') NOT NULL DEFAULT 'phase1',
    action_type VARCHAR(50) NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    status ENUM('pending','locked','available','claimed') NOT NULL DEFAULT 'pending',
    reference_id VARCHAR(100) NULL,
    user_level_at_time VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reward_ledger_user_status (user_id, status),
    KEY idx_reward_ledger_source (source),
    KEY idx_reward_ledger_reference (reference_id),
    KEY idx_reward_ledger_created_at (created_at),
    CONSTRAINT fk_reward_ledger_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE claim_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    total_amount DECIMAL(18,8) NOT NULL,
    nonce BIGINT UNSIGNED NOT NULL,
    status ENUM('generated','used','expired') NOT NULL DEFAULT 'generated',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_claim_snapshots_nonce (nonce),
    KEY idx_claim_snapshots_user_status (user_id, status),
    KEY idx_claim_snapshots_created_at (created_at),
    CONSTRAINT fk_claim_snapshots_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mini_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT NULL,
    reward DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    daily_limit INT NOT NULL DEFAULT 1,
    cooldown_seconds INT NOT NULL DEFAULT 86400,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_mini_tasks_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_task_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('completed','blocked') NOT NULL DEFAULT 'completed',
    PRIMARY KEY (id),
    KEY idx_user_task_logs_user_status (user_id, status),
    KEY idx_user_task_logs_task_status (task_id, status),
    KEY idx_user_task_logs_completed_at (completed_at),
    CONSTRAINT fk_user_task_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_user_task_logs_task
        FOREIGN KEY (task_id) REFERENCES mini_tasks(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_flags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    target_type VARCHAR(20) NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_flag_user_target (user_id, target_type, target_id),
    KEY idx_content_flags_target (target_type, target_id),
    KEY idx_content_flags_status (status),
    CONSTRAINT fk_content_flags_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id VARCHAR(80) NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_activity_admin_id (admin_id),
    KEY idx_admin_activity_target (target_type, target_id),
    KEY idx_admin_activity_created_at (created_at),
    CONSTRAINT fk_admin_activity_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NULL,
    body TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unread',
    recipient_admin_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_messages_status (status),
    KEY idx_messages_recipient (recipient_admin_id),
    KEY idx_messages_created_at (created_at),
    CONSTRAINT fk_messages_recipient_admin
        FOREIGN KEY (recipient_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
