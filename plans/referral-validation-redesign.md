# Referral Validation Logic Redesign Plan

## Current Problems Identified

### Problem 1: Admin Can Qualify Without TaskHub Completion
- **Issue**: When admin clicks "Valid" button, referral becomes qualified immediately, even if user hasn't completed 4 TaskHub days
- **Location**: [`applyReferralDecision()`](includes/functions/referrals.php:239) at line 263-267
- **Root Cause**: No validation check before setting status to 'qualified'

### Problem 2: Manual Review Validation Doesn't Work
- **Issue**: After admin marks a flagged referral as valid, the validation sometimes doesn't persist or work correctly
- **Location**: [`applyReferralDecision()`](includes/functions/referrals.php:239) and [`maybeActivateReferralQualification()`](includes/functions/referrals.php:295)
- **Root Cause**: No abuse detection flag stored in database; system re-evaluates and may re-flag

### Problem 3: Dashboard Shows All Referrals
- **Issue**: Admin dashboard shows all referrals (pending, clean, qualified, etc.) instead of only those needing review
- **Location**: [`adminRewardGetReferralRows()`](admin/includes/reward_admin.php:531)
- **Root Cause**: Query has no filter for abuse detection status

### Problem 4: Clean Referrals Clutter Dashboard
- **Issue**: Referrals without any abuse signals appear in dashboard, wasting admin time
- **Location**: [`adminRewardGetReferralRows()`](admin/includes/reward_admin.php:531)
- **Root Cause**: No distinction between "needs review" vs "clean"

---

## Solution Design

### New Database Schema Changes

**Add column to `users` table:**
```sql
ALTER TABLE users
ADD COLUMN IF NOT EXISTS referral_abuse_detected TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_qualified_at,
ADD COLUMN IF NOT EXISTS referral_abuse_reason VARCHAR(255) NULL AFTER referral_abuse_detected;
```

**Purpose:**
- `referral_abuse_detected`: Flag set to 1 when abuse pattern detected (same IP + fingerprint + behavior)
- `referral_abuse_reason`: Store the specific abuse signals detected (e.g., "same IP + same device fingerprint + same behavior pattern")

---

### Logic Changes Required

#### 1. **Modify [`evaluateReferralAbuseRisk()`](includes/functions/referrals.php:115)**
- Already returns abuse detection status correctly
- No changes needed - this function is working as designed

#### 2. **Update [`maybeActivateReferralQualification()`](includes/functions/referrals.php:295)**
- **Current behavior**: Calls `applyReferralDecision()` with 'flag_manual_review' if abuse detected
- **New behavior**: 
  - Store abuse detection flag in database BEFORE calling `applyReferralDecision()`
  - Set `referral_abuse_detected = 1` and `referral_abuse_reason` when abuse is detected
  - This prevents re-evaluation on subsequent calls

#### 3. **Enhance [`applyReferralDecision()`](includes/functions/referrals.php:239)**
- **Add validation for 'qualify' decision**:
  - Check if user has completed 4 TaskHub days using [`canReferralBecomeValid()`](includes/functions/referrals.php:99)
  - If NOT completed, throw error: "User must complete 4 TaskHub days before qualification"
  - Only allow qualification if requirement is met
- **Preserve abuse flag**:
  - When admin manually qualifies a flagged referral, keep the abuse detection flag for audit trail
  - Add new column `referral_manually_qualified_by` to track admin override

#### 4. **Modify [`adminRewardGetReferralRows()`](admin/includes/reward_admin.php:531)**
- **New filter logic**:
  - Show ONLY referrals where `referral_abuse_detected = 1` (abuse detected)
  - Hide all clean referrals (where `referral_abuse_detected = 0`)
  - Hide already-qualified referrals (where `referral_qualified_at IS NOT NULL`)
- **SQL change**:
  ```sql
  WHERE 1=1
  AND child.referral_abuse_detected = 1
  AND child.referral_qualified_at IS NULL
  ```

#### 5. **Modify [`adminRewardGetReferralRowsCount()`](admin/includes/reward_admin.php:571)**
- Apply same filter as `adminRewardGetReferralRows()`
- Count only flagged, unqualified referrals

