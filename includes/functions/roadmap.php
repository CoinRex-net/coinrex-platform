<?php
/** Dynamic Roadmap helpers for public display and admin publishing. */

function getDefaultRoadmapData(): array {
    return [
        'settings' => [
            'title' => 'The Road to Web3 Trust',
            'title_gold_word' => 'Web3',
            'subtitle' => 'Building trust, reputation, and utility before token speculation.',
            'eyebrow' => 'CoinRex Mission Journey',
            'progress_label' => 'Stage 01 Progress',
            'progress_percent' => 68,
            'progress_note' => 'MVP validation is active before RexLink Beta expansion.',
            'bottom_statement' => 'Users First. Utility First. Token Later.',
            'published_at' => null,
            'updated_at' => null,
        ],
        'stages' => [
            [
                'number' => '01',
                'stage_number' => '01',
                'title' => 'MVP Launch',
                'status' => 'Current Stage',
                'status_label' => 'Current Stage',
                'badge' => 'CURRENT',
                'tone' => 'current',
                'icon' => 'fa-rocket',
                'progress' => 68,
                'items' => ['Registration', 'Login', 'Email Verification', 'Referrals', 'LearnHub', 'Early Adopter Program'],
                'goals' => [],
                'milestone' => 'RexLink Beta arrives after MVP validation.',
                'milestone_note' => 'RexLink Beta arrives after MVP validation.',
                'is_visible' => 1,
            ],
            [
                'number' => '02',
                'stage_number' => '02',
                'title' => 'Ecosystem Launch',
                'status' => 'Next Expansion',
                'status_label' => 'Next Expansion',
                'badge' => 'NEXT',
                'tone' => 'next',
                'icon' => 'fa-network-wired',
                'items' => ['DevHub Live', 'Developer Verification', 'Project Listings', 'Reviews Live', 'Smart Contract Deployment'],
                'goals' => ['1,000 Users', '100 Listed Projects'],
                'milestone' => '',
                'milestone_note' => '',
                'is_visible' => 1,
            ],
            [
                'number' => '03',
                'stage_number' => '03',
                'title' => 'Reward Economy',
                'status' => 'Utility Layer',
                'status_label' => 'Utility Layer',
                'badge' => 'PLANNED',
                'tone' => 'planned',
                'icon' => 'fa-coins',
                'items' => ['TGE', 'Claim Rewards', 'Snapshot System', 'Trust Score', 'Reputation System', 'Leaderboards', 'Developer Profiles'],
                'goals' => ['5,000 Users'],
                'milestone' => '',
                'milestone_note' => '',
                'is_visible' => 1,
            ],
            [
                'number' => '04',
                'stage_number' => '04',
                'title' => 'Market Expansion',
                'status' => 'Liquidity Phase',
                'status_label' => 'Liquidity Phase',
                'badge' => 'PLANNED',
                'tone' => 'planned',
                'icon' => 'fa-chart-line',
                'items' => ['RexLink Play Store Release', 'Liquidity Addition', 'Public DEX Trading'],
                'goals' => [],
                'milestone' => '',
                'milestone_note' => '',
                'is_visible' => 1,
            ],
            [
                'number' => '05',
                'stage_number' => '05',
                'title' => 'To Be Announced',
                'status' => 'Future Signal',
                'status_label' => 'Future Signal',
                'badge' => 'FUTURE',
                'tone' => 'future',
                'icon' => 'fa-satellite-dish',
                'items' => [],
                'goals' => [],
                'milestone' => 'Future milestones will be announced after Stage 3 completion.',
                'milestone_note' => 'Future milestones will be announced after Stage 3 completion.',
                'is_visible' => 1,
            ],
        ],
    ];
}

function normalizeRoadmapTone($tone): string {
    $tone = strtolower(trim((string) $tone));
    return in_array($tone, ['current', 'next', 'planned', 'future'], true) ? $tone : 'planned';
}

function normalizeRoadmapBadge($badge): string {
    $badge = strtoupper(trim((string) $badge));
    return in_array($badge, ['CURRENT', 'NEXT', 'PLANNED', 'FUTURE'], true) ? $badge : 'PLANNED';
}

