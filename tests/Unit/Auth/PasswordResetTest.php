<?php

namespace CoinRex\Tests\Unit\Auth;

use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once dirname(__DIR__, 3) . '/includes/config.php';

class PasswordResetTest extends TestCase
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
        $_SERVER['HTTP_USER_AGENT'] = 'CoinRex PHPUnit PasswordResetTest';
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

    public function testPasswordResetResendCooldownReportsRemainingSeconds(): void
    {
        $_SESSION['pending_password_reset_last_sent_at'] = time() - 30;

        $remaining = getPasswordResetResendRemainingSeconds();

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS, $remaining);
    }

    public function testWeakResetPasswordFailsPolicy(): void
    {
        $validation = validatePasswordPolicy('password');

        $this->assertFalse($validation['is_valid']);
        $this->assertFalse($validation['requirements']['length']);
        $this->assertFalse($validation['requirements']['uppercase']);
        $this->assertFalse($validation['requirements']['digit']);
        $this->assertFalse($validation['requirements']['special']);
    }

    public function testCorrectPasswordResetOtpSetsVerifiedSession(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));

        $result = validatePasswordResetOtpSubmission($user, '123456');

        $this->assertTrue($result['success'], $result['message'] ?? 'OTP validation failed');
        $this->assertTrue(isPasswordResetOtpVerified());
        $this->assertNotEmpty($_SESSION['pending_password_reset_verified_at']);
    }

    public function testWrongPasswordResetOtpIsRejectedAndIncrementsAttempts(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));

        $result = validatePasswordResetOtpSubmission($user, '654321');
        $fresh = $this->findUserById((int) $user['id']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid OTP', $result['message']);
        $this->assertSame('1', (string) $fresh['otp_attempts']);
    }

    public function testExpiredPasswordResetOtpIsRejected(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));
        $this->db->prepare('UPDATE users SET otp_expiry = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([(int) $user['id']]);

        $result = validatePasswordResetOtpSubmission($user, '123456');

        $this->assertFalse($result['success']);
        $this->assertSame('This OTP has expired. Please request a new code.', $result['message']);
    }

    public function testResetUserPasswordChangesHashAndClearsOtpState(): void
    {
        $this->requireDatabase();
        $user = $this->createUnverifiedUser();
        $oldHash = (string) $user['password'];
        $this->assertTrue(storeEmailVerificationOtp((int) $user['id'], '123456'));
        $this->db->prepare('UPDATE users SET otp_attempts = 2, login_attempts = 3 WHERE id = ?')->execute([(int) $user['id']]);

        $this->assertTrue(resetUserPassword((int) $user['id'], 'NewStrong9!'));
        $fresh = $this->findUserById((int) $user['id']);

        $this->assertNotSame($oldHash, $fresh['password']);
        $this->assertTrue(password_verify('NewStrong9!', $fresh['password']));
        $this->assertNull($fresh['otp_code']);
        $this->assertNull($fresh['otp_expiry']);
        $this->assertSame('0', (string) $fresh['otp_attempts']);
        $this->assertSame('0', (string) $fresh['login_attempts']);
    }

    public function testClearPendingPasswordResetRemovesSessionState(): void
    {
        $_SESSION['pending_password_reset_user_id'] = 123;
        $_SESSION['pending_password_reset_email'] = 'user@example.com';
        $_SESSION['pending_password_reset_last_sent_at'] = time();
        $_SESSION['pending_password_reset_mail_status'] = ['success' => true];
        $_SESSION['pending_password_reset_verified_at'] = time();

        clearPendingPasswordReset();

        $this->assertArrayNotHasKey('pending_password_reset_user_id', $_SESSION);
        $this->assertArrayNotHasKey('pending_password_reset_email', $_SESSION);
        $this->assertArrayNotHasKey('pending_password_reset_last_sent_at', $_SESSION);
        $this->assertArrayNotHasKey('pending_password_reset_mail_status', $_SESSION);
        $this->assertArrayNotHasKey('pending_password_reset_verified_at', $_SESSION);
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
                $this->markTestSkipped('Password reset database tests require the configured test database: ' . DB_NAME);
            }

            $this->db = getDBConnection();
            $this->db->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Password reset database tests require an available test DB with the CoinRex schema: ' . $e->getMessage());
        }
    }

    private function createUnverifiedUser(): array
    {
        $email = 'codex.reset.' . bin2hex(random_bytes(5)) . '@example.com';
        $result = registerUser('Reset Reviewer', $email, 'StrongPass9!');
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
