<?php
$page_title = 'Roadmap Builder';
$activePage = 'roadmap';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
ensureRoadmapSchema($db);

$success_message = '';
$error_message = '';
$current_admin_id = (int) ($current_admin['id'] ?? 0);

$cleanText = static function ($value, int $limit = 255): string {
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));
    return substr($value, 0, $limit);
};

$collectRoadmapFromPost = static function (array $post) use ($cleanText): array {
    $settings = $post['settings'] ?? [];
    if (!is_array($settings)) {
        $settings = [];
    }

    $data = [
        'settings' => [
            'title' => $cleanText($settings['title'] ?? 'The Road to Web3 Trust', 180) ?: 'The Road to Web3 Trust',
            'title_gold_word' => $cleanText($settings['title_gold_word'] ?? 'Web3', 80),
            'subtitle' => $cleanText($settings['subtitle'] ?? '', 300),
            'eyebrow' => $cleanText($settings['eyebrow'] ?? 'CoinRex Mission Journey', 120) ?: 'CoinRex Mission Journey',
            'progress_label' => $cleanText($settings['progress_label'] ?? 'Stage 01 Progress', 120) ?: 'Stage 01 Progress',
            'progress_percent' => max(0, min(100, (int) ($settings['progress_percent'] ?? 0))),
            'progress_note' => $cleanText($settings['progress_note'] ?? '', 300),
            'bottom_statement' => $cleanText($settings['bottom_statement'] ?? 'Users First. Utility First. Token Later.', 220) ?: 'Users First. Utility First. Token Later.',
        ],
        'stages' => [],
    ];

    $stages = $post['stages'] ?? [];
    if (!is_array($stages)) {
        return $data;
    }

    foreach ($stages as $stageIndex => $stage) {
        if (!is_array($stage) || !empty($stage['delete'])) {
            continue;
        }

        $title = $cleanText($stage['title'] ?? '', 160);
        if ($title === '') {
            continue;
        }

        $entries = [];
        $postedEntries = $stage['entries'] ?? [];
        if (is_array($postedEntries)) {
            foreach ($postedEntries as $entryIndex => $entry) {
                if (!is_array($entry) || !empty($entry['delete'])) {
                    continue;
                }

                $label = $cleanText($entry['label'] ?? '', 180);
                if ($label === '') {
                    continue;
                }

                $entries[] = [
                    'entry_type' => normalizeRoadmapEntryType($entry['entry_type'] ?? 'item'),
                    'label' => $label,
                    'icon' => sanitizeRoadmapIcon($entry['icon'] ?? ''),
                    'sort_order' => (int) ($entry['sort_order'] ?? (($entryIndex + 1) * 10)),
                    'is_visible' => !empty($entry['is_visible']) ? 1 : 0,
                ];
            }
        }

        $data['stages'][] = [
            'stage_number' => $cleanText($stage['stage_number'] ?? sprintf('%02d', count($data['stages']) + 1), 20) ?: sprintf('%02d', count($data['stages']) + 1),
            'title' => $title,
            'status_label' => $cleanText($stage['status_label'] ?? '', 120),
            'badge' => normalizeRoadmapBadge($stage['badge'] ?? 'PLANNED'),
            'tone' => normalizeRoadmapTone($stage['tone'] ?? 'planned'),
            'icon' => sanitizeRoadmapIcon($stage['icon'] ?? 'fa-circle-nodes'),
            'milestone_note' => trim((string) ($stage['milestone_note'] ?? '')),
            'sort_order' => (int) ($stage['sort_order'] ?? (($stageIndex + 1) * 10)),
            'is_visible' => !empty($stage['is_visible']) ? 1 : 0,
            'entries' => $entries,
        ];
    }

    return $data;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_draft' || $action === 'publish_draft') {
            $data = $collectRoadmapFromPost($_POST);
            $db->beginTransaction();
            try {
                roadmapReplaceVersion($db, 'draft', $data, $current_admin_id, null);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            if ($action === 'publish_draft') {
                publishRoadmapDraft($current_admin_id, $db);
                $success_message = 'Roadmap draft saved and published to the public page.';
            } else {
                $success_message = 'Roadmap draft saved.';
            }
        } elseif ($action === 'restore_defaults') {
            seedDefaultRoadmap($db, $current_admin_id, true);
            $success_message = 'Default roadmap restored and published.';
        } else {
            $error_message = 'Invalid roadmap action.';
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$roadmap = getAdminRoadmapDraft($db);
$published = getRoadmapVersion('published', true, $db);
$settings = $roadmap['settings'] ?? getDefaultRoadmapData()['settings'];
$stages = $roadmap['stages'] ?? [];

$visible_stage_count = 0;
$total_entry_count = 0;
foreach ($stages as $stage) {
    if (!empty($stage['is_visible'])) {
        $visible_stage_count++;
    }
    $total_entry_count += count((array) ($stage['entries'] ?? []));
}

$published_at = trim((string) ($published['settings']['published_at'] ?? ''));
$draft_updated_at = trim((string) ($settings['updated_at'] ?? ''));

$stageTemplate = [
    'id' => 0,
    'stage_number' => '',
    'title' => '',
    'status_label' => '',
    'badge' => 'PLANNED',
    'tone' => 'planned',
    'icon' => 'fa-circle-nodes',
    'milestone_note' => '',
    'sort_order' => (count($stages) + 1) * 10,
    'is_visible' => 1,
    'entries' => [],
];
$modalStages = $stages;
$modalStages[] = $stageTemplate;
?>

<style>
.roadmap-admin { display: grid; gap: 18px; padding: 0 22px 28px; max-width: 1440px; margin: 0 auto; }
.roadmap-admin form { display: grid; gap: 18px; }
.roadmap-admin-hero { position: relative; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 20px; align-items: center; min-height: 154px; padding: 28px; border: 1px solid rgba(148,163,184,.16); border-radius: 18px; background: radial-gradient(circle at 0% 0%, rgba(245,158,11,.14), transparent 30%), radial-gradient(circle at 92% 0%, rgba(37,99,235,.18), transparent 34%), linear-gradient(135deg, rgba(15,23,42,.96), rgba(15,34,67,.86)); box-shadow: 0 22px 60px rgba(2,6,23,.28), inset 0 1px 0 rgba(255,255,255,.04); }
.roadmap-admin-hero::after { content: ""; position: absolute; width: 1px; inset: 22px auto 22px 52%; background: linear-gradient(180deg, transparent, rgba(148,163,184,.18), transparent); pointer-events: none; }
.roadmap-admin-hero > * { position: relative; z-index: 1; }
.roadmap-admin-kicker { display: inline-flex; align-items: center; gap: 8px; width: fit-content; padding: 7px 11px; border-radius: 999px; border: 1px solid rgba(245,158,11,.30); background: rgba(245,158,11,.11); color: #fbbf24; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
.roadmap-admin-hero h2 { margin: 12px 0 8px; color: #f8fafc; font-size: clamp(1.55rem, 3vw, 2.15rem); line-height: 1.12; letter-spacing: 0; }
.roadmap-admin-hero p { max-width: 760px; margin: 0; color: #cbd5e1; line-height: 1.65; font-size: 14px; }
.roadmap-admin-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; align-items: center; }
.roadmap-admin-actions .btn { min-height: 40px; }
.roadmap-admin-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.roadmap-admin-metric { min-height: 92px; border: 1px solid rgba(148,163,184,.13); border-radius: 16px; padding: 16px; background: linear-gradient(180deg, rgba(15,23,42,.78), rgba(15,23,42,.54)); box-shadow: inset 0 1px 0 rgba(255,255,255,.035); }
.roadmap-admin-metric span { display: block; color: #94a3b8; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .035em; }
.roadmap-admin-metric strong { display: block; margin-top: 10px; color: #f8fafc; font-size: 19px; line-height: 1.2; overflow-wrap: anywhere; }
.roadmap-admin-message { padding: 13px 15px; border-radius: 14px; font-weight: 800; box-shadow: inset 0 1px 0 rgba(255,255,255,.04); }
.roadmap-admin-message.success { background: rgba(34,197,94,.11); color: #86efac; border: 1px solid rgba(34,197,94,.22); }
.roadmap-admin-message.error { background: rgba(239,68,68,.11); color: #fca5a5; border: 1px solid rgba(239,68,68,.22); }
.roadmap-admin-panel { border: 1px solid rgba(148,163,184,.13); border-radius: 18px; overflow: hidden; background: rgba(15,23,42,.66); box-shadow: 0 16px 42px rgba(2,6,23,.18), inset 0 1px 0 rgba(255,255,255,.035); }
.roadmap-admin-panel-head { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 17px 20px; border-bottom: 1px solid rgba(148,163,184,.12); background: linear-gradient(180deg, rgba(15,23,42,.78), rgba(2,6,23,.20)); }
.roadmap-admin-panel-head h3 { margin: 0; color: #f8fafc; display: inline-flex; gap: 9px; align-items: center; font-size: 17px; line-height: 1.25; }
.roadmap-admin-panel-head i { color: #f59e0b; }
.roadmap-admin-body { padding: 20px; display: grid; gap: 16px; }
.roadmap-admin-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.roadmap-admin-grid.is-three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.roadmap-admin label { display: grid; gap: 7px; color: #a8b6cc; font-size: 12px; font-weight: 800; line-height: 1.35; }
.roadmap-admin input, .roadmap-admin textarea, .roadmap-admin select { width: 100%; min-height: 42px; border: 1px solid rgba(148,163,184,.18); border-radius: 12px; background: rgba(2,6,23,.42); color: #e2e8f0; padding: 10px 12px; font: inherit; transition: border-color .16s ease, box-shadow .16s ease, background .16s ease; }
.roadmap-admin textarea { min-height: 88px; resize: vertical; line-height: 1.55; }
.roadmap-admin input[type="checkbox"] { width: 16px; height: 16px; min-height: 16px; padding: 0; accent-color: #2563eb; }
.roadmap-admin input:focus, .roadmap-admin textarea:focus, .roadmap-admin select:focus { outline: none; border-color: rgba(96,165,250,.66); background: rgba(2,6,23,.56); box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
.roadmap-muted { color: #64748b; font-size: 12px; font-weight: 700; }
.roadmap-workflow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.roadmap-workflow-card { display: grid; grid-template-columns: 44px minmax(0, 1fr); gap: 13px; align-items: center; min-height: 96px; padding: 16px; border: 1px solid rgba(148,163,184,.12); border-radius: 16px; background: rgba(15,23,42,.52); box-shadow: inset 0 1px 0 rgba(255,255,255,.025); }
.roadmap-workflow-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; min-width: 44px; border: 1px solid rgba(96,165,250,.20); border-radius: 14px; background: linear-gradient(180deg, rgba(37,99,235,.22), rgba(37,99,235,.10)); color: #93c5fd; box-shadow: inset 0 1px 0 rgba(255,255,255,.05); }
.roadmap-workflow-icon i { display: block; width: 18px; height: 18px; font-size: 18px; line-height: 18px; text-align: center; color: inherit; }
.roadmap-workflow-card strong { display: block; color: #f8fafc; font-size: 14px; }
.roadmap-workflow-card > div > span { display: block; margin-top: 4px; color: #94a3b8; font-size: 12px; line-height: 1.45; }
.roadmap-help { display: flex; gap: 10px; align-items: flex-start; padding: 13px 14px; border: 1px solid rgba(37,99,235,.18); border-radius: 14px; background: rgba(37,99,235,.08); color: #bfdbfe; }
.roadmap-help i { margin-top: 2px; color: #93c5fd; }
.roadmap-help p { margin: 0; color: #b9c7e8; font-size: 13px; line-height: 1.55; }
.roadmap-field-note { display: block; margin-top: -2px; color: #64748b; font-size: 11px; font-weight: 700; line-height: 1.35; }
.roadmap-stage-pill { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(148,163,184,.16); background: rgba(15,23,42,.7); color: #cbd5e1; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.roadmap-stage-pill.is-current { color: #f59e0b; border-color: rgba(245,158,11,.28); background: rgba(245,158,11,.10); }
.roadmap-section-subtitle { margin: 3px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.45; }
.roadmap-stage-overview { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: stretch; }
.roadmap-stage-card { display: grid; gap: 14px; padding: 17px; border: 1px solid rgba(148,163,184,.13); border-radius: 16px; background: linear-gradient(180deg, rgba(15,23,42,.72), rgba(15,23,42,.52)); box-shadow: inset 0 1px 0 rgba(255,255,255,.035); transition: border-color .16s ease, transform .16s ease, box-shadow .16s ease; }
.roadmap-stage-card:hover { border-color: rgba(96,165,250,.24); transform: translateY(-1px); box-shadow: 0 16px 34px rgba(2,6,23,.18), inset 0 1px 0 rgba(255,255,255,.035); }
.roadmap-stage-card.is-hidden { opacity: .72; border-style: dashed; }
.roadmap-stage-card-top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.roadmap-stage-title { display: grid; gap: 8px; min-width: 0; }
.roadmap-stage-title h4 { margin: 0; color: #f8fafc; font-size: 17px; line-height: 1.25; }
.roadmap-stage-title small { color: #94a3b8; font-weight: 800; }
.roadmap-stage-meta { display: flex; gap: 8px; flex-wrap: wrap; }
.roadmap-mini-list { display: flex; flex-wrap: wrap; gap: 8px; min-height: 30px; }
.roadmap-mini-list span { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 7px 9px; border: 1px solid rgba(148,163,184,.12); border-radius: 999px; background: rgba(2,6,23,.28); color: #cbd5e1; font-size: 12px; font-weight: 800; overflow-wrap: anywhere; }
.roadmap-mini-list i { color: #f59e0b; font-size: 10px; }
.roadmap-card-note { display: flex; gap: 8px; align-items: flex-start; color: #f59e0b; font-size: 12px; line-height: 1.5; }
.roadmap-card-note i { margin-top: 3px; }
.roadmap-save-bar { position: sticky; bottom: 14px; z-index: 8; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 12px; border: 1px solid rgba(148,163,184,.18); border-radius: 16px; background: rgba(15,23,42,.96); backdrop-filter: blur(14px); box-shadow: 0 18px 50px rgba(2,6,23,.38), inset 0 1px 0 rgba(255,255,255,.04); }
.roadmap-save-bar .danger { margin-left: auto; }
.roadmap-save-copy { display: grid; gap: 2px; color: #94a3b8; font-size: 12px; line-height: 1.35; }
.roadmap-save-copy strong { color: #e2e8f0; font-size: 13px; }
.roadmap-modal-overlay { position: fixed; inset: 0; z-index: 2200; display: none; align-items: center; justify-content: center; padding: 22px; background: rgba(2,6,23,.76); backdrop-filter: blur(10px); }
.roadmap-modal-overlay.is-open { display: flex; }
.roadmap-modal { width: min(980px, 100%); max-height: min(88vh, 920px); display: grid; grid-template-rows: auto minmax(0, 1fr); overflow: hidden; border: 1px solid rgba(148,163,184,.20); border-radius: 18px; background: #0f172a; box-shadow: 0 30px 100px rgba(0,0,0,.56), inset 0 1px 0 rgba(255,255,255,.04); }
.roadmap-modal.is-narrow { width: min(780px, 100%); }
.roadmap-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 17px 20px; border-bottom: 1px solid rgba(148,163,184,.12); background: linear-gradient(180deg, rgba(15,23,42,.9), rgba(2,6,23,.22)); }
.roadmap-modal-head h3 { margin: 0; color: #f8fafc; font-size: 18px; display: inline-flex; align-items: center; gap: 9px; }
.roadmap-modal-head i { color: #f59e0b; }
.roadmap-modal-close { width: 38px; height: 38px; border: 1px solid rgba(148,163,184,.16); border-radius: 12px; background: rgba(15,23,42,.82); color: #cbd5e1; cursor: pointer; transition: border-color .16s ease, color .16s ease, background .16s ease; }
.roadmap-modal-close:hover { border-color: rgba(248,113,113,.32); color: #fecaca; background: rgba(127,29,29,.22); }
.roadmap-modal-body { overflow: auto; padding: 20px; display: grid; gap: 15px; }
.roadmap-entry-row { display: grid; grid-template-columns: 110px minmax(0, 1fr) 92px 100px 100px; gap: 10px; align-items: end; padding: 12px; border: 1px solid rgba(148,163,184,.10); border-radius: 14px; background: rgba(2,6,23,.22); }
.roadmap-modal-footer { position: sticky; bottom: -18px; display: flex; gap: 10px; justify-content: flex-end; padding: 12px 0 0; background: linear-gradient(180deg, rgba(15,23,42,0), #0f172a 28%); }
@media (max-width: 1120px) { .roadmap-admin-hero { grid-template-columns: 1fr; } .roadmap-admin-hero::after { display: none; } .roadmap-admin-actions { justify-content: flex-start; } .roadmap-entry-row { grid-template-columns: 1fr 1fr; } }
@media (max-width: 900px) { .roadmap-workflow, .roadmap-stage-overview { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .roadmap-admin { padding: 0 14px 18px; } .roadmap-admin-hero, .roadmap-admin-body, .roadmap-modal-body { padding: 16px; } .roadmap-admin-panel-head { align-items: flex-start; flex-direction: column; padding: 15px 16px; } .roadmap-admin-panel-head .btn { width: 100%; justify-content: center; } .roadmap-admin-metrics, .roadmap-admin-grid, .roadmap-admin-grid.is-three { grid-template-columns: 1fr; } .roadmap-stage-card-top { flex-direction: column; } .roadmap-stage-card-top .btn { width: 100%; justify-content: center; } .roadmap-save-bar .btn, .roadmap-save-bar .danger { width: 100%; margin-left: 0; justify-content: center; } .roadmap-save-copy { width: 100%; } .roadmap-entry-row { grid-template-columns: 1fr; } }
</style>

<div class="roadmap-admin">
    <?php if ($success_message !== ''): ?><div class="roadmap-admin-message success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error_message !== ''): ?><div class="roadmap-admin-message error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="roadmap-admin-hero">
        <div>
            <span class="roadmap-admin-kicker"><i class="fas fa-route"></i> Public Roadmap</span>
            <h2>Roadmap Builder</h2>
            <p>Manage the roadmap from simple stage cards. Edit details in popups, save privately as draft, and publish only when ready.</p>
        </div>
        <div class="roadmap-admin-actions">
            <button type="button" class="btn btn-secondary" data-roadmap-modal-target="roadmapHeroModal"><i class="fas fa-pen"></i> Edit Hero</button>
            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/public/roadmap.php" target="_blank" rel="noopener"><i class="fas fa-eye"></i> Preview Public</a>
        </div>
    </section>

    <section class="roadmap-admin-metrics">
        <div class="roadmap-admin-metric"><span>Published</span><strong><?php echo $published_at !== '' ? htmlspecialchars($published_at, ENT_QUOTES, 'UTF-8') : 'Not yet'; ?></strong></div>
        <div class="roadmap-admin-metric"><span>Draft Updated</span><strong><?php echo $draft_updated_at !== '' ? htmlspecialchars($draft_updated_at, ENT_QUOTES, 'UTF-8') : 'New draft'; ?></strong></div>
        <div class="roadmap-admin-metric"><span>Visible Stages</span><strong><?php echo (int) $visible_stage_count; ?></strong></div>
        <div class="roadmap-admin-metric"><span>Items + Goals</span><strong><?php echo (int) $total_entry_count; ?></strong></div>
    </section>

    <section class="roadmap-workflow" aria-label="Roadmap editing workflow">
        <div class="roadmap-workflow-card"><span class="roadmap-workflow-icon"><i class="fas fa-layer-group"></i></span><div><strong>1. Review cards</strong><span>Only stages and short details are shown on the page.</span></div></div>
        <div class="roadmap-workflow-card"><span class="roadmap-workflow-icon"><i class="fas fa-pen"></i></span><div><strong>2. Edit in popup</strong><span>Click Edit on a stage to update items, goals, notes, and visibility.</span></div></div>
        <div class="roadmap-workflow-card"><span class="roadmap-workflow-icon"><i class="fas fa-upload"></i></span><div><strong>3. Publish</strong><span>Save Draft stays private. Publish makes the current form live.</span></div></div>
    </section>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <section class="roadmap-admin-panel">
            <div class="roadmap-admin-panel-head">
                <div>
                    <h3><i class="fas fa-pen-nib"></i> Hero Preview</h3>
                    <p class="roadmap-section-subtitle">Use Edit Hero if you want to change the title, progress, or bottom statement.</p>
                </div>
                <button type="button" class="btn btn-secondary" data-roadmap-modal-target="roadmapHeroModal"><i class="fas fa-pen"></i> Edit Hero</button>
            </div>
            <div class="roadmap-admin-body">
                <article class="roadmap-stage-card">
                    <div class="roadmap-stage-title">
                        <span class="roadmap-stage-pill is-current"><?php echo (int) ($settings['progress_percent'] ?? 0); ?>% progress</span>
                        <h4><?php echo htmlspecialchars((string) ($settings['title'] ?? 'The Road to Web3 Trust'), ENT_QUOTES, 'UTF-8'); ?></h4>
                        <small><?php echo htmlspecialchars((string) ($settings['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <div class="roadmap-mini-list">
                        <span><i class="fas fa-tag"></i><?php echo htmlspecialchars((string) ($settings['eyebrow'] ?? 'CoinRex Mission Journey'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class="fas fa-gauge-high"></i><?php echo htmlspecialchars((string) ($settings['progress_label'] ?? 'Stage 01 Progress'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class="fas fa-star"></i>Gold word: <?php echo htmlspecialchars((string) ($settings['title_gold_word'] ?? 'Web3'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="roadmap-card-note"><i class="fas fa-circle-info"></i><span><?php echo htmlspecialchars((string) ($settings['progress_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
                </article>
            </div>
        </section>

        <div class="roadmap-modal-overlay" id="roadmapHeroModal" aria-hidden="true">
            <div class="roadmap-modal is-narrow" role="dialog" aria-modal="true" aria-labelledby="roadmapHeroModalTitle">
                <div class="roadmap-modal-head">
                    <h3 id="roadmapHeroModalTitle"><i class="fas fa-pen-nib"></i> Edit Hero Settings</h3>
                    <button type="button" class="roadmap-modal-close" data-roadmap-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="roadmap-modal-body">
                    <div class="roadmap-help"><i class="fas fa-circle-info"></i><p><strong>Beginner tip:</strong> The "Gold Word" field highlights one word in solid CoinRex gold on the public page.</p></div>
                    <div class="roadmap-admin-grid">
                        <label>Title <input name="settings[title]" value="<?php echo htmlspecialchars((string) ($settings['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Example: The Road to Web3 Trust</span></label>
                        <label>Gold Word <input name="settings[title_gold_word]" value="<?php echo htmlspecialchars((string) ($settings['title_gold_word'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Example: Web3</span></label>
                    </div>
                    <label>Subtitle <input name="settings[subtitle]" value="<?php echo htmlspecialchars((string) ($settings['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                    <div class="roadmap-admin-grid is-three">
                        <label>Eyebrow <input name="settings[eyebrow]" value="<?php echo htmlspecialchars((string) ($settings['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                        <label>Progress Label <input name="settings[progress_label]" value="<?php echo htmlspecialchars((string) ($settings['progress_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                        <label>Progress % <input type="number" min="0" max="100" name="settings[progress_percent]" value="<?php echo (int) ($settings['progress_percent'] ?? 0); ?>"></label>
                    </div>
                    <label>Progress Note <input name="settings[progress_note]" value="<?php echo htmlspecialchars((string) ($settings['progress_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                    <label>Bottom Statement <input name="settings[bottom_statement]" value="<?php echo htmlspecialchars((string) ($settings['bottom_statement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Example: Users First. Utility First. Token Later.</span></label>
                    <div class="roadmap-modal-footer"><button type="button" class="btn btn-secondary" data-roadmap-modal-close>Done</button></div>
                </div>
            </div>
        </div>

        <section class="roadmap-admin-panel" style="margin-top:18px;">
            <div class="roadmap-admin-panel-head">
                <div>
                    <h3><i class="fas fa-layer-group"></i> Stages</h3>
                    <p class="roadmap-section-subtitle">Click Edit on any card to update details in a popup. This keeps the page short and manageable.</p>
                </div>
                <button type="button" class="btn btn-primary" data-roadmap-modal-target="roadmapStageModalNew"><i class="fas fa-plus"></i> Add Stage</button>
            </div>
            <div class="roadmap-admin-body">
                <div class="roadmap-help"><i class="fas fa-wand-magic-sparkles"></i><p><strong>Easy editing:</strong> Cards show only the stage summary. Empty rows inside edit popups are ignored when saving.</p></div>
                <?php if (empty($stages)): ?>
                    <div class="roadmap-help"><i class="fas fa-circle-info"></i><p>No stages exist yet. Click Add Stage to create the first roadmap stage.</p></div>
                <?php else: ?>
                    <div class="roadmap-stage-overview">
                        <?php foreach ($stages as $stageIndex => $stage): ?>
                            <?php
                            $entries = (array) ($stage['entries'] ?? []);
                            $items = array_values(array_filter($entries, static function ($entry) { return (string) ($entry['entry_type'] ?? 'item') === 'item' && !empty($entry['is_visible']); }));
                            $goals = array_values(array_filter($entries, static function ($entry) { return (string) ($entry['entry_type'] ?? 'item') === 'goal' && !empty($entry['is_visible']); }));
                            $previewEntries = array_slice(array_merge($items, $goals), 0, 5);
                            $modalId = 'roadmapStageModal' . $stageIndex;
                            ?>
                            <article class="roadmap-stage-card <?php echo empty($stage['is_visible']) ? 'is-hidden' : ''; ?>">
                                <div class="roadmap-stage-card-top">
                                    <div class="roadmap-stage-title">
                                        <span class="roadmap-stage-pill <?php echo normalizeRoadmapTone($stage['tone'] ?? '') === 'current' ? 'is-current' : ''; ?>"><?php echo htmlspecialchars(normalizeRoadmapBadge($stage['badge'] ?? 'PLANNED'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <h4>Stage <?php echo htmlspecialchars((string) ($stage['stage_number'] ?? $stage['number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($stage['title'] ?? 'Stage'), ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <small><?php echo htmlspecialchars((string) ($stage['status_label'] ?? $stage['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                    <button type="button" class="btn btn-secondary" data-roadmap-modal-target="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-pen"></i> Edit</button>
                                </div>
                                <div class="roadmap-stage-meta">
                                    <span class="roadmap-stage-pill"><i class="fas <?php echo htmlspecialchars((string) ($stage['icon'] ?? 'fa-circle-nodes'), ENT_QUOTES, 'UTF-8'); ?>"></i> Icon</span>
                                    <span class="roadmap-stage-pill"><?php echo count($items); ?> items</span>
                                    <span class="roadmap-stage-pill"><?php echo count($goals); ?> goals</span>
                                    <?php if (empty($stage['is_visible'])): ?><span class="roadmap-stage-pill">Hidden</span><?php endif; ?>
                                </div>
                                <div class="roadmap-mini-list">
                                    <?php if (empty($previewEntries)): ?>
                                        <span><i class="fas fa-circle"></i>No entries yet</span>
                                    <?php else: ?>
                                        <?php foreach ($previewEntries as $entry): ?><span><i class="fas <?php echo (string) ($entry['entry_type'] ?? 'item') === 'goal' ? 'fa-bullseye' : 'fa-check'; ?>"></i><?php echo htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (trim((string) ($stage['milestone_note'] ?? $stage['milestone'] ?? '')) !== ''): ?><div class="roadmap-card-note"><i class="fas fa-bolt"></i><span><?php echo htmlspecialchars((string) ($stage['milestone_note'] ?? $stage['milestone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php foreach ($modalStages as $stageIndex => $stage): ?>
            <?php
            $isNew = empty($stage['id']) && trim((string) ($stage['title'] ?? '')) === '';
            $entries = $stage['entries'] ?? [];
            $blankEntryCount = $isNew ? 5 : 4;
            for ($i = 0; $i < $blankEntryCount; $i++) {
                $entries[] = ['entry_type' => 'item', 'label' => '', 'icon' => '', 'sort_order' => (count($entries) + 1) * 10, 'is_visible' => 1];
            }
            $modalId = $isNew ? 'roadmapStageModalNew' : ('roadmapStageModal' . $stageIndex);
            ?>
            <div class="roadmap-modal-overlay" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                <div class="roadmap-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo htmlspecialchars($modalId . 'Title', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="roadmap-modal-head">
                        <h3 id="<?php echo htmlspecialchars($modalId . 'Title', ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-layer-group"></i> <?php echo $isNew ? 'Add New Stage' : 'Edit Stage'; ?></h3>
                        <button type="button" class="roadmap-modal-close" data-roadmap-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="roadmap-modal-body">
                        <div class="roadmap-help"><i class="fas fa-circle-info"></i><p><strong>Simple rule:</strong> Fill the stage title and any entry labels you need. Empty entry rows are ignored when saving.</p></div>
                        <div class="roadmap-admin-grid is-three">
                            <label>Stage Number <input name="stages[<?php echo $stageIndex; ?>][stage_number]" value="<?php echo htmlspecialchars((string) ($stage['stage_number'] ?? $stage['number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Example: 01, 02, 03</span></label>
                            <label>Title <input name="stages[<?php echo $stageIndex; ?>][title]" value="<?php echo htmlspecialchars((string) ($stage['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Required. Blank new stages are ignored.</span></label>
                            <label>Sort Order <input type="number" name="stages[<?php echo $stageIndex; ?>][sort_order]" value="<?php echo (int) ($stage['sort_order'] ?? (($stageIndex + 1) * 10)); ?>"><span class="roadmap-field-note">Lower number appears first.</span></label>
                        </div>
                        <div class="roadmap-admin-grid is-three">
                            <label>Status Label <input name="stages[<?php echo $stageIndex; ?>][status_label]" value="<?php echo htmlspecialchars((string) ($stage['status_label'] ?? $stage['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><span class="roadmap-field-note">Example: Current Stage</span></label>
                            <label>Badge <select name="stages[<?php echo $stageIndex; ?>][badge]"><?php foreach (['CURRENT', 'NEXT', 'PLANNED', 'FUTURE'] as $badge): ?><option value="<?php echo $badge; ?>" <?php echo normalizeRoadmapBadge($stage['badge'] ?? '') === $badge ? 'selected' : ''; ?>><?php echo $badge; ?></option><?php endforeach; ?></select></label>
                            <label>Tone <select name="stages[<?php echo $stageIndex; ?>][tone]"><?php foreach (['current', 'next', 'planned', 'future'] as $tone): ?><option value="<?php echo $tone; ?>" <?php echo normalizeRoadmapTone($stage['tone'] ?? '') === $tone ? 'selected' : ''; ?>><?php echo ucfirst($tone); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="roadmap-admin-grid">
                            <label>FontAwesome Icon <input name="stages[<?php echo $stageIndex; ?>][icon]" value="<?php echo htmlspecialchars((string) ($stage['icon'] ?? 'fa-circle-nodes'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="fa-rocket"><span class="roadmap-field-note">Examples: fa-rocket, fa-coins, fa-chart-line.</span></label>
                            <label style="align-content:end;display:flex;grid-template-columns:none;gap:8px;align-items:center;padding-bottom:10px;"><input type="checkbox" name="stages[<?php echo $stageIndex; ?>][is_visible]" value="1" <?php echo !empty($stage['is_visible']) ? 'checked' : ''; ?>> Visible on public roadmap</label>
                        </div>
                        <label>Milestone Note <textarea name="stages[<?php echo $stageIndex; ?>][milestone_note]"><?php echo htmlspecialchars((string) ($stage['milestone_note'] ?? $stage['milestone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea><span class="roadmap-field-note">Optional note displayed below the stage items.</span></label>
                        <?php if (!$isNew): ?><label style="display:flex;grid-template-columns:none;gap:8px;align-items:center;color:#fca5a5;"><input type="checkbox" name="stages[<?php echo $stageIndex; ?>][delete]" value="1"> Delete this stage when saving</label><?php endif; ?>
                        <strong style="color:#f8fafc;">Entries <span class="roadmap-muted">- items are features, goals are target counters</span></strong>
                        <?php foreach ($entries as $entryIndex => $entry): ?>
                            <div class="roadmap-entry-row">
                                <label>Type <select name="stages[<?php echo $stageIndex; ?>][entries][<?php echo $entryIndex; ?>][entry_type]"><option value="item" <?php echo normalizeRoadmapEntryType($entry['entry_type'] ?? '') === 'item' ? 'selected' : ''; ?>>Item</option><option value="goal" <?php echo normalizeRoadmapEntryType($entry['entry_type'] ?? '') === 'goal' ? 'selected' : ''; ?>>Goal</option></select></label>
                                <label>Label <input name="stages[<?php echo $stageIndex; ?>][entries][<?php echo $entryIndex; ?>][label]" value="<?php echo htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                                <label>Order <input type="number" name="stages[<?php echo $stageIndex; ?>][entries][<?php echo $entryIndex; ?>][sort_order]" value="<?php echo (int) ($entry['sort_order'] ?? (($entryIndex + 1) * 10)); ?>"></label>
                                <label style="display:flex;grid-template-columns:none;gap:6px;align-items:center;padding-bottom:10px;"><input type="checkbox" name="stages[<?php echo $stageIndex; ?>][entries][<?php echo $entryIndex; ?>][is_visible]" value="1" <?php echo !empty($entry['is_visible']) ? 'checked' : ''; ?>> Visible</label>
                                <?php if (!empty($entry['label'])): ?><label style="display:flex;grid-template-columns:none;gap:6px;align-items:center;padding-bottom:10px;"><input type="checkbox" name="stages[<?php echo $stageIndex; ?>][entries][<?php echo $entryIndex; ?>][delete]" value="1"> Delete</label><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="roadmap-modal-footer"><button type="button" class="btn btn-secondary" data-roadmap-modal-close>Done</button></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="roadmap-save-bar">
            <div class="roadmap-save-copy"><strong>Draft workflow</strong><span>Save keeps changes private. Publish makes current form live.</span></div>
            <button type="submit" name="action" value="save_draft" class="btn btn-primary"><i class="fas fa-save"></i> Save Draft</button>
            <button type="submit" name="action" value="publish_draft" class="btn btn-success"><i class="fas fa-upload"></i> Publish Draft</button>
            <button type="submit" name="action" value="restore_defaults" class="btn btn-danger danger" onclick="return confirm('Restore the default roadmap and publish it? This replaces current draft and published roadmap content.');"><i class="fas fa-rotate-left"></i> Restore Default</button>
        </div>
    </form>
</div>

<script>
(function() {
    var openButtons = document.querySelectorAll('[data-roadmap-modal-target]');
    var closeButtons = document.querySelectorAll('[data-roadmap-modal-close]');

    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var focusTarget = modal.querySelector('input, textarea, select, button');
        if (focusTarget) focusTarget.focus();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.roadmap-modal-overlay.is-open')) {
            document.body.style.overflow = '';
        }
    }

    openButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            openModal(button.getAttribute('data-roadmap-modal-target'));
        });
    });

    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            closeModal(button.closest('.roadmap-modal-overlay'));
        });
    });

    document.addEventListener('click', function(event) {
        if (event.target.classList && event.target.classList.contains('roadmap-modal-overlay')) {
            closeModal(event.target);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.roadmap-modal-overlay.is-open').forEach(closeModal);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
