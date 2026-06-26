<?php
/** Auto-split from legacy functions.php */

/**
 * Shuffles quiz choices and remaps the correct answer index.
 * Uses a deterministic seed so the shuffle is consistent within the same request
 * (both getTaskHubState and taskHubValidateSingleQuizAnswer produce the same order).
 * This prevents the correct answer from always being at index 0.
 */
function shuffleQuizChoices(array $quiz, string $seed = ''): array {
    $shuffled = [];
    foreach ($quiz as $q_idx => $question) {
        $choices = $question['choices'] ?? [];
        $correct_index = (int) ($question['answer'] ?? 0);
        $correct_text = $choices[$correct_index] ?? '';

        // Use deterministic shuffle based on seed + question index.
        // mt_srand seeds the Mersenne Twister which shuffle() uses internally.
        $keys = array_keys($choices);
        $seed_str = $seed . '_q' . $q_idx;
        mt_srand(crc32($seed_str));
        shuffle($keys);
        mt_srand();

        $new_choices = [];
        $new_correct_index = 0;
        foreach ($keys as $new_idx => $old_idx) {
            $new_choices[] = $choices[$old_idx];
            if ((int) $old_idx === $correct_index) {
                $new_correct_index = $new_idx;
            }
        }

        $shuffled[] = [
            'question' => $question['question'],
            'choices' => $new_choices,
            'answer' => $new_correct_index,
        ];
    }
    return $shuffled;
}

