<?php
/**
 * Return claim snapshot status.
 * GET: snapshot_id
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $snapshot_id = (int) ($_GET['snapshot_id'] ?? 0);
    if ($snapshot_id <= 0) {
        throw new InvalidArgumentException('Valid snapshot_id is required.');
    }

    $actor = apiGetAuthenticatedUser();
    $user_scope = $actor['type'] === 'admin' ? null : (int) $actor['user_id'];
    $snapshot = getClaimSnapshotStatus($snapshot_id, $user_scope);

    apiSuccessResponse([
        'snapshot' => $snapshot,
    ]);
} catch (Throwable $e) {
    $status_code = stripos($e->getMessage(), 'not found') !== false ? 404 : 422;
    apiErrorResponse($status_code, $e->getMessage());
}

