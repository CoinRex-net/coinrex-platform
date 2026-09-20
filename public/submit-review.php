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

// This page contains live wallet-pairing state and inline orchestration code.
// Never allow a browser/back-forward cache to restore an older pairing flow.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$user = requireProjectReviewAccess('/taskhub.php');
$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureReviewEligibilitySchema($db);
$user_level_state = getUserLevelState($user, $db);
$error = '';
$success = '';
$submission_success = null;
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
    'proof_method' => 'manual',
    'tx_hash' => '',
    'wallet_address' => strtolower((string) ($user['wallet_address'] ?? '')),
    'wallet_type' => ($user['wallet_type'] ?? 'non_custodial'),
    'tokenomics_score' => '',
    'team_score' => '',
    'utility_score' => '',
    'community_score' => '',
    'risk_score' => '',
    'terms_accepted' => '0',
    'verification_confirmed' => '0'
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

function coinrexReviewBase64Url($value)
{
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function coinrexReviewNodeActorToken($user_id)
{
    $secret = (string) (getenv('COINREX_REALTIME_SECRET') ?: (getenv('COINREX_ENCRYPTION_KEY') ?: (getenv('COINREX_CSRF_KEY') ?: 'coinrex-dev-realtime-secret')));
    $payload = coinrexReviewBase64Url(json_encode([
        'user_id' => (int) $user_id,
        'iat' => time(),
        'exp' => time() + 900,
        'scope' => 'review_pairing',
    ], JSON_UNESCAPED_SLASHES));
    $signature = coinrexReviewBase64Url(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $signature;
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
$eligibility_rules = [];
$eligibility_primary_rule = null;
if ($project) {
    try {
        $eligibility_rules = reviewEligibilityMonitoringRule($db, (int) $project['id']);
        $eligibility_primary_rule = $eligibility_rules[0] ?? null;
    } catch (Throwable $e) {
        $eligibility_rules = [];
        $eligibility_primary_rule = null;
    }
}

$project_token_symbol = '';
$project_min_hold = 0.0;
$project_hold_days = 1;
$project_max_reward = 0.0;
if ($project) {
    $project_min_hold = max(0.0, (float) ($project['min_holding_amount'] ?? 0));
    $project_hold_days = max(1, (int) ($project['required_holding_days'] ?? 1));
    $project_max_reward = max(0.0, (float) ($project['max_reward_rex'] ?? 0));

    $primary_symbol = trim((string) ($eligibility_primary_rule['token_symbol'] ?? ''));
    if ($primary_symbol === '' && $eligibility_primary_rule) {
        $primary_symbol = reviewEligibilityTokenSymbol($eligibility_primary_rule);
    }
    if ($primary_symbol === '' || strtoupper($primary_symbol) === 'TOKEN' || strtoupper($primary_symbol) === 'NATIVE') {
        $primary_symbol = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', (string) $project['name']));
    }
    $project_token_symbol = strtoupper(trim($primary_symbol));
}

$submitted_review_id = (int) ($_GET['submitted'] ?? 0);
$submission_flash = $_SESSION['review_submission_success'] ?? null;
if ($submitted_review_id > 0 && is_array($submission_flash)
    && (int) ($submission_flash['review_id'] ?? 0) === $submitted_review_id
    && (int) ($submission_flash['user_id'] ?? 0) === (int) ($user['id'] ?? 0)
    && (int) ($submission_flash['project_id'] ?? 0) === $project_id) {
    $submission_success = $submission_flash;
    unset($_SESSION['review_submission_success']);
}

$format_clean_amount = static function ($value): string {
    $amount = rtrim(rtrim(number_format(max(0.0, (float) $value), 8, '.', ''), '0'), '.');
    return $amount === '' ? '0' : $amount;
};

if ($project_id > 0 && !isset($_SESSION['submit_review_started_at'][$project_id])) {
    $_SESSION['submit_review_started_at'][$project_id] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string)($_POST[$key] ?? $value));
    }
    $form['proof_method'] = in_array($form['proof_method'], ['instant', 'live', 'manual'], true) ? $form['proof_method'] : 'manual';
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
        $proof_method = $form['proof_method'];
        $tx_hash = $form['tx_hash'];
        $manual_wallet_address = strtolower(trim((string) ($_POST['manual_wallet_address'] ?? '')));
        $wallet_address = strtolower(trim($proof_method === 'manual' && $manual_wallet_address !== ''
            ? $manual_wallet_address
            : $form['wallet_address']));
        $form['wallet_address'] = $wallet_address;
        $wallet_type = $form['wallet_type'];

        $tokenomics_score = ($form['tokenomics_score'] !== '') ? (int)$form['tokenomics_score'] : null;
        $team_score = ($form['team_score'] !== '') ? (int)$form['team_score'] : null;
        $utility_score = ($form['utility_score'] !== '') ? (int)$form['utility_score'] : null;
        $community_score = ($form['community_score'] !== '') ? (int)$form['community_score'] : null;
        $risk_score = ($form['risk_score'] !== '') ? (int)$form['risk_score'] : null;

        $errors = [];
        foreach ($experience_score_fields as $score_field => $score_config) {
            if (!coinrexReviewRatingIsValid($form[$score_field] ?? '')) {
                $errors[] = ($score_config['label'] ?? 'Category rating') . ' must be rated from 1 to 5.';
            }
        }
        if ($project_id <= 0) $errors[] = 'Please select a project';
        if ($proof_method !== 'manual') $errors[] = 'Instant verification and live monitoring are coming soon. Please use manual proof for now.';
        if (empty($review_title)) $errors[] = 'Review title is required';
        if (strlen($review_content) < 150) $errors[] = 'Review must be at least 150 characters';
        if (!coinrexReviewRatingIsValid($form['rating'])) $errors[] = 'Overall rating must be an integer from 1 to 5.';
        if ($holding_amount <= 0) $errors[] = 'Valid holding amount is required';
        if ($holding_days <= 0) $errors[] = 'Valid holding days is required';
        if (empty($wallet_address)) $errors[] = 'Wallet address is required';
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) $errors[] = 'Enter a valid EVM wallet address';
        if ($proof_method === 'manual' && trim($tx_hash) === '') $errors[] = 'TX hash is required when submitting manual wallet proof.';
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
        $eligibility_monitoring_session = null;
        $instant_check = null;
        if (empty($errors) && $proof_method === 'live') {
            $used_review = reviewEligibilityFindWalletReviewUsage($db, strtolower($wallet_address), 0, $project_id);
            if ($used_review) {
                $errors[] = 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility';
            }
            try {
                $eligibility_monitoring_session = reviewEligibilityMonitoringValidateForSubmission($db, (int) $user['id'], $project_id, strtolower($wallet_address));
                $eligibility_check = [
                    'id' => null,
                    'matched_chain_id' => (int) ($eligibility_monitoring_session['chain_id'] ?? 0),
                    'matched_project_contract_id' => (int) ($eligibility_monitoring_session['project_contract_id'] ?? 0),
                ];
            } catch (Throwable $eligibility_error) {
                $errors[] = $eligibility_error->getMessage();
            }
        } elseif (empty($errors) && $proof_method === 'instant') {
            $used_review = reviewEligibilityFindWalletReviewUsage($db, strtolower($wallet_address), 0, $project_id);
            if ($used_review) {
                $errors[] = 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility';
            }
            $instant_check = reviewEligibilityGetFreshCheck($db, (int) $user['id'], $project_id, strtolower($wallet_address), 'eligible');
            if (!$instant_check) {
                $errors[] = 'Instant verification was not completed. Run the instant eligibility check first.';
            } else {
                $eligibility_check = [
                    'id' => (int) $instant_check['id'],
                    'matched_chain_id' => (int) ($instant_check['matched_chain_id'] ?? 0),
                    'matched_project_contract_id' => (int) ($instant_check['matched_project_contract_id'] ?? 0),
                ];
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

        if ($proof_method === 'manual' && $screenshot_url === '') {
            $errors[] = 'Screenshot proof is required when submitting manual wallet proof.';
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

                $proof_status = in_array($proof_method, ['instant', 'live'], true) ? 'verified' : 'pending';
                $eligibility_status = in_array($proof_method, ['instant', 'live'], true) ? 'eligible' : 'manual_pending';
                $can_auto_approve = $auto_approve && in_array($proof_method, ['instant', 'live'], true);

                $reviews_has_verification_method = hasTableColumn($db, 'reviews', 'verification_method');
                $reviews_has_instant_check_id = hasTableColumn($db, 'reviews', 'instant_check_id');

                if ($reviews_has_wallet_type_column) {
                    $sql = "INSERT INTO reviews (
                        user_id, project_id, review_title, review_content, rating,
                        pros, cons, holding_amount, holding_days, wallet_type, tx_hash, wallet_address, screenshot_url,
                        tokenomics_score, team_score, utility_score, community_score, risk_score,
                        calculated_rex, status, proof_status" . ($reviews_has_eligibility_columns ? ",
                        eligibility_check_id, eligibility_status, eligibility_wallet_address, eligibility_chain_id, eligibility_contract_address" : "") . ($reviews_has_verification_method ? ",
                        verification_method" : "") . ($reviews_has_instant_check_id ? ", instant_check_id" : "") . "
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?" . ($reviews_has_eligibility_columns ? ",
                        ?, ?, ?, ?, ?" : "") . ($reviews_has_verification_method ? ", ?" : "") . ($reviews_has_instant_check_id ? ", ?" : "") . "
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
                        $calculated_rex, $proof_status
                    ];
                    if ($reviews_has_eligibility_columns) {
                        $insert_params[] = !empty($eligibility_check['id']) ? (int) $eligibility_check['id'] : null;
                        $insert_params[] = $eligibility_status;
                        $insert_params[] = strtolower($wallet_address);
                        $insert_params[] = !empty($eligibility_check['matched_chain_id']) ? (int) $eligibility_check['matched_chain_id'] : null;
                        $insert_params[] = $eligibility_contract_address !== '' ? strtolower($eligibility_contract_address) : null;
                    }
                    if ($reviews_has_verification_method) {
                        $insert_params[] = $proof_method;
                    }
                    if ($reviews_has_instant_check_id) {
                        $insert_params[] = !empty($instant_check['id']) ? (int) $instant_check['id'] : null;
                    }
                    $result = $stmt->execute($insert_params);
                } else {
                    $sql = "INSERT INTO reviews (
                        user_id, project_id, review_title, review_content, rating,
                        pros, cons, holding_amount, holding_days, tx_hash, wallet_address, screenshot_url,
                        tokenomics_score, team_score, utility_score, community_score, risk_score,
                        calculated_rex, status, proof_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?
                    )";

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        $user['id'], $project_id, $review_title, $review_content, $rating,
                        $pros, $cons, $holding_amount, $holding_days, ($tx_hash !== '' ? $tx_hash : null), strtolower($wallet_address), $screenshot_url !== '' ? $screenshot_url : null,
                        $tokenomics_score, $team_score, $utility_score, $community_score, $risk_score,
                        $calculated_rex, $proof_status
                    ]);
                }

                if ($result) {
                    $new_review_id = (int) $db->lastInsertId();
                    if ($proof_method === 'live' && $eligibility_monitoring_session) {
                        $db->prepare('UPDATE reviews SET eligibility_monitoring_session_id = ? WHERE id = ?')
                            ->execute([(int) $eligibility_monitoring_session['id'], $new_review_id]);
                        if (!reviewEligibilityMonitoringConsume($db, (int) $eligibility_monitoring_session['id'], $new_review_id)) {
                            throw new RuntimeException('Eligibility was already used or expired. Please refresh and try again.');
                        }
                    }
                    if (in_array($proof_method, ['instant', 'live'], true) && $reviews_has_proof_verified_at_column) {
                        $db->prepare("UPDATE reviews SET proof_verified_at = NOW() WHERE id = ?")->execute([$new_review_id]);
                    }

                    if ($can_auto_approve && $new_review_id > 0) {
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
                        'proof_status' => $proof_status,
                    ];
                    unset($_SESSION['submit_review_started_at'][$project_id]);
                    $_SESSION['review_submission_success'] = [
                        'review_id' => $new_review_id,
                        'user_id' => (int) $user['id'],
                        'project_id' => $project_id,
                        'status' => $success === 'review_auto_approved' ? 'approved' : 'pending',
                        'proof_status' => $proof_status,
                        'reward_rex' => (float) ($success === 'review_auto_approved' ? ($final_reward ?? $calculated_rex) : $calculated_rex),
                        'reward_confirmed' => $success === 'review_auto_approved',
                    ];
                    redirect(BASE_URL . '/public/submit-review.php?project_id=' . $project_id . '&submitted=' . $new_review_id);
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

$rexlink_review_network_slugs = [];
if ($project_id > 0 && function_exists('reviewEligibilityGetProjectContracts')) {
    $project_contracts = reviewEligibilityGetProjectContracts($db, $project_id, true);
    $configured_slugs = array_values(array_unique(array_filter(array_map(static function ($contract) {
        return strtolower(trim((string) ($contract['network_slug'] ?? '')));
    }, $project_contracts))));
    if ($configured_slugs) {
        $enabled_networks_stmt = $db->query('SELECT slug FROM rex_signer_networks WHERE is_enabled = 1 AND chain_family = \'evm\' AND chain_id IS NOT NULL');
        $enabled_slugs = array_map('strtolower', array_column($enabled_networks_stmt->fetchAll(), 'slug'));
        $rexlink_review_network_slugs = array_values(array_intersect($configured_slugs, $enabled_slugs));
    }
}

// Capture session-backed values before releasing the session file lock so the
// browser's pairing/polling requests never block behind this page render.
$page_csrf_token = appCsrfToken();
@session_write_close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/includes/review-submit-view.php';
require_once __DIR__ . '/../includes/footer.php';
