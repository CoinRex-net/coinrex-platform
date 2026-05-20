USE koinrex;

-- ============================================================
-- TaskHub Quiz Questions Table (Admin-Managed)
-- Replaces hardcoded PHP quiz arrays with DB-driven storage.
-- Each task_key links to a mini_tasks row in the mission group.
-- ============================================================
CREATE TABLE IF NOT EXISTS taskhub_quiz_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_key VARCHAR(80) NOT NULL COMMENT 'References mini_tasks.task_key for mission tasks',
    question TEXT NOT NULL COMMENT 'The question text',
    choices JSON NOT NULL COMMENT 'JSON array of answer choices (strings)',
    answer INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Index of the correct answer in the choices array',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Display order within this task_key quiz',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_taskhub_quiz_task_key (task_key),
    KEY idx_taskhub_quiz_task_key_active (task_key, is_active),
    KEY idx_taskhub_quiz_sort (task_key, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