function getTaskHubMissionDefinitions() {
    static $definitions = null;

    if ($definitions !== null) {
        return $definitions;
    }

    $quiz_day_1 = [
        [
            'question' => 'What should you do before using CoinRex rewards?',
            'choices' => ['Read the platform terms', 'Skip the rules', 'Only check prices'],
            'answer' => 0,
        ],
        [
            'question' => 'Which action helps keep rewards fair?',
            'choices' => ['Using one verified account', 'Creating many accounts', 'Skipping verification'],
            'answer' => 0,
        ],
        [
            'question' => 'What powers CoinRex rewards?',
            'choices' => ['The ledger system', 'Manual browser edits', 'Local storage'],
            'answer' => 0,
        ],
    ];

    $quiz_day_2 = [
        [
            'question' => 'Which page explains what CoinRex is building?',
            'choices' => ['About page', 'Logout page', '404 page'],
            'answer' => 0,
        ],
        [
            'question' => 'What is the main user dashboard for?',
            'choices' => ['Tracking progress and rewards', 'Mining tokens', 'Deleting claims'],
            'answer' => 0,
        ],
        [
            'question' => 'Why explore the interface early?',
            'choices' => ['To understand review and reward flows', 'To bypass cooldowns', 'To unlock claims instantly'],
            'answer' => 0,
        ],
    ];

    $quiz_day_3 = [
        [
            'question' => 'What does the privacy policy mainly explain?',
            'choices' => ['How data is handled', 'How to skip login', 'How to mint NFTs'],
            'answer' => 0,
        ],
        [
            'question' => 'Which signals may be checked for abuse prevention?',
            'choices' => ['IP and user agent', 'Monitor brightness only', 'Browser tab color'],
            'answer' => 0,
        ],
        [
            'question' => 'Why should proof submissions be real?',
            'choices' => ['Because admins can review them', 'Because fake proofs earn more', 'Because proof is optional'],
            'answer' => 0,
        ],
    ];

    $quiz_day_4 = [
        [
            'question' => 'What does the roadmap help you understand?',
            'choices' => ['What unlocks over time', 'Your Wi-Fi password', 'Local PHP settings'],
            'answer' => 0,
        ],
        [
            'question' => 'How many questions are in this roadmap quiz?',
            'choices' => ['Five', 'One', 'Ten'],
            'answer' => 0,
        ],
        [
            'question' => 'What is BoostHub used for here?',
            'choices' => ['A rotating admin task assignment', 'A crypto wallet', 'A database backup'],
            'answer' => 0,
        ],
        [
            'question' => 'When can the next mission day unlock?',
            'choices' => ['After tasks are done and server reset passes', 'Immediately after Task 0', 'Only after claim'],
            'answer' => 0,
        ],
        [
            'question' => 'What happens if you miss a mission day window?',
            'choices' => ['Progress pauses', 'Everything resets to Day 1', 'You skip ahead'],
            'answer' => 0,
        ],
    ];

    $quiz_day_5 = [
        [
            'question' => 'What is DevHub mainly for?',
            'choices' => ['Developer-facing project activity', 'Claim approval only', 'Password resets'],
            'answer' => 0,
        ],
        [
            'question' => 'Does wallet onboarding require sending funds here?',
            'choices' => ['No', 'Yes', 'Only on Day 10'],
            'answer' => 0,
        ],
        [
            'question' => 'Why add a wallet address?',
            'choices' => ['To prepare for future reward operations', 'To bypass moderation', 'To create a referral'],
            'answer' => 0,
        ],
    ];

    $quiz_day_6 = [
        [
            'question' => 'What should a review include?',
            'choices' => ['Honest proof-backed detail', 'Only emojis', 'Nothing but a rating'],
            'answer' => 0,
        ],
        [
            'question' => 'Why learn the claim system before Pro?',
            'choices' => ['So users understand locked and unlocked rewards', 'So they can skip the queue', 'So they can mint tokens'],
            'answer' => 0,
        ],
        [
            'question' => 'Who controls reward availability?',
            'choices' => ['Backend rules and admin overrides', 'Frontend buttons only', 'Browser cache'],
            'answer' => 0,
        ],
    ];

    $quiz_day_7 = [
        [
            'question' => 'What score is required to pass Day 7?',
            'choices' => ['4 out of 5', '2 out of 5', '5 out of 10'],
            'answer' => 0,
        ],
        [
            'question' => 'What happens if you fail Day 7?',
            'choices' => ['You stay on Day 7 until you pass', 'You skip to Day 8', 'You unlock claims'],
            'answer' => 0,
        ],
        [
            'question' => 'Why does CoinRex use anti-farming checks?',
            'choices' => ['To protect reward quality', 'To slow the homepage', 'To hide balances'],
            'answer' => 0,
        ],
        [
            'question' => 'What should you avoid while earning?',
            'choices' => ['Rapid repeat submissions', 'Reading the guide', 'Using one account'],
            'answer' => 0,
        ],
        [
            'question' => 'When do claims unlock?',
            'choices' => ['After reaching Pro', 'On Day 1', 'Before onboarding starts'],
            'answer' => 0,
        ],
    ];

    $definitions = [
        ['day' => 1, 'step' => 0, 'task_key' => 'day1_checkin', 'title' => 'Daily Check-in', 'description' => 'Start the onboarding journey.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 1, 'task_key' => 'day1_profile_setup', 'title' => 'Profile Setup', 'description' => 'Open your profile page and save your profile basics.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'profile', 'learning_title' => 'Profile Page', 'learning_url' => BASE_URL . '/profile.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 2, 'task_key' => 'day1_social_follow', 'title' => 'Social Follow', 'description' => 'Submit a social follow proof for review.', 'reward' => 2, 'unlock_after_hours' => 2, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 3, 'task_key' => 'day1_terms_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the terms and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 5, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_1, 'learning_title' => 'Terms of Service', 'learning_url' => BASE_URL . '/terms.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 2, 'step' => 0, 'task_key' => 'day2_checkin', 'title' => 'Check-in', 'description' => 'Start Day 2.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 2, 'step' => 1, 'task_key' => 'day2_ui_exploration', 'title' => 'UI Exploration', 'description' => 'Explore the dashboard, reviews, and project areas.', 'reward' => 1, 'unlock_after_hours' => 3, 'verification_mode' => 'instant', 'learning_title' => 'Dashboard Overview', 'learning_url' => BASE_URL . '/dashboard.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 2, 'step' => 2, 'task_key' => 'day2_about_quiz', 'title' => 'Learn and Quiz', 'description' => 'Read the About page and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_2, 'learning_title' => 'About CoinRex', 'learning_url' => BASE_URL . '/about.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 3, 'step' => 0, 'task_key' => 'day3_checkin', 'title' => 'Check-in', 'description' => 'Start Day 3.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 3, 'step' => 1, 'task_key' => 'day3_share_experience', 'title' => 'Share Experience', 'description' => 'Share your experience on official social platforms and submit the public post URL.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => '', 'learning_url' => '', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 3, 'step' => 2, 'task_key' => 'day3_privacy_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the privacy policy and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_3, 'learning_title' => 'Privacy Policy', 'learning_url' => BASE_URL . '/privacy.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 4, 'step' => 0, 'task_key' => 'day4_checkin', 'title' => 'Check-in', 'description' => 'Start Day 4.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 4, 'step' => 1, 'task_key' => 'day4_roadmap_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the roadmap briefing and answer 5 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 5, 'quiz' => $quiz_day_4, 'learning_title' => 'Roadmap Briefing', 'learning_url' => BASE_URL . '/roadmap.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 5, 'step' => 0, 'task_key' => 'day5_checkin', 'title' => 'Check-in', 'description' => 'Start Day 5.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 5, 'step' => 1, 'task_key' => 'day5_devhub_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review DevHub and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_5, 'learning_title' => 'DevHub', 'learning_url' => BASE_URL . '/devhub/index.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 5, 'step' => 3, 'task_key' => 'day5_wallet_connect', 'title' => 'Wallet Add or Connect', 'description' => 'Add a wallet address without any real transaction.', 'reward' => 1, 'unlock_after_hours' => 6, 'verification_mode' => 'wallet', 'learning_title' => 'Profile Wallet Section', 'learning_url' => BASE_URL . '/profile.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 6, 'step' => 0, 'task_key' => 'day6_checkin', 'title' => 'Check-in', 'description' => 'Start Day 6.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 6, 'step' => 1, 'task_key' => 'day6_review_quiz', 'title' => 'Learn and Quiz', 'description' => 'Study the review guide and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_6, 'learning_title' => 'Review Guide', 'learning_url' => BASE_URL . '/submit-review.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 6, 'step' => 3, 'task_key' => 'day6_txhash_submit', 'title' => 'Transaction Proof (>=10 USDT)', 'description' => 'Submit one valid TX hash for a transaction worth at least 10 USDT for review.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Proof Submission Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 7, 'step' => 0, 'task_key' => 'day7_checkin', 'title' => 'Check-in', 'description' => 'Start Day 7.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 7, 'step' => 1, 'task_key' => 'day7_final_quiz', 'title' => 'Final Quiz', 'description' => 'Pass the quality gate with at least 4/5.', 'reward' => 2, 'unlock_after_hours' => 0, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 4, 'quiz' => $quiz_day_7, 'learning_title' => 'Quality Gate', 'learning_url' => '', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 7, 'step' => 3, 'task_key' => 'day7_volume_submit', 'title' => 'Volume Proof (>=100 USDT)', 'description' => 'Submit proof of cumulative 100+ USDT transaction volume completed within one day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Volume Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 8, 'step' => 0, 'task_key' => 'day8_checkin', 'title' => 'Check-in', 'description' => 'Start Day 8.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 8, 'step' => 2, 'task_key' => 'day8_hold_submit', 'title' => 'Hold Proof (>=10 USDT)', 'description' => 'Submit proof that you held tokens worth at least 10 USDT for one full day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Hold Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 9, 'step' => 0, 'task_key' => 'day9_checkin', 'title' => 'Check-in', 'description' => 'Start Day 9.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 9, 'step' => 2, 'task_key' => 'day9_hold_submit', 'title' => 'Hold Proof (>=10 USDT)', 'description' => 'Submit proof that you held tokens worth at least 10 USDT for one full day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Hold Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 10, 'step' => 0, 'task_key' => 'day10_checkin', 'title' => 'Check-in', 'description' => 'Start Day 10.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 10, 'step' => 2, 'task_key' => 'day10_mystery_box', 'title' => 'Mystery Box', 'description' => 'Claim the final reward box.', 'reward' => TASKHUB_MYSTERY_BOX_PERFECT_REWARD, 'unlock_after_hours' => 6, 'verification_mode' => 'mystery', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
    ];

    return $definitions;
}

function getTaskHubDayTitles() {
    return [
        1 => 'Welcome Day',
        2 => 'Explore Day',
        3 => 'Privacy Day',
        4 => 'Roadmap Day',
        5 => 'DevHub Day',
        6 => 'Review Day',
        7 => 'Filter Day',
        8 => 'Wallet Day',
        9 => 'Momentum Day',
        10 => 'Mystery Day',
    ];
}

function taskHubGetBoostRequirementByDay($mission_day) {
    $map = [
        4 => 2.0,
        5 => 2.0,
        6 => 2.0,
        7 => 2.0,
        8 => 3.0,
        9 => 3.0,
        10 => 3.0,
    ];
    return (float) ($map[(int) $mission_day] ?? 0.0);
}

function taskHubGetBoostGatewayTask($mission_day) {
    $required_reward = taskHubGetBoostRequirementByDay((int) $mission_day);
    if ($required_reward <= 0) {
        return null;
    }

    return [
        'id' => 0,
        'task_key' => 'day' . (int) $mission_day . '_boosthub_gateway',
        'mission_step' => 90,
        'title' => 'BoostHub Task',
        'description' => 'Open BoostHub and complete one task worth exactly ' . number_format((float) $required_reward, 0) . ' $REX.',
        'reward' => (float) $required_reward,
        'task_category' => 'custom',
        'task_link' => BASE_URL . '/boosthub.php',
        'completion_steps' => "1. Open BoostHub.\n2. Complete one task worth exactly " . number_format((float) $required_reward, 0) . " \$REX.\n3. Return to TaskHub.",
        'proof_notes' => 'This step auto-validates from completed BoostHub tasks.',
        'cta_label' => 'Open BoostHub',
        'verification_mode' => 'boosthub_redirect',
        'requires_quiz' => 0,
        'requires_manual_review' => 0,
    ];
}

function getTaskHubMissionTaskDefinitionByKey($task_key, PDO $db = null) {
    foreach (getTaskHubMissionDefinitions() as $definition) {
        if ((string) $definition['task_key'] === (string) $task_key) {
            // If this task requires a quiz, try loading from DB first.
            // DB questions override hardcoded quizzes when present.
            if (!empty($definition['requires_quiz'])) {
                $db_quiz = taskHubGetQuizByTaskKey((string) $task_key, $db);
                if (!empty($db_quiz)) {
                    $definition['quiz'] = $db_quiz;
                }
            }
            return $definition;
        }
    }

    return null;
}

function getTaskHubResetTimestamp($timestamp = null) {
    $timestamp = $timestamp !== null ? (int) $timestamp : time();
    return strtotime(date('Y-m-d', $timestamp) . ' ' . sprintf('%02d:00:00', TASKHUB_SERVER_RESET_HOUR));
}

function getTaskHubNextResetTimestamp($timestamp = null) {
    $base = getTaskHubResetTimestamp($timestamp);
    if (($timestamp !== null ? (int) $timestamp : time()) >= $base) {
        return strtotime('+1 day', $base);
    }

    return $base;
}

function getTaskHubCurrentPhase1Earnings($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND source = 'mini_task'
          AND reward_phase = 'phase1'
          AND status IN ('available', 'locked', 'claimed')
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function taskHubFormatDuration($seconds) {
    $seconds = max(0, (int) $seconds);
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($hours > 0 || $minutes > 0) {
        $parts[] = $minutes . 'm';
    }
    $parts[] = $remaining_seconds . 's';

    return implode(' ', $parts);
}

function getTaskHubRewardAmountForTask($user_id, array $task_row, array $log_row = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $reward = round((float) ($task_row['reward'] ?? 0), 8);
    $metadata = [];
    if (!empty($log_row['metadata'])) {
        $metadata = json_decode((string) $log_row['metadata'], true) ?: [];
    }

    if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_reward'])) {
        $reward = round((float) $metadata['boost_reward'], 8);
    }

    if (($task_row['verification_mode'] ?? '') === 'mystery') {
        $reward = taskHubHasMissedDays((int) $user_id, $db)
            ? (float) TASKHUB_MYSTERY_BOX_FALLBACK_REWARD
            : (float) TASKHUB_MYSTERY_BOX_PERFECT_REWARD;
    }

    $current_phase_earnings = getTaskHubCurrentPhase1Earnings((int) $user_id, $db);
    $remaining_cap = max(0, (float) TASKHUB_PHASE1_REWARD_CAP - $current_phase_earnings);
    return round(min($reward, $remaining_cap), 8);
}

/**
 * Loads quiz questions from the taskhub_quiz_questions DB table for a given task_key.
 * Returns the same [{question, choices, answer}] format as the hardcoded arrays.
 * Returns empty array if no DB rows found.
 */
function taskHubGetQuizByTaskKey(string $task_key, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT question, choices, answer
        FROM taskhub_quiz_questions
        WHERE task_key = ?
          AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$task_key]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        return [];
    }
    $quiz = [];
    foreach ($rows as $row) {
        $choices = json_decode((string) ($row['choices'] ?? '[]'), true);
        if (!is_array($choices) || empty($choices)) {
            continue;
        }
        $quiz[] = [
            'question' => (string) ($row['question'] ?? ''),
            'choices' => $choices,
            'answer' => (int) ($row['answer'] ?? 0),
        ];
    }
    return $quiz;
}

