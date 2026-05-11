USE koinrex;

INSERT INTO admins (id, email, username, name, password_hash, status, last_login_at, created_at, updated_at)
VALUES (
    1,
    'admin@coinrex.local',
    'admin',
    'CoinRex Admin',
    '$2y$12$rOhqL4N4AVgEZ8D2wHM.s.TJC420udsRSaFjJZuqg/lMdaevy7OQ.',
    'active',
    NULL,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    username = VALUES(username),
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    status = VALUES(status),
    updated_at = NOW();

-- Default Admin Login
-- Email: admin@coinrex.local
-- Password: Admin@12345
