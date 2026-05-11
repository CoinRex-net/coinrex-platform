-- CoinRex in-app notifications: templates + deliveries

CREATE TABLE IF NOT EXISTS notification_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(120) NOT NULL,
    recipient_type ENUM('user','admin','developer') NOT NULL DEFAULT 'user',
    title_template VARCHAR(180) NOT NULL,
    message_template TEXT NOT NULL,
    default_priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    default_action_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_template_key (template_key),
    KEY idx_notification_templates_recipient_type (recipient_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('user','admin','developer') NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    actor_type VARCHAR(30) NULL,
    actor_id INT UNSIGNED NULL,
    template_key VARCHAR(120) NULL,
    event_key VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(255) NULL,
    meta_json JSON NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_recipient_read (recipient_type, recipient_id, is_read),
    KEY idx_notifications_created_at (created_at),
    KEY idx_notifications_event_key (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notification_templates (template_key, recipient_type, title_template, message_template, default_priority, default_action_url, is_active)
VALUES
('review.approved', 'user', 'Review approved ✅', 'Great news! Your review for {{project_name}} was approved and rewards were added to your balance.', 'high', '/my-reviews.php', 1),
('review.rejected', 'user', 'Review rejected', 'Your review for {{project_name}} was rejected. Reason: {{reason}}', 'normal', '/my-reviews.php', 1),
('review.flagged', 'user', 'Review flagged for checks', 'Your review for {{project_name}} was flagged for additional moderation checks.', 'normal', '/my-reviews.php', 1),
('reward.added', 'user', 'Reward added 💰', '{{amount}} $REX was added to your reward ledger ({{action_type}}).', 'high', '/reward-history.php', 1),
('developer.verification.approved', 'developer', 'Profile verification approved ✅', 'Dear {{developer_name}}, congratulations. Your profile verification has been approved successfully. You can now start by opening your DevHub dashboard, submitting your project, and building your verified presence on CoinRex.', 'high', '/devhub/index.php', 1),
('developer.verification.rejected', 'developer', '⚠️ Verification Not Approved', 'Hi {{developer_name}},\nWe couldn''t verify your developer profile this time.\n\nNo worries — this usually happens due to incomplete or mismatched verification signals.\n\n---\n\n🔍 Possible reasons:\n• X (Twitter) verification post not found or missing required #tags\n• Submitted handle does not have a Verified Blue Tick by X\n• Domain meta tag not detected or incorrectly placed\n• Website not accessible or not linked properly\n\n---\n\n💡 How to fix it:\n\n🔗 Social Verification (X):\n• Post using the required hashtags exactly as instructed\n• Make sure your X handle matches your project branding\n• Keep the post public until verification is complete\n\n🌐 Domain Verification:\n• Add the provided meta tag inside your website''s <head> section\n• Ensure your domain is live and accessible\n• Double-check for typos or incorrect placement\n\n---\n\n🚀 Next step:\nUpdate your verification details and submit again.\n\n[Retry Verification]\n\n---\n\n📢 Pro Insight:\nDevelopers with verified social + domain presence gain significantly more trust and engagement on DevHub.\n\n🔒 We respect your privacy — no documents are required for verification.\n\nNeed help? → Contact Support', 'high', '/devhub/apply.php', 1),
('developer.verification.change_requested', 'developer', 'Profile verification under review', 'Dear {{developer_name}}, thank you for your patience. Your profile verification update is currently under review. We will notify you as soon as the review decision is completed.', 'normal', '/devhub/apply.php', 1),
('project.approved', 'developer', '🎉 You''re Live on DevHub!', 'Your project **"{{project_name}}"** has been successfully approved.\n\n---\n\n🚀 What you should do now:\n• Share your project link with your community\n• Drive engagement (clicks, reviews, activity)\n• Keep your project updated regularly\n\n---\n\n📈 Growth Tips:\n• Post on Twitter, Telegram, Discord\n• Encourage users to interact with your listing\n• Stay active to increase visibility\n\n[View Project]\n\n---\n\n🔥 Bonus:\nHigh-performing projects may get fast **featured placement** on CoinRex .', 'high', '/devhub/index.php', 1),
('project.rejected', 'developer', '❌ Project Not Approved', 'Your project **"{{project_name}}"** didn''t pass our review this time.\n\nWe know that''s not ideal — but the good news is you can fix it quickly.\n\n---\n\n🔍 What likely caused this:\n• Incomplete or unclear description\n• Missing social links (Twitter / Website)\n• Low-quality visuals or branding\n\n💡 How to improve your chances:\n• Write a clear, value-driven description\n• Add at least 2 active social links\n• Upload a clean logo + banner\n\n---\n\n🚀 Next step:\nUpdate your project and submit again for review after the cooldown Period.\n\n[Resubmit Project]\n\n---\n\n📢 Pro Insight:\nProjects with active communities and strong presentation get approved up to **3x faster**.\n\nNeed help? → Contact Support', 'high', '/devhub/projects/edit_project.php', 1),
('project.under_review', 'developer', 'Project "{{project_name}}" under review', 'Dear {{developer_name}}, your project "{{project_name}}" is currently under review by our moderation team. We appreciate your patience and will notify you once a decision is made.', 'normal', '/devhub/index.php', 1),
('project.flagged', 'developer', '⚠️ Attention Required', 'Your project **"{{project_name}}"** has been flagged for review.\n\n---\n\n🔍 What this means:\nSome aspects of your project may not meet platform guidelines.\n\n---\n\n💡 What you should do:\n• Review your project content carefully\n• Ensure all details are accurate and complete\n• Remove any misleading or unclear information\n\n---\n\n🚀 Action required:\nUpdate your project to avoid further restrictions.\n\n[Edit Project]\n\n---\n\n🔒 Note:\nIgnoring flags may impact your visibility or approval status.\n\nNeed clarification? → Contact Support', 'high', '/devhub/projects/edit_project.php', 1),
('project.feature.criteria_reached', 'developer', 'Project "{{project_name}}" reached featured criteria 🌟', 'Dear {{developer_name}}, great progress. Your project "{{project_name}}" has reached the featured eligibility criteria and is now in feature review queue.', 'high', '/devhub/index.php', 1),
('project.feature.approved', 'developer', 'Project "{{project_name}}" featured approved 🎉', 'Dear {{developer_name}}, congratulations. Your project "{{project_name}}" has been approved for featured status. Keep your community active and continue delivering quality updates.', 'high', '/devhub/index.php', 1),
('project.feature.rejected', 'developer', 'Project "{{project_name}}" featured review not approved', 'Dear {{developer_name}}, thank you for your effort. Your project "{{project_name}}" was reviewed for featured status but was not approved this time. Improve rating quality and engagement, then try again. You can freely contact us if you think if the rejection/flagged happen was wrong desicion', 'normal', '/devhub/index.php', 1),
('security.warning', 'user', 'Security warning', 'A security warning was applied to your account. Reason: {{reason}}', 'high', '/profile.php', 1),
('security.suspended', 'user', 'Account suspended', 'Your account has been suspended by security management. Reason: {{reason}}', 'high', '/profile.php', 1),
('marketing.platform_update', 'user', 'Platform update', '{{message}}', 'normal', '/dashboard.php', 1)
ON DUPLICATE KEY UPDATE
    recipient_type = VALUES(recipient_type),
    title_template = VALUES(title_template),
    message_template = VALUES(message_template),
    default_priority = VALUES(default_priority),
    default_action_url = VALUES(default_action_url),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;
