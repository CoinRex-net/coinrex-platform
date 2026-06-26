-- RexSigner passwordless website authentication.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS rex_signer_auth_challenges;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER password,
    MODIFY email VARCHAR(255) NULL,
    MODIFY password VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS wallet_verified_at DATETIME NULL AFTER wallet_address;

UPDATE users SET auth_provider = 'email' WHERE auth_provider IS NULL OR auth_provider = '';
UPDATE users SET wallet_address = NULL WHERE wallet_address IS NOT NULL AND TRIM(wallet_address) = '';
UPDATE users SET wallet_address = LOWER(wallet_address) WHERE wallet_address IS NOT NULL;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_wallet_address (wallet_address);

ALTER TABLE rex_signer_pairing_codes
    MODIFY user_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS pairing_purpose VARCHAR(30) NOT NULL DEFAULT 'claim' AFTER display_code,
    ADD COLUMN IF NOT EXISTS referral_code VARCHAR(32) NULL AFTER pairing_purpose,
    ADD COLUMN IF NOT EXISTS device_fingerprint VARCHAR(255) NULL AFTER completed_session_id;
