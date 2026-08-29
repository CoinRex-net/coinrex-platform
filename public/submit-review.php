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
    'proof_method' => 'connected',
    'tx_hash' => '',
    'wallet_address' => strtolower((string) ($user['wallet_address'] ?? '')),
    'wallet_type' => ($user['wallet_type'] ?? 'non_custodial'),
    'tokenomics_score' => '5',
    'team_score' => '5',
    'utility_score' => '5',
    'community_score' => '5',
    'risk_score' => '5',
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
    $form['proof_method'] = in_array($form['proof_method'], ['instant', 'live', 'manual'], true) ? $form['proof_method'] : 'instant';
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
        if ($project_id <= 0) $errors[] = 'Please select a project';
        if (empty($review_title)) $errors[] = 'Review title is required';
        if (strlen($review_content) < 150) $errors[] = 'Review must be at least 150 characters';
        if ($rating < 0.5 || $rating > 5) $errors[] = 'Invalid rating';
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
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rexlink-auth.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/rexlink-auth.css'); ?>">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/submit-review.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/submit-review.css'); ?>">

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
                        <div class="hero-point"><i class="fas fa-link"></i><span>Hybrid proof check</span></div>
                        <div class="hero-point"><i class="fas fa-user-shield"></i><span><?php echo esc($user_level_state['approval_label']); ?> moderation lane</span></div>
                    </div>
                </div>

                <aside class="hero-sidecard">
                    <span class="hero-sidecard-kicker">Before you start</span>
                    <h3>Need this first</h3>
                    <ul>
                        <li>Connected wallet or manual proof</li>
                        <li>Short honest review</li>
                        <li>TX hash and screenshot for manual proof</li>
                    </ul>
                </aside>
            </div>
        </section>

        <?php if(!empty($success) && in_array($success, ['review_submitted', 'review_auto_approved'], true) && $project): ?>
            <div class="review-success-panel is-visible <?php echo $success === 'review_auto_approved' ? 'is-approved' : 'is-pending'; ?>" id="reviewSuccessPanel" role="status" aria-live="polite">
                <div class="review-success-head">
                    <div class="review-success-icon"><i class="fas <?php echo $success === 'review_auto_approved' ? 'fa-bolt' : 'fa-hourglass-half'; ?>"></i></div>
                    <div>
                        <h2><?php echo $success === 'review_auto_approved' ? 'Review approved — fast-lane applied' : 'Review submitted successfully'; ?></h2>
                        <p><?php echo $success === 'review_auto_approved' ? 'Fast-lane review applied. Your review can surface sooner, but proof checks still continue. You can track its status in My Reviews.' : 'Proof verification usually takes 24–48 hours. You will be notified when moderation completes. You can track it in My Reviews.'; ?></p>
                    </div>
                </div>
                <div class="review-success-actions">
                    <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-submit"><i class="fas fa-list-check"></i> View My Reviews</a>
                    <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo (int) $project['id']; ?>" class="btn-cancel"><i class="fas fa-arrow-left"></i> Back to Project</a>
                </div>
            </div>
        <?php elseif(!$project && $project_id > 0): ?>
            <div class="error-message">Project not found. Please go back to <a href="<?php echo BASE_URL; ?>/public/projects.php">Projects Page</a>.</div>
        <?php elseif(!$project): ?>
            <div class="error-message">Please select a project from the <a href="<?php echo BASE_URL; ?>/public/projects.php">Projects Page</a> first.</div>
        <?php elseif($existing_project_review && empty($success)): ?>
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
            <span class="project-card-kicker">Selected project</span>
            <div class="project-card-body">
                <div class="project-card-left">
                    <div class="project-logo-mini<?php echo $project_logo_url !== '' ? ' has-logo-image' : ''; ?>"<?php if ($project_logo_url !== ''): ?> style="background-image: url('<?php echo esc($project_logo_url); ?>');" aria-label="<?php echo esc($project['name']); ?> logo"<?php endif; ?>>
                        <?php if($project_logo_url !== ''): ?>
                            <img src="<?php echo esc($project_logo_url); ?>" alt="<?php echo esc($project['name']); ?>">
                        <?php else: ?>
                            <div class="logo-placeholder-mini"><?php echo strtoupper(substr($project['name'], 0, 2)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="project-card-left-info">
                        <h3>
                            <?php echo esc($project['name']); ?>
                            <?php if($project['is_verified']): ?>
                                <i class="fas fa-check-circle verified-badge" title="Verified Project"></i>
                            <?php endif; ?>
                        </h3>
                        <p class="project-card-desc"><?php echo esc(mb_strimwidth((string) ($project['description'] ?? 'No description available.'), 0, 140, '...')); ?></p>
                    </div>
                </div>
                <div class="project-card-right">
                    <h4 class="project-card-right-title">Review Requirements</h4>
                    <div class="project-card-reqs">
                        <div class="project-card-req">
                            <i class="fas fa-coins"></i>
                            <div>
                                <span>Min hold</span>
                                <strong><?php echo esc($format_clean_amount($project_min_hold)); ?> <?php echo esc($project_token_symbol); ?></strong>
                            </div>
                        </div>
                        <div class="project-card-req">
                            <i class="fas fa-clock"></i>
                            <div>
                                <span>Duration</span>
                                <strong><?php echo (int)$project_hold_days; ?> day<?php echo $project_hold_days != 1 ? 's' : ''; ?></strong>
                            </div>
                        </div>
                        <div class="project-card-req">
                            <i class="fas fa-gift"></i>
                            <div>
                                <span>Reward</span>
                                <strong>Up to <?php echo esc(number_format($project_max_reward, 0)); ?> $REX</strong>
                </div>
            </div>
        </div>
                    <div class="project-card-req">
                        <i class="fas fa-shield-halved"></i>
                        <div>
                            <span>Limit</span>
                            <strong>One review per user</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="reviewForm" novalidate>
            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo esc($page_csrf_token); ?>">
            <div class="sr-honeypot" aria-hidden="true">
                <input type="text" name="website" id="reviewWebsite" tabindex="-1" autocomplete="off" placeholder="Leave blank">
            </div>

            <div class="wizard-progress wizard-progress-upgraded">
                <div class="wizard-track"><div class="wizard-fill" id="wizardFill"></div></div>
                <div class="wizard-step-nav active" data-nav-step="1"><span>1</span><strong>Method</strong><small>Choose Submission method</small></div>
                <div class="wizard-step-nav" data-nav-step="2"><span>2</span><strong>Wallet</strong><small>Verify Your Wallet</small></div>
                <div class="wizard-step-nav" data-nav-step="3"><span>3</span><strong>Review</strong><small>Put You Opinion</small></div>
                <div class="wizard-step-nav" data-nav-step="4"><span>4</span><strong>Submit</strong><small>Review & Confirm</small></div>
            </div>

            <div class="submit-card submit-card-upgraded" id="submitCard">
                <div class="eligibility-loading-overlay" id="eligibilityLoadingOverlay" aria-live="polite" aria-busy="false" hidden>
                    <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <p id="eligibilityLoadingTitle">Checking eligibility...</p>
                    <small id="eligibilityLoadingHint">This may take a few seconds while we reach the Explorer API.</small>
                </div>
                <section class="wizard-step active" data-step="1">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 1</span>
                            <h3><i class="fas fa-route"></i> Choose Verification Method</h3>
                            <p class="section-note">Pick the method that fits how you hold the project token.</p>
                        </div>
                        <aside class="step-tip-card" id="stepTipCard">
                            <strong id="stepTipTitle"><i class="fas fa-lightbulb"></i> Recommended</strong>
                            <p id="stepTipText">Instant is fastest if you hold the token.</p>
                        </aside>
                    </div>

                    <input type="hidden" name="wallet_type" value="non_custodial">

                    <div class="proof-path-summary" role="radiogroup" aria-label="Verification method">
                        <label class="proof-path-card" data-method-card="instant" data-method-icon="fa-bolt" data-method-tip-title="Instant is fastest" data-method-tip="Checks on-chain holding from the last 30 days — done in seconds if you already hold the token.">
                            <input type="radio" name="proof_method" value="instant" <?php echo $form['proof_method'] === 'instant' ? 'checked' : ''; ?>>
                            <span class="proof-path-check"><i class="fas fa-check"></i></span>
                            <span class="proof-path-icon"><i class="fas fa-bolt"></i></span>
                            <span class="proof-path-copy">
                                <strong>Instant Verification</strong>
                                <small class="method-recommended">Recommended</small>
                                <p>Checks on-chain holding from the last 30 days. Fastest if you already hold the token.</p>
                                <span class="proof-path-eta"><i class="fas fa-stopwatch"></i>≈ 10 seconds</span>
                            </span>
                        </label>
                        <label class="proof-path-card" data-method-card="live" data-method-icon="fa-video" data-method-tip-title="Live needs ongoing balance" data-method-tip="Receive the project token, pair the wallet, then keep the balance until the countdown completes.">
                            <input type="radio" name="proof_method" value="live" <?php echo $form['proof_method'] === 'live' ? 'checked' : ''; ?>>
                            <span class="proof-path-check"><i class="fas fa-check"></i></span>
                            <span class="proof-path-icon"><i class="fas fa-video"></i></span>
                            <span class="proof-path-copy">
                                <strong>Live Verification</strong>
                                <small>Forward monitoring</small>
                                <p>Pair wallet, receive token, then maintain balance until the countdown completes.</p>
                                <span class="proof-path-eta"><i class="fas fa-stopwatch"></i>≈ 10 minutes monitoring</span>
                            </span>
                        </label>
                        <label class="proof-path-card" data-method-card="manual" data-method-icon="fa-keyboard" data-method-tip-title="Manual proof + patience" data-method-tip="Submit wallet address, TX hash, and a screenshot; moderation reviews in 24–48 hours.">
                            <input type="radio" name="proof_method" value="manual" <?php echo $form['proof_method'] === 'manual' ? 'checked' : ''; ?>>
                            <span class="proof-path-check"><i class="fas fa-check"></i></span>
                            <span class="proof-path-icon"><i class="fas fa-keyboard"></i></span>
                            <span class="proof-path-copy">
                                <strong>Manual Verification</strong>
                                <small>Fallback</small>
                                <p>Submit TX hash and screenshot. Moderate review in 24-48 hours.</p>
                                <span class="proof-path-eta"><i class="fas fa-stopwatch"></i>24–48 hours review</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="wizard-step" data-step="2">
                    <div class="step-intro step-intro-tight">
                        <div>
                            <span class="step-kicker">Step 2</span>
                            <h3><i class="fas fa-shield-alt"></i> Verify & Check Your Wallet</h3>
                        </div>
                    </div>

                    <div class="wallet-explain connected-proof-actions eligibility-compact-panel" id="connectedProofPanel">
                        <div class="linked-wallet-panel" id="linkedWalletPanel">
                            <div>
                                <span>Linked Wallet</span>
                                <strong id="linkedWalletText"><?php echo !empty($user['wallet_address']) ? esc(strtolower((string) $user['wallet_address'])) : 'No wallet linked yet'; ?></strong>
                            </div>
                            <div class="linked-wallet-types" aria-label="Wallet connection types">
                                <span class="wallet-type-badge wallet-type-rexlink"><i class="fas fa-link"></i> RexLink Wallet</span>
                                <span class="wallet-type-badge wallet-type-external"><i class="fas fa-wallet"></i> External Wallet</span>
                            </div>
                        </div>
                        <input type="hidden" name="wallet_address" id="wallet_address" value="<?php echo esc($form['wallet_address']); ?>">
                        <div class="wallet-proof-grid" id="walletProofGrid" aria-label="Connected wallet ownership options">
                            <button type="button" class="wallet-proof-card wallet-proof-select" id="btnSelectRexLinkWallet">
                                <div class="wallet-proof-card-head">
                                    <i class="fas fa-link"></i>
                                    <div><strong>RexLink Wallet</strong></div>
                                </div>
                            </button>
                            <button type="button" class="wallet-proof-card wallet-proof-select" id="btnSelectExternalWallet">
                                <div class="wallet-proof-card-head">
                                    <i class="fas fa-wallet"></i>
                                    <div><strong>External Wallet</strong></div>
                                </div>
                            </button>
                        </div>
                        <p class="wallet-verified-line" id="eligibilityWalletText" hidden></p>
                        <div class="wallet-session-actions" id="walletSessionActions" hidden>
                            <button type="button" class="btn-submit eligibility-check-btn" id="btnCheckEligibility"><i class="fas fa-shield-check"></i> <span>Start Verification</span></button>
                            <button type="button" class="btn-cancel wallet-disconnect-btn" id="btnDisconnectWallet"><i class="fas fa-link-slash"></i> Disconnect</button>
                        </div>
                        <div class="eligibility-inline-skeleton" id="eligibilitySkeleton" aria-hidden="true"><span></span><span></span><span></span></div>
                        <div class="eligibility-inline-alert" id="eligibilityInlineAlert" role="status" aria-live="polite" hidden></div>
                        <div class="verification-report" id="verificationReport" hidden>
                            <div class="verification-report-head"><strong id="verificationReportTitle">Verification Report</strong><span id="verificationReportStatus"></span></div>
                            <p id="verificationReportReason"></p>
                            <div class="verification-report-grid" id="verificationReportDetails"></div>
                            <div class="verification-report-actions" id="verificationReportActions" hidden>
                                <label><input type="checkbox" name="verification_confirmed" value="1" id="verificationConfirmed"> I have reviewed and understood this verification report.</label>
                                <div id="verificationReportNextAction"></div>
                            </div>
                        </div>
                        <div class="live-progress-wrap" id="liveProgressWrap" hidden>
                            <div class="live-progress-bar" aria-hidden="true"><div class="live-progress-fill" id="liveProgressFill"></div></div>
                            <div class="live-progress-meta"><span id="liveProgressElapsed">—</span><span id="liveProgressChecked">—</span></div>
                        </div>
                    </div>

                    <div class="proof-grid step2-proof-details" id="step2ProofDetails">
                        <input type="hidden" name="holding_amount" id="holdingAmount" value="<?php echo esc($form['holding_amount'] !== '' ? $form['holding_amount'] : (string) ($project['min_holding_amount'] ?? '1')); ?>">
                        <input type="hidden" name="holding_days" id="holdingDays" value="<?php echo esc($form['holding_days'] !== '' ? $form['holding_days'] : (string) ($project['required_holding_days'] ?? '1')); ?>">
                        <div class="manual-moderation-note manual-proof-field step2-proof-note">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Manual proof stays pending until moderation checks the TX hash and screenshot. Never upload seed phrases, private keys, or recovery phrases.</span>
                        </div>
                        <div class="form-group manual-proof-field">
                            <label>Manual Wallet Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="manual_wallet_address" id="manual_wallet_address" value="<?php echo $form['proof_method'] === 'manual' ? esc($form['wallet_address']) : ''; ?>" placeholder="Paste wallet address: 0x..." autocomplete="off">
                        </div>
                        <div class="form-group manual-proof-field">
                            <label>Transaction Hash <span class="manual-required required-asterisk">*</span></label>
                            <input type="text" name="tx_hash" id="tx_hash" value="<?php echo esc($form['tx_hash']); ?>" placeholder="Paste the TX hash that shows activity with this project">
                        </div>
                        <div class="form-group full-width manual-proof-field">
                            <label>Screenshot Proof <span class="field-note field-note-block" id="screenshotRequirementText">Optional after connected wallet eligibility passes</span></label>
                            <div class="file-upload-area" id="fileUploadArea"><i class="fas fa-cloud-upload-alt"></i><p id="screenshotUploadText">Upload optional screenshot</p><small>Never upload seed phrases or private keys.</small><input type="file" name="screenshot" accept="image/*" hidden id="screenshotInput"></div>
                            <div id="filePreview" class="file-preview" style="display:none;"><i class="fas fa-image"></i><span id="fileName"></span><button type="button" id="removeFile">x</button></div>
                        </div>
                    </div>
                </section>

                <section class="wizard-step" data-step="3">
                    <div class="step-intro">
                        <div>
                            <span class="step-kicker">Step 3</span>
                            <h3><i class="fas fa-star"></i> Write Your Review</h3>
                            <p class="section-note">Keep it real and specific.</p>
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
                    <div class="review-prompt-chips" aria-label="Review writing prompts">
                        <button type="button" data-review-prompt="I used this project for ">Used for</button>
                        <button type="button" data-review-prompt="What felt trustworthy was ">Trust</button>
                        <button type="button" data-review-prompt="The confusing part was ">Confusing</button>
                        <button type="button" data-review-prompt="Other users should watch out for ">Warning</button>
                        <button type="button" data-review-prompt="I would use it again because ">Would use again</button>
                    </div>
                    <div class="form-group">
                        <label>Review Content <span class="required-asterisk">*</span> <span class="char-count" id="charCount">0/150</span></label>
                        <textarea name="review_content" id="review_content" rows="6" placeholder="What did you use, what happened, and what should others know?"><?php echo esc($form['review_content']); ?></textarea>
                        <div class="review-quality-row">
                            <span id="reviewQualityLevel">Too short</span>
                            <span id="reviewQualityHint">Original, specific reviews approve faster.</span>
                        </div>
                    </div>
                    <div class="terms-checkbox-wrapper">
                        <label class="terms-checkbox-label">
                            <input type="checkbox" name="terms_accepted" value="1" id="termsCheckbox" <?php echo $form['terms_accepted'] === '1' ? 'checked' : ''; ?>>
                            <span class="terms-checkbox-custom"></span>
                            <span class="terms-checkbox-text">I agree to the <a href="#" id="showTermsModalLink" class="terms-link">Review Submission Terms & Conditions</a></span>
                        </label>
                    </div>

                    <div class="submit-hint" id="reviewSubmitHint">
                        Connected wallet reviews unlock after asset detection completes. Manual proof submits to pending moderation.
                    </div>
                </section>

                <div class="wizard-actions" id="wizardActions">
                    <button type="button" class="btn-cancel" id="btnBack">Back</button>
                    <button type="button" class="btn-submit" id="btnNext">Next</button>
                    <button type="submit" name="submit_review" class="btn-submit" id="btnSubmit" style="display:none;"><i class="fas fa-paper-plane"></i> Submit Review</button>
                </div>
            </div>
        </form>

        <section class="review-trust-card review-trust-card-upgraded review-trust-card-bottom">
            <div class="review-trust-head">
                <span class="review-trust-kicker">Quick beginner tips</span>
                <h2>Quick reminders</h2>
                <p>Short tips so you can fill the form faster.</p>
            </div>
            <div class="review-trust-grid">
                <article class="review-trust-item">
                    <div class="review-trust-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="review-trust-copy">
                        <strong>Use real proof</strong>
                        <p>Connect the wallet that holds project assets, or paste the address with TX hash and screenshot.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon"><i class="fas fa-eye-slash"></i></div>
                    <div class="review-trust-copy">
                        <strong>Protect your security</strong>
                        <p>Never upload seed phrases or private keys.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon"><i class="fas fa-camera"></i></div>
                    <div class="review-trust-copy">
                        <strong>Screenshot proof</strong>
                        <p>Optional for connected wallet, required when you paste an address manually.</p>
                    </div>
                </article>
                <article class="review-trust-item">
                    <div class="review-trust-icon"><i class="fas fa-ban"></i></div>
                    <div class="review-trust-copy">
                        <strong>One project, one review</strong>
                        <p>Only one review is allowed per project.</p>
                    </div>
                </article>
            </div>
        </section>

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
                <div class="term-item"><i class="fas fa-check-circle"></i><div><strong>Proof Required</strong><p>Use connected wallet eligibility, or submit manual proof with wallet address, TX hash, and screenshot.</p></div></div>
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

<div class="rexlink-modal" id="reviewRexLinkModal" role="dialog" aria-modal="true" aria-labelledby="reviewRexLinkModalTitle" hidden>
    <div class="rexlink-backdrop" id="reviewRexLinkBackdrop"></div>
    <div class="rexlink-dialog">
        <div class="rexlink-head">
            <div>
                <span class="rexlink-tag" id="reviewWalletModalTag"><i class="fas fa-link"></i> Wallet Proof</span>
                <h3 id="reviewRexLinkModalTitle">Prove Wallet Access</h3>
            </div>
            <button type="button" class="rexlink-close" id="reviewRexLinkClose" aria-label="Close RexLink pairing">&times;</button>
        </div>
        <div class="rexlink-progress" aria-label="RexLink pairing progress">
            <span id="reviewRexLinkProgressScan" class="is-active">1. Scan QR</span>
            <span id="reviewRexLinkProgressSuccess">2. Connected</span>
        </div>
        <div class="review-wallet-question" id="reviewWalletQuestion">
            <h4 id="reviewWalletQuestionTitle">Do you still have access to this wallet?</h4>
            <p id="reviewWalletQuestionCopy">Choose how you want to continue.</p>
            <div class="review-wallet-address-highlight" id="reviewWalletQuestionAddress" hidden></div>
            <div class="review-wallet-question-actions">
                <button type="button" class="btn-submit" id="reviewWalletYesBtn"><i class="fas fa-check"></i> Yes, Prove Now</button>
                <button type="button" class="btn-cancel" id="reviewWalletNoBtn"><i class="fas fa-rotate"></i> No, Replace Now</button>
            </div>
        </div>
        <div class="rexlink-body" id="reviewRexLinkPairingBody" hidden>
            <section class="rexlink-step is-active" id="reviewRexLinkQrStep">
                <div class="rexlink-link-grid">
                    <div class="rexlink-copy">
                        <div class="rexlink-link-title">
                            <h4>Pair RexLink with CoinRex</h4>
                            <div class="rexlink-countdown" id="reviewRexLinkCountdown">Waiting for code</div>
                        </div>
                        <p>Open RexLink, scan this QR, or enter the 6 digit code. CoinRex will verify and link this wallet if it is not already used by another account.</p>
                        <ul class="rexlink-guide">
                            <li><i class="fas fa-mobile-screen-button"></i><span>Open RexLink app.</span></li>
                            <li><i class="fas fa-qrcode"></i><span>Scan QR or enter this 6 digit code.</span></li>
                        </ul>
                        <div class="rexlink-link-actions">
                            <button type="button" class="rexlink-primary" id="reviewRexLinkRefresh">Generate New QR</button>
                        </div>
                        <p class="rexlink-status" id="reviewRexLinkStatus">Ready to create RexLink pairing.</p>
                    </div>
                    <div class="rexlink-qr-card">
                        <div class="rexlink-qr-stage">
                            <div class="rexlink-qr-placeholder" id="reviewRexLinkQrPlaceholder"></div>
                            <img id="reviewRexLinkQrImage" alt="RexLink pairing QR" hidden>
                            <span class="rexlink-qr-logo-badge" id="reviewRexLinkQrLogoBadge" aria-hidden="true">
                                <img src="<?php echo ASSETS_URL; ?>/images/rexlink-logo.png" alt="">
                            </span>
                        </div>
                        <div class="rexlink-code-row">
                            <strong class="rexlink-code" id="reviewRexLinkPairingCode">No code yet</strong>
                            <button type="button" class="rexlink-copy-button" id="reviewRexLinkCopyCode" aria-label="Copy RexLink code" disabled><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                </div>
            </section>
            <section class="rexlink-step" id="reviewRexLinkSuccessStep">
                <div class="rexlink-success">
                    <div>
                        <div class="rexlink-success-icon"><i class="fas fa-check"></i></div>
                        <h4>Wallet connected</h4>
                        <p id="reviewRexLinkSuccessMessage">RexLink wallet verified for eligibility.</p>
                        <p class="rexlink-session-note">You can now run the eligibility check.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="<?php echo ASSETS_URL; ?>/js/qrcode-browser.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/qrcode-browser.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-pairing.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-pairing.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-sdk.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-sdk.js'); ?>"></script>
<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    const duration = type === 'error' || type === 'warning' ? 9000 : 6500;
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, duration);
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
    let currentAccountWallet = <?php echo json_encode(strtolower((string) ($user['wallet_address'] ?? ''))); ?>;
    const eligibilityProjectId = <?php echo (int) ($project['id'] ?? 0); ?>;
    const rexLinkReviewNetworkSlugs = <?php echo json_encode($rexlink_review_network_slugs, JSON_UNESCAPED_SLASHES); ?>;
    const rexSignerApiBaseUrl = window.location.origin + <?php echo json_encode(BASE_URI); ?>;
    const eligibilityNonceUrl = rexSignerApiBaseUrl + '/api/review-eligibility/wallet_nonce.php';
    const eligibilityVerifyUrl = rexSignerApiBaseUrl + '/api/review-eligibility/verify_wallet.php';
    const eligibilityCheckUrl = rexSignerApiBaseUrl + '/api/review-eligibility/check.php';
    const eligibilityStatusUrl = rexSignerApiBaseUrl + '/api/review-eligibility/status.php';
    const eligibilityInstantUrl = rexSignerApiBaseUrl + '/api/review-eligibility/instant.php';
    const rexSignerWebActorToken = <?php echo json_encode(coinrexReviewNodeActorToken((int) ($user['id'] ?? 0))); ?>;
    const rexSignerCreatePairingUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/api/review-eligibility/create_rexlink_pairing.php';
    const rexSignerPairingQrUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/api/rex-signer/pairing_qr.php';
    const rexSignerRevokeSessionUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/api/rex-signer/revoke_session.php';
    const rexSignerRealtimeAuthUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/api/rex-signer/realtime_auth.php';
    const rexSignerRealtimeWsUrl = <?php echo json_encode(preg_replace('/^http/i', 'ws', rtrim((defined('REXLINK_NODE_API_BASE_URL') ? REXLINK_NODE_API_BASE_URL : (defined('REXLINK_API_BASE_URL') ? REXLINK_API_BASE_URL : BASE_URL)), '/')) . '/ws'); ?>;
    const rexLinkWalletUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/api/review-eligibility/rexlink_wallet.php';
    const linkWalletUrl = rexSignerApiBaseUrl.replace(/\/+$/, '') + '/public/link-wallet.php';
    const rexSignerPublicBaseUrl = <?php echo json_encode(defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL); ?>;
    const rexPairing = window.CoinRexPairing || {};
    const RexLink = window.RexLink;
    if (RexLink && typeof RexLink.init === 'function') {
        RexLink.init({
            apiBaseUrl: <?php echo json_encode(REXLINK_NODE_API_BASE_URL); ?>,
            appId: 'coinrex',
            transport: 'auto',
            webActorToken: rexSignerWebActorToken || '',
            requestTimeoutMs: 2600,
        });
    }
    let eligibilityOk = false;
    let walletOwnershipVerified = false;
    let eligibilityStatusTimer = null;
    let eligibilityCountdownTimer = null;
    let eligibilityDeadlineMs = 0;
    let lastEligibilityStatus = '';

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
        updateFinalReviewSummary();
    }

    function maskWallet(value) {
        value = String(value || '').trim();
        if (value.length <= 14) return value || 'Not linked';
        return value.slice(0, 6) + '...' + value.slice(-4);
    }

    function updateFinalReviewSummary() {
        const finalRating = document.getElementById('finalRatingSummary');
        const finalProof = document.getElementById('finalProofSummary');
        const finalWallet = document.getElementById('finalWalletSummary');
        const finalReward = document.getElementById('finalRewardSummary');
        if (finalRating) finalRating.textContent = currentRating > 0 ? currentRating.toFixed(1) + '/5' : '-';
        if (finalProof) finalProof.textContent = getProofMethod() === 'manual' ? 'Manual' : 'Connected';
        const walletValue = syncStep2WalletAddress ? syncStep2WalletAddress() : (document.getElementById('wallet_address')?.value || '');
        if (finalWallet) finalWallet.textContent = maskWallet(walletValue);
        if (finalReward) finalReward.textContent = document.getElementById('rewardPreview')?.textContent || '0';
    }

    function validateStep(step) {
        if (step === 1) {
            if (!getProofMethod()) {
                showToast('Choose a verification method to continue.', 'error');
                return false;
            }
        }

        if (step === 2) {
            const method = getProofMethod();
            const wa = method === 'manual'
                ? (document.getElementById('manual_wallet_address')?.value.trim().toLowerCase() || '')
                : (walletAddressInput?.value.trim().toLowerCase() || '');
            const txHash = document.getElementById('tx_hash')?.value.trim() || '';
            const screenshotFile = document.getElementById('screenshotInput')?.files?.length || 0;
            if (!wa || !/^0x[a-fA-F0-9]{40}$/.test(wa)) {
                showToast(method === 'manual' ? 'Manual verification needs a valid wallet address.' : 'Step 2 incomplete: add wallet proof.', 'error');
                return false;
            }
            if (method === 'live' && !walletOwnershipVerified) {
                showToast('Step 2 incomplete: start a wallet pairing session first.', 'error');
                return false;
            }
            if (method === 'live' && !eligibilityOk) {
                showToast('Live verification is not complete. Keep the required token balance until the countdown reaches zero.', 'warning');
                return false;
            }
            if (['instant', 'live'].includes(method) && !document.getElementById('verificationConfirmed')?.checked) {
                showToast('Please review and confirm the verification report first.', 'warning');
                return false;
            }
            if (method === 'instant' && !eligibilityOk) {
                showToast('Instant eligibility check is not complete. Press "Check Eligibility" first.', 'warning');
                return false;
            }
            if (method === 'manual' && (!txHash || !screenshotFile)) {
                showToast('Manual verification needs TX hash and screenshot.', 'error');
                return false;
            }
        }

        if (step === 3) {
            const title = document.getElementById('review_title').value.trim();
            const content = document.getElementById('review_content').value.trim();
            if (!title || content.length < 150 || currentRating < 0.5) {
                showToast('Step 3 incomplete: title, rating, and 150+ chars required.', 'error');
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
                manual_wallet_address: document.getElementById('manual_wallet_address')?.value || '',
                proof_method: getProofMethod(),
                eligibility_ok: eligibilityOk ? '1' : '0',
                wallet_ownership_verified: walletOwnershipVerified ? '1' : '0',
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
            setValue('manual_wallet_address', payload.manual_wallet_address || '');
            eligibilityOk = false;
            walletOwnershipVerified = false;
            if (payload.proof_method) {
                const methodRadio = document.querySelector('input[name="proof_method"][value="' + payload.proof_method + '"]');
                if (methodRadio) methodRadio.checked = true;
            }
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
            // Always start at step 1 on page load. Restoring currentStep from a draft
            // causes step 2 validation errors to appear while the user is still on step 1.
            currentStep = 1;
        } catch (e) {}
    }

    btnNext?.addEventListener('click', () => {
        // Use the visible step, not the currentStep variable, to avoid
        // running step 2 validation while the user is still on step 1.
        const visibleStep = Number(document.querySelector('.wizard-step.active')?.dataset.step || 1);
        if (!validateStep(visibleStep)) return;
        saveDraft();
        showStep(visibleStep + 1);
    });

    btnBack?.addEventListener('click', () => {
        const visibleStep = Number(document.querySelector('.wizard-step.active')?.dataset.step || 1);
        saveDraft();
        showStep(visibleStep - 1);
    });

        document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
        // Use the visible step to determine if we're on the final step.
        const visibleStep = Number(document.querySelector('.wizard-step.active')?.dataset.step || 1);
        if (visibleStep !== totalSteps) {
            e.preventDefault();
            return;
        }
        syncStep2WalletAddress();
        if (!validateStep(1) || !validateStep(2) || !validateStep(3) || !validateStep(4)) {
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
            updateFinalReviewSummary();
            saveDraft();
        });
    });

    const reviewContent = document.getElementById('review_content');
    const charCount = document.getElementById('charCount');
    const reviewQualityLevel = document.getElementById('reviewQualityLevel');
    const reviewQualityHint = document.getElementById('reviewQualityHint');
    function updateReviewQuality() {
        const len = reviewContent?.value.length || 0;
        if (charCount) {
            charCount.textContent = `${len}/150`;
            charCount.classList.toggle('is-ready', len >= 150);
        }
        if (reviewQualityLevel) {
            let label = 'Too short';
            if (len >= 320) label = 'Detailed';
            else if (len >= 150) label = 'Good';
            reviewQualityLevel.textContent = label;
            reviewQualityLevel.classList.toggle('is-ready', len >= 150);
        }
        if (reviewQualityHint) {
            reviewQualityHint.textContent = len >= 150 ? 'Specific details help moderation.' : 'Add real usage details.';
        }
    }
    reviewContent?.addEventListener('input', function() {
        updateReviewQuality();
        updateRewardPreview();
        updateFinalReviewSummary();
        saveDraft();
    });
    document.querySelectorAll('[data-review-prompt]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!reviewContent) return;
            const prompt = button.getAttribute('data-review-prompt') || '';
            const spacer = reviewContent.value.trim() === '' ? '' : '\n';
            reviewContent.value += spacer + prompt;
            reviewContent.focus();
            updateReviewQuality();
            updateRewardPreview();
            saveDraft();
        });
    });

    const holdingAmount = document.getElementById('holdingAmount');
    const holdingDays = document.getElementById('holdingDays');
    const walletTypeEls = document.querySelectorAll('input[name="wallet_type"]');
    const walletAddressInput = document.getElementById('wallet_address');
    const manualWalletAddressInput = document.getElementById('manual_wallet_address');
    const eligibilityWalletText = document.getElementById('eligibilityWalletText');
    const linkedWalletPanel = document.getElementById('linkedWalletPanel');
    const linkedWalletText = document.getElementById('linkedWalletText');
    const walletProofGrid = document.getElementById('walletProofGrid');
    const btnSelectRexLinkWallet = document.getElementById('btnSelectRexLinkWallet');
    const btnSelectExternalWallet = document.getElementById('btnSelectExternalWallet');
    const btnCheckEligibility = document.getElementById('btnCheckEligibility');
    const walletSessionActions = document.getElementById('walletSessionActions');
    const btnDisconnectWallet = document.getElementById('btnDisconnectWallet');
    const proofMethodEls = document.querySelectorAll('input[name="proof_method"]');
    const connectedProofActions = document.querySelector('.connected-proof-actions');
    const proofModeChip = document.getElementById('proofModeChip');
    const screenshotRequirementText = document.getElementById('screenshotRequirementText');
    const screenshotUploadText = document.getElementById('screenshotUploadText');
    const rexModal = document.getElementById('reviewRexLinkModal');
    const rexBackdrop = document.getElementById('reviewRexLinkBackdrop');
    const rexClose = document.getElementById('reviewRexLinkClose');
    const rexModalTag = document.getElementById('reviewWalletModalTag');
    const rexModalTitle = document.getElementById('reviewRexLinkModalTitle');
    const walletQuestion = document.getElementById('reviewWalletQuestion');
    const walletQuestionCopy = document.getElementById('reviewWalletQuestionCopy');
    const walletQuestionAddress = document.getElementById('reviewWalletQuestionAddress');
    const walletYesBtn = document.getElementById('reviewWalletYesBtn');
    const walletNoBtn = document.getElementById('reviewWalletNoBtn');
    const rexPairingBody = document.getElementById('reviewRexLinkPairingBody');
    const rexQrStep = document.getElementById('reviewRexLinkQrStep');
    const rexSuccessStep = document.getElementById('reviewRexLinkSuccessStep');
    const rexProgressScan = document.getElementById('reviewRexLinkProgressScan');
    const rexProgressSuccess = document.getElementById('reviewRexLinkProgressSuccess');
    const rexSuccessMessage = document.getElementById('reviewRexLinkSuccessMessage');
    const rexRefresh = document.getElementById('reviewRexLinkRefresh');
    const rexStatus = document.getElementById('reviewRexLinkStatus');
    const rexCountdown = document.getElementById('reviewRexLinkCountdown');
    const rexPairingCode = document.getElementById('reviewRexLinkPairingCode');
    const rexCopyCode = document.getElementById('reviewRexLinkCopyCode');
    const rexQrPlaceholder = document.getElementById('reviewRexLinkQrPlaceholder');
    const rexQrImage = document.getElementById('reviewRexLinkQrImage');
    const rexQrLogoBadge = document.getElementById('reviewRexLinkQrLogoBadge');
    let rexPairingId = 0;
    let rexPollTimer = null;
    let rexPollGeneration = 0;
    let rexWatcherStartTimer = null;
    let rexCountdownTimer = null;
    let rexRealtimeSocket = null;
    let rexRealtimePingTimer = null;
    let rexRealtimeConnected = false;
    let rexPairingBusy = false;
    let rexConfirmBusy = false;
    let rexConfirmQueuedPayload = null;
    let rexSessionRestorePromise = null;
    let rexVerificationComplete = false;
    let rexPollFailureCount = 0;
    let rexRestoreTimer = null;
    let activeRexSessionId = 0;
    let walletProofMode = 'rexlink';
    let walletProofAction = 'prove';

    function getProofMethod() {
        const checked = document.querySelector('input[name="proof_method"]:checked');
        return checked ? checked.value : 'instant';
    }

    function normalizeAddress(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '');
    }

    function getStep2WalletAddress() {
        const manualValue = normalizeAddress(manualWalletAddressInput?.value || '');
        const connectedValue = normalizeAddress(walletAddressInput?.value || '');
        return getProofMethod() === 'manual' ? manualValue : connectedValue;
    }

    function syncStep2WalletAddress() {
        const method = getProofMethod();
        const value = method === 'manual'
            ? normalizeAddress(manualWalletAddressInput?.value || '')
            : normalizeAddress(walletAddressInput?.value || '');
        if (walletAddressInput) {
            walletAddressInput.value = value;
        }
        return value;
    }

    function setEligibilityStatus(message, type = '') {
        let card = document.getElementById('eligibilityMonitorCard');
        if (!card && walletSessionActions) {
            card = document.createElement('div');
            card.id = 'eligibilityMonitorCard';
            card.className = 'eligibility-monitor-card';
            card.innerHTML = '<div class=\'eligibility-monitor-head\'><strong id=\'eligibilityMonitorTitle\'>Holding verification</strong><span id=\'eligibilityMonitorCountdown\'></span></div><p id=\'eligibilityMonitorReason\'></p><small id=\'eligibilityMonitorMeta\'></small>';
            walletSessionActions.insertAdjacentElement('afterend', card);
        }
        if (!card) return;
        card.hidden = false;
        card.classList.toggle('is-success', type === 'success');
        card.classList.toggle('is-error', type === 'error');
        card.classList.toggle('is-warning', type === 'warning');
        const reason = document.getElementById('eligibilityMonitorReason');
        if (reason) reason.textContent = message || '';
    }

    function renderVerificationReport(payload, method) {
        const report = document.getElementById('verificationReport');
        const statusNode = document.getElementById('verificationReportStatus');
        const reasonNode = document.getElementById('verificationReportReason');
        const detailsNode = document.getElementById('verificationReportDetails');
        const actions = document.getElementById('verificationReportActions');
        const nextAction = document.getElementById('verificationReportNextAction');
        if (!report) return;
        if (!payload) { report.hidden = true; return; }
        const status = String(payload.status || 'not_eligible');
        const eligible = status === 'eligible';
        const labels = { wallet_address: 'Wallet', token_symbol: 'Token', chain_id: 'Network', required_balance: 'Required balance', current_balance: 'Detected balance', required_days: 'Required duration', holding_days: 'Detected duration', checked_at: 'Checked at', expires_at: 'Valid until' };
        const values = Object.assign({}, payload, { chain_id: payload.chain_id || payload.matched_chain_id });
        const items = Object.keys(labels).filter((key) => values[key] !== null && values[key] !== undefined && values[key] !== '').map((key) => '<div><span>' + labels[key] + '</span><strong>' + String(values[key]) + (key.endsWith('_days') ? ' day(s)' : '') + '</strong></div>').join('');
        report.hidden = false;
        report.classList.toggle('is-success', eligible);
        report.classList.toggle('is-error', !eligible);
        if (statusNode) statusNode.textContent = eligible ? 'Eligible' : (status === 'blocked' ? 'Unable to verify' : 'Not eligible');
        if (reasonNode) reasonNode.textContent = payload.reason || payload.message || 'The verification requirements were not met.';
        if (detailsNode) detailsNode.innerHTML = items;
        if (actions) actions.hidden = !eligible;
        const confirmed = document.getElementById('verificationConfirmed');
        if (confirmed && !eligible) confirmed.checked = false;
        if (nextAction) {
            const methods = (payload.suggested_methods || []).map((m) => m === 'live' ? 'Live Verification' : 'Manual Verification');
            nextAction.textContent = eligible ? 'You can now continue writing your review.' : (methods.length ? 'Next step: try ' + methods.join(' or ') + '.' : 'Next step: check the wallet, then try again.');
        }
    }

    function formatEligibilityCountdown(totalSeconds) {
        const seconds = Math.max(0, Number(totalSeconds || 0));
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        if (days > 0) return days + 'd ' + hours + 'h ' + minutes + 'm';
        if (hours > 0) return hours + 'h ' + minutes + 'm ' + secs + 's';
        return minutes + 'm ' + secs + 's';
    }

    function renderEligibilityMonitoring(payload, notifyChange = false) {
        const status = String(payload?.status || 'not_started');
        const reason = String(payload?.reason || payload?.message || 'Receive the project token, then start verification.');
        const type = status === 'eligible' ? 'success' : (status === 'disqualified' || status === 'expired' ? 'error' : (status === 'provider_delayed' ? 'warning' : ''));
        setEligibilityStatus(reason, type);
        eligibilityOk = status === 'eligible';
        renderVerificationReport(payload, 'live');
        eligibilityDeadlineMs = status === 'active' ? Date.now() + (Number(payload?.remaining_seconds || 0) * 1000) : 0;
        const title = document.getElementById('eligibilityMonitorTitle');
        const countdown = document.getElementById('eligibilityMonitorCountdown');
        const meta = document.getElementById('eligibilityMonitorMeta');
        if (title) title.textContent = status === 'eligible' ? 'Eligible to review' : (status === 'disqualified' ? 'Verification stopped' : (status === 'provider_delayed' ? 'Monitoring delayed' : 'Holding verification'));
        if (countdown) countdown.textContent = status === 'active' ? formatEligibilityCountdown(payload?.remaining_seconds) : (status === 'eligible' ? 'Complete' : '');
        if (meta) {
            const balance = payload?.current_balance && payload?.token_symbol ? payload.current_balance + ' ' + payload.token_symbol : '';
            const checked = payload?.last_checked_at ? 'Last checked ' + payload.last_checked_at : '';
            meta.textContent = [balance, checked].filter(Boolean).join(' · ');
        }
        if (btnCheckEligibility) {
            btnCheckEligibility.hidden = status === 'eligible';
            btnCheckEligibility.disabled = status === 'active' || status === 'provider_delayed';
            btnCheckEligibility.innerHTML = status === 'disqualified' || status === 'expired'
                ? '<i class=\'fas fa-rotate\'></i> <span>Restart Verification</span>'
                : '<i class=\'fas fa-shield-check\'></i> <span>Start Verification</span>';
        }
        if (eligibilityCountdownTimer) window.clearInterval(eligibilityCountdownTimer);
        if (status === 'active') {
            eligibilityCountdownTimer = window.setInterval(() => {
                const left = Math.max(0, Math.ceil((eligibilityDeadlineMs - Date.now()) / 1000));
                const node = document.getElementById('eligibilityMonitorCountdown');
                if (node) node.textContent = formatEligibilityCountdown(left);
                if (left <= 0) pollEligibilityStatus(true);
            }, 1000);
        }
        if (notifyChange && lastEligibilityStatus && lastEligibilityStatus !== status) {
            showToast(reason, status === 'eligible' ? 'success' : (status === 'provider_delayed' ? 'warning' : 'error'));
        }
        lastEligibilityStatus = status;
        saveDraft();
    }

    async function pollEligibilityStatus(notifyChange = false) {
        const wallet = walletAddressInput?.value.trim().toLowerCase() || '';
        if (!walletOwnershipVerified || !/^0x[a-f0-9]{40}$/.test(wallet)) return;
        try {
            const response = await fetch(eligibilityStatusUrl + '?project_id=' + encodeURIComponent(eligibilityProjectId) + '&wallet_address=' + encodeURIComponent(wallet), { credentials: 'include', cache: 'no-store' });
            const payload = await response.json();
            if (response.ok && payload?.success) renderEligibilityMonitoring(payload, notifyChange);
        } catch (error) {
            setEligibilityStatus('Monitoring status could not be refreshed. Your timer is still maintained by CoinRex.', 'warning');
        }
    }

    function startEligibilityStatusPolling() {
        if (eligibilityStatusTimer) window.clearInterval(eligibilityStatusTimer);
        pollEligibilityStatus(false);
        eligibilityStatusTimer = window.setInterval(() => pollEligibilityStatus(true), 30000);
    }

    function maskAddress(address) {
        const value = String(address || '');
        return value.length > 22 ? value.slice(0, 14) + '....' + value.slice(-4) : value;
    }

    function syncLinkedWallet(address) {
        currentAccountWallet = String(address || '').toLowerCase();
        if (linkedWalletText) {
            linkedWalletText.textContent = currentAccountWallet || 'No wallet linked yet';
        }
    }

    function renderWalletSessionState() {
        const method = getProofMethod();
        const value = walletAddressInput?.value.trim().toLowerCase() || '';
        const hasPairedSession = method !== 'manual' && walletOwnershipVerified && /^0x[a-f0-9]{40}$/.test(value);
        if (linkedWalletPanel) linkedWalletPanel.hidden = method === 'manual' || hasPairedSession;
        if (walletProofGrid) walletProofGrid.hidden = method === 'manual' || hasPairedSession;
        if (eligibilityWalletText) {
            eligibilityWalletText.hidden = !hasPairedSession;
            eligibilityWalletText.textContent = hasPairedSession ? 'Wallet Paired "' + maskAddress(value) + '"' : '';
        }
        if (walletSessionActions) walletSessionActions.hidden = !hasPairedSession;
        if (btnCheckEligibility) {
            btnCheckEligibility.disabled = !hasPairedSession;
            btnCheckEligibility.hidden = method === 'manual';
        }
    }

    function setWalletAddress(address, alreadyEligible = false, ownershipVerified = false) {
        const value = String(address || '').toLowerCase();
        if (walletAddressInput) walletAddressInput.value = value;
        eligibilityOk = alreadyEligible;
        walletOwnershipVerified = ownershipVerified;
        renderWalletSessionState();
        if (ownershipVerified) startEligibilityStatusPolling();
        saveDraft();
    }

    function clearReviewWalletSession() {
        walletOwnershipVerified = false;
        eligibilityOk = false;
        activeRexSessionId = 0;
        if (eligibilityStatusTimer) window.clearInterval(eligibilityStatusTimer);
        if (eligibilityCountdownTimer) window.clearInterval(eligibilityCountdownTimer);
        if (walletAddressInput) walletAddressInput.value = currentAccountWallet || '';
        renderWalletSessionState();
        saveDraft();
    }

    function syncProofMethodUI() {
        const method = getProofMethod();
        updateMethodTip();
        if (method === 'manual') {
            renderVerificationReport(null, method);
            const confirmed = document.getElementById('verificationConfirmed');
            if (confirmed) confirmed.checked = false;
        }
        const isConnected = method === 'instant' || method === 'live';
        document.body.classList.toggle('review-proof-connected', isConnected);
        document.body.classList.toggle('review-proof-manual', method === 'manual');
        if (connectedProofActions) connectedProofActions.style.display = isConnected ? 'grid' : 'none';
        if (proofModeChip) proofModeChip.textContent = isConnected ? 'Connected wallet' : 'Manual proof';
        if (screenshotRequirementText) screenshotRequirementText.textContent = method === 'manual'
            ? 'Required for manual address proof'
            : 'Optional after connected wallet eligibility passes';
        if (screenshotUploadText) screenshotUploadText.textContent = method === 'manual'
            ? 'Upload required screenshot'
            : 'Upload optional screenshot';
        if (method === 'manual') {
            eligibilityOk = false;
            walletOwnershipVerified = false;
            if (walletAddressInput) {
                walletAddressInput.readOnly = false;
                walletAddressInput.value = manualWalletAddressInput?.value.trim().toLowerCase() || '';
            }
            if (btnCheckEligibility) {
                btnCheckEligibility.hidden = true;
                btnCheckEligibility.disabled = true;
            }
        } else {
            if (walletAddressInput) walletAddressInput.readOnly = true;
            if (walletAddressInput && !walletAddressInput.value && currentAccountWallet) {
                walletAddressInput.value = currentAccountWallet;
            }
            if (btnCheckEligibility) {
                btnCheckEligibility.hidden = false;
                btnCheckEligibility.disabled = false;
            }
        }
        renderWalletSessionState();
        saveDraft();
    }

    function updateMethodTip() {
        const tipCard = document.getElementById('stepTipCard');
        const tipTitle = document.getElementById('stepTipTitle');
        const tipText = document.getElementById('stepTipText');
        const card = document.querySelector('.proof-path-card:has(input[name="proof_method"]:checked)');
        if (!tipCard || !card) return;
        const method = card.getAttribute('data-method-card') || '';
        tipCard.classList.remove('is-instant', 'is-live', 'is-manual');
        if (method) tipCard.classList.add('is-' + method);
        if (tipTitle) tipTitle.innerHTML = '<i class="fas ' + (card.getAttribute('data-method-icon') || 'fa-lightbulb') + '"></i> ' + (card.getAttribute('data-method-tip-title') || 'Recommended');
        if (tipText) tipText.textContent = card.getAttribute('data-method-tip') || '';
    }

    async function postJson(url, payload, options = {}) {
        let response = null;
        const timeoutMs = Math.max(0, Number(options.timeoutMs || 0));
        const controller = timeoutMs > 0 && 'AbortController' in window ? new AbortController() : null;
        const timeoutId = controller ? window.setTimeout(() => controller.abort(), timeoutMs) : null;
        const headers = { 'Content-Type': 'application/json' };
        if (rexSignerWebActorToken && String(url || '').replace(/\/+$/, '').indexOf(rexSignerApiBaseUrl.replace(/\/+$/, '')) === 0) {
            headers['X-CoinRex-Web-Actor'] = rexSignerWebActorToken;
        }
        try {
            response = await fetch(url, {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                signal: controller ? controller.signal : undefined,
                headers,
                body: JSON.stringify(payload || {})
            });
        } catch (error) {
            throw new Error(error && error.name === 'AbortError'
                ? (options.timeoutMessage || 'RexLink check timed out. Please try again.')
                : (options.networkMessage || 'Network request failed. Please check your connection and try again.'));
        } finally {
            if (timeoutId) window.clearTimeout(timeoutId);
        }
        const text = await response.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error('Unexpected server response. Please refresh and try again.');
        }
        if (!response.ok && data && data.message) {
            throw new Error(data.message);
        }
        return data || {};
    }

    function rexSetStatus(message, type = '') {
        if (!rexStatus) return;
        rexStatus.textContent = message;
        rexStatus.classList.toggle('is-error', type === 'error');
        rexStatus.classList.toggle('is-success', type === 'success');
    }

    function setEligibilityChecking(isChecking) {
        if (!btnCheckEligibility) return;
        btnCheckEligibility.disabled = Boolean(isChecking);
        btnCheckEligibility.classList.toggle('is-loading', Boolean(isChecking));
        btnCheckEligibility.innerHTML = isChecking
            ? '<i class="fas fa-spinner fa-spin"></i> <span>Checking...</span>'
            : '<i class="fas fa-shield-check"></i> <span>Check Eligibility</span>';
    }

    function rexSetStep(step) {
        if (rexQrStep) rexQrStep.classList.toggle('is-active', step === 'qr');
        if (rexSuccessStep) rexSuccessStep.classList.toggle('is-active', step === 'success');
        if (rexProgressScan) {
            rexProgressScan.classList.toggle('is-active', step === 'qr');
            rexProgressScan.classList.toggle('is-complete', step === 'success');
        }
        if (rexProgressSuccess) {
            rexProgressSuccess.classList.toggle('is-active', step === 'success');
        }
    }

    function stopRexPolling() {
        rexPollGeneration += 1;
        if (rexWatcherStartTimer) {
            window.clearTimeout(rexWatcherStartTimer);
            rexWatcherStartTimer = null;
        }
        if (rexPollTimer) {
            window.clearInterval(rexPollTimer);
            rexPollTimer = null;
        }
    }

    function stopRexRealtime() {
        if (rexRealtimePingTimer) {
            window.clearInterval(rexRealtimePingTimer);
            rexRealtimePingTimer = null;
        }
        if (rexRealtimeSocket) {
            try { rexRealtimeSocket.close(); } catch (error) {}
            rexRealtimeSocket = null;
        }
        rexRealtimeConnected = false;
    }

    function stopRexCountdown() {
        if (rexCountdownTimer) {
            window.clearInterval(rexCountdownTimer);
            rexCountdownTimer = null;
        }
    }

    function stopRexRestoreTimer() {
        if (rexRestoreTimer) {
            window.clearTimeout(rexRestoreTimer);
            rexRestoreTimer = null;
        }
    }

    function resetRexQrState() {
        rexVerificationComplete = false;
        rexConfirmBusy = false;
        rexPollFailureCount = 0;
        rexSetStep('qr');
        if (rexPairingCode) rexPairingCode.textContent = 'Creating code...';
        if (rexCopyCode) {
            rexCopyCode.disabled = true;
            rexCopyCode.innerHTML = '<i class="fas fa-copy"></i>';
        }
        if (rexRefresh) rexRefresh.textContent = 'Generate New QR';
        if (rexQrPlaceholder) {
            rexQrPlaceholder.hidden = false;
            rexQrPlaceholder.classList.remove('is-rendered');
            rexQrPlaceholder.innerHTML = '';
        }
        if (rexQrImage) {
            rexQrImage.hidden = true;
            rexQrImage.onload = null;
            rexQrImage.onerror = null;
            rexQrImage.removeAttribute('src');
        }
        if (rexQrLogoBadge) rexQrLogoBadge.classList.remove('is-visible');
    }

    function renderRexQr(qrPayload) {
        if (!rexQrPlaceholder || !qrPayload) return Promise.resolve(false);
        const normalizedRexApiBaseUrl = String(qrPayload.api_base_url || qrPayload.base_url || rexSignerApiBaseUrl || '').replace(/\/+$/, '');
        const payloadDefaults = {
            purpose: 'review_eligibility',
            apiBaseUrl: normalizedRexApiBaseUrl,
            baseUrl: normalizedRexApiBaseUrl,
            dappName: 'CoinRex Review',
            dappUrl: rexSignerPublicBaseUrl,
            networkSlug: 'polygon',
            chainId: 137,
            durationMinutes: 10,
        };

        if (rexPairing.renderQr) {
            return rexPairing.renderQr(qrPayload, {
                placeholder: rexQrPlaceholder,
                image: rexQrImage,
                logoBadge: rexQrLogoBadge,
                fallbackUrl: rexSignerPairingQrUrl,
                fallbackText: 'Use the 6 digit code below.',
                // SVG is generated locally in milliseconds and avoids the
                // blank-canvas issue seen in some Chromium wallet browsers.
                preferCanvas: false,
                slowRenderMs: 250,
                beforeSlowRender: (placeholder) => {
                    placeholder.innerHTML = '<span>QR is taking longer than expected. You can enter the code below.</span>';
                },
                qrOptions: {
                    width: 232,
                    margin: 1,
                    errorCorrectionLevel: 'L',
                    maskPattern: 0,
                },
                payloadDefaults,
            });
        }
        rexQrPlaceholder.hidden = false;
        rexQrPlaceholder.classList.remove('is-rendered');
        rexQrPlaceholder.innerHTML = '<span>Use the 6 digit code below.</span>';
        if (rexQrImage) {
            rexQrImage.hidden = true;
            rexQrImage.removeAttribute('src');
        }
        if (rexQrLogoBadge) rexQrLogoBadge.classList.remove('is-visible');
        return Promise.resolve(false);
    }

    function startRexPairingWatchers() {
        rexWatcherStartTimer = null;
        if (!rexPairingId || !rexModal || rexModal.hidden) return;
        stopRexPolling();
        const generation = rexPollGeneration;
        if (RexLink && typeof RexLink.pollPairingStatus === 'function') {
            RexLink.pollPairingStatus(rexPairingId, {
                interval: 300,
                timeout: 300000,
                shouldContinue: function() {
                    return generation === rexPollGeneration && Boolean(rexPairingId && rexModal && !rexModal.hidden) && !rexVerificationComplete;
                },
            }).then(function(data) {
                if (generation !== rexPollGeneration || rexVerificationComplete) return;
                const sessionId = Number(data.session_id || (data.session && (data.session.id || data.session.session_id)) || 0);
                return confirmRexLinkWallet({ pairing_id: rexPairingId, session_id: sessionId }).then(function(connected) {
                    // Pairing completion and the same-origin PHP session update
                    // can land a fraction apart. Keep checking until CoinRex has
                    // consumed the exact completed pairing and updated the modal.
                    if (!connected) startRexConfirmationPolling(generation);
                });
            }).catch(function(error) {
                if (generation !== rexPollGeneration || /watch cancelled/i.test(error.message || '')) return;
                startRexConfirmationPolling(generation);
            });
            window.setTimeout(() => connectRexRealtime().catch(() => {}), 0);
            return;
        }
        rexPollTimer = window.setInterval(pollRexLinkPairing, 500);
        window.setTimeout(pollRexLinkPairing, 0);
        window.setTimeout(() => connectRexRealtime().catch(() => {}), 0);
    }

    function startRexCountdown(seconds, expiresAtUnix = 0) {
        const startedAtMs = Date.now();
        const ttlSeconds = Math.min(300, Math.max(0, Number(seconds || 300)));
        const localDeadline = startedAtMs + ttlSeconds * 1000;
        const suppliedDeadline = Number(expiresAtUnix || 0) * 1000;
        const expiresAtMs = suppliedDeadline > startedAtMs
            ? Math.min(suppliedDeadline, localDeadline)
            : localDeadline;
        stopRexCountdown();
        const tick = () => {
            const remaining = Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000));
            const minutes = Math.floor(remaining / 60);
            const secs = String(remaining % 60).padStart(2, '0');
            if (rexCountdown) rexCountdown.textContent = remaining > 0 ? 'QR expires in ' + minutes + 'm ' + secs + 's' : 'QR expired';
            if (remaining <= 0) {
                stopRexPolling();
                stopRexCountdown();
                rexSetStatus('This RexLink QR expired. Generate a new QR.', 'error');
                return;
            }
        };
        tick();
        rexCountdownTimer = window.setInterval(tick, 1000);
    }

    async function confirmRexLinkWallet(payload = {}) {
        if (rexVerificationComplete) return true;
        if (rexConfirmBusy) {
            rexConfirmQueuedPayload = Object.assign({}, rexConfirmQueuedPayload || {}, payload || {});
            return false;
        }
        rexConfirmBusy = true;
        try {
            const silent = Boolean(payload.silent);
            const advanceToCheck = Boolean(payload.advance_to_check);
            const requestPayload = Object.assign({}, payload);
            delete requestPayload.silent;
            delete requestPayload.advance_to_check;
            const result = await postJson(rexLinkWalletUrl, Object.assign({
                project_id: eligibilityProjectId,
            }, requestPayload), { timeoutMs: 3500 });
            if (!result.success) throw new Error(result.message || 'Could not verify RexLink wallet.');
                const status = String(result.status || '');
                if (status === 'connected') {
                    rexPollFailureCount = 0;
                    activeRexSessionId = Number(result.session_id || requestPayload.session_id || activeRexSessionId || 0);
                    rexVerificationComplete = true;
                    stopRexPolling();
                stopRexCountdown();
                syncLinkedWallet(result.wallet_address || '');
                setWalletAddress(result.wallet_address || '', false, true);
                syncProofMethodUI();
                rexSetStatus('RexLink wallet verified. You can run eligibility check.', 'success');
                if (rexSuccessMessage) {
                    rexSuccessMessage.textContent = 'Wallet Linked "' + maskAddress(result.wallet_address || '') + '".';
                }
                rexSetStep('success');
                connectRexRealtime().catch(() => {});
                if (advanceToCheck && currentStep < 2) {
                    showStep(2);
                }
                if (!silent) showToast('RexLink wallet verified.', 'success');
                if (!silent && rexModal) {
                    window.setTimeout(closeRexModal, 450);
                }
                return true;
            }
            if (status === 'change_wallet') {
                stopRexPolling();
                stopRexCountdown();
                if (!silent) {
                    showToast(result.message || 'Please change your linked wallet.', 'error');
                }
                window.setTimeout(function() {
                    window.location.href = result.change_wallet_url || linkWalletUrl;
                }, 1200);
                return false;
            }
            if (['expired', 'revoked'].includes(status)) {
                const currentPairingId = Number(requestPayload.pairing_id || 0);
                if (currentPairingId > 0 && currentPairingId === Number(rexPairingId || 0)) {
                    stopRexPolling();
                    stopRexCountdown();
                    rexSetStatus(status === 'expired' ? 'This QR expired. Generate a new QR.' : 'This pairing was cancelled. Generate a new QR.', 'error');
                } else if (!silent) {
                    rexSetStatus('Waiting for RexLink pairing.');
                }
            } else if (status === 'none') {
                rexPollFailureCount += 1;
                if (!silent) {
                    rexSetStatus(rexPollFailureCount > 2
                        ? 'Still waiting for RexLink. Checking session sync...'
                        : 'Waiting for RexLink pairing.');
                }
            } else {
                rexPollFailureCount = 0;
                rexSetStatus(result.message || 'Waiting for RexLink pairing.');
            }
            return false;
        } finally {
            rexConfirmBusy = false;
            if (rexConfirmQueuedPayload && !rexVerificationComplete) {
                const nextPayload = rexConfirmQueuedPayload;
                rexConfirmQueuedPayload = null;
                window.setTimeout(() => {
                    confirmRexLinkWallet(nextPayload).catch(() => {});
                }, 0);
            }
        }
    }

    function pollRexLinkPairing() {
        if (!rexPairingId || rexConfirmBusy) return;
        confirmRexLinkWallet({ pairing_id: rexPairingId }).catch((error) => {
            const message = String(error && error.message ? error.message : '');
            const transient = /network|fetch|slow|timeout|temporar|unexpected server|unreachable|failed to fetch|load failed|session sync/i.test(message);
            const duplicateWallet = /already have used to review the same project|already been used for a review/i.test(message);
            if (transient) {
                rexPollFailureCount += 1;
                rexSetStatus(rexPollFailureCount > 2 ? 'Connection is slow. Still checking RexLink...' : 'Waiting for RexLink pairing.');
                return;
            }
            if (duplicateWallet) {
                stopRexPolling();
                stopRexCountdown();
                walletProofAction = 'replace';
                rexPairingId = 0;
                clearReviewWalletSession();
                if (rexPairingCode) rexPairingCode.textContent = 'Switch wallet';
                if (rexCopyCode) rexCopyCode.disabled = true;
                if (rexRefresh) rexRefresh.textContent = 'Generate Fresh QR';
                rexSetStatus(message || 'Could not verify RexLink wallet.', 'error');
                return;
            }
            rexPollFailureCount += 1;
            rexSetStatus(message ? message + ' Still checking until QR expiry.' : 'Could not verify yet. Still checking until QR expiry.', 'error');
        });
    }

    async function restoreActiveRexLinkSession(options = {}) {
        if (getProofMethod() === 'manual' || walletOwnershipVerified) return;
        try {
            await confirmRexLinkWallet({
                silent: true,
                advance_to_check: Boolean(options.advance_to_check),
            });
        } catch (error) {
            // A missing review-scoped session should leave the page in the normal pairing state.
        }
    }

    async function useActiveRexLinkSessionBeforePairing(options = {}) {
        if (getProofMethod() === 'manual' || walletOwnershipVerified) {
            return walletOwnershipVerified;
        }
        if (rexSessionRestorePromise) {
            return rexSessionRestorePromise;
        }

        const showStatus = options.show_status !== false;
        if (showStatus) {
            if (walletQuestion) walletQuestion.hidden = true;
            if (rexPairingBody) rexPairingBody.hidden = false;
            rexSetStep('qr');
            rexSetStatus('Checking existing RexLink session...');
            if (rexPairingCode) rexPairingCode.textContent = 'Checking session';
            if (rexQrPlaceholder) {
                rexQrPlaceholder.hidden = false;
                rexQrPlaceholder.classList.remove('is-rendered');
                rexQrPlaceholder.innerHTML = '<span>Checking existing RexLink session...</span>';
            }
        }

        rexSessionRestorePromise = (async function() {
            try {
                const sharedSession = window.CoinRexActiveRexLinkSession && typeof window.CoinRexActiveRexLinkSession === 'object'
                    ? window.CoinRexActiveRexLinkSession
                    : null;
                const sharedSessionId = sharedSession
                    && String(sharedSession.status || 'active').toLowerCase() === 'active'
                    && Number(sharedSession.remaining_seconds || 0) > 0
                    ? Number(sharedSession.id || sharedSession.session_id || 0)
                    : 0;
                const verificationPayload = {
                    silent: Boolean(options.silent),
                    advance_to_check: options.advance_to_check !== false,
                };
                if (sharedSessionId > 0) {
                    verificationPayload.session_id = sharedSessionId;
                }
                const connected = await confirmRexLinkWallet(verificationPayload);
                return Boolean(connected && walletOwnershipVerified && activeRexSessionId > 0);
            } catch (error) {
                return false;
            } finally {
                rexPollFailureCount = 0;
            }
        })();

        try {
            return await rexSessionRestorePromise;
        } finally {
            rexSessionRestorePromise = null;
        }
    }

    function realtimeUrlWithToken(wsUrl, token) {
        return String(wsUrl || '') + (String(wsUrl || '').includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token || '');
    }

    async function connectRexRealtime() {
        if (!('WebSocket' in window)) return false;
        if (rexRealtimeSocket && [WebSocket.CONNECTING, WebSocket.OPEN].includes(rexRealtimeSocket.readyState)) return true;
        try {
            const data = await postJson(rexSignerRealtimeAuthUrl, {});
            if (!data.success || !data.token) throw new Error(data.message || 'Realtime auth failed.');
            const wsUrl = data.ws_url || rexSignerRealtimeWsUrl;
            rexRealtimeSocket = new WebSocket(realtimeUrlWithToken(wsUrl, data.token));
            rexRealtimeSocket.addEventListener('open', () => {
                rexRealtimeConnected = true;
                if (!walletOwnershipVerified) {
                    rexSetStatus('Live pairing listener connected. Scan the QR now.');
                }
                window.clearInterval(rexRealtimePingTimer);
                rexRealtimePingTimer = window.setInterval(() => {
                    if (rexRealtimeSocket && rexRealtimeSocket.readyState === WebSocket.OPEN) {
                        rexRealtimeSocket.send(JSON.stringify({ type: 'ping' }));
                    }
                }, 25000);
            });
            rexRealtimeSocket.addEventListener('message', (message) => {
                let event = null;
                try { event = JSON.parse(message.data); } catch (error) {}
                if (!event || ['realtime.ready', 'pong'].includes(String(event.type || ''))) return;
                if (String(event.type || '') === 'session.connected') {
                    confirmRexLinkWallet({ pairing_id: rexPairingId }).catch(() => {});
                    return;
                }
                if (['session.revoked', 'session.expired'].includes(String(event.type || ''))) {
                    const eventSessionId = Number((event.payload && event.payload.session_id) || event.session_id || 0);
                    if (eventSessionId > 0 && activeRexSessionId > 0 && eventSessionId !== activeRexSessionId) return;
                    clearReviewWalletSession();
                    rexVerificationComplete = false;
                    rexSetStatus(String(event.type || '') === 'session.revoked'
                        ? 'RexLink session disconnected.'
                        : 'RexLink session expired. Pair again.', 'error');
                    showToast(String(event.type || '') === 'session.revoked'
                        ? 'RexLink disconnected.'
                        : 'RexLink session expired.', 'error');
                }
            });
            rexRealtimeSocket.addEventListener('close', () => {
                rexRealtimeConnected = false;
                window.clearInterval(rexRealtimePingTimer);
                rexRealtimePingTimer = null;
            });
            rexRealtimeSocket.addEventListener('error', () => {
                rexRealtimeConnected = false;
            });
            return true;
        } catch (error) {
            rexRealtimeConnected = false;
            return false;
        }
    }

    /**
     * Create the review-eligibility pairing code.
     * Fast path: node via RexLink SDK with the web-actor token (like the auth page).
     * Fallback: PHP endpoint when the SDK/token is unavailable or the node times out.
     */
    async function createRexLinkPairingCode() {
        const nodeTimeoutMs = 2600;
        const phpFallbackTimeoutMs = 3000;
        const phpBody = {
            purpose: 'review_eligibility',
            duration_minutes: 5,
            dapp_name: 'CoinRex Review Eligibility',
            dapp_url: rexSignerPublicBaseUrl,
            requested_wallet_address: '',
            force_new_pairing: true,
            network_slugs: rexLinkReviewNetworkSlugs,
        };
        if (RexLink && typeof RexLink.createPairing === 'function' && rexSignerWebActorToken) {
            try {
                const nodeData = await RexLink.createPairing({
                        purpose: 'review_eligibility',
                        durationMinutes: 5,
                        forceNewPairing: true,
                        timeoutMs: nodeTimeoutMs,
                        networkSlugs: rexLinkReviewNetworkSlugs,
                        meta: {
                            dapp_name: 'CoinRex Review Eligibility',
                            dapp_url: rexSignerPublicBaseUrl,
                        },
                    });
                if (nodeData && nodeData.success !== false && nodeData.pairing_id) {
                    return nodeData;
                }
            } catch (e) {
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('RexLink Node create failed; using same-origin fallback.', e);
                }
            }
            return postJson(rexSignerCreatePairingUrl, phpBody, {
                timeoutMs: phpFallbackTimeoutMs,
                timeoutMessage: 'RexLink could not start in time. Please try again.',
            });
        }
        return postJson(rexSignerCreatePairingUrl, phpBody, {
            timeoutMs: 2800,
            timeoutMessage: 'RexLink could not start in time. Please try again.',
        });
    }

    function startRexConfirmationPolling(generation) {
        if (
            generation !== rexPollGeneration
            || !rexPairingId
            || rexVerificationComplete
            || !rexModal
            || rexModal.hidden
        ) {
            return;
        }
        if (!rexPollTimer) {
            rexPollTimer = window.setInterval(function() {
                if (generation !== rexPollGeneration || rexVerificationComplete || !rexModal || rexModal.hidden) {
                    stopRexPolling();
                    return;
                }
                pollRexLinkPairing();
            }, 500);
        }
        pollRexLinkPairing();
    }

    async function createRexLinkPairing() {
        if (rexPairingBusy) return;
        const pairingStartedAt = (window.performance && performance.now) ? performance.now() : Date.now();
        stopRexRestoreTimer();
        rexPairingBusy = true;
        stopRexPolling();
        stopRexRealtime();
        stopRexCountdown();
        rexPairingId = 0;
        resetRexQrState();
        rexSetStatus('Creating RexLink pairing code...');
        try {
            const data = await createRexLinkPairingCode();
            const pairingApiMs = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - pairingStartedAt);
            if (!data.success) throw new Error(data.message || 'Could not create RexLink pairing.');
            walletProofAction = 'prove';
            if (data.already_connected && data.session) {
                activeRexSessionId = Number(data.session.id || data.session.session_id || 0);
                rexSetStatus('Active RexLink session found. Verifying wallet...');
                await confirmRexLinkWallet({ session_id: activeRexSessionId, advance_to_check: true });
                return;
            }
            rexPairingId = Number(data.pairing_id || 0);
            if (rexPairingCode) rexPairingCode.textContent = data.display_code || 'Code ready';
            if (rexCopyCode) rexCopyCode.disabled = !data.display_code;
            if (rexQrPlaceholder) {
                rexQrPlaceholder.hidden = false;
                rexQrPlaceholder.classList.remove('is-rendered');
                rexQrPlaceholder.innerHTML = '<span>Generating QR. If it is slow, enter the code below.</span>';
            }
            if (data.qr_payload) {
                const qrStartedAt = (window.performance && performance.now) ? performance.now() : Date.now();
                const qrPayload = Object.assign({
                    purpose: 'review_eligibility',
                    coinrex_purpose: 'review_eligibility',
                }, data.qr_payload || {});
                const qrRendered = await renderRexQr(qrPayload);
                const qrMs = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - qrStartedAt);
                if (!qrRendered) {
                    throw new Error('QR could not be displayed. Use the pairing code below or generate a new QR.');
                }
                if (window.console && console.info) {
                    console.info('[RexLink review pairing]', {
                        apiMs: pairingApiMs,
                        serverMs: data.server_timing_ms || null,
                        qrMs,
                        apiBaseUrl: qrPayload.api_base_url || '',
                    });
                }
            } else if (rexQrPlaceholder) {
                rexQrPlaceholder.innerHTML = '<span>Use the code below to pair.</span>';
            }
            startRexCountdown(data.expires_in_seconds || 300, data.expires_at_unix || (data.qr_payload && data.qr_payload.expires_at_unix) || 0);
            const qrApiBase = String((data.qr_payload && (data.qr_payload.api_base_url || data.qr_payload.base_url)) || rexSignerApiBaseUrl || '').replace(/\/+$/, '');
            rexSetStatus('Open RexLink and pair with this QR or code.');
            rexWatcherStartTimer = window.setTimeout(startRexPairingWatchers, 100);
        } catch (error) {
            rexSetStatus(error.message || 'RexLink pairing could not start.', 'error');
        } finally {
            rexPairingBusy = false;
        }
    }

    function openRexModal() {
        if (!rexModal) return;
        rexModal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeRexModal() {
        stopRexPolling();
        if (!walletOwnershipVerified) {
            stopRexRealtime();
        }
        stopRexCountdown();
        if (rexModal) rexModal.hidden = true;
        document.body.style.overflow = '';
    }

    async function openWalletProofModal(mode) {
        stopRexRestoreTimer();
        walletProofMode = mode === 'external' ? 'external' : 'rexlink';
        walletProofAction = 'prove';
        stopRexPolling();
        stopRexRealtime();
        stopRexCountdown();
        rexSetStep('qr');
        if (rexModalTag) {
            rexModalTag.innerHTML = walletProofMode === 'rexlink'
                ? '<i class="fas fa-link"></i> RexLink Wallet'
                : '<i class="fas fa-wallet"></i> External Wallet';
        }
        if (rexModalTitle) {
            rexModalTitle.textContent = walletProofMode === 'rexlink'
                ? 'RexLink Wallet Access'
                : 'External Wallet Access';
        }
        if (walletQuestionCopy) {
            walletQuestionCopy.textContent = 'Choose how you want to continue.';
        }
        if (walletQuestionAddress) {
            const linkedWallet = currentAccountWallet || '';
            walletQuestionAddress.hidden = !linkedWallet;
            walletQuestionAddress.textContent = linkedWallet ? 'Linked Wallet: ' + linkedWallet : '';
        }
        if (walletQuestion) walletQuestion.hidden = false;
        if (rexPairingBody) rexPairingBody.hidden = true;
        resetRexQrState();
        if (rexPairingCode) rexPairingCode.textContent = 'No code yet';
        openRexModal();
        if (walletProofMode === 'rexlink') {
            if (walletQuestion) walletQuestion.hidden = false;
            if (rexPairingBody) rexPairingBody.hidden = true;
            resetRexQrState();
            if (rexPairingCode) rexPairingCode.textContent = 'No code yet';
            rexSetStatus('Ready to create RexLink pairing.');
        }
    }

    async function continueWalletProof(action = 'prove') {
        if (action === 'replace') {
            closeRexModal();
            showToast('Change or reset your linked wallet to continue.', 'info');
            window.setTimeout(function() {
                window.location.href = linkWalletUrl;
            }, 900);
            return;
        }
        walletProofAction = action === 'replace' ? 'replace' : 'prove';
        if (walletProofMode === 'external') {
            closeRexModal();
            connectExternalWallet();
            return;
        }
        if (walletQuestion) walletQuestion.hidden = true;
        if (rexPairingBody) rexPairingBody.hidden = false;
        if (walletYesBtn) walletYesBtn.disabled = true;
        let reusedSession = false;
        try {
            reusedSession = await useActiveRexLinkSessionBeforePairing({
                silent: false,
                show_status: true,
                advance_to_check: true,
            });
        } finally {
            if (walletYesBtn) walletYesBtn.disabled = false;
        }
        if (reusedSession) {
            if (rexModal && !rexModal.hidden) {
                window.setTimeout(closeRexModal, 450);
            }
            return;
        }
        if (!rexModal || rexModal.hidden) {
            return;
        }
        createRexLinkPairing();
    }

    btnSelectRexLinkWallet?.addEventListener('click', () => openWalletProofModal('rexlink'));
    btnSelectExternalWallet?.addEventListener('click', () => openWalletProofModal('external'));
    walletYesBtn?.addEventListener('click', () => continueWalletProof('prove'));
    walletNoBtn?.addEventListener('click', () => continueWalletProof('replace'));
    rexRefresh?.addEventListener('click', createRexLinkPairing);
    [rexBackdrop, rexClose].forEach((el) => el?.addEventListener('click', closeRexModal));
    rexCopyCode?.addEventListener('click', function() {
        const code = rexPairingCode?.textContent.trim() || '';
        if (!code || code === 'No code yet' || code === 'Creating code...') return;
        if (rexPairing.copyText) {
            rexPairing.copyText(code, rexCopyCode, 1200);
            return;
        }
        rexCopyCode.innerHTML = '<i class="fas fa-check"></i>';
        window.setTimeout(() => { rexCopyCode.innerHTML = '<i class="fas fa-copy"></i>'; }, 1200);
    });

    async function connectExternalWallet() {
        try {
            if (!window.ethereum || !window.ethereum.request) {
                showToast('Browser wallet not found. Pair RexLink or install MetaMask.', 'error');
                return;
            }
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            const wallet = String(accounts && accounts[0] ? accounts[0] : '').toLowerCase();
            if (!/^0x[a-f0-9]{40}$/.test(wallet)) throw new Error('No valid wallet returned.');
            const nonce = await postJson(eligibilityNonceUrl, { wallet_address: wallet }, {
                timeoutMs: 8000,
                timeoutMessage: 'Wallet verification request timed out. Please try again.',
                networkMessage: 'Could not reach CoinRex to verify this wallet. Check your connection and try again.',
            });
            if (!nonce.success) throw new Error(nonce.message || 'Could not create wallet nonce.');
            const signature = await window.ethereum.request({ method: 'personal_sign', params: [nonce.message, wallet] });
            const verify = await postJson(eligibilityVerifyUrl, { wallet_address: wallet, signature, project_id: eligibilityProjectId }, {
                timeoutMs: 10000,
                timeoutMessage: 'Wallet signature verification took too long. Please try again.',
                networkMessage: 'Wallet signature could not be verified because CoinRex is unreachable.',
            });
            if (!verify.success) throw new Error(verify.message || 'Wallet verification failed.');
            syncLinkedWallet(verify.wallet_address || wallet);
            setWalletAddress(verify.wallet_address || wallet, false, true);
            syncProofMethodUI();
            showToast('External wallet verified.', 'success');
        } catch (error) {
            showToast(error.message || 'Wallet connection failed.', 'error');
        }
    }

    async function disconnectExternalWalletProvider() {
        if (!window.ethereum || !window.ethereum.request) return false;
        try {
            await window.ethereum.request({
                method: 'wallet_revokePermissions',
                params: [{ eth_accounts: {} }],
            });
            return true;
        } catch (error) {
            return false;
        }
    }

    async function refreshExternalWalletProviderState() {
        if (walletProofMode !== 'external' || !walletOwnershipVerified || !window.ethereum || !window.ethereum.request) return;
        try {
            const accounts = await window.ethereum.request({ method: 'eth_accounts' });
            const nextWallet = String(accounts && accounts[0] ? accounts[0] : '').toLowerCase();
            const currentWallet = walletAddressInput?.value.trim().toLowerCase() || '';
            if (!nextWallet || (currentWallet && nextWallet !== currentWallet)) {
                clearReviewWalletSession();
                showToast(nextWallet ? 'Wallet account changed. Verify the new wallet first.' : 'MetaMask disconnected from this site.', 'error');
            }
        } catch (error) {}
    }

    async function disconnectReviewWalletSession() {
        const sessionId = Number(activeRexSessionId || 0);
        const wasExternalWallet = walletProofMode === 'external';
        clearReviewWalletSession();
        window.dispatchEvent(new CustomEvent('rexlink:session-disconnected'));
        const providerRevoked = wasExternalWallet ? await disconnectExternalWalletProvider() : false;
        showToast(providerRevoked ? 'MetaMask permission revoked for this site.' : 'Wallet session disconnected on this website.', 'success');
        if (sessionId <= 0) return;
        try {
            const result = await postJson(rexSignerRevokeSessionUrl, {
                session_id: sessionId,
                reason: 'Revoked from review eligibility page',
            });
            if (!result.success) {
                showToast('Local session cleared. RexLink will refresh shortly.', 'error');
            }
        } catch (error) {
            showToast('Local session cleared. RexLink will refresh shortly.', 'error');
        }
    }

    btnCheckEligibility?.addEventListener('click', async function() {
        const method = getProofMethod();
        if (method === 'manual') {
            showToast('Manual mode does not run on-chain check. Add TX hash and screenshot instead.', 'error');
            return;
        }
        const wallet = walletAddressInput?.value.trim().toLowerCase() || '';
        if (!walletOwnershipVerified || !/^0x[a-f0-9]{40}$/.test(wallet)) {
            openWalletProofModal('rexlink');
            return;
        }
        setEligibilityChecking(true);
        try {
            if (method === 'instant') {
                const result = await postJson(eligibilityInstantUrl, { project_id: eligibilityProjectId, wallet_address: wallet }, {
                    timeoutMs: 15000,
                    timeoutMessage: 'Instant eligibility check timed out. Please try again.',
                    networkMessage: 'Could not reach CoinRex for the instant check. Check your connection.',
                });
                if (!result.success) throw new Error(result.message || 'Instant eligibility check failed.');
                const status = String(result.status || 'not_eligible');
                if (status === 'eligible') {
                    eligibilityOk = true;
                    setEligibilityStatus(result.reason || 'You are eligible to review this project.', 'success');
                    renderVerificationReport(result, 'instant');
                    showToast('✅ Instant verification passed. You can continue.', 'success');
                    if (btnCheckEligibility) btnCheckEligibility.hidden = true;
                } else {
                    eligibilityOk = false;
                    setEligibilityStatus(result.reason || 'Instant verification did not pass. Try Live or Manual.', 'error');
                    renderVerificationReport(result, 'instant');
                    const suggested = (result.suggested_methods || []).map((m) => m === 'live' ? 'Live Verification' : 'Manual Verification').join(' or ');
                    showToast('Instant verification did not pass. Try ' + (suggested || 'Live or Manual Verification') + ' instead.', 'warning');
                    showStep(1);
                }
            } else {
                const result = await postJson(eligibilityCheckUrl, { project_id: eligibilityProjectId, wallet_address: wallet });
                if (!result.success) throw new Error(result.message || 'Eligibility check failed.');
                renderEligibilityMonitoring(result, false);
                renderVerificationReport(result, 'live');
                const toastType = result.status === 'eligible' ? 'success' : (result.status === 'provider_delayed' ? 'warning' : 'success');
                showToast(result.reason || result.message || 'Holding verification started.', toastType);
                startEligibilityStatusPolling();
            }
        } catch (error) {
            eligibilityOk = false;
            showToast(error.message || 'Eligibility could not be verified. Recheck later.', 'error');
        } finally {
            setEligibilityChecking(false);
            if (getProofMethod() === 'live') pollEligibilityStatus(false);
        }
    });

    btnDisconnectWallet?.addEventListener('click', function() {
        disconnectReviewWalletSession();
    });

    if (window.ethereum && window.ethereum.on) {
        window.ethereum.on('accountsChanged', function(accounts) {
            const nextWallet = String(accounts && accounts[0] ? accounts[0] : '').toLowerCase();
            const currentWallet = walletAddressInput?.value.trim().toLowerCase() || '';
            if (!nextWallet || (currentWallet && nextWallet !== currentWallet)) {
                clearReviewWalletSession();
                eligibilityOk = false;
                showToast(nextWallet ? 'Wallet account changed. Verify the new wallet first.' : 'MetaMask disconnected from this site.', 'error');
            }
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshExternalWalletProviderState();
        }
        if (!document.hidden && rexPairingId && !rexVerificationComplete) {
            pollRexLinkPairing();
        }
    });

    window.addEventListener('focus', () => {
        refreshExternalWalletProviderState();
        if (rexPairingId && !rexVerificationComplete) {
            pollRexLinkPairing();
        }
    });

    // The shared SDK publishes this event on document. Use it as an immediate
    // modal refresh signal while HTTP polling remains the reliability fallback.
    document.addEventListener('rexlink:session-connected', function() {
        if (rexPairingId && !rexVerificationComplete && rexModal && !rexModal.hidden) {
            pollRexLinkPairing();
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
        updateFinalReviewSummary();
    }

    holdingAmount?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    holdingDays?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    reviewContent?.addEventListener('input', () => { updateRewardPreview(); saveDraft(); });
    walletTypeEls.forEach(el => el.addEventListener('change', () => { updateRewardPreview(); saveDraft(); }));
    proofMethodEls.forEach(el => el.addEventListener('change', syncProofMethodUI));
    walletAddressInput?.addEventListener('input', function() {
        eligibilityOk = false;
        walletOwnershipVerified = false;
        renderWalletSessionState();
        saveDraft();
    });

    manualWalletAddressInput?.addEventListener('input', function() {
        const value = this.value.trim().toLowerCase();
        if (getProofMethod() === 'manual' && walletAddressInput) {
            walletAddressInput.value = value;
        }
        eligibilityOk = false;
        walletOwnershipVerified = false;
        saveDraft();
    });

    document.querySelectorAll('.score-item input[type="range"]').forEach(slider => {
        slider.addEventListener('input', function() {
            if (this.nextElementSibling) this.nextElementSibling.value = this.value;
            saveDraft();
        });
    });

    document.querySelectorAll('#review_title, #tx_hash, #wallet_address, #manual_wallet_address, textarea[name="pros"], textarea[name="cons"], #holdingAmount, #holdingDays').forEach((el) => {
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
    syncProofMethodUI();
    if (getProofMethod() === 'manual') {
        if (manualWalletAddressInput?.value && walletAddressInput) {
            walletAddressInput.value = manualWalletAddressInput.value.trim().toLowerCase();
        }
    } else if (walletAddressInput?.value) {
        const restoredWallet = walletAddressInput.value.trim().toLowerCase();
        setWalletAddress(restoredWallet, false, false);
    } else if (currentAccountWallet) {
        setWalletAddress(currentAccountWallet, false, false);
    }
    syncProofMethodUI();
    paintStars(currentRating);
    renderRatingLabel(currentRating);
    if (reviewContent && charCount) {
        updateReviewQuality();
    }
    updateRewardPreview();
    updateFinalReviewSummary();

    // Auto-restore an existing RexLink session so users with an active session
    // bypass the wallet pairing step.
    if (currentAccountWallet && getProofMethod() !== 'manual') {
        useActiveRexLinkSessionBeforePairing({
            silent: true,
            show_status: false,
            advance_to_check: true,
        }).catch(function() {});
    }

    showStep(1);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
