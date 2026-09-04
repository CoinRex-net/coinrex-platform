CREATE TABLE IF NOT EXISTS boosthub_campaigns (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 campaign_name VARCHAR(150) NOT NULL,
 project_name VARCHAR(150) NOT NULL,
 project_logo VARCHAR(500) NULL,
 project_cover VARCHAR(500) NULL,
 project_website VARCHAR(500) NULL,
 short_description TEXT NULL,
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 max_participants INT UNSIGNED NOT NULL,
 status ENUM('draft','scheduled','active','paused','completed') NOT NULL DEFAULT 'draft',
 internal_notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_campaign_availability(status,start_at,end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mini_tasks ADD COLUMN IF NOT EXISTS campaign_id INT UNSIGNED NULL AFTER task_group;
ALTER TABLE boosthub_campaigns ADD COLUMN IF NOT EXISTS project_cover VARCHAR(500) NULL AFTER project_logo;
ALTER TABLE mini_tasks ADD KEY IF NOT EXISTS idx_mini_tasks_campaign(campaign_id);
ALTER TABLE mini_tasks ADD CONSTRAINT fk_mini_tasks_campaign FOREIGN KEY(campaign_id)
 REFERENCES boosthub_campaigns(id) ON DELETE SET NULL ON UPDATE CASCADE;
