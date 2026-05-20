# Testing Mode - Restoration Guide

## Overview

A `TESTING_MODE` flag has been added to bypass certain security validations during the testing phase. When testing is complete, follow this guide to restore all production validations.

## How to Disable Testing Mode

### Quick Method (Single Change)

Edit `includes/config.php` and change:

```php
define('TESTING_MODE', true);
```

to:

```php
define('TESTING_MODE', false);
```

This single change will restore ALL validations across the entire platform.

---

## What Was Bypassed (and Where)

Below is a complete list of every change made, organized by file. Each change is wrapped with `if (!defined('TESTING_MODE') || !TESTING_MODE) { ... }` blocks.

### 1. `includes/config.php`
- **Added**: `define('TESTING_MODE', true);` constant
- **To restore**: Set to `false` or delete the line

### 2. `includes/functions/taskhub.php`

| Function | What Was Bypassed | Search For |
|---|---|---|
| `getTaskHubState()` | Level check (Beginner-only restriction) | `// TESTING_MODE: Skip level check` |
| `submitTaskHubTask()` | Level check | `// TESTING_MODE: Skip level check` |
| `submitTaskHubTask()` | Security signals (IP/device farming detection) | `// TESTING_MODE: Skip security signals check` |
| `submitTaskHubTask()` | Day progression check (must be on correct day) | `// TESTING_MODE: Skip day progression check` |
| `submitTaskHubTask()` | Unlock timer cooldown (task_available_at) | `// TESTING_MODE: Skip unlock timer cooldown check` |
| `submitTaskHubTask()` | Learning gate requirement | `// TESTING_MODE: Skip learning gate requirement` |
| `syncTaskHubDayProgress()` | Server reset wait (day advancement) | `// TESTING_MODE: Skip server reset wait` |
| `taskHubCreateFollowupTasksAfterCheckIn()` | `unlock_after_hours` cooldown between tasks | `// TESTING_MODE: Bypass unlock_after_hours cooldown` |

### 3. `includes/functions/boosthub.php`

| Function | What Was Bypassed | Search For |
|---|---|---|
| `getBoostHubStateForUser()` | Profile completeness check | `// TESTING_MODE: Skip profile completeness` |
| `getBoostHubStateForUser()` | Account age check (3-day minimum) | (same block as above) |
| `getBoostHubStateForUser()` | 24-hour cooldown between tasks | `// TESTING_MODE: Skip 24h cooldown` |

### 4. `includes/functions/core.php`

| Function | What Was Bypassed | Search For |
|---|---|---|
| `completeMiniTask()` | Security signals check | `// TESTING_MODE: Skip security signals` |
| `completeMiniTask()` | Daily task limit (`BEGINNER_GLOBAL_TASKS_PER_DAY`) | (same block) |
| `completeMiniTask()` | Per-task cooldown | (same block) |
| `completeMiniTask()` | Anti-farming rapid action check | (same block) |

---

## How to Manually Restore Each Change

If you want to selectively restore validations instead of using the flag:

### Option A: Search and Remove TESTING_MODE Blocks

Search for `TESTING_MODE` across all PHP files:

```
grep -rn "TESTING_MODE" includes/
```

For each match, remove the `if (!defined('TESTING_MODE') || !TESTING_MODE) {` wrapper and its closing `}`.

### Option B: Use Git to See All Changes

```bash
git diff
```

This will show every line that was added. Simply revert the changes:

```bash
git checkout -- includes/config.php includes/functions/taskhub.php includes/functions/boosthub.php includes/functions/core.php
```

> **Warning**: This will discard ALL changes to these files, including any other modifications you may have made.

---

## Verification Checklist (After Restoring)

- [ ] TaskHub: Level check works (Beginner-only)
- [ ] TaskHub: Security signals block suspicious accounts
- [ ] TaskHub: Day progression requires server reset
- [ ] TaskHub: Tasks unlock after their `unlock_after_hours` delay
- [ ] TaskHub: Learning gate must be opened before quiz/submit
- [ ] BoostHub: Profile must be complete
- [ ] BoostHub: Account must be 3+ days old
- [ ] BoostHub: 24-hour cooldown between tasks
- [ ] Core: Daily task limit enforced
- [ ] Core: Per-task cooldown enforced
- [ ] Core: Anti-farming rapid action check enforced