function getTaskHubTaskRows(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT *
        FROM mini_tasks
        WHERE task_group = 'mission'
          AND is_active = 1
        ORDER BY mission_day ASC, mission_step ASC
    ");

    return $stmt->fetchAll();
}

function getTaskHubTasksByDay(PDO $db = null) {
    $tasks_by_day = [];
    foreach (getTaskHubTaskRows($db) as $task_row) {
        $day = (int) ($task_row['mission_day'] ?? 0);
        if (!isset($tasks_by_day[$day])) {
            $tasks_by_day[$day] = [];
        }
        $tasks_by_day[$day][] = $task_row;
    }

    return $tasks_by_day;
}

function getTaskHubLatestLog($user_id, $task_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT *
        FROM user_task_logs
        WHERE user_id = ?
          AND task_id = ?
          AND mission_day = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $user_id, (int) $task_id, (int) $mission_day]);
    return $stmt->fetch() ?: null;
}

function taskHubInsertLog($user_id, $task_id, $status, array $extra = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_task_logs (
            user_id, task_id, completed_at, task_completed_at, task_available_at, mission_day, mission_step,
            attempt_no, proof_data, score, metadata, status
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user_id,
        (int) $task_id,
        $extra['task_completed_at'] ?? null,
        $extra['task_available_at'] ?? null,
        isset($extra['mission_day']) ? (int) $extra['mission_day'] : null,
        isset($extra['mission_step']) ? (int) $extra['mission_step'] : null,
        isset($extra['attempt_no']) ? (int) $extra['attempt_no'] : 1,
        $extra['proof_data'] ?? null,
        isset($extra['score']) ? (int) $extra['score'] : null,
        isset($extra['metadata']) ? json_encode($extra['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        (string) $status,
    ]);

    return (int) $db->lastInsertId();
}

function taskHubUpdateLog($log_id, array $fields, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $sets = [];
    $params = [];

    $map = [
        'completed_at' => 'completed_at',
        'task_completed_at' => 'task_completed_at',
        'task_available_at' => 'task_available_at',
        'proof_data' => 'proof_data',
        'score' => 'score',
        'status' => 'status',
    ];

    foreach ($map as $input_key => $column_name) {
        if (array_key_exists($input_key, $fields)) {
            $sets[] = $column_name . ' = ?';
            $params[] = $fields[$input_key];
        }
    }

    if (array_key_exists('metadata', $fields)) {
        $sets[] = 'metadata = ?';
        $params[] = json_encode($fields['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int) $log_id;
    $stmt = $db->prepare("UPDATE user_task_logs SET " . implode(', ', $sets) . " WHERE id = ?");
    return $stmt->execute($params);
}

function taskHubSelectRandomBoostTask($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT id, title, description, reward, task_category, task_link, completion_steps, proof_notes, cta_label
        FROM mini_tasks
        WHERE task_group = 'boosthub'
          AND is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ");
    $task = $stmt->fetch();
    if (!$task) {
        return null;
    }

    return $task;
}

function taskHubCreatePendingDayTasks($user_id, $mission_day, $day_available_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return;
    }

    $day_available_at = (string) $day_available_at;
    $checkin_task = $day_tasks[0];
    $existing = getTaskHubLatestLog((int) $user_id, (int) $checkin_task['id'], (int) $mission_day, $db);
    if (!$existing) {
        taskHubInsertLog((int) $user_id, (int) $checkin_task['id'], 'pending', [
            'task_available_at' => $day_available_at,
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($checkin_task['mission_step'] ?? 0),
        ], $db);
    }
}

function taskHubCreateFollowupTasksAfterCheckIn($user_id, $mission_day, $checkin_completed_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (count($day_tasks) <= 1) {
        return;
    }

    foreach ($day_tasks as $day_task) {
        if ((int) ($day_task['mission_step'] ?? 0) === 0) {
            continue;
        }

        $existing = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        if ($existing) {
            continue;
        }

        // TESTING_MODE: Bypass unlock_after_hours cooldown - tasks available immediately
        $unlock_hours = (defined('TESTING_MODE') && TESTING_MODE) ? 0 : (int) ($day_task['unlock_after_hours'] ?? 0);
        $available_at_ts = strtotime((string) $checkin_completed_at . ' +' . $unlock_hours . ' hours');
        $metadata = [];
        if (($day_task['verification_mode'] ?? '') === 'boosthub') {
            $boost_task = taskHubSelectRandomBoostTask((int) $user_id, $db);
            if ($boost_task) {
                $last_boost_stmt = $db->prepare("
                    SELECT MAX(task_completed_at) AS completed_at
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'mission'
                      AND mt.verification_mode = 'boosthub'
                ");
                $last_boost_stmt->execute([(int) $user_id]);
                $last_boost_at = $last_boost_stmt->fetch()['completed_at'] ?? null;
                if ($last_boost_at) {
                    $available_at_ts = max($available_at_ts, strtotime((string) $last_boost_at . ' +24 hours'));
                }

                $metadata = [
                    'boost_task_id' => (int) $boost_task['id'],
                    'boost_title' => (string) $boost_task['title'],
                    'boost_description' => (string) ($boost_task['description'] ?? ''),
                    'boost_reward' => (float) ($boost_task['reward'] ?? $day_task['reward']),
                    'boost_category' => (string) ($boost_task['task_category'] ?? 'custom'),
                    'boost_link' => (string) ($boost_task['task_link'] ?? ''),
                    'boost_steps' => (string) ($boost_task['completion_steps'] ?? ''),
                    'boost_proof_notes' => (string) ($boost_task['proof_notes'] ?? ''),
                    'boost_cta_label' => (string) ($boost_task['cta_label'] ?? ''),
                ];
            }
        }

        taskHubInsertLog((int) $user_id, (int) $day_task['id'], 'pending', [
            'task_available_at' => date('Y-m-d H:i:s', $available_at_ts),
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($day_task['mission_step'] ?? 0),
            'metadata' => $metadata,
        ], $db);
    }
}

function taskHubGetDayCompletionInfo($user_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return ['all_completed' => false, 'completed_at' => null, 'day_started_at' => null, 'tasks' => []];
    }

    $all_completed = true;
    $completed_at_ts = 0;
    $day_started_at_ts = 0;
    $task_states = [];

    foreach ($day_tasks as $day_task) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        $task_states[] = ['task' => $day_task, 'log' => $log];

        if (!$log || !in_array((string) ($log['status'] ?? ''), ['completed'], true)) {
            $all_completed = false;
        }

        $available_at = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $completed_at = !empty($log['task_completed_at']) ? strtotime((string) $log['task_completed_at']) : 0;
        if ($available_at > 0 && ($day_started_at_ts === 0 || $available_at < $day_started_at_ts)) {
            $day_started_at_ts = $available_at;
        }
        if ($completed_at > $completed_at_ts) {
            $completed_at_ts = $completed_at;
        }
    }

    // TESTING_MODE: Skip BoostHub gateway requirement
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        $boost_required_reward = taskHubGetBoostRequirementByDay((int) $mission_day);
        if ($boost_required_reward > 0) {
            $started_at_value = $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null;
            $has_boost = false;
            if (!empty($started_at_value)) {
                $boost_stmt = $db->prepare("
                    SELECT COUNT(*) AS total
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'boosthub'
                      AND ROUND(mt.reward, 2) = ?
                      AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
                ");
                $boost_stmt->execute([(int) $user_id, round((float) $boost_required_reward, 2), (string) $started_at_value]);
                $has_boost = ((int) ($boost_stmt->fetch()['total'] ?? 0)) > 0;
            }
            if (!$has_boost) {
                $all_completed = false;
            }
        }
    }

    return [
        'all_completed' => $all_completed,
        'completed_at' => $completed_at_ts > 0 ? date('Y-m-d H:i:s', $completed_at_ts) : null,
        'day_started_at' => $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null,
        'tasks' => $task_states,
    ];
}

function taskHubHasMissedDays($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $info = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        if (empty($info['day_started_at']) || empty($info['completed_at'])) {
            continue;
        }

        if (strtotime((string) $info['completed_at']) > getTaskHubNextResetTimestamp(strtotime((string) $info['day_started_at']))) {
            return true;
        }
    }

    return false;
}

function taskHubMissionCompleted($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $day_10 = taskHubGetDayCompletionInfo((int) $user_id, TASKHUB_TOTAL_DAYS, $db);
    return !empty($day_10['all_completed']);
}

function taskHubDayHasScheduledUnlocks(array $day_completion) {
    foreach (($day_completion['tasks'] ?? []) as $task_state) {
        $log = $task_state['log'] ?? [];
        $status = (string) ($log['status'] ?? '');
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        if ($status === 'pending' && $available_at_ts > time()) {
            return true;
        }
    }

    return false;
}

function syncTaskHubDayProgress($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        return null;
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s'), $db);

    while ($current_day <= TASKHUB_TOTAL_DAYS) {
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
        if (empty($completion['all_completed'])) {
            break;
        }

        $completed_at_ts = strtotime((string) ($completion['completed_at'] ?? 'now'));
        $next_reset = getTaskHubNextResetTimestamp($completed_at_ts);
        $db->prepare("UPDATE users SET last_day_completed_at = ? WHERE id = ?")->execute([
            date('Y-m-d H:i:s', $completed_at_ts),
            (int) $user_id,
        ]);

        if ($current_day >= TASKHUB_TOTAL_DAYS) {
            break;
        }

        // TESTING_MODE: Skip server reset wait - advance to next day immediately
        if (!defined('TESTING_MODE') || !TESTING_MODE) {
            if (time() < $next_reset) {
                break;
            }
        }

        $current_day++;
        $db->prepare("UPDATE users SET current_day = ? WHERE id = ?")->execute([(int) $current_day, (int) $user_id]);
        taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s', $next_reset), $db);
    }

    return getUserById((int) $user_id);
}

function getTaskHubState($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    // TESTING_MODE: Skip level check so testers can access TaskHub
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
            return [
                'access' => 'closed',
                'message' => 'TaskHub is available for Beginner accounts only.',
                'current_day' => (int) ($user['current_day'] ?? 1),
                'status' => 'completed',
            ];
        }
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_titles = getTaskHubDayTitles();
    $profile_complete = isUserProfileComplete($user);
    $current_tasks = $tasks_by_day[$current_day] ?? [];
    $next_day_tasks = $tasks_by_day[$current_day + 1] ?? [];
    $day_completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
    $day_started_at_ts = !empty($day_completion['day_started_at']) ? strtotime((string) $day_completion['day_started_at']) : time();
    $next_reset_ts = getTaskHubNextResetTimestamp($day_started_at_ts);
    $has_scheduled_unlocks = taskHubDayHasScheduledUnlocks($day_completion);
    $overall_total_tasks = 0;
    $overall_completed_tasks = 0;
    foreach ($tasks_by_day as $mission_tasks) {
        $overall_total_tasks += count($mission_tasks);
    }

    $status = 'in_progress';
    $status_message = 'In progress';
    $day_deadline_passed = !empty($day_completion['day_started_at']) && time() >= $next_reset_ts;
    if (!empty($day_completion['all_completed'])) {
        $status = 'completed';
        $status_message = $current_day >= TASKHUB_TOTAL_DAYS
            ? 'Completed'
            : 'Day cleared. Waiting for server reset';
    } elseif ($has_scheduled_unlocks) {
        $status = 'in_progress';
        $status_message = 'Waiting for next task unlock';
    } elseif ($day_deadline_passed) {
        $status = 'paused';
        $status_message = 'Progress paused until completion';
    }

    $build_task_payload = static function (array $task_row, $log, $active_day, PDO $inner_db) use ($profile_complete, $user_id) {
        $metadata = !empty($log['metadata']) ? (json_decode((string) $log['metadata'], true) ?: []) : [];
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $countdown = $available_at_ts > time() ? max(0, $available_at_ts - time()) : 0;

        // TESTING_MODE: Ignore unlock timers - show all pending tasks as available
        $is_testing = defined('TESTING_MODE') && TESTING_MODE;
        $task_status = 'locked';
        $task_message = 'Complete previous tasks to continue';
        if ($log) {
            $task_status = (string) ($log['status'] ?? 'locked');
            if ($task_status === 'pending' && (!$active_day || $available_at_ts <= time() || $is_testing)) {
                $task_status = 'available';
                $task_message = 'Ready';
            } elseif ($task_status === 'pending') {
                $task_status = 'locked';
                $task_message = 'Next task unlocks in ' . taskHubFormatDuration(max(0, $countdown));
            } elseif ($task_status === 'submitted') {
                $task_message = 'Awaiting manual review';
            } elseif ($task_status === 'failed') {
                $task_status = 'available';
                $task_message = !empty($task_row['requires_manual_review'])
                    ? 'Submission rejected. Try again.'
                    : 'Pass the quiz to proceed';
            } elseif ($task_status === 'completed') {
                $task_message = 'Completed';
            }
        }

        if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_title'])) {
            $task_row['title'] = $metadata['boost_title'];
            $task_row['description'] = $metadata['boost_description'] ?? $task_row['description'];
            $task_row['reward'] = $metadata['boost_reward'] ?? $task_row['reward'];
            $task_row['task_category'] = $metadata['boost_category'] ?? ($task_row['task_category'] ?? 'custom');
            $task_row['task_link'] = $metadata['boost_link'] ?? ($task_row['task_link'] ?? '');
            $task_row['completion_steps'] = $metadata['boost_steps'] ?? ($task_row['completion_steps'] ?? '');
            $task_row['proof_notes'] = $metadata['boost_proof_notes'] ?? ($task_row['proof_notes'] ?? '');
            $task_row['cta_label'] = $metadata['boost_cta_label'] ?? ($task_row['cta_label'] ?? '');
        }

        $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
        // Shuffle quiz choices deterministically so correct answer isn't always at index 0.
        // Seed uses user_id + task_key so the shuffle is consistent across requests.
        $quiz_data = $definition['quiz'] ?? [];
        if (!empty($quiz_data)) {
            $seed = (string) $user_id . '_' . (string) ($task_row['task_key'] ?? '');
            $quiz_data = shuffleQuizChoices($quiz_data, $seed);
        }
        return [
            'id' => (int) $task_row['id'],
            'task_key' => (string) $task_row['task_key'],
            'mission_step' => (int) ($task_row['mission_step'] ?? 0),
            'title' => (string) $task_row['title'],
            'description' => (string) ($task_row['description'] ?? ''),
            'reward' => round((float) ($task_row['reward'] ?? 0), 2),
            'task_category' => (string) ($task_row['task_category'] ?? 'custom'),
            'task_link' => (string) ($task_row['task_link'] ?? ''),
            'completion_steps' => (string) ($task_row['completion_steps'] ?? ''),
            'proof_notes' => (string) ($task_row['proof_notes'] ?? ''),
            'cta_label' => (string) ($task_row['cta_label'] ?? ''),
            'status' => $task_status,
            'status_message' => $task_message,
            'countdown_seconds' => $countdown,
            'task_available_at' => $log['task_available_at'] ?? null,
            'task_completed_at' => $log['task_completed_at'] ?? null,
            'verification_mode' => (string) ($task_row['verification_mode'] ?? 'instant'),
            'requires_quiz' => !empty($task_row['requires_quiz']),
            'requires_manual_review' => !empty($task_row['requires_manual_review']),
            'learning_title' => $definition['learning_title'] ?? '',
            'learning_url' => $definition['learning_url'] ?? '',
            'learning_opened' => !empty($metadata['learning_opened']),
            'quiz' => $quiz_data,
            'profile_complete' => ($task_row['verification_mode'] ?? '') === 'profile' ? $profile_complete : null,
        ];
    };

    $tasks_payload = [];
    foreach ($current_tasks as $task_row) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], $current_day, $db);
        $tasks_payload[] = $build_task_payload($task_row, $log, true, $db);
    }
    $boost_gateway = taskHubGetBoostGatewayTask($current_day);
    if ($boost_gateway) {
        $has_boost_completion = false;
        if (!empty($day_completion['day_started_at'])) {
            $boost_check_stmt = $db->prepare("
                SELECT COUNT(*) AS total
                FROM user_task_logs utl
                INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                WHERE utl.user_id = ?
                  AND utl.status = 'completed'
                  AND mt.task_group = 'boosthub'
                  AND ROUND(mt.reward, 2) = ?
                  AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
            ");
            $boost_check_stmt->execute([(int) $user_id, round((float) $boost_gateway['reward'], 2), (string) $day_completion['day_started_at']]);
            $has_boost_completion = ((int) ($boost_check_stmt->fetch()['total'] ?? 0)) > 0;
        }

        $tasks_payload[] = [
            'id' => 0,
            'task_key' => (string) $boost_gateway['task_key'],
            'mission_step' => (int) $boost_gateway['mission_step'],
            'title' => (string) $boost_gateway['title'],
            'description' => (string) $boost_gateway['description'],
            'reward' => round((float) $boost_gateway['reward'], 2),
            'task_category' => (string) $boost_gateway['task_category'],
            'task_link' => (string) $boost_gateway['task_link'],
            'completion_steps' => (string) $boost_gateway['completion_steps'],
            'proof_notes' => (string) $boost_gateway['proof_notes'],
            'cta_label' => (string) $boost_gateway['cta_label'],
            'status' => $has_boost_completion ? 'completed' : 'available',
            'status_message' => $has_boost_completion ? 'Completed' : 'Complete a matching BoostHub task, then come back.',
            'countdown_seconds' => 0,
            'task_available_at' => null,
            'task_completed_at' => null,
            'verification_mode' => 'boosthub_redirect',
            'requires_quiz' => false,
            'requires_manual_review' => false,
            'learning_title' => '',
            'learning_url' => '',
            'quiz' => [],
            'profile_complete' => null,
        ];
    }

    $next_day_preview = [];
    foreach ($next_day_tasks as $next_task) {
        $next_day_preview[] = [
            'title' => (string) $next_task['title'],
            'description' => (string) ($next_task['description'] ?? ''),
        ];
    }

    $days_payload = [];
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $day_tasks = $tasks_by_day[$day] ?? [];
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        $day_status = 'locked';
        $day_message = 'Locked';
        $day_countdown = 0;

        if ($day < $current_day) {
            $day_status = 'completed';
            $day_message = 'Completed';
        } elseif ($day === $current_day) {
            if (!empty($completion['all_completed'])) {
                $day_status = 'completed';
                $day_message = $day >= TASKHUB_TOTAL_DAYS ? 'Completed' : 'Day cleared. Waiting for server reset';
                if ($day < TASKHUB_TOTAL_DAYS) {
                    $day_countdown = max(0, $next_reset_ts - time());
                }
            } else {
                $day_status = $status;
                $day_message = $status_message;
            }
        } elseif ($day === ($current_day + 1) && !empty($day_completion['all_completed'])) {
            $day_status = 'locked';
            $day_message = 'Unlocks after server reset';
            $day_countdown = max(0, $next_reset_ts - time());
        }

        $day_task_payload = [];
        foreach ($day_tasks as $day_task) {
            if ($day > $current_day) {
                $day_task_payload[] = [
                    'id' => (int) $day_task['id'],
                    'task_key' => (string) $day_task['task_key'],
                    'mission_step' => (int) ($day_task['mission_step'] ?? 0),
                    'title' => 'Surprise Task',
                    'description' => 'This task will be revealed when Day ' . $day . ' unlocks.',
                    'reward' => round((float) ($day_task['reward'] ?? 0), 2),
                    'task_category' => (string) ($day_task['task_category'] ?? 'custom'),
                    'task_link' => '',
                    'completion_steps' => '',
                    'proof_notes' => '',
                    'cta_label' => '',
                    'status' => 'locked',
                    'status_message' => 'Hidden until this day unlocks',
                    'countdown_seconds' => 0,
                    'task_available_at' => null,
                    'task_completed_at' => null,
                    'verification_mode' => (string) ($day_task['verification_mode'] ?? 'instant'),
                    'requires_quiz' => !empty($day_task['requires_quiz']),
                    'requires_manual_review' => !empty($day_task['requires_manual_review']),
                    'learning_title' => '',
                    'learning_url' => '',
                    'quiz' => [],
                ];
                continue;
            }

            $day_log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], $day, $db);
            $day_task_payload[] = $build_task_payload($day_task, $day_log, $day === $current_day, $db);
        }
        $day_boost_gateway = taskHubGetBoostGatewayTask($day);
        if ($day_boost_gateway && $day <= $current_day) {
            $day_info_started = !empty($completion['day_started_at']) ? (string) $completion['day_started_at'] : '';
            $day_boost_completed = false;
            if ($day_info_started !== '') {
                $day_boost_stmt = $db->prepare("
                    SELECT COUNT(*) AS total
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'boosthub'
                      AND ROUND(mt.reward, 2) = ?
                      AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
                ");
                $day_boost_stmt->execute([(int) $user_id, round((float) $day_boost_gateway['reward'], 2), $day_info_started]);
                $day_boost_completed = ((int) ($day_boost_stmt->fetch()['total'] ?? 0)) > 0;
            }

            $day_task_payload[] = [
                'id' => 0,
                'task_key' => (string) $day_boost_gateway['task_key'],
                'mission_step' => (int) $day_boost_gateway['mission_step'],
                'title' => (string) $day_boost_gateway['title'],
                'description' => (string) $day_boost_gateway['description'],
                'reward' => round((float) $day_boost_gateway['reward'], 2),
                'task_category' => (string) $day_boost_gateway['task_category'],
                'task_link' => (string) $day_boost_gateway['task_link'],
                'completion_steps' => (string) $day_boost_gateway['completion_steps'],
                'proof_notes' => (string) $day_boost_gateway['proof_notes'],
                'cta_label' => (string) $day_boost_gateway['cta_label'],
                'status' => $day_boost_completed ? 'completed' : ($day === $current_day ? 'available' : 'locked'),
                'status_message' => $day_boost_completed ? 'Completed' : ($day === $current_day ? 'Complete in BoostHub to continue.' : 'Locked'),
                'countdown_seconds' => 0,
                'task_available_at' => null,
                'task_completed_at' => null,
                'verification_mode' => 'boosthub_redirect',
                'requires_quiz' => false,
                'requires_manual_review' => false,
                'learning_title' => '',
                'learning_url' => '',
                'quiz' => [],
                'profile_complete' => null,
            ];
        }

        $days_payload[] = [
            'day' => $day,
            'title' => (string) ($day_titles[$day] ?? ('Day ' . $day)),
            'status' => $day_status,
            'status_message' => $day_message,
            'countdown_seconds' => $day_countdown,
            'tasks' => $day_task_payload,
            'completed_tasks' => count(array_filter($day_task_payload, static function ($task) {
                return ($task['status'] ?? '') === 'completed';
            })),
            'total_tasks' => count($day_task_payload),
            'is_current' => $day === $current_day,
            'is_past' => $day < $current_day,
        ];

        $overall_completed_tasks += count(array_filter($day_task_payload, static function ($task) use ($day, $current_day) {
            return $day <= $current_day && ($task['status'] ?? '') === 'completed';
        }));
    }

    $current_day_completed_tasks = count(array_filter($tasks_payload, static function ($task) {
        return ($task['status'] ?? '') === 'completed';
    }));
    $current_day_total_tasks = count($tasks_payload);
    $current_day_progress_percent = $current_day_total_tasks > 0
        ? (int) round(($current_day_completed_tasks / $current_day_total_tasks) * 100)
        : 0;
    $overall_progress_percent = $overall_total_tasks > 0
        ? (int) round(($overall_completed_tasks / $overall_total_tasks) * 100)
        : 0;

    return [
        'access' => 'open',
        'current_day' => $current_day,
        'status' => $status,
        'status_message' => $status_message,
        'next_reset_at' => date('Y-m-d H:i:s', $next_reset_ts),
        'tasks' => $tasks_payload,
        'next_day' => min(TASKHUB_TOTAL_DAYS, $current_day + 1),
        'next_day_preview' => $next_day_preview,
        'completed_tasks' => count(array_filter($tasks_payload, static function ($task) {
            return ($task['status'] ?? '') === 'completed';
        })),
        'total_tasks' => count($tasks_payload),
        'current_day_progress_percent' => $current_day_progress_percent,
        'overall_completed_tasks' => $overall_completed_tasks,
        'overall_total_tasks' => $overall_total_tasks,
        'overall_progress_percent' => $overall_progress_percent,
        'profile_complete' => $profile_complete,
        'paused' => $status === 'paused',
        'mission_completed' => taskHubMissionCompleted((int) $user_id, $db),
        'has_missed_days' => taskHubHasMissedDays((int) $user_id, $db),
        'days' => $days_payload,
    ];
}

function taskHubCompleteInstantTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user = getUserById((int) $user_id);
    $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];

    if (($task_row['verification_mode'] ?? '') === 'profile') {
        if (!isUserProfileComplete($user)) {
            throw new RuntimeException('Open your profile page and complete your avatar, full name, username, and country before finishing this task.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'wallet') {
        $wallet_address = trim((string) ($payload['wallet_address'] ?? $user['wallet_address'] ?? ''));
        if ($wallet_address === '') {
            throw new RuntimeException('Add a wallet address to finish this task.');
        }
        $wallet_update = $db->prepare("UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE id = ?");
        $wallet_update->execute([$wallet_address, (int) $user_id]);
    }

    $reward = getTaskHubRewardAmountForTask((int) $user_id, $task_row, $log_row, $db);

    if ($reward <= 0) {
        throw new RuntimeException('TaskHub phase1 reward cap reached.');
    }

    $completed_at = date('Y-m-d H:i:s');
    taskHubUpdateLog((int) $log_row['id'], [
        'status' => 'completed',
        'completed_at' => $completed_at,
        'task_completed_at' => $completed_at,
        'metadata' => $metadata,
    ], $db);

    $entry = addRewardLedgerEntry(
        (int) $user_id,
        $reward,
        'mini_task',
        'taskhub_completion',
        'available',
        'taskhub:' . (string) ($task_row['task_key'] ?? $task_row['id']),
        $db,
        'phase1',
        'beginner'
    );

    creditReferralCommission((int) $user_id, $reward, 'taskhub', $db);

    if ((int) ($task_row['mission_step'] ?? 0) === 0) {
        taskHubCreateFollowupTasksAfterCheckIn((int) $user_id, (int) ($task_row['mission_day'] ?? 0), $completed_at, $db);
    }

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }
    syncUserLevelStatus((int) $user_id, $db);

    return $entry;
}

