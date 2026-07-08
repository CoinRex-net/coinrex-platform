<?php
/**
 * Complete one mini task for the authenticated user.
 * POST: task_id, proof (text), screenshot_url (optional)
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $task_id = (int) ($_POST['task_id'] ?? 0);

    if ($task_id <= 0) {
        throw new InvalidArgumentException('Valid task_id is required.');
    }

    $proof_text = trim((string) ($_POST['proof'] ?? ''));
    $screenshot_url = trim((string) ($_POST['screenshot_url'] ?? ''));

    // Build evidence payload - store both text and screenshot as JSON
    $evidence = [];
    if ($proof_text !== '') {
        $evidence['text'] = $proof_text;
    }
    if ($screenshot_url !== '') {
        $evidence['screenshot'] = $screenshot_url;
    }

    if (empty($evidence)) {
        throw new InvalidArgumentException('Please provide evidence (text link/username and/or screenshot).');
    }

    $payload = [
        'proof' => json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];

    // Also pass individual fields for backward compatibility
    foreach (['wallet_address', 'x_handle', 'telegram_handle', 'platform'] as $field) {
        if (isset($_POST[$field])) {
            $payload[$field] = trim((string) $_POST[$field]);
        }
    }
    if (!empty($_POST['answers_json'])) {
        $answers = json_decode((string) $_POST['answers_json'], true);
        if (is_array($answers)) {
            $payload['answers'] = $answers;
        }
    }

    $result = completeMiniTask($user_id, $task_id, $payload);

    apiSuccessResponse([
        'message' => !empty($result['submitted']) ? 'Evidence submitted. Waiting for admin approval.' : 'Mini task completed and reward added.',
        'entry' => $result['entry'] ?? null,
        'submitted' => !empty($result['submitted']),
        'balance' => number_format(getRewardLedgerBalance($user_id, 'available'), 8, '.', ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
