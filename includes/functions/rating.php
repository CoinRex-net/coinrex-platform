<?php
/**
 * Universal rating presentation helpers.
 */

function normalizeRatingProvider($provider) {
    $provider = strtolower(trim((string) $provider));
    $allowed = ['coinrex', 'trustpilot', 'imdb'];
    return in_array($provider, $allowed, true) ? $provider : 'coinrex';
}

function normalizeRatingSize($size) {
    $size = strtolower(trim((string) $size));
    $allowed = ['sm', 'md', 'lg'];
    return in_array($size, $allowed, true) ? $size : 'md';
}

function normalizeRatingVariant($variant) {
    $variant = strtolower(trim((string) $variant));
    $allowed = ['default', 'cr-row-small', 'cr-box-large'];

    if ($variant === '') {
        return 'default';
    }

    return in_array($variant, $allowed, true) ? $variant : 'default';
}

function getRatingProviderMeta($provider) {
    $provider = normalizeRatingProvider($provider);

    $map = [
        'coinrex' => [
            'label' => 'CoinRex Rating',
            'icon' => 'fas fa-shield-star',
            'icon_fallback' => 'fas fa-star',
        ],
        'trustpilot' => [
            'label' => 'TrustPilot Rating',
            'icon' => 'fas fa-badge-check',
            'icon_fallback' => 'fas fa-star',
        ],
        'imdb' => [
            'label' => 'IMDb Rating',
            'icon' => 'fas fa-film',
            'icon_fallback' => 'fas fa-star',
        ],
    ];

    return $map[$provider] ?? $map['coinrex'];
}

function buildRatingScoreLabel($value, $scale) {
    return number_format((float) $value, 1) . '/' . number_format((float) $scale, 1);
}

function renderCoinrexBrandLabel($prefix = 'Coin', $suffix = 'Rex Ratings:') {
    $prefix = htmlspecialchars(trim((string) $prefix), ENT_QUOTES, 'UTF-8');
    $suffix_raw = trim((string) $suffix);
    $rex_text = '';
    $tail_text = $suffix_raw;

    if (stripos($suffix_raw, 'rex') === 0) {
        $rex_text = 'Rex';
        $tail_text = trim((string) substr($suffix_raw, 3));
    }

    if ($tail_text !== '') {
        $tail_text = ' ' . $tail_text;
    }

    $rex = htmlspecialchars($rex_text, ENT_QUOTES, 'UTF-8');
    $tail = htmlspecialchars($tail_text, ENT_QUOTES, 'UTF-8');

    return '<span class="universal-rating__brand">'
        . '<span class="universal-rating__brand-coin">' . $prefix . '</span>'
        . ($rex !== '' ? '<span class="universal-rating__brand-rex">' . $rex . '</span>' : '')
        . ($tail !== '' ? '<span class="universal-rating__brand-suffix">' . $tail . '</span>' : '')
        . '</span>';
}

