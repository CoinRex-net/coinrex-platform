<?php

namespace CoinRex\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions/review_eligibility_monitoring.php';
require_once dirname(__DIR__, 2) . '/includes/functions/review_eligibility.php';

final class ReviewEligibilityMonitoringTest extends TestCase
{
    public function testRawIntegerArithmeticDoesNotLoseTokenPrecision(): void
    {
        self::assertGreaterThan(0, reviewEligibilityMonitoringCompare('10000000000000000000', '9999999999999999999'));
        self::assertSame('10000000000000000000', reviewEligibilityMonitoringAdd('9999999999999999999', '1'));
        self::assertSame('9999999999999999999', reviewEligibilityMonitoringSubtract('10000000000000000000', '1'));
        self::assertSame('630000000000000', reviewEligibilityMonitoringMultiply('21000', '30000000000'));
    }

    public function testDecimalConversionUsesTokenUnitsWithoutUsdOrAverage(): void
    {
        self::assertSame('10500000000000000000', reviewEligibilityMonitoringDecimalToRaw('10.5', 18));
        self::assertSame('10.5', reviewEligibilityMonitoringRawToDecimal('10500000000000000000', 18));
        self::assertSame('0.000001', reviewEligibilityMonitoringRawToDecimal('1', 6));
    }

    public function testSubtractFloorsAtZeroForAThresholdBreach(): void
    {
        self::assertSame('0', reviewEligibilityMonitoringSubtract('10', '11'));
    }

    public function testConfigurableDurationLabels(): void
    {
        self::assertSame('24 hours', reviewEligibilityMonitoringFormatDuration(1440));
        self::assertSame('3 days', reviewEligibilityMonitoringFormatDuration(4320));
        self::assertSame('90 minutes', reviewEligibilityMonitoringFormatDuration(90));
    }

    public function testPayloadExposesCountdownAndNoAverageBalance(): void
    {
        $payload = reviewEligibilityMonitoringPayload([
            'id' => 7,
            'status' => 'active',
            'reason_code' => 'monitoring_active',
            'reason' => 'Keep holding.',
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'token_symbol' => 'POL',
            'token_decimals' => 18,
            'required_amount' => '10',
            'last_balance_raw' => '12000000000000000000',
            'eligible_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        self::assertSame('12', $payload['current_balance']);
        self::assertArrayHasKey('remaining_seconds', $payload);
        self::assertArrayNotHasKey('average_balance', $payload);
        self::assertArrayNotHasKey('balance_usd', $payload);
    }

    public function testProjectContractRuleNormalizesTokenAmountAndDuration(): void
    {
        $errors = [];
        $rows = reviewEligibilityNormalizeContractRows([
            'contract_network_name' => ['Polygon'],
            'contract_chain_id' => ['137'],
            'contract_address_multi' => [''],
            'contract_token_type' => ['NATIVE'],
            'contract_token_symbol' => ['POL'],
            'contract_decimals' => ['18'],
            'contract_min_amount' => ['10'],
            'contract_holding_value' => ['72'],
            'contract_holding_unit' => ['hours'],
            'contract_is_active' => ['1'],
            'primary_contract_index' => 0,
        ], $errors);

        self::assertSame([], $errors);
        self::assertCount(1, $rows);
        self::assertSame('10', $rows[0]['eligibility_min_amount']);
        self::assertSame(4320, $rows[0]['eligibility_holding_minutes']);
        self::assertSame('POL', $rows[0]['token_symbol']);
    }
}
