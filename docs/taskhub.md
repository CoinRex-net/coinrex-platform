# TaskHub — Complete Documentation

## Overview

TaskHub is a **10-day mission board** for Beginner-level users on CoinRex. It serves as an onboarding and engagement system where users complete daily tasks to earn $REX rewards and progress through the platform. Each day unlocks a new set of tasks, and users must complete all tasks within a day before the server reset to advance to the next day.

---

## 1. Architecture & File Structure

### Core Files

| File | Purpose |
|------|---------|
| `taskhub.php` | Main page — renders the mission board UI |
| `includes/functions/taskhub.php` | All backend logic (1529 lines) |
| `api/submit_taskhub_task.php` | API endpoint for task submission |
| `api/get_taskhub_state.php` | API endpoint to fetch current state |
| `api/mark_taskhub_learning.php` | API endpoint to mark learning as opened |
| `api/claim_mystery_box.php` | API endpoint for Day 10 mystery box claim |
| `assets/js/taskhub-features.js` | Frontend JS — quiz system, greeting modal, mystery box, countdowns |
| `assets/css/reward-pages.css` | Base styles for reward pages including TaskHub |
| `includes/taskhub/greeting-modal.php` | Greeting modal HTML shown on check-in |
| `includes/taskhub/mystery-box.php` | Mystery box modal HTML for Day 10 |

### Database Tables Used

- `mini_tasks` — Task definitions (task_group = 'mission')
- `user_task_logs` — Per-user task progress logs
- `reward_ledger` — Reward credit entries
- `taskhub_quiz_questions` — DB-stored quiz questions (overrides hardcoded quizzes)
- `taskhub_quiz_attempts` — Quiz attempt history
- `users` — User data (current_day, level, wallet_address, etc.)

### Configuration Constants (in `includes/config.php`)

| Constant | Value | Description |
|----------|-------|-------------|
| `TASKHUB_TOTAL_DAYS` | 10 | Total number of mission days |
| `TASKHUB_SERVER_RESET_HOUR` | 0 (midnight UTC) | Hour when server resets for day progression |
| `TASKHUB_PHASE1_REWARD_CAP` | 80 | Maximum $REX earnable from TaskHub phase 1 |
| `TASKHUB_MYSTERY_BOX_PERFECT_REWARD` | 20 | Reward for completing all 10 days without missing |
| `TASKHUB_MYSTERY_BOX_FALLBACK_REWARD` | 5 | Reward if any day was missed |

---

## 2. Mission Structure — 10 Days

Each day has a theme and a set of tasks. Tasks are defined in `getTaskHubMissionDefinitions()` inside `includes/functions/taskhub.php`.

### Day Titles

| Day | Title | Theme |
|-----|-------|-------|
| 1 | Welcome Day | Onboarding, profile, social follow, terms quiz |
| 2 | Explore Day | Dashboard exploration, about page quiz |
| 3 | Privacy Day | Share experience, privacy policy quiz |
| 4 | Roadmap Day | Roadmap briefing quiz (5 questions) |
| 5 | DevHub Day | DevHub quiz, wallet connect |
| 6 | Review Day | Review guide quiz, transaction proof (≥10 USDT) |
| 7 | Filter Day | Final quiz (pass 4/5), volume proof (≥100 USDT) |
| 8 | Wallet Day | Hold proof (≥10 USDT for 1 day) |
| 9 | Momentum Day | Hold proof (≥10 USDT for 1 day) |
| 10 | Mystery Day | Mystery box reward |

---

## 3. Task Types & Verification Modes

Each task has a `verification_mode` that determines how it is completed:

### 3.1 Instant Tasks (`verification_mode = 'instant'`)

- **Completion**: Task is completed immediately upon submission.
- **Examples**: Daily check-in tasks (`day*_checkin`), UI exploration (`day2_ui_exploration`).
- **Flow**: User clicks Submit → backend marks as completed → reward credited instantly.

### 3.2 Profile Tasks (`verification_mode = 'profile'`)

