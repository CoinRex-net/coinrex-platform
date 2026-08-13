<?php
/**
 * RexLink Wallet Platform — Header + navigation.
 * Location: /coinrex/wallet/includes/header.php
 *
 * Self-contained. Renders the wallet platform's own nav.
 * The main coinrex.xyz navigation is NOT touched.
 */

require_once __DIR__ . '/config.php';

$walletPageTitle = trim((string) ($page_title ?? ''));
$walletPageTitle = $walletPageTitle !== ''
    ? $walletPageTitle
    : WALLET_NAME . ' — ' . WALLET_TAGLINE;
$walletMetaDescription = trim((string) ($meta_description ?? ''));
$walletMetaDescription = $walletMetaDescription !== ''
    ? $walletMetaDescription
    : 'Download ' . WALLET_NAME . ' — the secure, non-custodial mobile wallet for ' . WALLET_SITE_NAME . '. Approve transactions, link your wallet, and manage rewards from your phone.';
$walletMetaKeywords = trim((string) ($meta_keywords ?? ''));
$walletMetaKeywords = $walletMetaKeywords !== ''
    ? $walletMetaKeywords
    : 'RexLink, CoinRex wallet, crypto wallet, Web3 wallet, non-custodial wallet, Android APK, download RexLink';

$walletScriptBase = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
$walletCurrentPage = strtolower($walletScriptBase);

$walletNav = [
    ['label' => 'Home',   'href' => WALLET_BASE_URL . '/index.php',    'icon' => 'fas fa-home',           'page' => 'index.php'],
    ['label' => 'About',  'href' => WALLET_BASE_URL . '/about.php',    'icon' => 'fas fa-circle-info',    'page' => 'about.php'],
    ['label' => 'Contact','href' => WALLET_BASE_URL . '/contact.php',  'icon' => 'fas fa-envelope',       'page' => 'contact.php'],
    ['label' => 'Privacy','href' => WALLET_BASE_URL . '/privacy.php',  'icon' => 'fas fa-shield-alt',     'page' => 'privacy.php'],
    ['label' => 'Terms',  'href' => WALLET_BASE_URL . '/terms.php',    'icon' => 'fas fa-file-contract',  'page' => 'terms.php'],
    ['label' => 'How it Works', 'href' => WALLET_BASE_URL . '/index.php#how-it-works', 'icon' => 'fas fa-route', 'page' => ''],
    ['label' => 'FAQ',    'href' => WALLET_BASE_URL . '/index.php#faq', 'icon' => 'fas fa-circle-question', 'page' => ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($walletPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($walletMetaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($walletMetaKeywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0f172a">
    <meta name="base-url" content="<?php echo htmlspecialchars(WALLET_BASE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(WALLET_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($walletPageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($walletMetaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars(WALLET_BASE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/icon.png', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-theme="dark">

<!-- ========================================
     WALLET PLATFORM NAVIGATION
     ======================================== -->
<nav class="wallet-nav" aria-label="RexLink wallet platform navigation">
    <div class="wallet-nav-inner">
        <a class="wallet-nav-brand" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="RexLink home">
            <img src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/rexlink-logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="RexLink" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=wallet-nav-brand-fallback>🦁</span>';">
        </a>

        <button type="button" class="wallet-nav-toggle" id="walletNavToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="walletNavLinks">
            <i class="fas fa-bars wallet-nav-toggle-icon-open"></i>
            <i class="fas fa-xmark wallet-nav-toggle-icon-close" style="display:none;"></i>
        </button>

        <div class="wallet-nav-links" id="walletNavLinks">
            <?php foreach ($walletNav as $walletLink): ?>
                <?php
                $walletLinkActive = $walletLink['page'] !== '' && $walletLink['page'] === $walletCurrentPage;
                ?>
                <a href="<?php echo htmlspecialchars($walletLink['href'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="wallet-nav-link<?php echo $walletLinkActive ? ' is-active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($walletLink['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($walletLink['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
            <a class="wallet-nav-cta wallet-nav-cta-mobile" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-download"></i> Download APK
            </a>
        </div>

        <div class="wallet-nav-actions">
            <a class="wallet-nav-coinrex" href="<?php echo htmlspecialchars(WALLET_MAIN_SITE_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                CoinRex/ <i class="fas fa-arrow-up-right-from-square"></i>
            </a>
            <a class="wallet-nav-cta" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-download"></i> Download APK
            </a>
        </div>
    </div>
</nav>