#### 6. **Update [`adminRewardGetReferralMetrics()`](admin/includes/reward_admin.php:596)**
- Adjust metrics to reflect new filtering:
  - `total`: Count where `referral_abuse_detected = 1`
  - `pending`: Count where `referral_abuse_detected = 1` AND `referral_qualified_at IS NULL`
  - `qualified`: Count where `referral_abuse_detected = 1` AND `referral_qualified_at IS NOT NULL`
  - `flagged`: Count where `referral_abuse_detected = 1` AND `referral_review_status = 'flagged_manual_review'`
  - `invalid`: Count where `referral_abuse_detected = 1` AND `referral_review_status = 'invalid'`

---

## Implementation Workflow

### Phase 1: Database Migration
1. Create migration file: `database/migrations/2026_05_26_referral_abuse_detection.sql`
2. Add `referral_abuse_detected` and `referral_abuse_reason` columns to `users` table
3. Add `referral_manually_qualified_by` column for audit trail

### Phase 2: Core Logic Updates
1. Update [`maybeActivateReferralQualification()`](includes/functions/referrals.php:295)
   - Store abuse detection flag before calling `applyReferralDecision()`
   
2. Update [`applyReferralDecision()`](includes/functions/referrals.php:239)
   - Add TaskHub completion validation for 'qualify' decision
   - Throw error if requirement not met
   - Track admin override in new column

### Phase 3: Admin Dashboard Updates
1. Update [`adminRewardGetReferralRows()`](admin/includes/reward_admin.php:531)
   - Add filter: `referral_abuse_detected = 1 AND referral_qualified_at IS NULL`

2. Update [`adminRewardGetReferralRowsCount()`](admin/includes/reward_admin.php:571)
   - Apply same filter

3. Update [`adminRewardGetReferralMetrics()`](admin/includes/reward_admin.php:596)
   - Adjust all metric queries to use new filter

4. Update [`admin/referrals.php`](admin/referrals.php)
   - Update header text to reflect "Flagged Referrals" instead of "All Referrals"
   - Update description to explain only abuse-detected referrals are shown

### Phase 4: Testing & Validation
1. Verify only flagged referrals appear in dashboard
2. Verify admin cannot qualify without 4 TaskHub days
3. Verify abuse detection flag persists after manual qualification
4. Verify metrics update correctly

---

## Key Benefits

✅ **Admin can only qualify referrals that meet requirements** - Prevents premature qualification
✅ **Abuse detection flag persists** - Prevents re-evaluation and inconsistent behavior
✅ **Dashboard shows only actionable items** - Reduces admin clutter and improves efficiency
✅ **Audit trail maintained** - Track which admin manually qualified flagged referrals
✅ **Clean referrals hidden** - No need to review referrals without abuse signals

---

## Files to Modify

1. **Database**: `database/migrations/2026_05_26_referral_abuse_detection.sql` (NEW)
2. **Functions**: `includes/functions/referrals.php`
   - [`maybeActivateReferralQualification()`](includes/functions/referrals.php:295)
   - [`applyReferralDecision()`](includes/functions/referrals.php:239)
3. **Admin Functions**: `admin/includes/reward_admin.php`
   - [`adminRewardGetReferralRows()`](admin/includes/reward_admin.php:531)
   - [`adminRewardGetReferralRowsCount()`](admin/includes/reward_admin.php:571)
   - [`adminRewardGetReferralMetrics()`](admin/includes/reward_admin.php:596)
4. **Admin UI**: `admin/referrals.php`
   - Update header and description text

---

## Validation Checklist

- [ ] Migration creates new columns successfully
- [ ] Abuse detection flag is set when abuse pattern detected
- [ ] Admin cannot qualify without 4 TaskHub days (error thrown)
- [ ] Dashboard shows only flagged referrals
- [ ] Metrics reflect new filtering logic
- [ ] Manual qualification preserves abuse flag for audit
- [ ] Existing qualified referrals remain unaffected
- [ ] Commission calculation still works correctly