- **Completion**: Requires the user's profile to be complete (avatar, full name, username, country).
- **Example**: `day1_profile_setup`.
- **Flow**: User clicks Submit → backend checks `isUserProfileComplete()` → if incomplete, error message shown with redirect to profile page.

### 3.3 Quiz Tasks (`verification_mode = 'quiz'`)

- **Completion**: User must answer all questions correctly (or meet minimum score).
- **Examples**: `day1_terms_quiz`, `day2_about_quiz`, `day3_privacy_quiz`, `day4_roadmap_quiz`, `day5_devhub_quiz`, `day6_review_quiz`, `day7_final_quiz`.
- **Flow**:
  1. User opens the learning page (link opens in new tab).
  2. Frontend calls `mark_taskhub_learning.php` to mark learning as validated.
  3. Quiz block becomes visible.
  4. User answers questions one-by-one (frontend validates each answer in real-time).
  5. User clicks "Submit Quiz" → all answers sent to backend.
  6. Backend scores the quiz. If score ≥ `min_quiz_score`, task is completed and reward credited.
  7. If score < `min_quiz_score`, task status is set to 'failed' and user can retry.

### 3.4 Manual Review Tasks (`verification_mode = 'manual'`)

- **Completion**: User submits proof, which is reviewed by an admin.
- **Examples**: `day1_social_follow`, `day3_share_experience`, `day6_txhash_submit`, `day7_volume_submit`, `day8_hold_submit`, `day9_hold_submit`.
- **Flow**:
  1. User fills in proof fields (text, URLs, handles).
  2. User clicks Submit → status changes to 'submitted'.
  3. Admin reviews in admin panel → approves or rejects.
  4. If approved: reward credited, task marked 'completed'.
  5. If rejected: task status set to 'failed', user can resubmit.

### 3.5 Wallet Tasks (`verification_mode = 'wallet'`)

- **Completion**: User provides a wallet address.
- **Example**: `day5_wallet_connect`.
- **Flow**: User enters wallet address → submits → address saved to user profile → reward credited.

### 3.6 BoostHub Redirect Tasks (`verification_mode = 'boosthub_redirect'`)

- **Completion**: User must complete a BoostHub task of a specific reward value.
- **Trigger**: Days 4-10 require a BoostHub task of 2.0 or 3.0 $REX.
- **Flow**: User clicks "Open BoostHub" → completes a task there → returns to TaskHub → system auto-detects completion.

### 3.7 Mystery Box Tasks (`verification_mode = 'mystery'`)

- **Completion**: Day 10 final reward — user picks one of three mystery boxes.
- **Flow**: User clicks a box → reward revealed → user clicks "Claim Reward" → reward credited.

---

## 4. Day Progression Logic

### 4.1 How Days Advance

1. User starts on Day 1 (`current_day = 1` in `users` table).
2. Each day has a set of tasks defined in `mini_tasks` with `mission_day` and `mission_step`.
3. Tasks within a day unlock sequentially based on `unlock_after_hours` (hours after check-in).
4. When all tasks in a day are completed, the day is marked as "cleared".
5. The user must wait for the **server reset** (midnight UTC) before advancing to the next day.
6. After server reset, `current_day` increments and new tasks are created.

### 4.2 Key Functions

- **`syncTaskHubDayProgress()`** — Called on every page load. Checks if current day is completed and if server reset has passed. If so, advances to next day and creates pending tasks.
- **`taskHubCreatePendingDayTasks()`** — Creates the check-in task for a new day in `user_task_logs`.
- **`taskHubCreateFollowupTasksAfterCheckIn()`** — After check-in is completed, creates the remaining tasks for that day with their unlock timers.
- **`taskHubGetDayCompletionInfo()`** — Checks if all tasks in a day are completed (including BoostHub gateway requirement).

### 4.3 Timer & Unlock System

- Tasks have `unlock_after_hours` (e.g., 2h, 3h, 5h, 6h).
- The unlock timer starts from when the check-in task was completed.
- Frontend displays countdown timers that tick every second.
- If a day's tasks are not completed before the next server reset, the day status becomes "paused".

