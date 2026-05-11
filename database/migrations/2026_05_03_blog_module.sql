USE koinrex;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255) NULL,
    author_admin_id INT UNSIGNED NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    seo_title VARCHAR(180) NULL,
    seo_description VARCHAR(255) NULL,
    cta_text VARCHAR(120) NULL,
    cta_url VARCHAR(255) NULL,
    cta_type VARCHAR(50) NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_posts_slug (slug),
    KEY idx_blog_posts_status_published (status, published_at),
    KEY idx_blog_posts_author (author_admin_id),
    CONSTRAINT fk_blog_posts_author_admin
        FOREIGN KEY (author_admin_id) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_categories_name (name),
    UNIQUE KEY uq_blog_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_tags_name (name),
    UNIQUE KEY uq_blog_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, category_id),
    CONSTRAINT fk_blog_post_categories_post FOREIGN KEY (post_id)
        REFERENCES blog_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_blog_post_categories_category FOREIGN KEY (category_id)
        REFERENCES blog_categories(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_blog_post_tags_post FOREIGN KEY (post_id)
        REFERENCES blog_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_blog_post_tags_tag FOREIGN KEY (tag_id)
        REFERENCES blog_tags(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description)
SELECT 'manage_blog', 'Manage blog posts, categories and tags'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'manage_blog');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name = 'manage_blog'
WHERE r.name IN ('super_admin', 'admin');

INSERT INTO blog_categories (name, slug, description)
SELECT 'Platform Guides', 'platform-guides', 'How-to guides for CoinRex products'
WHERE NOT EXISTS (SELECT 1 FROM blog_categories WHERE slug = 'platform-guides');

INSERT INTO blog_categories (name, slug, description)
SELECT 'Product Updates', 'product-updates', 'New features and release notes'
WHERE NOT EXISTS (SELECT 1 FROM blog_categories WHERE slug = 'product-updates');