<?php
/**
 * CoinRex Premium Roadmap Page
 * Location: /coinrex/public/roadmap.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

$roadmap = getPublishedRoadmap();
$roadmap_settings = $roadmap['settings'] ?? getDefaultRoadmapData()['settings'];
$roadmap_stages = $roadmap['stages'] ?? getDefaultRoadmapData()['stages'];
$roadmap_progress = max(0, min(100, (int) ($roadmap_settings['progress_percent'] ?? 0)));
$roadmap_title = trim((string) ($roadmap_settings['title'] ?? 'The Road to Web3 Trust'));
$roadmap_gold_word = trim((string) ($roadmap_settings['title_gold_word'] ?? 'Web3'));

$renderRoadmapTitle = static function (string $title, string $goldWord): string {
    $title = trim($title) !== '' ? trim($title) : 'The Road to Web3 Trust';
    $goldWord = trim($goldWord);
    if ($goldWord === '') {
        return htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    }
    $pos = stripos($title, $goldWord);
    if ($pos === false) {
        return htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    }
    $before = substr($title, 0, $pos);
    $word = substr($title, $pos, strlen($goldWord));
    $after = substr($title, $pos + strlen($goldWord));
    return htmlspecialchars($before, ENT_QUOTES, 'UTF-8')
        . '<span class="roadmap-title-gold">' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</span>'
        . htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
};
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/roadmap.css">

<main class="roadmap-page">
    <section class="roadmap-mission" aria-labelledby="roadmapTitle">
        <div class="roadmap-grid-layer" aria-hidden="true"></div>
        <div class="roadmap-glow roadmap-glow-blue" aria-hidden="true"></div>
        <div class="roadmap-glow roadmap-glow-gold" aria-hidden="true"></div>
        <div class="roadmap-particles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>

        <div class="roadmap-container">
            <header class="roadmap-hero roadmap-reveal">
                <div class="roadmap-eyebrow">
                    <i class="fas fa-shield-halved"></i>
                    <?php echo htmlspecialchars((string) ($roadmap_settings['eyebrow'] ?? 'CoinRex Mission Journey'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <h1 id="roadmapTitle"><?php echo $renderRoadmapTitle($roadmap_title, $roadmap_gold_word); ?></h1>
                <p><?php echo htmlspecialchars((string) ($roadmap_settings['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="roadmap-hero-panel" aria-label="Current roadmap progress">
                    <div class="roadmap-progress-copy">
                        <span><?php echo htmlspecialchars((string) ($roadmap_settings['progress_label'] ?? 'Stage 01 Progress'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $roadmap_progress; ?>%</strong>
                    </div>
                    <div class="roadmap-progress-track">
                        <span style="width: <?php echo $roadmap_progress; ?>%"></span>
                    </div>
                    <div class="roadmap-progress-note">
                        <?php echo htmlspecialchars((string) ($roadmap_settings['progress_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </header>

            <div class="roadmap-stage-path" aria-hidden="true">
                <span class="roadmap-path-core"></span>
                <span class="roadmap-path-signal"></span>
            </div>

            <div class="roadmap-stages">
                <?php foreach ($roadmap_stages as $index => $stage): ?>
                <?php $stageTone = normalizeRoadmapTone($stage['tone'] ?? ''); $stageBadge = $stageTone === 'completed' ? 'COMPLETED' : normalizeRoadmapBadge($stage['badge'] ?? 'PLANNED'); ?>
                <article class="roadmap-stage roadmap-reveal roadmap-stage-<?php echo htmlspecialchars($stageTone, ENT_QUOTES, 'UTF-8'); ?>" style="--roadmap-delay: <?php echo (int) $index * 110; ?>ms">
                    <div class="roadmap-node" aria-hidden="true">
                        <span><?php echo $stageTone === 'completed' ? '&#10003;' : htmlspecialchars($stage['number'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="roadmap-card">
                        <div class="roadmap-card-top">
                            <div class="roadmap-stage-icon">
                                <i class="fas <?php echo htmlspecialchars($stage['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </div>
                            <div>
                                <span class="roadmap-stage-kicker">Stage <?php echo htmlspecialchars($stage['number'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <h2><?php echo htmlspecialchars($stage['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            </div>
                            <span class="roadmap-badge"><?php echo htmlspecialchars($stageBadge, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <p class="roadmap-status"><?php echo htmlspecialchars($stage['status'], ENT_QUOTES, 'UTF-8'); ?></p>

                        <?php if (!empty($stage['items'])): ?>
                        <ul class="roadmap-items">
                            <?php foreach ($stage['items'] as $item_index => $item): ?>
                            <li style="--item-delay: <?php echo (int) $item_index * 55; ?>ms">
                                <i class="fas fa-check"></i>
                                <span><?php echo htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <?php if (!empty($stage['goals'])): ?>
                        <div class="roadmap-goals" aria-label="Stage goals">
                            <?php foreach ($stage['goals'] as $goal): ?>
                            <div class="roadmap-goal">
                                <i class="fas fa-bullseye"></i>
                                <span><?php echo htmlspecialchars($goal, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($stage['milestone'])): ?>
                        <div class="roadmap-milestone">
                            <i class="fas fa-bolt"></i>
                            <span><?php echo htmlspecialchars($stage['milestone'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <footer class="roadmap-bottom roadmap-reveal">
                <?php
                $bottom_parts = array_values(array_filter(array_map('trim', explode('.', (string) ($roadmap_settings['bottom_statement'] ?? 'Users First. Utility First. Token Later.')))));
                foreach ($bottom_parts as $part):
                ?>
                <span><?php echo htmlspecialchars($part . '.', ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            </footer>
        </div>
    </section>
</main>

<script src="<?php echo ASSETS_URL; ?>/js/roadmap.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
