-- Review comments and compact engagement actions.

CREATE TABLE IF NOT EXISTS review_comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'visible',
    like_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_comment_user (review_id, user_id),
    KEY idx_review_comments_review_status (review_id, status),
    KEY idx_review_comments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_comment_likes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_comment_like_user (comment_id, user_id),
    KEY idx_review_comment_like_comment (comment_id),
    KEY idx_review_comment_like_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
