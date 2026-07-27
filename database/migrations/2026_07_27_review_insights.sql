CREATE TABLE IF NOT EXISTS review_insights (
    review_id INT UNSIGNED NOT NULL,
    impression_count INT UNSIGNED NOT NULL DEFAULT 0,
    read_full_click_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_impression_at DATETIME NULL,
    last_read_full_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    KEY idx_review_insights_impressions (impression_count),
    KEY idx_review_insights_reads (read_full_click_count),
    CONSTRAINT fk_review_insights_review
        FOREIGN KEY (review_id) REFERENCES reviews(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