/**
 * Validates a single quiz answer (used for real-time per-question checking).
 * Returns success only if ALL answers so far are correct.
 * NOTE: Must shuffle quiz choices the same way as getTaskHubState() so
 * the answer indices match what the frontend received.
 */
function taskHubValidateSingleQuizAnswer($user_id, array $task_row, array $log_row, array $answers, PDO $db = null): bool {
    $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
    $questions = $definition['quiz'] ?? [];
    if (empty($questions)) {
        return false;
    }

    // Shuffle the same way as getTaskHubState() so answer indices match.
    // Use the same seed: user_id + task_key
    $seed = (string) $user_id . '_' . (string) ($task_row['task_key'] ?? '');
    $questions = shuffleQuizChoices($questions, $seed);

    // Find the last answered question (ignore -1 / unanswered)
    $last_answered = -1;
    foreach ($answers as $idx => $val) {
        if ((int) $val >= 0) {
            $last_answered = (int) $idx;
        }
    }

    foreach ($questions as $index => $question) {
        if ($index > $last_answered) {
            // Don't check unanswered questions
            break;
        }
        $user_answer = (int) ($answers[$index] ?? -1);
        $correct_answer = (int) ($question['answer'] ?? -2);
        if ($user_answer !== $correct_answer) {
            return false;
        }
    }

    return true;
}

