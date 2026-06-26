<?php

namespace CoinRex\Tests\Unit\Auth;

use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once dirname(__DIR__, 3) . '/includes/config.php';

class EmailVerificationTest extends TestCase
{
    private ?PDO $db = null;
    private array $createdUserIds = [];
    private array $sessionBackup = [];
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $this->sessionBackup = $_SESSION ?? [];
        $this->serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'CoinRex PHPUnit EmailVerificationTest';
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            foreach (array_reverse($this->createdUserIds) as $userId) {
                $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
            }
        }

        $_SESSION = $this->sessionBackup;
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    public function testGeneratedOtpUsesConfiguredLength(): void
    {
        $otp = generateEmailVerificationOtp();

        $this->assertMatchesRegularExpression('/^\d{' . EMAIL_VERIFICATION_OTP_LENGTH . '}$/', $otp);
    }

    public function testResendCooldownReportsRemainingSeconds(): void
    {
        $_SESSION['pending_verification_last_sent_at'] = time() - 30;

        $remaining = getEmailVerificationResendRemainingSeconds();

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS, $remaining);
    }

    public function testStoreOtpPopulatesExpiryAndResetsAttempts(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();

        $this->db->prepare('UPDATE users SET otp_attempts = 3 WHERE id = ?')->execute([(int) $user['id']]);

        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));
        $fresh = $this->findUserById((int) $user['id']);

        $this->assertSame('123456', $fresh['otp_code']);
        $this->assertNotEmpty($fresh['otp_expiry']);
        $this->assertSame('0', (string) $fresh['otp_attempts']);
    }

    public function testWrongOtpIsRejectedAndIncrementsAttempts(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));

        $result = validateEmailVerificationOtpSubmission($user, '654321');
        $fresh = $this->findUserById((int) $user['id']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid OTP', $result['message']);
        $this->assertSame('1', (string) $fresh['otp_attempts']);
    }

    public function testExpiredOtpIsRejected(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));
        $this->db->prepare('UPDATE users SET otp_expiry = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([(int) $user['id']]);

        $result = validateEmailVerificationOtpSubmission($user, '123456');

        $this->assertFalse($result['success']);
        $this->assertSame('This OTP has expired. Please request a new code.', $result['message']);
    }

    public function testCorrectOtpValidatesAndMarkEmailAsVerifiedUpdatesDatabase(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));

        $result = validateEmailVerificationOtpSubmission($user, '123456');
        $this->assertTrue($result['success'], $result['message'] ?? 'OTP validation failed');

        $this->assertTrue(markEmailAsVerified((int) $user['id']));
        $fresh = $this->findUserById((int) $user['id']);

        $this->assertSame('1', (string) $fresh['email_verified']);
        $this->assertNotEmpty($fresh['email_verified_at']);
        $this->assertNull($fresh['otp_code']);
        $this->assertNull($fresh['otp_expiry']);
        $this->assertSame('0', (string) $fresh['otp_attempts']);
        $this->assertTrue(userAuthIdentityVerified($fresh));
    }

    public function testUnverifiedEmailUserIsNotAllowedByAuthIdentity(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();

        $this->assertFalse(userAuthIdentityVerified($user));
    }

    private function requireDatabase(): void
    {
        try {
            $probe = new PDO(
                'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $stmt = $probe->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([DB_NAME]);

            if (!$stmt->fetch()) {
                $this->markTestSkipped('Email verification database tests require the configured test database: ' . DB_NAME);
            }

            $this->db = getDBConnection();
            $this->db->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Email verification database tests require an available test DB with the CoinRex schema: ' . $e->getMessage());
        }
    }

    private function createUnverifiedUser(): array
    {
        $email = 'codex.otp.' . bin2hex(random_bytes(5)) . '@example.com';
        $result = registerUser('OTP Reviewer', $email, 'StrongPass9!');
        $this->assertTrue($result['success'], $result['message'] ?? 'Registration failed');
        $this->createdUserIds[] = (int) $result['user_id'];

        return $this->findUserById((int) $result['user_id']);
    }

    private function findUserById(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $this->assertIsArray($user);

        return $user;
    }
}