function renderUniversalRating(array $config = []) {
    $provider = normalizeRatingProvider($config['provider'] ?? 'coinrex');
    $provider_meta = getRatingProviderMeta($provider);
    $size = normalizeRatingSize($config['size'] ?? 'md');
    $variant = normalizeRatingVariant($config['variant'] ?? 'default');
    $label = trim((string) ($config['label'] ?? ($provider_meta['label'] ?? 'CoinRex Rating')));
    $value = max(0.0, (float) ($config['value'] ?? 0));
    $scale = max(1, (int) ($config['scale'] ?? 5));
    $review_count = isset($config['review_count']) ? max(0, (int) $config['review_count']) : null;
    $show_stars = array_key_exists('show_stars', $config) ? (bool) $config['show_stars'] : true;
    $show_score = array_key_exists('show_score', $config) ? (bool) $config['show_score'] : true;
    $show_count = !empty($config['show_count']) && $review_count !== null;
    $show_provider_icon = array_key_exists('show_provider_icon', $config) ? (bool) $config['show_provider_icon'] : true;
    $extra_class = trim((string) ($config['class'] ?? ''));
    $aria_label = trim((string) ($config['aria_label'] ?? ($label . ' ' . number_format($value, 1) . ' out of ' . $scale)));
    $provider_icon = trim((string) ($config['provider_icon'] ?? ($provider_meta['icon'] ?? '')));
    $provider_icon_fallback = trim((string) ($provider_meta['icon_fallback'] ?? 'fas fa-star'));
    $display_value = min($value, (float) $scale);
    $score_whole = floor($display_value);
    $score_decimal = (int) round(($display_value - $score_whole) * 10);
    $score_label = buildRatingScoreLabel($display_value, $scale);
    $filled_stars = max(0, min($scale, (int) floor($display_value)));
    $brand_prefix = trim((string) ($config['brand_prefix'] ?? 'Coin'));
    $brand_suffix = trim((string) ($config['brand_suffix'] ?? 'Rex Ratings:'));

    $classes = [
        'universal-rating',
        'universal-rating--' . $provider,
        'universal-rating--' . $size,
    ];

    if ($variant !== 'default') {
        $classes[] = 'universal-rating--' . $variant;
        if ($variant === 'cr-row-small') {
            $classes[] = 'cr-rating-row-small';
        }
        if ($variant === 'cr-box-large') {
            $classes[] = 'cr-rating-box-large';
        }
    }

    if (!$show_stars) {
        $classes[] = 'is-no-stars';
    }
    if ($extra_class !== '') {
        $classes[] = $extra_class;
    }

    if ($provider === 'coinrex' && $variant === 'cr-box-large') {
        ob_start();
        ?>
        <div class="<?php echo htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="universal-rating__box-header">
                <div class="universal-rating__box-label">
                    <?php echo renderCoinrexBrandLabel($brand_prefix, $brand_suffix); ?>
                </div>
                <?php if ($show_score): ?>
                    <div class="universal-rating__score-inline"><?php echo htmlspecialchars($score_label, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($show_stars): ?>
                <div class="universal-rating__divider" aria-hidden="true"></div>
                <div class="universal-rating__box-stars" aria-hidden="true">
                    <?php for ($i = 1; $i <= $scale; $i++): ?>
                        <span class="universal-rating__star-shell <?php echo $i <= $filled_stars ? 'is-filled' : 'is-outline'; ?>">
                            <i class="<?php echo $i <= $filled_stars ? 'fas' : 'far'; ?> fa-star"></i>
                        </span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    if ($provider === 'coinrex' && $variant === 'cr-row-small') {
        ob_start();
        ?>
        <div class="<?php echo htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="universal-rating__row-label">
                <?php echo renderCoinrexBrandLabel($brand_prefix, $brand_suffix); ?>
            </div>
            <div class="universal-rating__row-score" aria-hidden="true">
                <?php if ($show_stars): ?>
                    <span class="universal-rating__row-score-star"><i class="fas fa-star"></i></span>
                <?php endif; ?>
                <?php if ($show_score): ?>
                    <span class="universal-rating__row-score-text"><?php echo htmlspecialchars($score_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    ob_start();
    ?>
    <div class="<?php echo htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="universal-rating__main">
            <?php if ($label !== ''): ?>
                <span class="universal-rating__label">
                    <?php if ($show_provider_icon): ?>
                        <span class="universal-rating__provider-mark" aria-hidden="true">
                            <i class="<?php echo htmlspecialchars($provider_icon !== '' ? $provider_icon : $provider_icon_fallback, ENT_QUOTES, 'UTF-8'); ?> universal-rating__provider-icon"></i>
                        </span>
                    <?php endif; ?>
                    <span class="universal-rating__label-text"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
            <?php endif; ?>

            <?php if ($show_stars || $show_count): ?>
                <div class="universal-rating__meta">
                    <?php if ($show_stars): ?>
                        <div class="universal-rating__stars" aria-hidden="true">
                            <?php for ($i = 1; $i <= $scale; $i++): ?>
                                <i class="<?php echo ($value >= $i) ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_count): ?>
                        <span class="universal-rating__count"><?php echo htmlspecialchars(number_format($review_count) . ' reviews', ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($show_score): ?>
            <div class="universal-rating__score-block" aria-hidden="true">
                <span class="universal-rating__score-value">
                    <span class="universal-rating__score-whole"><?php echo (int) $score_whole; ?></span><span class="universal-rating__score-decimal">.<?php echo (int) $score_decimal; ?></span>
                </span>
                <span class="universal-rating__score-scale">/<?php echo (int) $scale; ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}