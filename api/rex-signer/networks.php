<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db, ['publish_session_expired_events' => false]);

    $stmt = $db->query("
        SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment,
               chain_family, claim_enabled, token_support_enabled, is_enabled
        FROM rex_signer_networks
        WHERE is_enabled = 1
        ORDER BY sort_order ASC, id ASC
    ");

    apiSuccessResponse([
        'networks' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
