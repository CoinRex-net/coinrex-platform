<?php

namespace CoinRex\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions/review_submission.php';

final class ReviewSubmissionPreflightTest extends TestCase
{
    public function testRatingsRequireStrictWholeNumbers(): void
    {
        foreach (['1', '2', '3', '4', '5', 1, 5] as $valid) {
            self::assertTrue(coinrexReviewRatingIsValid($valid));
        }

        foreach (['', '0', '6', '1.5', '1abc', null] as $invalid) {
            self::assertFalse(coinrexReviewRatingIsValid($invalid));
        }
    }
    public function testFreshEligibilityBypassesAnExpiredOrMissingSession(): void
    {
        $result = coinrexReviewPreflightDecision(true, null, ['status' => 'eligible', 'reason' => 'Fresh check'], null, null);
        self::assertSame('eligible', $result['state']);
        self::assertSame('write_review', $result['next_action']);
    }

    public function testActiveMonitoringIsRestoredBeforePairing(): void
    {
        $result = coinrexReviewPreflightDecision(true, null, null, ['status' => 'active', 'reason' => 'Keep holding'], null);
        self::assertSame('monitoring', $result['state']);
        self::assertSame('wait_for_monitoring', $result['next_action']);
    }

    public function testActiveSessionRunsInstantCheck(): void
    {
        $result = coinrexReviewPreflightDecision(true, null, null, null, ['id' => 10]);
        self::assertSame('session_ready', $result['state']);
        self::assertSame('run_instant_check', $result['next_action']);
    }

    public function testMissingSessionNeverRequestsAutomaticQrCreation(): void
    {
        $result = coinrexReviewPreflightDecision(true, null, null, null, null);
        self::assertSame('connection_required', $result['state']);
        self::assertSame('connect_wallet', $result['next_action']);
    }

    public function testExistingReviewAlwaysWins(): void
    {
        $result = coinrexReviewPreflightDecision(true, ['id' => 4], ['status' => 'eligible'], ['status' => 'active'], ['id' => 10]);
        self::assertSame('duplicate_review', $result['state']);
    }
}