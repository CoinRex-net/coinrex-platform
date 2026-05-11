USE koinrex;

-- SECURITY NOTE:
-- This repository intentionally does NOT ship with a usable default admin account.
-- Before running this seed, generate your own bcrypt password hash and replace the
-- placeholder values below.
--
-- Example hash generation command:
-- php -r "echo password_hash('ChangeThisAdminPassword!', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
--
-- Then replace:
--   CHANGE_ME_ADMIN_EMAIL
--   CHANGE_ME_ADMIN_USERNAME
--   CHANGE_ME_ADMIN_NAME
--   CHANGE_ME_BCRYPT_HASH

INSERT INTO admins (id, email, username, name, password_hash, status, last_login_at, created_at, updated_at)
VALUES (
    1,
    'CHANGE_ME_ADMIN_EMAIL',
    'CHANGE_ME_ADMIN_USERNAME',
    'CHANGE_ME_ADMIN_NAME',
    'CHANGE_ME_BCRYPT_HASH',
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