### 4.4 BoostHub Gateway

- Days 4-7 require a BoostHub task of **2.0 $REX**.
- Days 8-10 require a BoostHub task of **3.0 $REX**.
- The BoostHub task must be completed **after** the day started.
- System checks `user_task_logs` for completed BoostHub tasks with matching reward values.

---

## 5. Submission Flow (Detailed)

### 5.1 Frontend Flow (`assets/js/taskhub-features.js`)

1. User clicks the **Submit** button on a task.
2. JavaScript collects:
   - `task_key` (from `data-task-key` attribute)
   - Verification-specific data (wallet address, proof text, social handles, quiz answers)
3. POST request sent to `api/submit_taskhub_task.php`.
4. On success:
   - If check-in task: greeting modal shown.
   - Modal with success message appears.
   - Page reloads to reflect new state.
5. On failure: error modal shown, button re-enabled.

### 5.2 Backend Flow (`api/submit_taskhub_task.php`)

1. Validates user authentication.
2. Resolves `task_key` from `mini_tasks` table.
3. Checks:
   - User level is Beginner.
   - Module access is allowed.
   - No suspicious security signals.
   - Task belongs to current day.
   - Task is not already submitted (for manual review).
   - Unlock timer has expired.
   - Learning page has been opened (if required).
4. Routes to appropriate handler based on `verification_mode`:
   - `quiz` → `taskHubSubmitQuizTask()`
   - `manual` → `taskHubSubmitManualTask()`
   - `instant`/`profile`/`wallet` → `taskHubCompleteInstantTask()`
5. Returns success/error response.

### 5.3 Reward Crediting

- Rewards are added to `reward_ledger` with:
  - `source = 'mini_task'`
  - `reward_phase = 'phase1'`
  - `status = 'available'`
- Referral commissions are credited via `creditReferralCommission()`.
- Phase 1 reward cap (80 $REX) is enforced.
- User level is synced after task completion.

---

## 6. Quiz System

### 6.1 Quiz Definition

Quizzes are defined in two ways:
1. **Hardcoded** in `getTaskHubMissionDefinitions()` (PHP arrays).
2. **Database-stored** in `taskhub_quiz_questions` table (overrides hardcoded when present).

### 6.2 Quiz Shuffling

- Quiz choices are **deterministically shuffled** so the correct answer isn't always at index 0.
- Seed: `user_id + task_key` — ensures consistent order across requests.
- Function: `shuffleQuizChoices()` uses `mt_srand()` with CRC32 hash of the seed.

### 6.3 Frontend Quiz Flow

1. Quiz block is hidden until learning page is opened.
2. Once learning is validated, quiz becomes visible.
3. Questions are shown **one at a time**.
4. User selects an answer → auto-advances to next question after 400ms.
5. Progress bar updates with each question.
6. After all questions answered, "Submit Quiz" button becomes enabled.
7. All answers are sent to backend for scoring.

### 6.4 Backend Quiz Validation

- `taskHubSubmitQuizTask()` scores all answers.
- Each question's answer is compared against the shuffled correct index.
- If score ≥ `min_quiz_score`: task completed, reward credited.
- If score < `min_quiz_score`: task status set to 'failed', user can retry.
- Quiz attempts are logged in `taskhub_quiz_attempts`.

### 6.5 Real-time Single Question Validation

- The frontend also validates each answer individually via `quiz_validate_single` mode.
- `taskHubValidateSingleQuizAnswer()` checks all answers up to the current question.
- If any previous answer is wrong, the user is notified immediately.

---

## 7. Learning Gate System

- Tasks with a `learning_url` require the user to open that URL before the task can be completed.
- When the user clicks the learning link:
  1. Link opens in a new tab.
  2. After 1.2 seconds, frontend calls `api/mark_taskhub_learning.php`.
  3. Backend sets `metadata.learning_opened = true` in the task log.
  4. Frontend updates the status badge to "Learning validated".
  5. If the task has a quiz, the quiz block becomes visible.

