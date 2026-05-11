USE koinrex;

-- 1) Core RBAC schema
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    hierarchy_level INT UNSIGNED NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Admin table upgrade (backward-compatible)
ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS role_id INT UNSIGNED NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_attempts;

ALTER TABLE admins
    ADD CONSTRAINT fk_admins_role_id
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- 3) Audit log table for admin security events
CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_logs_admin (admin_id),
    KEY idx_admin_logs_action (action),
    KEY idx_admin_logs_created_at (created_at),
    CONSTRAINT fk_admin_logs_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Default roles
INSERT INTO roles (name, description, hierarchy_level)
SELECT 'super_admin', 'Full system access', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'super_admin');

INSERT INTO roles (name, description, hierarchy_level)
SELECT 'admin', 'Operational admin with limited control', 10
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'admin');

INSERT INTO roles (name, description, hierarchy_level)
SELECT 'moderator', 'Moderation-only role', 50
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'moderator');

-- 5) Default permissions
INSERT INTO permissions (name, description)
SELECT 'manage_admins', 'Create/edit admins and assign roles' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_admins');
INSERT INTO permissions (name, description)
SELECT 'manage_users', 'Manage users and user status' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_users');
INSERT INTO permissions (name, description)
SELECT 'view_users', 'View users list and details' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='view_users');
INSERT INTO permissions (name, description)
SELECT 'manage_projects', 'Manage project moderation' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_projects');
INSERT INTO permissions (name, description)
SELECT 'manage_tasks', 'Manage task systems' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_tasks');
INSERT INTO permissions (name, description)
SELECT 'moderate_tasks', 'Review/approve task submissions' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='moderate_tasks');
INSERT INTO permissions (name, description)
SELECT 'manage_rewards', 'Manage rewards and ledger controls' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_rewards');
INSERT INTO permissions (name, description)
SELECT 'view_reports', 'View dashboard and operational reports' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='view_reports');
INSERT INTO permissions (name, description)
SELECT 'manage_reviews', 'Manage review moderation and trust actions' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_reviews');
INSERT INTO permissions (name, description)
SELECT 'manage_developers', 'Manage developer verification queue' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name='manage_developers');

-- 6) Role-permission mapping
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('manage_users','manage_tasks','manage_rewards','view_reports','manage_projects','manage_reviews','manage_developers')
WHERE r.name = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('view_users','moderate_tasks','view_reports')
WHERE r.name = 'moderator';

-- 7) Backfill existing admins into super_admin role
UPDATE admins a
JOIN roles r ON r.name = 'super_admin'
SET a.role_id = r.id
WHERE a.role_id IS NULL;
