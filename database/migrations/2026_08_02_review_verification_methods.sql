USE koinrex;

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS verification_method VARCHAR(20) NULL AFTER eligibility_monitoring_session_id,
    ADD COLUMN IF NOT EXISTS instant_check_id INT UNSIGNED NULL AFTER verification_method;

ALTER TABLE reviews
    ADD INDEX IF NOT EXISTS idx_reviews_verification_method (verification_method);

INSERT INTO notification_templates (template_key, recipient_type, title_template, message_template, default_priority, default_action_url, is_active)
VALUES
('review.verification.instant_failed', 'user', 'Instant verification needs more evidence', '{{message}}', 'normal', '/public/submit-review.php', 1),
('review.verification.live_disqualified', 'user', 'Holding verification stopped', '{{message}}', 'high', '/public/submit-review.php', 1)
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    message_template = VALUES(message_template),
    default_priority = VALUES(default_priority),
    default_action_url = VALUES(default_action_url),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;