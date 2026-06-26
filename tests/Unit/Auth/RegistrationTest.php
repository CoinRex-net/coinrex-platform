<?php

namespace CoinRex\Tests\Unit\Auth;

use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once dirname(__DIR__, 3) . '/includes/config.php';

class RegistrationTest extends TestCase
{
    private ?PDO $db = null;
    private array $createdUserIds = [];
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'CoinRex PHPUnit RegistrationTest';
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
                $this->markTestSkipped('Registration database tests require the configured test database: ' . DB_NAME);
            }

            $this->db = getDBConnection();
            $this->db->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Registration database tests require an available test DB with the CoinRex schema: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            foreach (array_reverse($this->createdUserIds) as $userId) {
                $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
            }
        }

        $_SERVER = $this->serverBackup;
        $_POST = [];

        parent::tearDown();
    }

    public function testSuccessfulRegistrationCreatesExpectedUserDefaults(): void
    {
        $this->requireDatabase();

        $email = $this->uniqueEmail('Success');

        $result = registerUser('MVP Reviewer', $email, 'StrongPass9!');

        $this->assertTrue($result['success'], $result['message'] ?? 'Registration failed');
        $this->createdUserIds[] = (int) $result['user_id'];

        $user = $this->findUserById((int) $result['user_id']);

        $this->assertSame(strtolower($email), $user['email']);
        $this->assertNotSame('StrongPass9!', $user['password']);
        $this->assertTrue(password_verify('StrongPass9!', $user['password']));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $user['referral_code']);
        $this->assertSame('beginner', $user['level']);
        $this->assertSame('0', (string) $user['email_verified']);
        $this->assertSame('active', $user['status']);
    }

    public function testRegistrationValidationBlocksEmptyFields(): void
    {
        $result = validateReviewerRegistrationSubmission('', '', '', '', null, true);

        $this->assertFalse($result['valid']);
        $this->assertSame('Please fill in all required fields', $result['message']);
    }

    public function testRegistrationValidationBlocksInvalidEmail(): void
    {
        $result = validateReviewerRegistrationSubmission('MVP Reviewer', 'not-an-email', 'StrongPass9!', 'StrongPass9!', null, true);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid email address', $result['message']);
    }

    public function testRegistrationValidationBlocksTemporaryEmail(): void
    {
        $result = validateReviewerRegistrationSubmission('MVP Reviewer', 'test@mailinator.com', 'StrongPass9!', 'StrongPass9!', null, true);

        $this->assertFalse($result['valid']);
        $this->assertSame('Temporary email addresses are not allowed', $result['message']);
    }

    public function testRegistrationValidationBlocksDuplicateNormalizedEmail(): void
    {
        $this->requireDatabase();

        $email = $this->uniqueEmail('Duplicate');
        $created = registerUser('Existing Reviewer', $email, 'StrongPass9!');
        $this->assertTrue($created['success'], $created['message'] ?? 'Registration failed');
        $this->createdUserIds[] = (int) $created['user_id'];

        $result = validateReviewerRegistrationSubmission('MVP Reviewer', strtoupper($email), 'StrongPass9!', 'StrongPass9!', null, true);

        $this->assertFalse($result['valid']);
        $this->assertSame('Email already registered', $result['message']);
    }

    public function testRegistrationValidationBlocksWeakPassword(): void
    {
        $this->requireDatabase();

        $result = validateReviewerRegistrationSubmission('MVP Reviewer', $this->uniqueEmail('Weak'), 'password', 'password', null, true);

        $this->assertFalse($result['valid']);
        $this->assertSame('Password must be at least 9 characters and include an uppercase letter, a number, and a special character', $result['message']);
    }

    public function testRegisterUserBlocksWeakPasswordWithoutCreatingUser(): void
    {
        $this->requireDatabase();

        $email = $this->uniqueEmail('WeakPersist');

        $result = registerUser('MVP Reviewer', $email, 'password');

        $this->assertFalse($result['success']);
        $this->assertSame('Password does not meet security requirements', $result['message']);
        $this->assertNull($this->findUserByEmail($email));
    }

    public function testRegisterUserBlocksInvalidEmailWithoutCreatingUser(): void
    {
        $this->requireDatabase();

        $result = registerUser('MVP Reviewer', 'not-an-email', 'StrongPass9!');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email address', $result['message']);
        $this->assertNull($this->findUserByEmail('not-an-email'));
    }

    public function testUniqueReferralCodeHelperAvoidsExistingCode(): void
    {
        $this->requireDatabase();

        $existing = registerUser('Existing Referral', $this->uniqueEmail('Referral'), 'StrongPass9!');
        $this->assertTrue($existing['success'], $existing['message'] ?? 'Registration failed');
        $this->createdUserIds[] = (int) $existing['user_id'];

        $user = $this->findUserById((int) $existing['user_id']);
        $generated = generateUniqueReferralCode($this->db);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $generated);
        $this->assertNotSame($user['referral_code'], $generated);
    }

    private function uniqueEmail(string $label): string
    {
        return 'codex.' . strtolower($label) . '.' . bin2hex(random_bytes(5)) . '@example.com';
    }

    private function findUserById(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);

        $user = $stmt->fetch();
        $this->assertIsArray($user);

        return $user;
    }

    private function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([normalizeEmail($email)]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }
}
