<?php
/**
 * Wallet Platform Header
 * Standalone header for wallet.coinrex.xyz — renders its own nav,
 * does NOT touch the main coinrex.xyz navigation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/wallet_nav.php';

if (!function_exists('coinrexSeoUrl')) {
    function coinrexSeoUrl($path = '') {
        $base_url = defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL;
        $base_url = rtrim((string) $base_url, '/');
        $path = '/' . ltrim((string) $path, '/');

        return $base_url . ($path === '/' ? '' : $path);
    }
}

$wallet_page_title = trim((string) ($page_title ?? ''));
$wallet_page_title = $wallet_page_title !== '' ? $wallet_page_title : 'RexLink — Extension Free Web3 Access';
$wallet_meta_description = trim((string) ($meta_description ?? ''));
$wallet_meta_description = $wallet_meta_description !== ''
    ? $wallet_meta_description
    : 'Download RexLink — the secure, non-custodial mobile wallet for CoinRex. Approve transactions, link your wallet, and manage rewards from your phone.';
$wallet_meta_keywords = trim((string) ($meta_keywords ?? ''));
$wallet_meta_keywords = $wallet_meta_keywords !== ''
    ? $wallet_meta_keywords
    : 'RexLink, CoinRex wallet, crypto wallet, Web3 wallet, non-custodial wallet, Android APK, download RexLink';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($wallet_page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($wallet_meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($wallet_meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CoinRex">
    <meta name="theme-color" content="#0f172a">
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($wallet_page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?php echo htmlspecialchars(coinrexSeoUrl('/assets/images/shield-logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/wallet.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/wallet.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-theme="dark" class="wallet-platform-body">

<!-- ========================================
     WALLET PLATFORM NAVIGATION
     ======================================== -->
<nav class="wallet-nav" aria-label="Wallet platform navigation">
    <div class="wallet-nav-container">
        <div class="wallet-nav-brand">
            <a href="<?php echo BASE_URL; ?>" class="wallet-nav-logo" title="Back to coinrex.xyz">
                <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="RexLink" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\'font-size:2rem;\'>🦁</span>';">
            </a>
        </div>
        <div class="wallet-nav-links">
            <?php foreach ($wallet_navigation_items as $wallet_nav_item): ?>
                <?php if ((int) ($wallet_nav_item['is_enabled'] ?? 1) !== 1) { continue; } ?>
                <a href="<?php echo htmlspecialchars((string) ($wallet_nav_item['admin_route_hint'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" class="wallet-nav-link">
                    <?php if (trim((string) ($wallet_nav_item['icon_class'] ?? '')) !== ''): ?>
                        <i class="<?php echo htmlspecialchars((string) $wallet_nav_item['icon_class'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars((string) $wallet_nav_item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="wallet-nav-actions">
            <a href="<?php echo BASE_URL; ?>/api/rexlink_download.php" class="wallet-btn-download">
                <i class="fas fa-download"></i>
                Download APK
            </a>
        </div>
    </div>
</nav>
