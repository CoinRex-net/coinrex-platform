# Quiz Admin Sync Implementation Plan

## Changes Needed

### 1. Database: Add `learning_title` and `learning_url` columns to `mini_tasks`
- Add columns after `cta_label` in `ensureRewardClaimSchema()` in `includes/functions/reward_ledger.php`

### 2. Admin Task Management: Add fields + "Manage Quiz" link
- `admin/task-management.php`: Add `learning_title` and `learning_url` input fields in mission task editor
- Add "Manage Quiz →" button linking to `quiz-manager.php?task_key=XYZ` (only for quiz tasks)

### 3. Admin Reward Save: Handle new fields
- `admin/includes/reward_admin.php`: Read and save `learning_title`/`learning_url` in `save_task` action

### 4. Backend: Read learning fields from DB with fallback
- `includes/functions/taskhub.php`: Modify `getTaskHubMissionTaskDefinitionByKey()` to read `learning_title`/`learning_url` from DB, fall back to hardcoded

### 5. Quiz Manager: Add "Seed from Hardcoded" button
- `admin/quiz-manager.php`: Add button to seed DB questions from hardcoded quiz arrays
