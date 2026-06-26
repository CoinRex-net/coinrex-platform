
<?php
/**
 * CoinRex Submit Review Page - Wizard Flow
 * Location: /coinrex/submit-review.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireFeatureAccess('reviews');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$user = requireProjectReviewAccess('/taskhub.php');
$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureReviewEligibilitySchema($db);
$user_level_state = getUserLevelState($user, $db);
$error = '';
$success = '';
$project = null;
$existing_project_review = null;
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0);

$form = [
    'review_title' => '',
    'review_content' => '',
    'rating' => '',
    'pros' => '',
    'cons' => '',
    'holding_amount' => '',
    'holding_days' => '',
    'tx_hash' => '',
    'wallet_address' => strtolower((string) ($user['wallet_address'] ?? '')),
    'wallet_type' => ($user['wallet_type'] ?? 'non_custodial'),
    'tokenomics_score' => '5',
    'team_score' => '5',
    'utility_score' => '5',
    'community_score' => '5',
    'risk_score' => '5',
    'terms_accepted' => '0'
];

$experience_score_fields = [
    'tokenomics_score' => [
        'label' => 'Ease of Use',
        'hint' => 'How easy was it to start, buy, bridge, stake, or use the product?',
    ],
    'team_score' => [
        'label' => 'Product Works as Promised',
        'hint' => 'Did the token, app, or chain behave the way the project claimed?',
    ],
    'utility_score' => [
        'label' => 'Transparency',
        'hint' => 'Were fees, rules, lock periods, and conditions explained clearly?',
    ],
    'community_score' => [
        'label' => 'Trust & Safety Feeling',
        'hint' => 'Did anything feel misleading, suspicious, broken, or unsafe while using it?',
    ],
    'risk_score' => [
        'label' => 'Community / Support Quality',
        'hint' => 'Were updates, announcements, or support channels active and useful?',
    ],
];

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeWalletType($value)
{
    $value = strtolower(trim((string)$value));
    if (in_array($value, ['non_custodial', 'non-custodial', 'non custodial'], true)) {
        return 'non_custodial';
    }
    if (in_array($value, ['custodial'], true)) {
        return 'custodial';
    }
    return 'non_custodial';
}

function hasTableColumn(PDO $db, $table, $column)
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );
    $stmt->execute([(string)$table, (string)$column]);

    return ((int)($stmt->fetch()['total'] ?? 0) > 0);
}

function getExistingProjectReview(PDO $db, $user_id, $project_id)
{
    $stmt = $db->prepare(
        "SELECT id, status, proof_status
         FROM reviews
         WHERE user_id = ? AND project_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([(int) $user_id, (int) $project_id]);

    return $stmt->fetch() ?: null;
}

function calculateREXReward($holding_amount, $holding_days, $review_content, $rating, $project_rules, $wallet_type)
{
    $base_reward = (float)$project_rules['max_reward_rex'];
    $min_holding = max(1.0, (float)$project_rules['min_holding_amount']);
    $required_days = max(1, (int)$project_rules['required_holding_days']);

    if ($holding_amount >= $min_holding * 2 && $holding_days >= $required_days) {
        $holding_score = 1.0;
    } elseif ($holding_amount >= $min_holding && $holding_days >= $required_days) {
        $holding_score = 0.75;
    } elseif ($holding_amount >= $min_holding / 2 && $holding_days >= $required_days) {
        $holding_score = 0.5;
    } elseif ($holding_amount >= $min_holding / 4 && $holding_days >= $required_days) {
        $holding_score = 0.25;
    } elseif ($holding_amount >= $min_holding / 10) {
        $holding_score = 0.15;
    } else {
        $holding_score = 0.10;
    }

    $review_length = strlen((string)$review_content);
    $quality_bonus = 0;
    if ($review_length >= 500) {
        $quality_bonus = 0.30;
    } elseif ($review_length >= 300) {
        $quality_bonus = 0.20;
    } elseif ($review_length >= 200) {
        $quality_bonus = 0.15;
    } elseif ($review_length >= 150) {
        $quality_bonus = 0.10;
    }

    if ($rating >= 4.5 || ($rating <= 1.5 && $rating > 0)) {
        $quality_bonus += 0.05;
    }

    $wallet_multiplier = ($wallet_type === 'custodial') ? 0.50 : 1.00;
    $final_rex = $base_reward * $holding_score * (1 + $quality_bonus) * $wallet_multiplier;

    return round($final_rex, 2);
}

function submissionScoreHoldingAmount($amount) {
    $amount = (float) $amount;
    if ($amount >= 100) {
        return 20;
    }
    if ($amount >= 50) {
        return 15;
    }
    if ($amount >= 20) {
        return 10;
    }
    return 5;
}

function submissionScoreHoldingDuration($days) {
    $days = (int) $days;
    if ($days >= 30) {
        return 20;
    }
    if ($days >= 15) {
        return 15;
    }
    if ($days >= 7) {
        return 10;
    }
    return 5;
}

function submissionScoreReviewQuality($content) {
    $length = mb_strlen(trim((string) $content));
    if ($length >= 150 && $length <= 250) {
        return 20;
    }
    if (($length >= 100 && $length <= 149) || ($length >= 250 && $length <= 400)) {
        return 15;
    }
    if ($length >= 50 && $length <= 99) {
        return 10;
    }
    return 5;
}

function submissionScoreReviewerHistory($approved_count, $rejected_count) {
    $approved_count = (int) $approved_count;
    $rejected_count = (int) $rejected_count;

    if ($rejected_count === 0 && $approved_count >= 5) {
        return 20;
    }
    if ($rejected_count <= 2) {
        return 15;
    }
    if ($rejected_count <= 5) {
        return 10;
    }
    return 5;
}

function submissionScoreWalletType($wallet_type) {
    return normalizeWalletType($wallet_type) === 'non_custodial' ? 20 : 10;
}

function calculateSubmissionBaseScore(array $review_context) {
    $total = submissionScoreHoldingAmount($review_context['holding_amount'] ?? 0)
        + submissionScoreHoldingDuration($review_context['holding_days'] ?? 0)
        + submissionScoreReviewQuality($review_context['review_content'] ?? '')
        + submissionScoreReviewerHistory(
            $review_context['user_approved_reviews'] ?? 0,
            $review_context['user_rejected_reviews'] ?? 0
        )
        + submissionScoreWalletType($review_context['wallet_type'] ?? 'custodial');

    return round((float) $total, 2);
}

if ($project_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND approval_status = 'approved'");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();

        if ($project && !empty($user['id'])) {
            $existing_project_review = getExistingProjectReview($db, (int) $user['id'], $project_id);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$project_logo_url = $project ? coinrexNormalizeMediaUrl((string) ($project['logo'] ?? '')) : '';

if ($project_id > 0 && !isset($_SESSION['submit_review_started_at'][$project_id])) {
    $_SESSION['submit_review_started_at'][$project_id] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string)($_POST[$key] ?? $value));
    }
    $form['wallet_type'] = normalizeWalletType($form['wallet_type']);

    if (!validateAppCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $error = 'We could not verify this submission. Please try again.';
    } elseif ($existing_project_review) {
        $error = 'You already submitted a review for this project. CoinRex allows one review per user for the same project.';
    } elseif (!isset($_POST['terms_accepted']) || $_POST['terms_accepted'] !== '1') {
        $error = 'You must agree to the Terms & Conditions before submitting.';
    } else {
        $review_title = $form['review_title'];
        $review_content = $form['review_content'];
        $rating = (float)$form['rating'];
        $pros = $form['pros'];
        $cons = $form['cons'];
        $holding_amount = (float)$form['holding_amount'];
        $holding_days = (int)$form['holding_days'];
        $tx_hash = $form['tx_hash'];
        $wallet_address = $form['wallet_address'];
        $wallet_type = $form['wallet_type'];

        $tokenomics_score = ($form['tokenomics_score'] !== '') ? (int)$form['tokenomics_score'] : null;
        $team_score = ($form['team_score'] !== '') ? (int)$form['team_score'] : null;
        $utility_score = ($form['utility_score'] !== '') ? (int)$form['utility_score'] : null;
        $community_score = ($form['community_score'] !== '') ? (int)$form['community_score'] : null;
        $risk_score = ($form['risk_score'] !== '') ? (int)$form['risk_score'] : null;

        $errors = [];
        if ($project_id <= 0) $errors[] = 'Please select a project';
        if (empty($review_title)) $errors[] = 'Review title is required';
        if (strlen($review_content) < 150) $errors[] = 'Review must be at least 150 characters';
        if ($rating < 0.5 || $rating > 5) $errors[] = 'Invalid rating';
        if ($holding_amount <= 0) $errors[] = 'Valid holding amount is required';
        if ($holding_days <= 0) $errors[] = 'Valid holding days is required';
        if (empty($wallet_address)) $errors[] = 'Wallet address is required';
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) $errors[] = 'Connect a valid EVM wallet address';
        if (strtolower($wallet_address) !== strtolower((string) ($user['wallet_address'] ?? ''))) $errors[] = 'Connected wallet must match your verified CoinRex wallet.';
        if (!in_array($wallet_type, ['custodial', 'non_custodial'], true)) $errors[] = 'Invalid wallet type';

        $form_started_at = (int) ($_SESSION['submit_review_started_at'][$project_id] ?? 0);
        if ($form_started_at > 0 && (time() - $form_started_at) < 3) {
            $errors[] = 'Please take a moment to complete the review carefully before submitting.';
        }

        if ($existing_project_review) $errors[] = 'You already submitted a review for this project.';

        try {
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND approval_status = 'approved'");
            $stmt->execute([$project_id]);
            $project_rules = $stmt->fetch();
            if (!$project_rules) {
                $errors[] = 'Project not found';
            }

            $duplicate_review = getExistingProjectReview($db, (int) $user['id'], $project_id);
            if ($duplicate_review) {
                $errors[] = 'You already submitted a review for this project.';
                $existing_project_review = $duplicate_review;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            $project_rules = null;
        }

        $eligibility_check = null;
        if (empty($errors)) {
            $eligibility_check = reviewEligibilityGetFreshCheck($db, (int) $user['id'], $project_id, strtolower($wallet_address), 'eligible');
            if (!$eligibility_check) {
                $errors[] = 'Review eligibility was not verified on-chain. Connect wallet and run Check Eligibility first.';
            }
        }

        $screenshot_url = '';
        $uploaded_screenshot_path = '';
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $max_upload_size = 5 * 1024 * 1024;
            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $tmp_name = (string) ($_FILES['screenshot']['tmp_name'] ?? '');
            $file_size = (int) ($_FILES['screenshot']['size'] ?? 0);
            $ext = '';

            if (!is_uploaded_file($tmp_name)) {
                $errors[] = 'Invalid screenshot upload.';
            } elseif ($file_size <= 0 || $file_size > $max_upload_size) {
                $errors[] = 'Screenshot must be a valid image under 5MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = $finfo ? finfo_file($finfo, $tmp_name) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }

                $ext = $allowed_mimes[$mime_type] ?? '';
                $image_info = @getimagesize($tmp_name);

                if ($ext === '' || $image_info === false) {
                    $errors[] = 'Invalid screenshot format. Allowed: JPG, PNG, GIF, WEBP';
                }
            }

            if (empty($errors)) {
                $new_filename = 'proof_' . $user['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
                $upload_path = BASE_PATH . '/uploads/proofs/';

                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }

                if (move_uploaded_file($tmp_name, $upload_path . $new_filename)) {
                    $uploaded_screenshot_path = $upload_path . $new_filename;
                    $screenshot_url = BASE_URL . '/uploads/proofs/' . $new_filename;
                } else {
                    $errors[] = 'Failed to upload screenshot';
                }
            }
        }

        if (empty($errors) && $project_rules) {
            try {
                $db->beginTransaction();

                $transaction_duplicate = getExistingProjectReview($db, (int) $user['id'], $project_id);
                if ($transaction_duplicate) {
                    $db->rollBack();
                    if ($uploaded_screenshot_path !== '' && file_exists($uploaded_screenshot_path)) {
                        @unlink($uploaded_screenshot_path);
                    }
                    $existing_project_review = $transaction_duplicate;
                    $error = 'You already submitted a review for this project. CoinRex allows one review per user for the same project.';
                    goto submit_review_complete;
                }

                $calculated_rex = calculateREXReward($holding_amount, $holding_days, $review_content, $rating, $project_rules, $wallet_type);
                $user_review_stats = getUserReviewPerformanceStats((int) $user['id'], $db);
                $auto_approve = shouldAutoApproveReview($user_level_state);
                $users_has_wallet_type_column = hasTableColumn($db, 'users', 'wallet_type');
                $reviews_has_wallet_type_column = hasTableColumn($db, 'reviews', 'wallet_type');
                $reviews_has_final_rex_column = hasTableColumn($db, 'reviews', 'final_rex');
                $reviews_has_review_score_column = hasTableColumn($db, 'reviews', 'review_score');
                $reviews_has_auto_approved_at_column = hasTableColumn($db, 'reviews', 'auto_approved_at');
                $reviews_has_auto_approved_by_level_column = hasTableColumn($db, 'reviews', 'auto_approved_by_level');
                $reviews_has_eligibility_columns = true;
                $reviews_has_proof_verified_at_column = hasTableColumn($db, 'reviews', 'proof_verified_at');

                if ($users_has_wallet_type_column) {
                    $updateWallet = $db->prepare("UPDATE users SET wallet_type = ? WHERE id = ?");
                    $updateWallet->execute([$wallet_type, $user['id']]);
                }

                if ($reviews_has_wallet_type_column) {
                    $sql = "INSERT INTO reviews (
                        user_id, project_id, review_title, review_content, rating,
                        pros, cons, holding_amount, holding_days, wallet_type, tx_hash, wallet_address, screenshot_url,
                        tokenomics_score, team_score, utility_score, community_score, risk_score,
                        calculated_rex, status, proof_status" . ($reviews_has_eligibility_columns ? ",
                        eligibility_check_id, eligibility_status, eligibility_wallet_address, eligibility_chain_id, eligibility_contract_address" : "") . "
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'verified'" . ($reviews_has_eligibility_columns ? ",
                        ?, 'eligible', ?, ?, ?" : "") . "
                    )";

                    $eligibility_contract_address = '';
                    if ($eligibility_check && !empty($eligibility_check['matched_project_contract_id'])) {
                        $contract_lookup = $db->prepare("SELECT contract_address FROM project_contracts WHERE id = ? LIMIT 1");
                        $contract_lookup->execute([(int) $eligibility_check['matched_project_contract_id']]);
                        $eligibility_contract_address = (string) (($contract_lookup->fetch()['contract_address'] ?? ''));
                    }
                    $stmt = $db->prepare($sql);
                    $insert_params = [
                        $user['id'], $project_id, $review_title, $review_content, $rating,
                        $pros, $cons, $holding_amount, $holding_days, $wallet_type, ($tx_hash !== '' ? $tx_hash : null), strtolower($wallet_address), $screenshot_url !== '' ? $screenshot_url : null,
                        $tokenomics_score, $team_score, $utility_score, $community_score, $risk_score,
                        $calculated_rex
                    ];
                    if ($reviews_has_eligibility_columns) {
                        $insert_params[] = (int) ($eligibility_check['id'] ?? 0);
                        $insert_params[] = strtolower($wallet_address);
                        $insert_params[] = !empty($eligibility_check['matched_chain_id']) ? (int) $eligibility_check['matched_chain_id'] : null;
                        $insert_params[] = $eligibility_contract_address !== '' ? strtolower($eligibility_contract_address) : null;
                    }
                    $result = $stmt->execute($insert_params);
                } else {
                    $sql = "INSERT INTO reviews (
                        user_id, project_id, review_title, review_content, rating,
                        pros, cons, holding_amount, holding_days, tx_hash, wallet_address, screenshot_url,
                        tokenomics_score, team_score, utility_score, community_score, risk_score,
                        calculated_rex, status, proof_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'verified'
                    )";

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        $user['id'], $project_id, $review_title, $review_content, $rating,
                        $pros, $cons, $holding_amount, $holding_days, ($tx_hash !== '' ? $tx_hash : null), strtolower($wallet_address), $screenshot_url !== '' ? $screenshot_url : null,
                        $tokenomics_score, $team_score, $utility_score, $community_score, $risk_score,
                        $calculated_rex
                    ]);
                }

                if ($result) {
                    $new_review_id = (int) $db->lastInsertId();
                    if ($reviews_has_proof_verified_at_column) {
                        $db->prepare("UPDATE reviews SET proof_verified_at = NOW() WHERE id = ?")->execute([$new_review_id]);
                    }

                    if ($auto_approve && $new_review_id > 0) {
                        $base_score = calculateSubmissionBaseScore([
                            'holding_amount' => $holding_amount,
                            'holding_days' => $holding_days,
                            'review_content' => $review_content,
                            'wallet_type' => $wallet_type,
                            'user_approved_reviews' => $user_review_stats['approved_reviews'] ?? 0,
                            'user_rejected_reviews' => $user_review_stats['rejected_reviews'] ?? 0,
                        ]);
                        $score_details = calculateReviewFinalScore(
                            $base_score,
                            $user_level_state,
                            ['user_total_reviews' => ((int) ($user_review_stats['total_reviews'] ?? 0)) + 1]
                        );
                        $final_score = (float) $score_details['final_score'];
                        $final_reward = calculateRewardFromFinalScore($final_score, (float) ($project_rules['max_reward_rex'] ?? 0), $wallet_type);

                        $review_updates = ["status = 'approved'", "proof_status = 'verified'", "updated_at = NOW()"];
                        $review_params = [];

                        if ($reviews_has_review_score_column) {
                            $review_updates[] = "review_score = ?";
                            $review_params[] = $final_score;
                        }

                        if ($reviews_has_final_rex_column) {
                            $review_updates[] = "final_rex = ?";
                            $review_params[] = $final_reward;
                        }

                        if ($reviews_has_auto_approved_at_column) {
                            $review_updates[] = "auto_approved_at = NOW()";
                        }

                        if ($reviews_has_auto_approved_by_level_column) {
                            $review_updates[] = "auto_approved_by_level = 1";
                        }

                        $review_params[] = $new_review_id;
                        $auto_update = $db->prepare("UPDATE reviews SET " . implode(', ', $review_updates) . " WHERE id = ?");
                        $auto_update->execute($review_params);

                        if ($final_reward > 0) {
                            $credit = $db->prepare("
                                UPDATE users
                                SET rex_balance = rex_balance + ?,
                                    total_rex_earned = total_rex_earned + ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $credit->execute([$final_reward, $final_reward, $user['id']]);
                        }

                        syncUserReviewCounters((int) $user['id'], $db);
                        maybeActivateReferralQualification((int) $user['id'], $db);
                        creditReferralCommissionForReview((int) $user['id'], $final_reward, $db);
                        syncUserLevelStatus((int) $user['id'], $db);
                        syncProjectAggregateMetrics((int) $project_id, $db);
                        $success = 'review_auto_approved';
                    } else {
                        syncUserReviewCounters((int) $user['id'], $db);
                        syncUserLevelStatus((int) $user['id'], $db);
                        $success = 'review_submitted';
                    }

                    $db->commit();
                    $existing_project_review = [
                        'id' => $new_review_id,
                        'status' => $success === 'review_auto_approved' ? 'approved' : 'pending',
                        'proof_status' => 'pending',
                    ];
                    unset($_SESSION['submit_review_started_at'][$project_id]);
                    $_POST = [];
                    $_FILES = [];
                } else {
                    $db->rollBack();
                    $error = 'Failed to insert review into database';
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($uploaded_screenshot_path !== '' && file_exists($uploaded_screenshot_path)) {
                    @unlink($uploaded_screenshot_path);
                }
                $error = 'Database error: ' . $e->getMessage();
            }
        } elseif (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

submit_review_complete:

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/submit-review.css">

<main class="submit-review-main">
    <div class="submit-container submit-container-upgraded">
        <div id="toastContainer" class="toast-container"></div>

        <section class="page-header page-header-upgraded">
            <div class="hero-grid">
                <div class="hero-copy">
                    <div class="header-badge">
                        <i class="fas fa-shield-check"></i>
                        <span>Guided review wizard</span>
                    </div>
                    <h1>Share your <span class="hero-title-accent">Real Project Experience</span></h1>
                    <p>Follow the steps, add your proof, write your experience, and submit clearly.</p>

                    <div class="hero-points">
                        <div class="hero-point"><i class="fas fa-check-circle"></i><span>Simple 4-step flow</span></div>
                        <div class="hero-point"><i class="fas fa-link"></i><span>On-chain eligibility check</span></div>
                        <div class="hero-point"><i class="fas fa-user-shield"></i><span><?php echo esc($user_level_state['approval_label']); ?> moderation lane</span></div>
                    </div>
                </div>

                <aside class="hero-sidecard">
                    <span class="hero-sidecard-kicker">Before you start</span>
                    <h3>Need this first</h3>
                    <ul>
                        <li>Connected wallet</li>
                        <li>On-chain holder eligibility</li>
                        <li>Short honest review</li>
                        <li>Short honest review</li>
                    </ul>
                </aside>
            </div>
        </section>

        <?php if(!$project && $project_id > 0): ?>
            <div class="error-message">Project not found. Please go back to <a href="<?php echo BASE_URL; ?>/public/projects.php">Projects Page</a>.</div>
        <?php elseif(!$project): ?>
            <div class="error-message">Please select a project from the <a href="<?php echo BASE_URL; ?>/public/projects.php">Projects Page</a> first.</div>
        <?php elseif($existing_project_review): ?>
            <div class="review-status-card">
                <div class="review-status-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="review-status-copy">
                    <span class="review-status-kicker">Review Locked</span>
                    <h2>You already reviewed <?php echo esc($project['name']); ?></h2>
                    <p>To protect trust and stop reward abuse, CoinRex allows only one review per user for the same project.</p>
                    <div class="review-status-meta">
                        <span>Status: <strong><?php echo esc(ucfirst((string) ($existing_project_review['status'] ?? 'pending'))); ?></strong></span>
                        <span>Proof Check: <strong><?php echo esc(ucfirst((string) ($existing_project_review['proof_status'] ?? 'pending'))); ?></strong></span>
                    </div>
                    <div class="review-status-actions">
                        <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-submit">View My Reviews</a>
                        <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo (int) $project['id']; ?>" class="btn-cancel">Back to Project</a>
                    </div>
                </div>
            </div>
        <?php else: ?>

        <div class="selected-project-card selected-project-card-upgraded">
            <div class="project-info-mini">
                <div class="project-logo-mini">
                    <?php if($project_logo_url !== ''): ?>
                        <img src="<?php echo esc($project_logo_url); ?>" alt="<?php echo esc($project['name']); ?>">
                    <?php else: ?>
                        <div class="logo-placeholder-mini"><?php echo strtoupper(substr($project['name'], 0, 2)); ?></div>
                    <?php endif; ?>
                </div>
                <div class="project-details-mini">
                    <span class="project-card-kicker">Selected project</span>
                    <h3>
                        <?php echo esc($project['name']); ?>
                        <?php if($project['is_verified']): ?>
                            <i class="fas fa-check-circle verified-badge" title="Verified Project"></i>
                        <?php endif; ?>
                    </h3>
                    <div class="project-summary-pills">
                        <span>Min hold $<?php echo number_format((float)$project['min_holding_amount'], 2); ?></span>
                        <span><?php echo (int)$project['required_holding_days']; ?> holding days</span>
                        <span>One review per user</span>
                    </div>
                    <p>Checked in <strong><?php echo esc($user_level_state['approval_label']); ?></strong>. Better proof helps more.</p>
                </div>
            </div>
        </div>

        <section class="review-trust-card review-trust-card-upgraded">
            <div class="review-trust-head">
                <span class="review-trust-kicker">Quick beginner tips</span>
                <h2>Quick reminders</h2>
                <p>Short tips so you can fill the form faster.</p>
            </div>
            <div class="review-trust-grid">
                <article class="review-trust-item">
                    <div class="review-trust-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="review-trust-copy">
                        <strong>Use your real wallet</strong>
                        <p>Eligibility comes from the wallet that holds the project token/NFT.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon">
                        <i class="fas fa-eye-slash"></i>
                    </div>
                    <div class="review-trust-copy">
                        <strong>Protect your security</strong>
                        <p>Never upload seed phrases or private keys.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="review-trust-copy">
                        <strong>Optional screenshots</strong>
                        <p>Add screenshots only as extra context after eligibility passes.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="review-trust-copy">
                        <strong>One project, one review</strong>
                        <p>Only one review is allowed per project.</p>
                    </div>
                </article>
            </div>
        </section>

        <form method="POST" enctype="multipart/form-data" id="reviewForm" novalidate>
            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
            <div class="sr-honeypot" aria-hidden="true">
                <input type="text" name="website" id="reviewWebsite" tabindex="-1" autocomplete="off" placeholder="Leave blank">
            </div>

            <div class="wizard-progress wizard-progress-upgraded">
                <div class="wizard-track"></div>
                <div class="wizard-fill" id="wizardFill"></div>
                <div class="wizard-step-nav active" data-nav-step="1"><span>1</span><strong>Check eligibility</strong><small>Wallet holder proof</small></div>
                <div class="wizard-step-nav" data-nav-step="2"><span>2</span><strong>Your review</strong><small>Rating & written feedback</small></div>
                <div class="wizard-step-nav" data-nav-step="3"><span>3</span><strong>Extra detail</strong><small>Optional scoring</small></div>
                <div class="wizard-step-nav" data-nav-step="4"><span>4</span><strong>Submit</strong><small>Check & confirm</small></div>
            </div>

            <div class="submit-card submit-card-upgraded">
                <section class="wizard-step active" data-step="1">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 1</span>
                            <h3><i class="fas fa-shield-alt"></i> Check on-chain eligibility</h3>
                            <p class="section-note">Connect the wallet you used for this project. CoinRex checks configured project contracts automatically.</p>
                        </div>
                        <aside class="step-tip-card">
                            <strong><i class="fas fa-lightbulb"></i> Holder proof</strong>
                            <p>No TX hash or screenshot is required when your wallet passes.</p>
                        </aside>
                    </div>

                    <input type="hidden" name="wallet_type" value="non_custodial">
                    <input type="hidden" name="tx_hash" id="tx_hash" value="<?php echo esc($form['tx_hash']); ?>">
                    <input type="hidden" name="wallet_address" id="wallet_address" value="<?php echo esc($form['wallet_address']); ?>">

                    <div class="wallet-explain">
                        <div class="section-mini-head">
                            <h4><i class="fas fa-wallet"></i> Connected wallet</h4>
                            <span class="optional-chip">Required</span>
                        </div>
                        <p id="eligibilityWalletText"><?php echo !empty($form['wallet_address']) ? esc($form['wallet_address']) : 'No wallet connected yet.'; ?></p>
                        <div class="action-grid" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
                            <button type="button" class="btn-submit" id="btnUseAccountWallet" <?php echo empty($user['wallet_address']) ? 'disabled' : ''; ?>><i class="fas fa-link"></i> Use Account Wallet</button>
                            <button type="button" class="btn-cancel" id="btnConnectBrowserWallet"><i class="fas fa-wallet"></i> Connect Browser Wallet</button>
                            <button type="button" class="btn-submit" id="btnCheckEligibility" disabled><i class="fas fa-shield-check"></i> Check Eligibility</button>
                        </div>
                        <p class="section-note" id="eligibilityStatus">Eligibility must be checked before you can submit.</p>
                    </div>

                    <div class="proof-grid">
                        <div class="form-group"><label>Holding Amount (USD) <span class="required-asterisk">*</span></label><input type="number" name="holding_amount" id="holdingAmount" step="0.01" value="<?php echo esc($form['holding_amount']); ?>" placeholder="e.g. 100.00"></div>
                        <div class="form-group"><label>Holding Duration (Days) <span class="required-asterisk">*</span></label><input type="number" name="holding_days" id="holdingDays" value="<?php echo esc($form['holding_days']); ?>" placeholder="e.g. 30"></div>
                        <div class="form-group full-width">
                            <label>Screenshot Proof <span class="field-note field-note-block">Optional: add context only if useful</span></label>
                            <div class="file-upload-area" id="fileUploadArea"><i class="fas fa-cloud-upload-alt"></i><p>Upload optional screenshot</p><small>Never upload seed phrases or private keys.</small><input type="file" name="screenshot" accept="image/*" hidden id="screenshotInput"></div>
                            <div id="filePreview" class="file-preview" style="display:none;"><i class="fas fa-image"></i><span id="fileName"></span><button type="button" id="removeFile">x</button></div>
                        </div>
                    </div>

                    <div class="proof-safety-note">
                        <i class="fas fa-circle-info"></i>
                        <span>Never upload seed phrases or private keys.</span>
                    </div>
                </section>

                <section class="wizard-step" data-step="2">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 2</span>
                            <h3><i class="fas fa-star"></i> Tell us what really happened</h3>
                            <p class="section-note">Write what happened, what felt good, and what felt bad.</p>
                        </div>
                    </div>
                    <div class="form-group"><label>Review Title <span class="required-asterisk">*</span></label><input type="text" name="review_title" id="review_title" value="<?php echo esc($form['review_title']); ?>" placeholder="Easy to start, but withdrawals felt slow"></div>
                    <div class="form-group">
                        <label>Rating <span class="required-asterisk">*</span></label>
                        <div class="rating-input">
                            <div class="stars" id="starRating">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo ((float)$form['rating'] >= $i) ? 'fas' : 'far'; ?> fa-star" data-value="<?php echo $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="<?php echo esc($form['rating']); ?>">
                            <span class="rating-text" id="ratingText">Select rating</span>
                        </div>
                    </div>
                    <div class="form-group"><label>Review Content <span class="required-asterisk">*</span> <span class="char-count" id="charCount">0/150 min</span></label><textarea name="review_content" id="review_content" rows="7" placeholder="What did you do, what happened, what felt trustworthy or risky, and would you use it again? Minimum 150 characters."><?php echo esc($form['review_content']); ?></textarea></div>
                    <div class="pros-cons-grid">
                        <div class="form-group"><label><i class="fas fa-thumbs-up"></i> What Felt Good</label><textarea name="pros" rows="3" placeholder="What was clear, smooth, useful, or trustworthy?"><?php echo esc($form['pros']); ?></textarea></div>
                        <div class="form-group"><label><i class="fas fa-thumbs-down"></i> What Needs Improvement</label><textarea name="cons" rows="3" placeholder="What felt confusing, slow, risky, or disappointing?"><?php echo esc($form['cons']); ?></textarea></div>
                    </div>
                </section>

                <section class="wizard-step" data-step="3">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 3</span>
                            <h3><i class="fas fa-chart-bar"></i> Add extra detail if you want</h3>
                            <p class="section-note">Optional scores. Add only if you want extra detail.</p>
                        </div>
                        <aside class="step-tip-card tip-soft">
                            <strong><i class="fas fa-sliders"></i> Optional step</strong>
                            <p>If unsure, keep scores balanced and continue.</p>
                        </aside>
                    </div>
                    <div class="signals-intro">
                        <div class="signals-intro-copy">
                            <strong>Simple guide</strong>
                            <p>Low = weak, 5 = average, high = strong.</p>
                        </div>
                        <div class="signals-intro-badge">
                            <i class="fas fa-sliders"></i>
                            <span>Optional but useful</span>
                        </div>
                    </div>
                    <div class="scores-grid">
                        <?php foreach ($experience_score_fields as $field_name => $field): ?>
                            <div class="score-item">
                                <div class="score-card-top">
                                    <label><?php echo esc($field['label']); ?></label>
                                    <span class="score-card-chip">0-10</span>
                                </div>
                                <p class="score-hint"><?php echo esc($field['hint']); ?></p>
                                <div class="score-input-row">
                                    <input type="range" name="<?php echo esc($field_name); ?>" min="0" max="10" value="<?php echo esc($form[$field_name]); ?>" oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo esc($form[$field_name]); ?></output>
                                </div>
                                <div class="score-scale"><span>1-2 Weak</span><span>5 Balanced</span><span>9-10 Strong</span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="reward-preview improved">
                        <h4><i class="fas fa-coins"></i> Estimated Reward After Proof Check</h4>
                        <div class="reward-amount"><span id="rewardPreview">0</span> <small>$REX</small></div>
                        <div class="reward-breakdown">
                            <div><span>Wallet Multiplier</span><b id="walletMultiplierLabel">x1.00</b></div>
                            <div><span>Holding Qualification</span><b id="holdingTierLabel">Starter</b></div>
                            <div><span>Quality Bonus</span><b id="qualityBonusLabel">0%</b></div>
                        </div>
                        <p class="reward-note" id="rewardNote">This is only an estimate. Final reward depends on proof strength, review quality, trust level, and moderation outcome.</p>
                    </div>
                </section>

                <section class="wizard-step" data-step="4">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 4</span>
                            <h3><i class="fas fa-paper-plane"></i> Final check and submit</h3>
                            <p class="section-note">Check once, accept terms, then submit.</p>
                        </div>
                    </div>

                    <div class="consent-panel">
                        <div class="consent-panel-head">
                            <div>
                                <span class="consent-kicker">Final Review Check</span>
                                <h4>Ready To Submit Your Proof-Backed Review</h4>
                                <p>Final check before submission.</p>
                            </div>
                            <div class="consent-status-pill">
                                <i class="fas fa-hourglass-half"></i>
                                <span><?php echo esc($user_level_state['approval_label']); ?></span>
                            </div>
                        </div>

                        <div class="final-review-card">
                            <div class="final-review-item"><span>Project</span><strong><?php echo esc($project['name']); ?></strong></div>
                        <div class="final-review-item"><span>Proof Required</span><strong>On-chain wallet eligibility</strong></div>
                            <div class="final-review-item"><span>Review Limit</span><strong>One review per user</strong></div>
                            <div class="final-review-item"><span>Moderation</span><strong>All proof remains subject to validation</strong></div>
                        </div>

                        <div class="beginner-checklist">
                            <h5><i class="fas fa-list-check"></i> Quick final checklist</h5>
                            <div class="beginner-checklist-grid">
                                <span>Screenshot added</span>
                                <span>Review is original</span>
                                <span>No private keys shared</span>
                                <span>One review only</span>
                            </div>
                        </div>
                    </div>

                    <div class="terms-checkbox-wrapper">
                        <label class="terms-checkbox-label">
                            <input type="checkbox" name="terms_accepted" value="1" id="termsCheckbox" <?php echo $form['terms_accepted'] === '1' ? 'checked' : ''; ?>>
                            <span class="terms-checkbox-custom"></span>
                            <span class="terms-checkbox-text">I agree to the <a href="#" id="showTermsModalLink" class="terms-link">Review Submission Terms & Conditions</a></span>
                        </label>
                    </div>

                    <div class="submit-hint">
                        <?php if (shouldAutoApproveReview($user_level_state)): ?>
                            Your review can move through the <strong>fast lane</strong>, but proof and fraud checks still continue before it is fully trusted.
                        <?php elseif (normalizeUserLevel($user_level_state['level']) === 'pro'): ?>
                            Your review enters the <strong>priority queue</strong> for faster moderation and proof verification.
                        <?php else: ?>
                            Your review enters <strong>pending</strong> state and is manually checked in 24-48 hours.
                        <?php endif; ?>
                    </div>
                </section>

                <div class="wizard-actions" id="wizardActions">
                    <button type="button" class="btn-cancel" id="btnBack">Back</button>
                    <button type="button" class="btn-submit" id="btnNext">Next</button>
                    <button type="submit" name="submit_review" class="btn-submit" id="btnSubmit" style="display:none;"><i class="fas fa-paper-plane"></i> Submit Review</button>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </div>
</main>

<div id="termsModal" class="terms-modal">
    <div class="terms-modal-content">
        <div class="terms-modal-header">
            <h3><i class="fas fa-file-contract"></i> Review Submission Terms & Conditions</h3>
            <button class="terms-modal-close" id="closeTermsModal">&times;</button>
        </div>
        <div class="terms-modal-body">
            <div class="terms-list">
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>Honest Reviews Only</strong><p>Fake, copied, or AI-spam reviews are rejected.</p></div></div>
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>Proof Required</strong><p>Connect your wallet and pass the on-chain eligibility check for this project.</p></div></div>
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>One Review Per Project</strong><p>Duplicate reviews for the same project by the same user are not allowed.</p></div></div>
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>Moderator Validation</strong><p>Fast-lane handling does not remove proof checks. All submissions remain subject to moderation.</p></div></div>
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>Wallet Safety</strong><p>CoinRex never asks for private keys or seed phrases.</p></div></div>
            </div>
            <div class="terms-agreement">
                <label class="checkbox-label"><input type="checkbox" id="modalTermsAgree"><span class="checkbox-custom"></span><span>I have read and agree</span></label>
            </div>
        </div>
        <div class="terms-modal-footer"><button class="btn-submit" id="acceptTermsBtn" disabled>Accept & Continue</button></div>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 5000);
}

<?php if($success === 'review_submitted'): ?>
showToast('Review submitted successfully. Proof verification usually takes 24-48 hours.', 'success');
<?php endif; ?>

<?php if($success === 'review_auto_approved'): ?>
showToast('Fast-lane review applied. Your review can surface sooner, but proof checks still continue.', 'success');
<?php endif; ?>

<?php if($error): ?>
showToast('<?php echo addslashes(strip_tags($error)); ?>', 'error');
<?php endif; ?>

(function() {
    'use strict';

        const steps = Array.from(document.querySelectorAll('.wizard-step'));
    const nav = Array.from(document.querySelectorAll('.wizard-step-nav'));
    const fill = document.getElementById('wizardFill');
    const btnBack = document.getElementById('btnBack');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');
        const totalSteps = 4;
    let currentStep = 1;
        const draftKey = 'coinrex_submit_review_draft_' + <?php echo (int) ($project['id'] ?? 0); ?>;
    const accountWallet = <?php echo json_encode(strtolower((string) ($user['wallet_address'] ?? ''))); ?>;
    const eligibilityProjectId = <?php echo (int) ($project['id'] ?? 0); ?>;
    const eligibilityNonceUrl = <?php echo json_encode(BASE_URL . '/api/review-eligibility/wallet_nonce.php'); ?>;
    const eligibilityVerifyUrl = <?php echo json_encode(BASE_URL . '/api/review-eligibility/verify_wallet.php'); ?>;
    const eligibilityCheckUrl = <?php echo json_encode(BASE_URL . '/api/review-eligibility/check.php'); ?>;
    let eligibilityOk = false;

    if (!steps.length || !fill || !btnBack || !btnNext || !btnSubmit) {
        return;
    }

    const starEls = document.querySelectorAll('#starRating i');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    let currentRating = parseFloat(ratingValue?.value || 0);

    function renderRatingLabel(v) {
        const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        ratingText.textContent = labels[Math.round(v)] || 'Select rating';
    }

    function paintStars(v) {
        starEls.forEach((s, i) => {
            if (i < v) {
                s.classList.remove('far');
                s.classList.add('fas');
            } else {
                s.classList.remove('fas');
                s.classList.add('far');
            }
        });
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, step));
        steps.forEach(el => el.classList.toggle('active', Number(el.dataset.step) === currentStep));
        nav.forEach(el => {
            const n = Number(el.dataset.navStep);
            el.classList.toggle('active', n === currentStep);
            el.classList.toggle('completed', n < currentStep);
        });

        const percent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        fill.style.width = percent + '%';

        btnBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        btnNext.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        btnSubmit.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
    }

    function validateStep(step) {
        if (step === 1) {
            const wa = document.getElementById('wallet_address').value.trim();
            const ha = parseFloat(document.getElementById('holdingAmount').value || 0);
            const hd = parseInt(document.getElementById('holdingDays').value || 0, 10);
            if (!wa || !/^0x[a-fA-F0-9]{40}$/.test(wa) || !eligibilityOk || ha <= 0 || hd <= 0) {
                showToast('Step 1 incomplete: connect wallet, pass eligibility, and add holding info.', 'error');
                return false;
            }
        }

        if (step === 2) {
            const title = document.getElementById('review_title').value.trim();
            const content = document.getElementById('review_content').value.trim();
            if (!title || content.length < 150 || currentRating < 0.5) {
                showToast('Step 2 incomplete: title, rating, and 150+ chars required.', 'error');
                return false;
            }
        }

        if (step === 4) {
            const accepted = document.getElementById('termsCheckbox').checked;
            if (!accepted) {
                showToast('Please accept terms before submitting.', 'error');
                return false;
            }
        }

        return true;
    }

    function saveDraft() {
        try {
            const payload = {
                currentStep,
                tx_hash: document.getElementById('tx_hash')?.value || '',
                wallet_address: document.getElementById('wallet_address')?.value || '',
                eligibility_ok: eligibilityOk ? '1' : '0',
                holding_amount: document.getElementById('holdingAmount')?.value || '',
                holding_days: document.getElementById('holdingDays')?.value || '',
                review_title: document.getElementById('review_title')?.value || '',
                review_content: document.getElementById('review_content')?.value || '',
                pros: document.querySelector('textarea[name="pros"]')?.value || '',
                cons: document.querySelector('textarea[name="cons"]')?.value || '',
                rating: document.getElementById('ratingValue')?.value || '',
                wallet_type: getWalletType(),
                tokenomics_score: document.querySelector('input[name="tokenomics_score"]')?.value || '5',
                team_score: document.querySelector('input[name="team_score"]')?.value || '5',
                utility_score: document.querySelector('input[name="utility_score"]')?.value || '5',
                community_score: document.querySelector('input[name="community_score"]')?.value || '5',
                risk_score: document.querySelector('input[name="risk_score"]')?.value || '5',
                terms_accepted: document.getElementById('termsCheckbox')?.checked ? '1' : '0'
            };
            localStorage.setItem(draftKey, JSON.stringify(payload));
        } catch (e) {}
    }

    function restoreDraft() {
        try {
            const raw = localStorage.getItem(draftKey);
            if (!raw) return;
            const payload = JSON.parse(raw);
            const setValue = (id, value) => {
                const el = document.getElementById(id);
                if (el && typeof value === 'string' && !el.value) el.value = value;
            };
            setValue('tx_hash', payload.tx_hash || '');
            setValue('wallet_address', payload.wallet_address || '');
            eligibilityOk = payload.eligibility_ok === '1';
            setValue('holdingAmount', payload.holding_amount || '');
            setValue('holdingDays', payload.holding_days || '');
            setValue('review_title', payload.review_title || '');
            setValue('review_content', payload.review_content || '');
            const pros = document.querySelector('textarea[name="pros"]');
            if (pros && !pros.value) pros.value = payload.pros || '';
            const cons = document.querySelector('textarea[name="cons"]');
            if (cons && !cons.value) cons.value = payload.cons || '';
            if (payload.rating) {
                currentRating = parseFloat(payload.rating || 0);
                ratingValue.value = payload.rating;
            }
            if (payload.wallet_type) {
                const radio = document.querySelector('input[name="wallet_type"][value="' + payload.wallet_type + '"]');
                if (radio) radio.checked = true;
            }
            ['tokenomics_score','team_score','utility_score','community_score','risk_score'].forEach((name) => {
                const input = document.querySelector('input[name="' + name + '"]');
                const value = payload[name];
                if (input && value) {
                    input.value = value;
                    if (input.nextElementSibling) input.nextElementSibling.value = value;
                }
            });
            if (payload.terms_accepted === '1' && termsCheckbox) termsCheckbox.checked = true;
            if (payload.currentStep) currentStep = Math.min(totalSteps, Math.max(1, parseInt(payload.currentStep, 10) || 1));
        } catch (e) {}
    }

    btnNext?.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        saveDraft();
        showStep(currentStep + 1);
    });

    btnBack?.addEventListener('click', () => {
        saveDraft();
        showStep(currentStep - 1);
    });

    document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
        if (!validateStep(1) || !validateStep(2) || !validateStep(4)) {
            e.preventDefault();
            return;
        }
        localStorage.removeItem(draftKey);
    });

    starEls.forEach(star => {
        star.addEventListener('click', function() {
            currentRating = parseInt(this.dataset.value, 10);
            ratingValue.value = currentRating;
            paintStars(currentRating);
            renderRatingLabel(currentRating);
            updateRewardPreview();
            saveDraft();
        });
    });

    const reviewContent = document.getElementById('review_content');
    const charCount = document.getElementById('charCount');
    reviewContent?.addEventListener('input', function() {
        const len = this.value.length;
        charCount.textContent = `${len}/150 min`;
        charCount.style.color = len >= 150 ? '#22c55e' : '#ef4444';
        updateRewardPreview();
        saveDraft();
    });

    const holdingAmount = document.getElementById('holdingAmount');
    const holdingDays = document.getElementById('holdingDays');
    const walletTypeEls = document.querySelectorAll('input[name="wallet_type"]');
    const walletAddressInput = document.getElementById('wallet_address');
    const eligibilityWalletText = document.getElementById('eligibilityWalletText');
    const eligibilityStatus = document.getElementById('eligibilityStatus');
    const btnUseAccountWallet = document.getElementById('btnUseAccountWallet');
    const btnConnectBrowserWallet = document.getElementById('btnConnectBrowserWallet');
    const btnCheckEligibility = document.getElementById('btnCheckEligibility');

    function setEligibilityStatus(message, type = '') {
        if (!eligibilityStatus) return;
        eligibilityStatus.textContent = message;
        eligibilityStatus.style.color = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#cbd5e1';
    }

    function setWalletAddress(address, alreadyEligible = false) {
        const value = String(address || '').toLowerCase();
        if (walletAddressInput) walletAddressInput.value = value;
        if (eligibilityWalletText) eligibilityWalletText.textContent = value || 'No wallet connected yet.';
        eligibilityOk = alreadyEligible;
        if (btnCheckEligibility) btnCheckEligibility.disabled = !/^0x[a-f0-9]{40}$/.test(value);
        setEligibilityStatus(alreadyEligible ? 'Eligibility verified. You can continue.' : 'Wallet connected. Run Check Eligibility.', alreadyEligible ? 'success' : '');
        saveDraft();
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        });
        return response.json();
    }

    btnUseAccountWallet?.addEventListener('click', function() {
        if (!accountWallet) {
            setEligibilityStatus('No verified account wallet found. Connect browser wallet instead.', 'error');
            return;
        }
        setWalletAddress(accountWallet, false);
    });

    btnConnectBrowserWallet?.addEventListener('click', async function() {
        try {
            if (!window.ethereum || !window.ethereum.request) {
                setEligibilityStatus('Browser wallet not found. Use RexLink/account wallet or install MetaMask.', 'error');
                return;
            }
            btnConnectBrowserWallet.disabled = true;
            setEligibilityStatus('Requesting wallet connection...');
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            const wallet = String(accounts && accounts[0] ? accounts[0] : '').toLowerCase();
            if (!/^0x[a-f0-9]{40}$/.test(wallet)) throw new Error('No valid wallet returned.');
            const nonce = await postJson(eligibilityNonceUrl, { wallet_address: wallet });
            if (!nonce.success) throw new Error(nonce.message || 'Could not create wallet nonce.');
            const signature = await window.ethereum.request({ method: 'personal_sign', params: [nonce.message, wallet] });
            const verify = await postJson(eligibilityVerifyUrl, { wallet_address: wallet, signature });
            if (!verify.success) throw new Error(verify.message || 'Wallet verification failed.');
            setWalletAddress(wallet, false);
        } catch (error) {
            setEligibilityStatus(error.message || 'Wallet connection failed.', 'error');
        } finally {
            btnConnectBrowserWallet.disabled = false;
        }
    });

    btnCheckEligibility?.addEventListener('click', async function() {
        const wallet = walletAddressInput?.value.trim().toLowerCase() || '';
        if (!/^0x[a-f0-9]{40}$/.test(wallet)) {
            setEligibilityStatus('Connect a valid wallet first.', 'error');
            return;
        }
        btnCheckEligibility.disabled = true;
        setEligibilityStatus('Checking project contracts on-chain...');
        try {
            const result = await postJson(eligibilityCheckUrl, { project_id: eligibilityProjectId, wallet_address: wallet });
            if (!result.success) throw new Error(result.message || 'Eligibility check failed.');
            eligibilityOk = result.status === 'eligible';
            if (eligibilityOk) {
                setEligibilityStatus(result.reason || 'Eligibility verified. You can continue.', 'success');
                showToast('Eligibility verified.', 'success');
            } else {
                setEligibilityStatus(result.reason || 'Not eligible on supported project contracts.', 'error');
                showToast(result.reason || 'Not eligible.', 'error');
            }
            saveDraft();
        } catch (error) {
            eligibilityOk = false;
            setEligibilityStatus(error.message || 'Eligibility could not be verified. Recheck later.', 'error');
        } finally {
            btnCheckEligibility.disabled = false;
        }
    });

    function getWalletType() {
        const checked = document.querySelector('input[name="wallet_type"]:checked');
        return checked ? checked.value : 'non_custodial';
    }

    function updateRewardPreview() {
        const amount = parseFloat(holdingAmount?.value || 0);
        const days = parseInt(holdingDays?.value || 0, 10);
        const length = reviewContent?.value.length || 0;
        const rating = parseFloat(ratingValue?.value || 0);
        const walletType = getWalletType();

        const minHolding = <?php echo $project ? (float)$project['min_holding_amount'] : 10; ?>;
        const requiredDays = <?php echo $project ? (int)$project['required_holding_days'] : 30; ?>;
        const baseReward = <?php echo $project ? (float)$project['max_reward_rex'] : 50; ?>;

        let holdingScore = 0.10;
        let holdingTier = 'Starter';
        if (amount >= minHolding * 2 && days >= requiredDays) { holdingScore = 1.0; holdingTier = 'Excellent'; }
        else if (amount >= minHolding && days >= requiredDays) { holdingScore = 0.75; holdingTier = 'Strong'; }
        else if (amount >= minHolding / 2 && days >= requiredDays) { holdingScore = 0.5; holdingTier = 'Good'; }
        else if (amount >= minHolding / 4 && days >= requiredDays) { holdingScore = 0.25; holdingTier = 'Basic'; }
        else if (amount >= minHolding / 10) { holdingScore = 0.15; holdingTier = 'Starter'; }

        let qualityBonus = 0;
        if (length >= 500) qualityBonus = 0.30;
        else if (length >= 300) qualityBonus = 0.20;
        else if (length >= 200) qualityBonus = 0.15;
        else if (length >= 150) qualityBonus = 0.10;

        if (rating >= 4.5 || (rating <= 1.5 && rating > 0)) qualityBonus += 0.05;

        const walletMultiplier = walletType === 'custodial' ? 0.50 : 1.00;
        const estimated = baseReward * holdingScore * (1 + qualityBonus) * walletMultiplier;

        document.getElementById('rewardPreview').textContent = Math.round(estimated);
        document.getElementById('walletMultiplierLabel').textContent = 'x' + walletMultiplier.toFixed(2);
        document.getElementById('holdingTierLabel').textContent = holdingTier;
        document.getElementById('qualityBonusLabel').textContent = Math.round(qualityBonus * 100) + '%';

        const note = walletType === 'custodial'
            ? 'Custodial wallet selected: screenshot proof will carry more moderation weight and the estimate uses the 50% multiplier.'
            : 'Non-custodial wallet selected: on-chain proof is stronger and the full multiplier is applied.';
        document.getElementById('rewardNote').textContent = note;
    }

    holdingAmount?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    holdingDays?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    reviewContent?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    walletTypeEls.forEach(el => el.addEventListener('change', () => { updateRewardPreview(); saveDraft(); }));

    document.querySelectorAll('.score-item input[type="range"]').forEach(slider => {
        slider.addEventListener('input', function() {
            if (this.nextElementSibling) this.nextElementSibling.value = this.value;
            saveDraft();
        });
    });

    document.querySelectorAll('#review_title, #tx_hash, #wallet_address, textarea[name="pros"], textarea[name="cons"], #holdingAmount, #holdingDays').forEach((el) => {
        el?.addEventListener('input', saveDraft);
    });

    const uploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('screenshotInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const removeFileBtn = document.getElementById('removeFile');

    uploadArea?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', (e) => {
        if (e.target.files[0]) {
            fileName.textContent = e.target.files[0].name;
            uploadArea.style.display = 'none';
            filePreview.style.display = 'flex';
            saveDraft();
        }
    });
    removeFileBtn?.addEventListener('click', () => {
        fileInput.value = '';
        uploadArea.style.display = 'flex';
        filePreview.style.display = 'none';
        saveDraft();
    });

    const termsModal = document.getElementById('termsModal');
    const showTermsModalLink = document.getElementById('showTermsModalLink');
    const closeTermsModal = document.getElementById('closeTermsModal');
    const modalTermsAgree = document.getElementById('modalTermsAgree');
    const acceptTermsBtn = document.getElementById('acceptTermsBtn');
    const termsCheckbox = document.getElementById('termsCheckbox');

    showTermsModalLink?.addEventListener('click', (e) => { e.preventDefault(); termsModal.style.display = 'flex'; });
    closeTermsModal?.addEventListener('click', () => { termsModal.style.display = 'none'; });
    window.addEventListener('click', (e) => { if (e.target === termsModal) termsModal.style.display = 'none'; });

    modalTermsAgree?.addEventListener('change', function() {
        acceptTermsBtn.disabled = !this.checked;
    });

    acceptTermsBtn?.addEventListener('click', () => {
        if (termsCheckbox) termsCheckbox.checked = true;
        termsModal.style.display = 'none';
        showToast('Terms accepted. You can submit now.', 'success');
        saveDraft();
    });

    restoreDraft();
    if (walletAddressInput?.value) {
        setWalletAddress(walletAddressInput.value, eligibilityOk);
    } else if (accountWallet) {
        setWalletAddress(accountWallet, false);
    }
    paintStars(currentRating);
    renderRatingLabel(currentRating);
    if (reviewContent && charCount) {
        const initialLength = reviewContent.value.length;
        charCount.textContent = `${initialLength}/150 min`;
        charCount.style.color = initialLength >= 150 ? '#22c55e' : '#ef4444';
    }
    updateRewardPreview();
    showStep(1);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