function sanitizeRoadmapIcon($icon): string {
    $icon = strtolower(trim((string) $icon));
    $icon = preg_replace('/[^a-z0-9\- ]/', '', $icon);
    $parts = preg_split('/\s+/', $icon);
    foreach ($parts as $part) {
        if (strpos($part, 'fa-') === 0 && strlen($part) <= 60) {
            return $part;
        }
    }
    return 'fa-circle-nodes';
}

function normalizeRoadmapEntryType($type): string {
    return strtolower(trim((string) $type)) === 'goal' ? 'goal' : 'item';
}

function ensureRoadmapSchema(PDO $db = null): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS roadmap_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            version_status ENUM('draft','published') NOT NULL,
            title VARCHAR(180) NOT NULL,
            title_gold_word VARCHAR(80) NOT NULL DEFAULT '',
            subtitle VARCHAR(300) NOT NULL DEFAULT '',
            eyebrow VARCHAR(120) NOT NULL DEFAULT '',
            progress_label VARCHAR(120) NOT NULL DEFAULT '',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            progress_note VARCHAR(300) NOT NULL DEFAULT '',
            bottom_statement VARCHAR(220) NOT NULL DEFAULT '',
            published_at DATETIME NULL,
            published_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_roadmap_settings_status (version_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS roadmap_stages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            version_status ENUM('draft','published') NOT NULL,
            stage_number VARCHAR(20) NOT NULL,
            title VARCHAR(160) NOT NULL,
            status_label VARCHAR(120) NOT NULL DEFAULT '',
            badge ENUM('CURRENT','NEXT','PLANNED','FUTURE') NOT NULL DEFAULT 'PLANNED',
            tone ENUM('current','next','planned','future') NOT NULL DEFAULT 'planned',
            icon VARCHAR(80) NOT NULL DEFAULT 'fa-circle-nodes',
            milestone_note TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_roadmap_stages_status_sort (version_status, sort_order),
            KEY idx_roadmap_stages_visible (is_visible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS roadmap_stage_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            stage_id INT UNSIGNED NOT NULL,
            entry_type ENUM('item','goal') NOT NULL DEFAULT 'item',
            label VARCHAR(180) NOT NULL,
            icon VARCHAR(80) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_roadmap_entries_stage_sort (stage_id, sort_order),
            CONSTRAINT fk_roadmap_entries_stage
                FOREIGN KEY (stage_id) REFERENCES roadmap_stages(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $db->query("SELECT COUNT(*) FROM roadmap_settings");
    if ((int) ($stmt ? $stmt->fetchColumn() : 0) === 0) {
        seedDefaultRoadmap($db, null, true);
    }

    ensureRoadmapAdminPermission($db);

    $ensured = true;
}

function ensureRoadmapAdminPermission(PDO $db = null): void {
    $db = $db ?: getDBConnection();
    if (!(function_exists('tableExists') && tableExists('permissions') && tableExists('roles') && tableExists('role_permissions'))) {
        return;
    }

    $db->exec("
        INSERT INTO permissions (name, description)
        SELECT 'manage_roadmap', 'Manage public roadmap stages and publishing'
        WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'manage_roadmap')
    ");

    $db->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT r.id, p.id
        FROM roles r
        JOIN permissions p ON p.name = 'manage_roadmap'
        WHERE r.name = 'super_admin'
    ");
}

function roadmapReplaceVersion(PDO $db, string $version, array $data, ?int $adminId = null, ?DateTimeInterface $publishedAt = null): void {
    $version = $version === 'published' ? 'published' : 'draft';
    $settings = $data['settings'] ?? [];
    $progress = max(0, min(100, (int) ($settings['progress_percent'] ?? 0)));

    $db->prepare("DELETE FROM roadmap_stages WHERE version_status = ?")->execute([$version]);

    $stmt = $db->prepare("
        INSERT INTO roadmap_settings (
            version_status, title, title_gold_word, subtitle, eyebrow, progress_label,
            progress_percent, progress_note, bottom_statement, published_at, published_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            title_gold_word = VALUES(title_gold_word),
            subtitle = VALUES(subtitle),
            eyebrow = VALUES(eyebrow),
            progress_label = VALUES(progress_label),
            progress_percent = VALUES(progress_percent),
            progress_note = VALUES(progress_note),
            bottom_statement = VALUES(bottom_statement),
            published_at = VALUES(published_at),
            published_by = VALUES(published_by),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stmt->execute([
        $version,
        trim((string) ($settings['title'] ?? 'The Road to Web3 Trust')) ?: 'The Road to Web3 Trust',
        trim((string) ($settings['title_gold_word'] ?? 'Web3')),
        trim((string) ($settings['subtitle'] ?? '')),
        trim((string) ($settings['eyebrow'] ?? 'CoinRex Mission Journey')),
        trim((string) ($settings['progress_label'] ?? 'Stage 01 Progress')),
        $progress,
        trim((string) ($settings['progress_note'] ?? '')),
        trim((string) ($settings['bottom_statement'] ?? 'Users First. Utility First. Token Later.')),
        $publishedAt ? $publishedAt->format('Y-m-d H:i:s') : ($version === 'published' ? date('Y-m-d H:i:s') : null),
        $version === 'published' && $adminId ? $adminId : null,
        $adminId ?: null,
    ]);

    $stageInsert = $db->prepare("
        INSERT INTO roadmap_stages (
            version_status, stage_number, title, status_label, badge, tone, icon,
            milestone_note, sort_order, is_visible
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $entryInsert = $db->prepare("
        INSERT INTO roadmap_stage_entries (stage_id, entry_type, label, icon, sort_order, is_visible)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach (($data['stages'] ?? []) as $index => $stage) {
        $title = trim((string) ($stage['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $stageInsert->execute([
            $version,
            trim((string) ($stage['stage_number'] ?? $stage['number'] ?? sprintf('%02d', $index + 1))) ?: sprintf('%02d', $index + 1),
            $title,
            trim((string) ($stage['status_label'] ?? $stage['status'] ?? '')),
            normalizeRoadmapBadge($stage['badge'] ?? 'PLANNED'),
            normalizeRoadmapTone($stage['tone'] ?? 'planned'),
            sanitizeRoadmapIcon($stage['icon'] ?? 'fa-circle-nodes'),
            trim((string) ($stage['milestone_note'] ?? $stage['milestone'] ?? '')),
            (int) ($stage['sort_order'] ?? (($index + 1) * 10)),
            !empty($stage['is_visible']) ? 1 : 0,
        ]);
        $stageId = (int) $db->lastInsertId();

        $entries = $stage['entries'] ?? [];
        if (empty($entries)) {
            foreach (($stage['items'] ?? []) as $itemIndex => $item) {
                $entries[] = ['entry_type' => 'item', 'label' => $item, 'sort_order' => ($itemIndex + 1) * 10, 'is_visible' => 1];
            }
            foreach (($stage['goals'] ?? []) as $goalIndex => $goal) {
                $entries[] = ['entry_type' => 'goal', 'label' => $goal, 'sort_order' => ($goalIndex + 1) * 10, 'is_visible' => 1];
            }
        }

        foreach ($entries as $entryIndex => $entry) {
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $entryInsert->execute([
                $stageId,
                normalizeRoadmapEntryType($entry['entry_type'] ?? 'item'),
                $label,
                sanitizeRoadmapIcon($entry['icon'] ?? ''),
                (int) ($entry['sort_order'] ?? (($entryIndex + 1) * 10)),
                !empty($entry['is_visible']) ? 1 : 0,
            ]);
        }
    }
}

function seedDefaultRoadmap(PDO $db = null, ?int $adminId = null, bool $force = false): void {
    $db = $db ?: getDBConnection();
    $data = getDefaultRoadmapData();
    if (!$force) {
        $stmt = $db->query("SELECT COUNT(*) FROM roadmap_settings");
        if ((int) ($stmt ? $stmt->fetchColumn() : 0) > 0) {
            return;
        }
    }

    $db->beginTransaction();
    try {
        roadmapReplaceVersion($db, 'draft', $data, $adminId, null);
        roadmapReplaceVersion($db, 'published', $data, $adminId, new DateTimeImmutable());
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function getRoadmapVersion(string $version, bool $visibleOnly = false, PDO $db = null): array {
    $version = $version === 'published' ? 'published' : 'draft';
    $db = $db ?: getDBConnection();
    ensureRoadmapSchema($db);

    $stmt = $db->prepare("SELECT * FROM roadmap_settings WHERE version_status = ? LIMIT 1");
    $stmt->execute([$version]);
    $settings = $stmt->fetch() ?: [];
    if (!$settings) {
        return getDefaultRoadmapData();
    }

    $whereVisible = $visibleOnly ? ' AND is_visible = 1' : '';
    $stageStmt = $db->prepare("SELECT * FROM roadmap_stages WHERE version_status = ? {$whereVisible} ORDER BY sort_order ASC, id ASC");
    $stageStmt->execute([$version]);
    $stages = $stageStmt->fetchAll() ?: [];

    $entryStmt = $db->prepare("SELECT * FROM roadmap_stage_entries WHERE stage_id = ? " . ($visibleOnly ? "AND is_visible = 1 " : "") . "ORDER BY sort_order ASC, id ASC");
    foreach ($stages as &$stage) {
        $entryStmt->execute([(int) $stage['id']]);
        $entries = $entryStmt->fetchAll() ?: [];
        $stage['number'] = (string) $stage['stage_number'];
        $stage['status'] = (string) $stage['status_label'];
        $stage['milestone'] = (string) ($stage['milestone_note'] ?? '');
        $stage['items'] = [];
        $stage['goals'] = [];
        $stage['entries'] = [];
        $seenEntries = [];
        foreach ($entries as $entry) {
            $entryType = normalizeRoadmapEntryType($entry['entry_type'] ?? 'item');
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $entryKey = $entryType . ':' . mb_strtolower($label);
            if (isset($seenEntries[$entryKey])) {
                continue;
            }
            $seenEntries[$entryKey] = true;

            $normalized = [
                'id' => (int) $entry['id'],
                'entry_type' => $entryType,
                'label' => $label,
                'icon' => (string) ($entry['icon'] ?? ''),
                'sort_order' => (int) $entry['sort_order'],
                'is_visible' => (int) $entry['is_visible'],
            ];
            $stage['entries'][] = $normalized;
            if ($normalized['entry_type'] === 'goal') {
                $stage['goals'][] = $normalized['label'];
            } else {
                $stage['items'][] = $normalized['label'];
            }
        }
    }
    unset($stage);

    return [
        'settings' => [
            'title' => (string) $settings['title'],
            'title_gold_word' => (string) $settings['title_gold_word'],
            'subtitle' => (string) $settings['subtitle'],
            'eyebrow' => (string) $settings['eyebrow'],
            'progress_label' => (string) $settings['progress_label'],
            'progress_percent' => (int) $settings['progress_percent'],
            'progress_note' => (string) $settings['progress_note'],
            'bottom_statement' => (string) $settings['bottom_statement'],
            'published_at' => $settings['published_at'] ?? null,
            'updated_at' => $settings['updated_at'] ?? null,
        ],
        'stages' => $stages,
    ];
}

function getPublishedRoadmap(PDO $db = null): array {
    try {
        return getRoadmapVersion('published', true, $db);
    } catch (Throwable $e) {
        return getDefaultRoadmapData();
    }
}

function getAdminRoadmapDraft(PDO $db = null): array {
    return getRoadmapVersion('draft', false, $db);
}

function publishRoadmapDraft(?int $adminId = null, PDO $db = null): void {
    $db = $db ?: getDBConnection();
    ensureRoadmapSchema($db);
    $draft = getRoadmapVersion('draft', false, $db);

    $db->beginTransaction();
    try {
        roadmapReplaceVersion($db, 'published', $draft, $adminId, new DateTimeImmutable());
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
?>
