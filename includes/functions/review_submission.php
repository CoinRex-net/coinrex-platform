<?php

/**
 * Resolve the review wizard's next state from already-loaded persistence data.
 * This helper is intentionally pure so API behavior can be regression tested.
 */
/**
 * Ratings submitted by the review wizard are whole numbers from 1 through 5.
 */
function coinrexReviewRatingIsValid($value): bool
{
    return preg_match('/^[1-5]$/', trim((string) $value)) === 1;
}
function coinrexReviewPreflightDecision(
    bool $has_wallet,
    ?array $existing_review,
    ?array $fresh_check,
    ?array $monitoring_payload,
    ?array $active_session
): array {
    if ($existing_review) {
        return ['state' => 'duplicate_review', 'next_action' => 'view_review', 'reason' => 'You already submitted a review for this project.'];
    }
    if ($fresh_check && (string) ($fresh_check['status'] ?? '') === 'eligible') {
        return ['state' => 'eligible', 'next_action' => 'write_review', 'reason' => (string) ($fresh_check['reason'] ?? 'Your wallet is eligible to review this project.')];
    }
    if ($monitoring_payload && in_array((string) ($monitoring_payload['status'] ?? ''), ['active', 'provider_delayed'], true)) {
        return ['state' => 'monitoring', 'next_action' => 'wait_for_monitoring', 'reason' => (string) ($monitoring_payload['reason'] ?? 'Holding verification is in progress.')];
    }
    if ($monitoring_payload && (string) ($monitoring_payload['status'] ?? '') === 'eligible') {
        return ['state' => 'eligible', 'next_action' => 'write_review', 'reason' => (string) ($monitoring_payload['reason'] ?? 'Your wallet is eligible to review this project.')];
    }
    if ($active_session) {
        return ['state' => 'session_ready', 'next_action' => 'run_instant_check', 'reason' => 'Active RexLink session found. Verifying eligibility now.'];
    }
    return [
        'state' => 'connection_required',
        'next_action' => 'connect_wallet',
        'reason' => $has_wallet
            ? 'Connect this wallet with RexLink or a browser wallet to verify ownership.'
            : 'Link a wallet to your CoinRex account before checking eligibility.',
    ];
}