<?php
/**
 * RexLink — Extension Free Web3 Access
 * Location: /coinrex/wallet.php
 *
 * Redirect stub — the full wallet platform now lives at /coinrex/wallet/.
 * Keeps old bookmarks and links working.
 */

// Build the wallet base URL relative to this script.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$base   = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');

header('Location: ' . $scheme . '://' . $host . $base . '/wallet/index.php', true, 301);
exit;
