<?php

$base = 'c:/xampp/htdocs/coinrex/includes';
$srcPath = $base . '/functions.php';

if (!file_exists($srcPath)) {
    fwrite(STDERR, "functions.php not found\n");
    exit(1);
}

$content = file_get_contents($srcPath);
$content = preg_replace('/^<\?php\s*/', '', $content, 1);
$content = preg_replace('/\?>\s*$/', '', $content, 1);
$lines = preg_split('/\R/', $content);

$functions = [];
$outside = [];

$i = 0;
$n = count($lines);
while ($i < $n) {
    $line = $lines[$i];
    if (preg_match('/^\s*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $m)) {
        $name = $m[1];
        $start = $i;
        $brace = 0;
        $started = false;
        while ($i < $n) {
            $l = $lines[$i];
            $brace += substr_count($l, '{');
            if (strpos($l, '{') !== false) {
                $started = true;
            }
            $brace -= substr_count($l, '}');
            if ($started && $brace === 0) {
                $end = $i;
                $i++;
                break;
            }
            $i++;
        }
        $block = implode("\n", array_slice($lines, $start, $end - $start + 1));
        $functions[] = [$name, $block];
    } else {
        $outside[] = $line;
        $i++;
    }
}

$modules = [
    'core' => [],
    'auth' => [],
    'user' => [],
    'email' => [],
    'reward_ledger' => [],
    'taskhub' => [],
    'boosthub' => [],
    'levels' => [],
    'referrals' => [],
    'security' => [],
    'helpers' => [],
];

$modFor = function ($name) {
    $lname = strtolower($name);
    if (strpos($lname, 'taskhub') !== false) return 'taskhub';
    if (strpos($lname, 'boosthub') !== false) return 'boosthub';
    if (strpos($lname, 'otp') !== false || strpos($lname, 'mail') !== false || strpos($lname, 'smtp') !== false) return 'email';
    if (strpos($lname, 'remember') !== false || in_array($lname, ['loginuser','logoutuser','isloggedin','establishauthenticatedsession','restorerememberedsession','touchauthenticateduseractivity'], true)) return 'auth';
    if (strpos($lname, 'getuser') === 0 || strpos($lname, 'updateuser') === 0 || in_array($lname, ['registeruser','uploadprofileavatar','isuserprofilecomplete','getcurrentuser','getcurrentuserid','generateusername'], true)) return 'user';
    if (strpos($lname, 'ledger') !== false || strpos($lname, 'claim') !== false || strpos($lname, 'reward') !== false) return 'reward_ledger';
    if (strpos($lname, 'referral') !== false) return 'referrals';
    if (strpos($lname, 'level') !== false || strpos($lname, 'review') !== false || strpos($lname, 'project') !== false || strpos($lname, 'expert') !== false) return 'levels';
    if (strpos($lname, 'csrf') !== false || strpos($lname, 'sanitize') !== false || strpos($lname, 'security') !== false || strpos($lname, 'disposable') !== false || strpos($lname, 'passwordpolicy') !== false || strpos($lname, 'clientip') !== false) return 'security';
    if (in_array($lname, ['redirect','normalizeemail','normalizereferralcode','getemaildomain'], true)) return 'helpers';
    return 'core';
};

foreach ($functions as [$name, $block]) {
    $modules[$modFor($name)][] = $block;
}

$funcDir = $base . '/functions';
if (!is_dir($funcDir)) {
    mkdir($funcDir, 0755, true);
}

$outsideTxt = trim(implode("\n", $outside));
foreach ($modules as $module => $blocks) {
    $path = $funcDir . '/' . $module . '.php';
    $body = "<?php\n/** Auto-split from legacy functions.php */\n\n";
    if ($module === 'core' && $outsideTxt !== '') {
        $body .= $outsideTxt . "\n\n";
    }
    $body .= trim(implode("\n\n", $blocks)) . "\n";
    file_put_contents($path, $body);
}

$legacy = $base . '/functions_legacy_backup.php';
if (!file_exists($legacy)) {
    file_put_contents($legacy, "<?php\n" . $content . "\n?>\n");
}

$loader = <<<'PHP'
<?php
/**
 * CoinRex Helper Functions Loader
 * Split into modular files for maintainability.
 */

require_once __DIR__ . '/functions/core.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/user.php';
require_once __DIR__ . '/functions/email.php';
require_once __DIR__ . '/functions/reward_ledger.php';
require_once __DIR__ . '/functions/boosthub.php';
require_once __DIR__ . '/functions/taskhub.php';
require_once __DIR__ . '/functions/referrals.php';
require_once __DIR__ . '/functions/levels.php';
require_once __DIR__ . '/functions/auth.php';
?>
PHP;

file_put_contents($srcPath, $loader . "\n");

echo "Split complete. Functions: " . count($functions) . PHP_EOL;
foreach ($modules as $k => $v) {
    echo $k . ' ' . count($v) . PHP_EOL;
}
