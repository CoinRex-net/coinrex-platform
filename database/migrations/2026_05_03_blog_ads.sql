USE koinrex;

CREATE TABLE IF NOT EXISTS blog_ads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    placement ENUM('blog_leaderboard','blog_infeed','blog_sidebar') NOT NULL,
    ad_type ENUM('image','gif','text') NOT NULL DEFAULT 'text',
    title VARCHAR(180) NULL,
    description VARCHAR(255) NULL,
    media_url VARCHAR(255) NULL,
    target_url VARCHAR(255) NULL,
    cta_text VARCHAR(80) NULL,
    after_post TINYINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    priority INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_blog_ads_placement_active (placement, is_active, priority),
    KEY idx_blog_ads_schedule (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
