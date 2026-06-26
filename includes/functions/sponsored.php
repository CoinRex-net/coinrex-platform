<?php
/**
 * Sponsored Projects Functions
 * Token management for sponsored project applications.
 * Note: adminHandleProjectPromotionAction() is defined in levels.php
 */

/**
 * Generate a new sponsored application token.
 *
 * @param PDO    $db
 * @param int    $expiry_days Number of days until token expires (default 7)
 * @param int|null $project_id Optional project ID to link token to (for editing)
 * @return string The generated token
 */
function generateSponsoredToken(PDO $db, $expiry_days = 7, $project_id = null)
{
    $token = bin2hex(random_bytes(32)); // 64-char hex token
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));

    $stmt = $db->prepare("
        INSERT INTO sponsored_tokens (token, project_id, used, expires_at, created_at)
        VALUES (?, ?, 0, ?, NOW())
    ");
    $stmt->execute([$token, $project_id ? (int) $project_id : null, $expires_at]);

    return $token;
}

/**
 * Validate a sponsored application token.
 *
 * @param PDO    $db
 * @param string $token
 * @return array|false Token row if valid, false otherwise
 */
function validateSponsoredToken(PDO $db, $token)
{
    $stmt = $db->prepare("
        SELECT * FROM sponsored_tokens
        WHERE token = ?
          AND used = 0
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Check if token exists but is used (for edit flow)
        $used_stmt = $db->prepare("
            SELECT st.*, p.approval_status
            FROM sponsored_tokens st
            LEFT JOIN projects p ON p.id = st.project_id
            WHERE st.token = ?
            LIMIT 1
        ");
        $used_stmt->execute([$token]);
        $used_row = $used_stmt->fetch(PDO::FETCH_ASSOC);

        if ($used_row && (int) $used_row['used'] === 1 && $used_row['project_id']) {
            // Token was used - allow edit if project is still pending
            if (strtolower(trim($used_row['approval_status'] ?? '')) === 'pending') {
                return $used_row;
            }
        }

        return false;
    }

    return $row;
}

/**
 * Mark a sponsored token as used.
 *
 * @param PDO    $db
 * @param string $token
 * @param int    $project_id
 * @return bool
 */
function markSponsoredTokenUsed(PDO $db, $token, $project_id)
{
    $stmt = $db->prepare("
        UPDATE sponsored_tokens
        SET used = 1, project_id = ?
        WHERE token = ?
    ");
    $stmt->execute([$project_id, $token]);
    return $stmt->rowCount() > 0;
}
