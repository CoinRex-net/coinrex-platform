<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db);

    $code = rexSignerNormalizePairCode(rexSignerInput('code', ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        apiErrorResponse(422, 'Valid pairing code is required.');
    }

    $reason = trim((string) rexSignerInput('reason', 'RexLink authentication was cancelled.'));
    $reason = $reason !== '' ? substr($reason, 0, 255) : 'RexLink authentication was cancelled.';

    $stmt = $db->prepare("
        UPDATE rex_signer_pairing_codes
        SET status = 'revoked'
        WHERE code_hash = ?
          AND status = 'pending'
          AND expires_at > NOW()
    ");
    $stmt->execute([rexSignerHashSecret($code)]);

    if ($stmt->rowCount() <= 0) {
        apiSuccessResponse([
            'message' => 'Pairing code is no longer active.',
            'status' => 'none',
        ]);
    }

    apiSuccessResponse([
        'message' => $reason,
        'status' => 'revoked',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