---

## 8. Mystery Box (Day 10)

### 8.1 Trigger

- Mystery box modal appears when Day 10 check-in is completed.
- Three boxes are shown, each with a random reward (10-20 $REX).

### 8.2 Flow

1. User clicks one of three boxes.
2. Box flips to reveal the reward.
3. "Claim Reward" button appears.
4. User clicks Claim → POST to `api/claim_mystery_box.php`.
5. Backend verifies:
   - All 10 days completed.
   - Mystery box not already claimed.
6. Reward credited to balance.
7. Confetti animation plays.
8. Page reloads after 2 seconds.

### 8.3 Reward Calculation

- **Perfect completion** (no missed days): 20 $REX (from `TASKHUB_MYSTERY_BOX_PERFECT_REWARD`).
- **Missed days**: 5 $REX (from `TASKHUB_MYSTERY_BOX_FALLBACK_REWARD`).
- The frontend generates random rewards (10-20) for visual effect, but the actual reward is determined by the backend.

---

## 9. Greeting Modal

- Shown when a user completes a check-in task.
- Displays:
  - Day number.
  - Motivational message.
  - "Let's Go!" button to dismiss.
- HTML defined in `includes/taskhub/greeting-modal.php`.

---

## 10. Admin Review System

### 10.1 Manual Review

- Admin panel (`admin/task-management.php`) lists all 'submitted' tasks.
- Admin can approve or reject submissions.
- `reviewTaskHubSubmission()` in `includes/functions/taskhub.php` handles the review logic.

### 10.2 Review Flow

1. Admin views submission with proof data.
2. Admin clicks Approve or Reject.
3. If approved:
   - Task status set to 'completed'.
   - Reward credited to user.
   - Day progress synced.
   - User level synced.
4. If rejected:
   - Task status set to 'failed'.
   - User can resubmit.

---

## 11. Testing Mode

The system has a `TESTING_MODE` constant that, when defined as `true`, bypasses several restrictions:

| Restriction | Normal Mode | Testing Mode |
|-------------|-------------|--------------|
| Level check | Beginner only | All levels |
| Day progression | Must wait for server reset | Immediate advancement |
| Unlock timers | Enforced | Ignored |
| Learning gate | Required | Skipped |
| Manual review | Requires admin approval | Auto-completed |
| Security signals | Checked | Skipped |
| Re-submission | Blocked if submitted | Allowed |
| BoostHub gateway | Required | Skipped |

---

## 12. Frontend Features

### 12.1 Day Selector

- Pill-style buttons at the top of the page.
- Only current and past days are clickable.
- Future days are disabled.
- Clicking a day shows that day's task panel.

### 12.2 Countdown Timers

- Two types of countdowns:
  - **Day countdown**: Shows when the next day will unlock (after server reset).
  - **Task countdown**: Shows when the next task within a day will unlock.
- Timers tick every 1 second via `setInterval()`.

### 12.3 Auto-Refresh

- Page state is refreshed every 30 seconds via AJAX.
- Updates current day number, status message, and progress status without full page reload.

### 12.4 Task States

Each task can be in one of these states:

| State | Description | Visual |
|-------|-------------|--------|
| `pending` | Task created but not yet available | Locked with timer |
| `available` | Task is ready to be completed | Active with submit button |
| `submitted` | Awaiting manual review | "Awaiting Review" button |
| `completed` | Task done | Condensed view with checkmark |
| `failed` | Quiz failed or review rejected | Available again for retry |
| `locked` | Previous tasks not completed | Dashed border, disabled |

---

## 13. Security & Anti-Abuse

- **IP/Device tracking**: `getUserSecuritySignals()` checks for suspicious activity.
- **Proof validation**: URL domain matching for social share tasks.
- **Quiz shuffling**: Deterministic shuffle prevents answer memorization.
- **Reward cap**: Phase 1 cap of 80 $REX prevents excessive earnings.
- **Day progression**: Enforced server-side — cannot skip days.
- **Learning gate**: Must actually open learning pages before quiz/instant tasks.