function taskHubSubmitQuizTask($user_id, array $task_row, array $log_row, array $answers, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $log_metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];

    $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));

    $questions = $definition['quiz'] ?? [];
    if (empty($questions)) {
        throw new RuntimeException('Quiz definition not found.');
    }

    // Shuffle the same way as getTaskHubState() so answer indices match.
    // Use the same seed: user_id + task_key
    $questions = shuffleQuizChoices($questions, (string) $user_id . '_' . (string) ($task_row['task_key'] ?? ''));

    $score = 0;
    $detail = [];
    foreach ($questions as $index => $question) {
        $user_answer = (int) ($answers[$index] ?? -1);
        $correct_answer = (int) ($question['answer'] ?? -2);
        $is_correct = $user_answer === $correct_answer;
        if ($is_correct) {
            $score++;
        }
        $detail[] = [
            'question' => $question['question'],
            'correct' => $is_correct,
            'correct_answer' => $question['choices'][$correct_answer] ?? 'Unknown',
            'user_answer' => $user_answer >= 0 && isset($question['choices'][$user_answer]) ? $question['choices'][$user_answer] : '(none)',
        ];
    }

    $attempt_stmt = $db->prepare("
        INSERT INTO taskhub_quiz_attempts (user_id, task_id, mission_day, score, total_questions, answers_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $attempt_stmt->execute([
        (int) $user_id,
        (int) $task_row['id'],
        (int) ($task_row['mission_day'] ?? 0),
        (int) $score,
        count($questions),
        json_encode(array_values($answers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if ($score < (int) ($task_row['min_quiz_score'] ?? 0)) {
        taskHubUpdateLog((int) $log_row['id'], [
            'status' => 'failed',
            'score' => (int) $score,
            'metadata' => ['last_score' => (int) $score, 'required_score' => (int) ($task_row['min_quiz_score'] ?? 0)],
        ], $db);
        throw new QuizFailedException('Pass the quiz to proceed. Score: ' . $score . '/' . count($questions), $detail);
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, ['quiz_score' => $score], $db);
}

/**
 * Exception that carries quiz detail (which questions were correct/wrong).
 */
class QuizFailedException extends RuntimeException {
    private array $detail;

    public function __construct(string $message, array $detail = []) {
        parent::__construct($message);
        $this->detail = $detail;
    }

    public function getDetail(): array {
        return $this->detail;
    }
}

function taskHubSubmitManualTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $proof = trim((string) ($payload['proof'] ?? ''));
    $x_handle = trim((string) ($payload['x_handle'] ?? ''));
    $telegram_handle = trim((string) ($payload['telegram_handle'] ?? ''));

    if ((string) ($task_row['task_key'] ?? '') === 'day1_social_follow') {
        if ($x_handle === '' && $telegram_handle === '') {
            throw new RuntimeException('Add your X or Telegram username/URL for review.');
        }

        $proof_parts = [];
        if ($x_handle !== '') {
            $proof_parts[] = 'X: ' . $x_handle;
        }
        if ($telegram_handle !== '') {
            $proof_parts[] = 'Telegram: ' . $telegram_handle;
        }
        $proof = implode(' | ', $proof_parts);
    } elseif ((string) ($task_row['task_key'] ?? '') === 'day3_share_experience') {
        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        $allowed_platforms = ['x', 'facebook', 'binance_square', 'medium', 'reddit'];
        if (!in_array($platform, $allowed_platforms, true)) {
            throw new RuntimeException('Select a valid platform (X, Facebook, Binance Square, Medium, Reddit).');
        }
        if ($proof === '' || filter_var($proof, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Paste a valid public post URL for review.');
        }

        $host = strtolower((string) (parse_url($proof, PHP_URL_HOST) ?? ''));
        $domain_map = [
            'x' => ['x.com', 'twitter.com'],
            'facebook' => ['facebook.com', 'fb.com'],
            'binance_square' => ['binance.com'],
            'medium' => ['medium.com'],
            'reddit' => ['reddit.com'],
        ];
        $allowed_hosts = $domain_map[$platform] ?? [];
        $host_ok = false;
        foreach ($allowed_hosts as $allowed_host) {
            if ($host === $allowed_host || substr($host, -strlen('.' . $allowed_host)) === '.' . $allowed_host) {
                $host_ok = true;
                break;
            }
        }
        if (!$host_ok) {
            throw new RuntimeException('The submitted URL does not match the selected platform domain.');
        }
    } elseif ($proof === '') {
        throw new RuntimeException('Proof is required for this task.');
    }

    // IMPORTANT: Manual/proof tasks must always go through admin review,
    // even when TESTING_MODE is enabled.

    taskHubUpdateLog((int) $log_row['id'], [
        'status' => 'submitted',
        'proof_data' => $proof,
        'metadata' => [
            'submitted_at' => date('Y-m-d H:i:s'),
            'x_handle' => $x_handle,
            'telegram_handle' => $telegram_handle,
            'platform' => strtolower(trim((string) ($payload['platform'] ?? ''))),
            'submitted_ip' => resolveClientIpAddress(),
            'submitted_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'submitted_referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
        ],
    ], $db);

    return ['submitted' => true];
}

function submitTaskHubTask($user_id, $task_key, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    // TESTING_MODE: Skip level check
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
            throw new RuntimeException('TaskHub is available for Beginner accounts only.');
        }
    }

    enforceUserModuleAccess($user, 'taskhub');

    // TESTING_MODE: Skip security signals check (IP/device farming detection)
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        $signals = getUserSecuritySignals((int) $user_id, $db);
        if (!empty($signals['is_suspicious'])) {
            throw new RuntimeException('Suspicious activity detected. Try again later.');
        }
    }

    syncTaskHubDayProgress((int) $user_id, $db);

    $task_stmt = $db->prepare("
        SELECT *
        FROM mini_tasks
        WHERE task_key = ?
          AND task_group = 'mission'
          AND is_active = 1
        LIMIT 1
    ");
    $task_stmt->execute([(string) $task_key]);
    $task_row = $task_stmt->fetch();
    if (!$task_row) {
        throw new RuntimeException('TaskHub task not found.');
    }

    // TESTING_MODE: Skip day progression check - testers can do all days
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        if ((int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
            throw new RuntimeException('Complete previous tasks to continue.');
        }
    }

    $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
    if (!$log_row) {
        throw new RuntimeException('This task is still locked.');
    }

    // TESTING_MODE: Allow re-submission even if previously submitted
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        if ((string) ($log_row['status'] ?? '') === 'submitted') {
            throw new RuntimeException('This task is awaiting manual review.');
        }
    }

    // TESTING_MODE: Skip unlock timer cooldown check
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        $available_at_ts = !empty($log_row['task_available_at']) ? strtotime((string) $log_row['task_available_at']) : 0;
        if ($available_at_ts > time()) {
            throw new RuntimeException('Next task unlocks in ' . taskHubFormatDuration(max(0, $available_at_ts - time())) . '.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'quiz') {

        return taskHubSubmitQuizTask((int) $user_id, $task_row, $log_row, $payload['answers'] ?? [], $db);
    }

    if (!empty($task_row['requires_manual_review'])) {
        return taskHubSubmitManualTask((int) $user_id, $task_row, $log_row, $payload, $db);
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, $payload, $db);
}

/**
 * ============================================================
 * LEARNING SESSION MANAGEMENT (Secure Backend Validation)
 * ============================================================
 */

/**
 * Ensures the taskhub_learning_sessions table exists.
 */
function ensureTaskHubLearningSessionsSchema(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS taskhub_learning_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            task_key VARCHAR(80) NOT NULL,
            session_token VARCHAR(128) NOT NULL,
            start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_heartbeat TIMESTAMP NULL DEFAULT NULL,
            active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            required_seconds INT UNSIGNED NOT NULL DEFAULT 30,
            interruption_count INT UNSIGNED NOT NULL DEFAULT 0,
            max_scroll_depth INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active','paused','invalid','completed') NOT NULL DEFAULT 'active',
            validation_failed_reason VARCHAR(255) DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_learning_sessions_user (user_id),
            KEY idx_learning_sessions_task (task_key),
            KEY idx_learning_sessions_token (session_token),
            KEY idx_learning_sessions_status (status),
            KEY idx_learning_sessions_user_task (user_id, task_key, status),
            KEY idx_learning_sessions_heartbeat (last_heartbeat)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Generates a cryptographically secure session token.
 */
function taskHubGenerateSessionToken(): string {
    return bin2hex(random_bytes(32)); // 64-char hex token
}

/**
 * Creates a new learning session for a user/task.
 * Returns the session token.
 */
function taskHubCreateLearningSession(int $user_id, int $task_id, string $task_key, int $required_seconds = 30, PDO $db = null): string {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    // Invalidate any existing active sessions for this user+task
    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'invalid', validation_failed_reason = 'new_session_created' WHERE user_id = ? AND task_key = ? AND status IN ('active', 'paused')")
       ->execute([$user_id, $task_key]);

    $token = taskHubGenerateSessionToken();
    $stmt = $db->prepare("
        INSERT INTO taskhub_learning_sessions (user_id, task_id, task_key, session_token, required_seconds, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$user_id, $task_id, $task_key, $token, $required_seconds]);

    return $token;
}

/**
 * Processes a heartbeat for a learning session.
 * Returns the updated session data or null if invalid.
 */
function taskHubProcessHeartbeat(string $session_token, int $reported_active_seconds, bool $is_visible, bool $is_focused, int $scroll_depth, PDO $db = null): ?array {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE session_token = ? LIMIT 1");
    $stmt->execute([$session_token]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    if (!in_array((string) ($session['status'] ?? ''), ['active', 'paused'], true)) {
        return null;
    }

    // Calculate server-side active time
    $now = time();
    $start_ts = strtotime((string) ($session['start_time'] ?? 'now'));
    $last_hb_ts = $session['last_heartbeat'] ? strtotime((string) $session['last_heartbeat']) : $start_ts;
    $elapsed = $now - $last_hb_ts;
    $trusted_elapsed = min($elapsed, 10);
    $new_active_seconds = (int) ($session['active_seconds'] ?? 0) + $trusted_elapsed;

    $current_max_depth = max((int) ($session['max_scroll_depth'] ?? 0), min(100, (int) $scroll_depth));
    $status = $is_visible && $is_focused ? 'active' : 'paused';

    $db->prepare("UPDATE taskhub_learning_sessions SET last_heartbeat = NOW(), active_seconds = ?, max_scroll_depth = ?, status = ? WHERE id = ?")
       ->execute([$new_active_seconds, $current_max_depth, $status, (int) $session['id']]);

    $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE id = ?");
    $stmt->execute([(int) $session['id']]);
    return $stmt->fetch() ?: null;
}


/**
 * Reports an interruption (tab close, refresh, navigation away).
 */
function taskHubReportInterruption(string $session_token, string $reason = 'tab_closed', PDO $db = null): void {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'invalid', validation_failed_reason = ?, interruption_count = interruption_count + 1 WHERE session_token = ? AND status IN ('active', 'paused')")
       ->execute([$reason, $session_token]);
}


function reviewTaskHubSubmission($log_id, $approve, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT utl.*, mt.task_key, mt.verification_mode, mt.reward, mt.mission_day, mt.mission_step, mt.task_group, mt.title
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.id = ?
          AND utl.status = 'submitted'
        LIMIT 1
    ");
    $stmt->execute([(int) $log_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Submission not found.');
    }

    if (!$approve) {
        taskHubUpdateLog((int) $row['id'], [
            'status' => 'failed',
            'metadata' => ['reviewed_at' => date('Y-m-d H:i:s'), 'review_outcome' => 'rejected'],
        ], $db);
        return ['approved' => false];
    }

    if ((string) ($row['task_group'] ?? 'mission') === 'boosthub') {
        $reward = round((float) ($row['reward'] ?? 0), 8);
        if ($reward <= 0) {
            throw new RuntimeException('BoostHub reward amount is invalid.');
        }
    } else {
        $reward = getTaskHubRewardAmountForTask((int) $row['user_id'], $row, $row, $db);
        if ($reward <= 0) {
            throw new RuntimeException('TaskHub phase1 reward cap reached.');
        }
    }

    $completed_at = date('Y-m-d H:i:s');
    taskHubUpdateLog((int) $row['id'], [
        'status' => 'completed',
        'completed_at' => $completed_at,
        'task_completed_at' => $completed_at,
        'metadata' => ['reviewed_at' => $completed_at, 'review_outcome' => 'approved'],
    ], $db);

    $entry = addRewardLedgerEntry(
        (int) $row['user_id'],
        $reward,
        'mini_task',
        (string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub_manual_approval' : 'taskhub_manual_approval',
        'available',
        ((string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub:' : 'taskhub:') . (string) ($row['task_key'] ?? $row['task_id']),
        $db,
        'phase1',
        'beginner'
    );

    creditReferralCommission(
        (int) $row['user_id'],
        $reward,
        (string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub' : 'taskhub',
        $db
    );

    if ((string) ($row['task_group'] ?? 'mission') === 'mission') {
        syncTaskHubDayProgress((int) $row['user_id'], $db);
    }
    syncUserLevelStatus((int) $row['user_id'], $db);
    return ['approved' => true, 'entry' => $entry, 'task_group' => (string) ($row['task_group'] ?? 'mission')];
}
