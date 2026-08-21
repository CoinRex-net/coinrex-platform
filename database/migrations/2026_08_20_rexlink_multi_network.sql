-- Registry-driven, dApp-scoped RexLink pairing and chain-bound approvals.

SET NAMES utf8mb4;

ALTER TABLE rex_signer_pairing_codes
    ADD COLUMN IF NOT EXISTS network_scope VARCHAR(20) NOT NULL DEFAULT 'multi' AFTER app_id,
    ADD COLUMN IF NOT EXISTS requested_networks_json JSON NULL AFTER network_scope;

ALTER TABLE rex_signer_approval_requests
    ADD COLUMN IF NOT EXISTS chain_id INT UNSIGNED NULL AFTER network_slug,
    ADD INDEX IF NOT EXISTS idx_rex_signer_approvals_chain (network_slug, chain_id, status);

UPDATE rex_signer_approval_requests approvals
INNER JOIN rex_signer_networks networks
    ON networks.slug = approvals.network_slug
SET approvals.chain_id = networks.chain_id
WHERE approvals.chain_id IS NULL;
