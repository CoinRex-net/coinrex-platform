<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$error_message = '';
$success_message = consumeFlashMessage('profile_success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updated_user = updateUserProfileBasics((int) $user['id'], [
            'full_name' => $_POST['full_name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'country' => $_POST['country'] ?? '',
        ], $_FILES, $db);

        setFlashMessage('profile_success', 'Profile saved successfully.');
        redirect(BASE_URL . '/profile.php');
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
        $user = array_merge($user, [
            'full_name' => trim((string) ($_POST['full_name'] ?? ($user['full_name'] ?? ''))),
            'username' => trim((string) ($_POST['username'] ?? ($user['username'] ?? ''))),
            'country' => trim((string) ($_POST['country'] ?? ($user['country'] ?? ''))),
        ]);
    }
}

$profile_name = trim((string) ($user['full_name'] ?: $user['username'] ?: 'User'));
$avatar_initial = strtoupper(substr((string) ($user['username'] ?: $profile_name), 0, 1));
$user_avatar_url = coinrexNormalizeMediaUrl((string) ($user['avatar'] ?? ''));

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/profile.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/profile.css'); ?>">

<main class="profile-page">
    <div class="profile-shell">
        <section class="profile-hero">
            <div>
                <span class="profile-kicker">Profile</span>
                <h1>Set up your CoinRex profile</h1>
            </div>
            <div class="profile-identity-card">
                <div class="profile-avatar-large<?php echo $user_avatar_url !== '' ? ' has-avatar-image' : ''; ?>"<?php if ($user_avatar_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($user_avatar_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                    <?php if ($user_avatar_url === ''): ?>
                        <span class="profile-avatar-initial"><?php echo htmlspecialchars($avatar_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <strong><?php echo htmlspecialchars($profile_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>@<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </section>

        <?php if ($success_message !== ''): ?>
            <section class="profile-alert success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></section>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <section class="profile-alert error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></section>
        <?php endif; ?>

        <section class="profile-card">
            <form method="post" enctype="multipart/form-data" class="profile-form">
                <div class="profile-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="profile-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="profile-field">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="<?php echo htmlspecialchars((string) ($user['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="profile-field">
                    <label for="avatar">Profile Photo</label>
                    <input type="file" id="avatar" name="avatar" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                </div>

                <div class="profile-actions">
                    <button type="submit" class="profile-primary-btn">Save Profile</button>
                    <a href="<?php echo BASE_URL; ?>/taskhub.php" class="profile-secondary-btn">Back to LearnHub</a>
                </div>
            </form>
        </section>

        <!-- Upload animation & preview -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const avatarDiv = document.querySelector('.profile-avatar-large');
                const fileInput = document.getElementById('avatar');

                if (!fileInput || !avatarDiv) return;

                const avatarInitial = avatarDiv.querySelector('.profile-avatar-initial');

                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    // Show uploading state
                    avatarDiv.classList.add('uploading');

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = new Image();
                        img.onload = function() {
                            // Compress image - max 500px dimension, 80% quality for JPEG
                            const maxDimension = 500;
                            let width = img.width;
                            let height = img.height;

                            // Calculate new dimensions maintaining aspect ratio
                            if (width > height) {
                                if (width > maxDimension) {
                                    height = (height * maxDimension) / width;
                                    width = maxDimension;
                                }
                            } else {
                                if (height > maxDimension) {
                                    width = (width * maxDimension) / height;
                                    height = maxDimension;
                                }
                            }

                            // Draw to canvas and compress
                            const canvas = document.createElement('canvas');
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);

                            // Get compressed data - use JPEG for photos, keep PNG for graphics
                            const isPNG = file.type && file.type.startsWith('image/png');
                            const quality = isPNG ? 1.0 : 0.8;
                            const dataURL = canvas.toDataURL('image' + (isPNG ? 'png' : 'jpeg'), quality);

                            // Update avatar background
                            avatarDiv.style.backgroundImage = `url('${dataURL}')`;
                            avatarDiv.classList.add('has-avatar-image');

                            // Hide initials if present
                            if (avatarInitial) {
                                avatarInitial.style.display = 'none';
                            }

                            // Remove uploading state after short delay
                            setTimeout(() => {
                                avatarDiv.classList.remove('uploading');
                            }, 500);
                        };
                        img.src = e.target.result;
                    };

                    reader.readAsDataURL(file);
                });
            });
        </script>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
