<?php
namespace CoinRex\Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/includes/functions/boosthub_campaigns.php';
require_once dirname(__DIR__, 2) . '/includes/functions/boosthub.php';

final class BoostHubCampaignTest extends TestCase {
    private array $base = [
        'id' => 1, 'status' => 'active', 'start_at' => '2026-08-01 00:00:00',
        'end_at' => '2026-09-30 23:59:59', 'max_participants' => 10,
    ];

    public function testScheduledCampaignCannotStartEarly(): void {
        self::assertSame('scheduled', boostHubCampaignEffectiveState($this->base, strtotime('2026-07-31')));
    }

    public function testPausedCompletedAndExpiredCampaignsAreClosed(): void {
        $paused = $this->base; $paused['status'] = 'paused';
        $done = $this->base; $done['status'] = 'completed';
        self::assertSame('paused', boostHubCampaignEffectiveState($paused, strtotime('2026-08-20')));
        self::assertSame('completed', boostHubCampaignEffectiveState($done, strtotime('2026-08-20')));
        self::assertSame('expired', boostHubCampaignEffectiveState($this->base, strtotime('2026-10-01')));
    }

    public function testAnalyticsKeepsParticipantsDistinctFromSubmissions(): void {
        $raw = ['unique_approved_participants' => 2, 'approved_submissions' => 4, 'rejected_submissions' => 1, 'total_submissions' => 7];
        $result = boostHubCampaignFormatAnalytics($this->base, $raw, []);
        self::assertSame(2, $result['summary']['unique_approved_participants']);
        self::assertSame(8, $result['summary']['remaining_participant_slots']);
        self::assertSame(20.0, $result['summary']['capacity_utilization_percent']);
        self::assertSame(80.0, $result['summary']['approval_rate']);
    }

    public function testParticipantCapAllowsExistingButBlocksNewUsers(): void {
        self::assertTrue(boostHubCampaignCapacityAllows(10, 10, true));
        self::assertFalse(boostHubCampaignCapacityAllows(10, 10, false));
        self::assertTrue(boostHubCampaignCapacityAllows(9, 10, false));
    }

    public function testImplementationUsesLocksAndExistingRewardPath(): void {
        $root = dirname(__DIR__, 2);
        $campaigns = file_get_contents($root . '/includes/functions/boosthub_campaigns.php');
        $api = file_get_contents($root . '/api/admin/boosthub.php');
        $core = file_get_contents($root . '/includes/functions/core.php');
        self::assertStringContainsString('FOR UPDATE', $campaigns);
        self::assertStringContainsString('COUNT(DISTINCT', $campaigns);
        self::assertStringContainsString('reviewTaskHubSubmission($log', $campaigns);
        self::assertStringContainsString('reviewTaskHubSubmissionSafely', $api);
        self::assertStringContainsString('moderate_tasks', $api);
        self::assertStringContainsString('if (!empty($task[\'campaign_id\']))', $core);
    }

    public function testPublicCampaignTaskSelectionKeepsAuthenticationAndCooldownGuardrails(): void {
        $root = dirname(__DIR__, 2);
        $campaigns = file_get_contents($root . '/includes/functions/boosthub_campaigns.php');
        $endpoint = file_get_contents($root . '/api/start_boosthub_campaign_task.php');
        $page = file_get_contents($root . '/public/boosthub.php');

        self::assertStringContainsString('apiResolveAuthorizedUserId', $endpoint);
        self::assertStringContainsString('boostHubStartCampaignTask', $endpoint);
        self::assertStringContainsString('No task can be switched right now.', $campaigns);
        self::assertStringContainsString('boostHubGetAssignableTasks', $campaigns);
        self::assertStringContainsString('start_boosthub_campaign_task.php', $page);
        self::assertStringContainsString('data-campaign-task-start', $page);
    }

    public function testReturnedEvidenceKeepsCooldownAndCampaignVisualProgressContracts(): void {
        $root = dirname(__DIR__, 2);
        $boosthub = file_get_contents($root . '/includes/functions/boosthub.php');
        $core = file_get_contents($root . '/includes/functions/core.php');
        $campaigns = file_get_contents($root . '/includes/functions/boosthub_campaigns.php');
        $migration = file_get_contents($root . '/database/migrations/2026_08_31_boosthub_partner_campaigns.sql');
        $page = file_get_contents($root . '/public/boosthub.php');

        self::assertStringContainsString('function boostHubGetCooldownState', $boosthub);
        self::assertStringContainsString('correction_requested', $boosthub);
        self::assertStringContainsString('boostHubGetCooldownState((int) $user_id', $core);
        self::assertStringContainsString('project_cover', $migration);
        self::assertStringContainsString('progress_percent', $campaigns);
        self::assertStringContainsString('bh-campaign-progress-track', $page);
    }

    public function testReturnedCorrectionUsesOriginalSubmissionAsCooldownAnchor(): void {
        $now = strtotime('2026-09-01 12:00:00');
        $activities = [[
            'status' => 'failed',
            'completed_at' => null,
            'task_completed_at' => null,
            'metadata' => json_encode([
                'submitted_at' => '2026-09-01 06:00:00',
                'correction_requested' => true,
                'review_outcome' => 'returned_for_correction',
            ]),
        ]];

        $cooldown = boostHubCooldownFromActivities($activities, $now);
        self::assertSame('2026-09-02 06:00:00', $cooldown['unlock_at']);
        self::assertSame(64800, $cooldown['countdown_seconds']);
        self::assertSame('2026-09-01 06:00:00', $cooldown['anchor_at']);
    }

    public function testOrdinaryFailedRowsDoNotCreateBoostHubCooldown(): void {
        $cooldown = boostHubCooldownFromActivities([[
            'status' => 'failed',
            'completed_at' => '2026-09-01 11:00:00',
            'task_completed_at' => '2026-09-01 11:00:00',
            'metadata' => json_encode(['review_outcome' => 'rejected']),
        ]], strtotime('2026-09-01 12:00:00'));

        self::assertSame(0, $cooldown['countdown_seconds']);
        self::assertNull($cooldown['unlock_at']);
    }
}