---

## 14. Error Handling

- All API endpoints return JSON with `success` boolean.
- Errors return `apiErrorResponse()` with HTTP status code and message.
- Frontend shows errors in a modal dialog.
- `QuizFailedException` carries detailed per-question results.

---

## 15. Summary of All Task Definitions

| Day | Task Key | Title | Verification | Reward | Unlock After |
|-----|----------|-------|-------------|--------|-------------|
| 1 | `day1_checkin` | Daily Check-in | instant | 1 $REX | 0h |
| 1 | `day1_profile_setup` | Profile Setup | profile | 1 $REX | 0h |
| 1 | `day1_social_follow` | Social Follow | manual | 2 $REX | 2h |
| 1 | `day1_terms_quiz` | Learn and Quiz | quiz (3/3) | 2 $REX | 5h |
| 2 | `day2_checkin` | Check-in | instant | 1 $REX | 0h |
| 2 | `day2_ui_exploration` | UI Exploration | instant | 1 $REX | 3h |
| 2 | `day2_about_quiz` | Learn and Quiz | quiz (3/3) | 2 $REX | 6h |
| 3 | `day3_checkin` | Check-in | instant | 1 $REX | 0h |
| 3 | `day3_share_experience` | Share Experience | manual | 2 $REX | 3h |
| 3 | `day3_privacy_quiz` | Learn and Quiz | quiz (3/3) | 2 $REX | 6h |
| 4 | `day4_checkin` | Check-in | instant | 1 $REX | 0h |
| 4 | `day4_roadmap_quiz` | Learn and Quiz | quiz (5/5) | 2 $REX | 3h |
| 5 | `day5_checkin` | Check-in | instant | 1 $REX | 0h |
| 5 | `day5_devhub_quiz` | Learn and Quiz | quiz (3/3) | 2 $REX | 3h |
| 5 | `day5_wallet_connect` | Wallet Add/Connect | wallet | 1 $REX | 6h |
| 6 | `day6_checkin` | Check-in | instant | 1 $REX | 0h |
| 6 | `day6_review_quiz` | Learn and Quiz | quiz (3/3) | 2 $REX | 3h |
| 6 | `day6_txhash_submit` | TX Proof (≥10 USDT) | manual | 2 $REX | 6h |
| 7 | `day7_checkin` | Check-in | instant | 1 $REX | 0h |
| 7 | `day7_final_quiz` | Final Quiz | quiz (4/5) | 2 $REX | 0h |
| 7 | `day7_volume_submit` | Volume Proof (≥100 USDT) | manual | 3 $REX | 6h |
| 8 | `day8_checkin` | Check-in | instant | 1 $REX | 0h |
| 8 | `day8_hold_submit` | Hold Proof (≥10 USDT) | manual | 3 $REX | 6h |
| 9 | `day9_checkin` | Check-in | instant | 1 $REX | 0h |
| 9 | `day9_hold_submit` | Hold Proof (≥10 USDT) | manual | 3 $REX | 6h |
| 10 | `day10_checkin` | Check-in | instant | 1 $REX | 0h |
| 10 | `day10_mystery_box` | Mystery Box | mystery | 5-20 $REX | 6h |

---

## 16. API Endpoints

### `GET /api/get_taskhub_state.php`
Returns the full TaskHub state for the authenticated user.

### `POST /api/submit_taskhub_task.php`
Submit a task. Parameters:
- `task_key` (required)
- `wallet_address` (for wallet tasks)
- `proof` (for manual tasks)
- `x_handle`, `telegram_handle` (for social follow)
- `platform`, `proof` (for share experience)
- `answers_json` (for quiz tasks)
- `quiz_validate_single` (for real-time single question validation)

### `POST /api/mark_taskhub_learning.php`
Mark a learning page as opened. Parameters:
- `task_key` (required)

### `POST /api/claim_mystery_box.php`
Claim the Day 10 mystery box reward. Parameters:
- `reward` (the reward amount to claim)
