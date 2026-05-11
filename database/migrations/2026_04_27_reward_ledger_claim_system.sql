USE koinrex;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS wallet_address VARCHAR(100) NULL AFTER country;

CREATE TABLE IF NOT EXISTS reward_ledger (
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

CREATE TABLE IF NOT EXISTS claim_snapshots (
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

CREATE TABLE IF NOT EXISTS mini_tasks (
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

CREATE TABLE IF NOT EXISTS user_task_logs (
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
