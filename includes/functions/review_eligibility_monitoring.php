<?php
/**
 * Forward-looking review eligibility monitoring.
 *
 * Eligibility is based on a continuously maintained token threshold. No
 * average balance or fiat conversion is used anywhere in this flow.
 */

function reviewEligibilityMonitoringEnsureSchema(PDO $db = null) {
    static $ready = false;
    if ($ready) return;
    $db = $db ?: getDBConnection();

    foreach ([
        'eligibility_min_amount' => "ALTER TABLE project_contracts ADD COLUMN eligibility_min_amount VARCHAR(120) NULL AFTER decimals",
        'eligibility_holding_minutes' => "ALTER TABLE project_contracts ADD COLUMN eligibility_holding_minutes INT UNSIGNED NULL AFTER eligibility_min_amount",
    ] as $column => $sql) {
        if (!tableHasColumn('project_contracts', $column)) $db->exec($sql);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS review_eligibility_monitoring_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL, project_id INT UNSIGNED NOT NULL, project_contract_id INT UNSIGNED NOT NULL,
        wallet_address VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active',
        reason_code VARCHAR(80) NOT NULL DEFAULT 'monitoring_active', reason TEXT NULL,
        token_type VARCHAR(20) NOT NULL, token_symbol VARCHAR(40) NOT NULL, token_decimals TINYINT UNSIGNED NOT NULL DEFAULT 18,
        required_amount VARCHAR(120) NOT NULL, required_amount_raw VARCHAR(120) NOT NULL,
        start_balance_raw VARCHAR(120) NOT NULL, last_balance_raw VARCHAR(120) NOT NULL,
        qualifying_tx_hash VARCHAR(100) NULL, start_block BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_checked_block BIGINT UNSIGNED NOT NULL DEFAULT 0, failure_tx_hash VARCHAR(100) NULL,
        ownership_verified_at DATETIME NOT NULL, started_at DATETIME NOT NULL, eligible_at DATETIME NOT NULL,
        next_check_at DATETIME NOT NULL, last_checked_at DATETIME NULL, completed_at DATETIME NULL,
        disqualified_at DATETIME NULL, expires_at DATETIME NULL, consumed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_review_monitor_due (status, next_check_at),
        KEY idx_review_monitor_lookup (user_id, project_id, wallet_address, status),
        KEY idx_review_monitor_contract (project_contract_id, status),
        CONSTRAINT fk_review_monitor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_review_monitor_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_review_monitor_contract FOREIGN KEY (project_contract_id) REFERENCES project_contracts(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS review_eligibility_monitoring_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, monitoring_session_id BIGINT UNSIGNED NOT NULL,
        tx_hash VARCHAR(100) NOT NULL, log_index INT UNSIGNED NOT NULL DEFAULT 0,
        block_number BIGINT UNSIGNED NOT NULL, block_hash VARCHAR(100) NULL, event_at DATETIME NOT NULL,
        direction VARCHAR(20) NOT NULL, amount_raw VARCHAR(120) NOT NULL, balance_after_raw VARCHAR(120) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
        UNIQUE KEY uq_review_monitor_event (monitoring_session_id, tx_hash, log_index),
        KEY idx_review_monitor_event_block (monitoring_session_id, block_number),
        CONSTRAINT fk_review_monitor_event_session FOREIGN KEY (monitoring_session_id) REFERENCES review_eligibility_monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS review_eligibility_notification_outbox (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, monitoring_session_id BIGINT UNSIGNED NOT NULL,
        event_key VARCHAR(120) NOT NULL, title VARCHAR(180) NOT NULL, message TEXT NOT NULL, action_url VARCHAR(255) NULL,
        in_app_delivered_at DATETIME NULL, email_delivered_at DATETIME NULL, attempts INT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL, next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_review_monitor_notification (monitoring_session_id, event_key),
        KEY idx_review_monitor_notification_due (next_attempt_at, in_app_delivered_at, email_delivered_at),
        CONSTRAINT fk_review_monitor_notification_session FOREIGN KEY (monitoring_session_id) REFERENCES review_eligibility_monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!tableHasColumn('reviews', 'eligibility_monitoring_session_id')) {
        $db->exec("ALTER TABLE reviews ADD COLUMN eligibility_monitoring_session_id BIGINT UNSIGNED NULL AFTER eligibility_check_id");
    }
    $ready = true;
}

function reviewEligibilityMonitoringDigits($value) {
    $value = ltrim(preg_replace('/\D/', '', (string) $value), '0');
    return $value === '' ? '0' : $value;
}

function reviewEligibilityMonitoringCompare($left, $right) {
    $left = reviewEligibilityMonitoringDigits($left);
    $right = reviewEligibilityMonitoringDigits($right);
    if (strlen($left) !== strlen($right)) return strlen($left) <=> strlen($right);
    return strcmp($left, $right) <=> 0;
}

function reviewEligibilityMonitoringAdd($left, $right) {
    $left = strrev(reviewEligibilityMonitoringDigits($left));
    $right = strrev(reviewEligibilityMonitoringDigits($right));
    $length = max(strlen($left), strlen($right));
    $carry = 0;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $sum = (int) ($left[$i] ?? 0) + (int) ($right[$i] ?? 0) + $carry;
        $out .= (string) ($sum % 10);
        $carry = intdiv($sum, 10);
    }
    if ($carry) $out .= (string) $carry;
    return reviewEligibilityMonitoringDigits(strrev($out));
}

function reviewEligibilityMonitoringSubtract($left, $right) {
    $left = reviewEligibilityMonitoringDigits($left);
    $right = reviewEligibilityMonitoringDigits($right);
    if (reviewEligibilityMonitoringCompare($left, $right) <= 0) return '0';
    $a = strrev($left);
    $b = strrev($right);
    $borrow = 0;
    $out = '';
    for ($i = 0; $i < strlen($a); $i++) {
        $digit = (int) $a[$i] - $borrow - (int) ($b[$i] ?? 0);
        if ($digit < 0) { $digit += 10; $borrow = 1; } else { $borrow = 0; }
        $out .= (string) $digit;
    }
    return reviewEligibilityMonitoringDigits(strrev($out));
}

function reviewEligibilityMonitoringMultiply($left, $right) {
    $left = reviewEligibilityMonitoringDigits($left);
    $right = reviewEligibilityMonitoringDigits($right);
    if ($left === '0' || $right === '0') return '0';
    $result = array_fill(0, strlen($left) + strlen($right), 0);
    for ($i = strlen($left) - 1; $i >= 0; $i--) {
        for ($j = strlen($right) - 1; $j >= 0; $j--) {
            $pos = $i + $j + 1;
            $sum = $result[$pos] + ((int) $left[$i] * (int) $right[$j]);
            $result[$pos] = $sum % 10;
            $result[$pos - 1] += intdiv($sum, 10);
        }
    }
    return reviewEligibilityMonitoringDigits(implode('', $result));
}

function reviewEligibilityMonitoringDecimalToRaw($amount, $decimals) {
    $amount = trim((string) $amount);
    if (!preg_match('/^\d+(?:\.\d+)?$/', $amount)) return '0';
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    $decimals = max(0, min(36, (int) $decimals));
    $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
    return reviewEligibilityMonitoringDigits($whole . $fraction);
}

function reviewEligibilityMonitoringRawToDecimal($raw, $decimals, $precision = 8) {
    $raw = reviewEligibilityMonitoringDigits($raw);
    $decimals = max(0, min(36, (int) $decimals));
    if ($decimals === 0) return $raw;
    $padded = str_pad($raw, $decimals + 1, '0', STR_PAD_LEFT);
    $whole = substr($padded, 0, -$decimals);
    $fraction = substr(substr($padded, -$decimals), 0, max(0, (int) $precision));
    $fraction = rtrim($fraction, '0');
    return $fraction === '' ? reviewEligibilityMonitoringDigits($whole) : reviewEligibilityMonitoringDigits($whole) . '.' . $fraction;
}

function reviewEligibilityMonitoringNativeSymbol(array $contract) {
    if (!empty($contract['token_symbol'])) return strtoupper((string) $contract['token_symbol']);
    $symbols = [1 => 'ETH', 56 => 'BNB', 137 => 'POL', 42161 => 'ETH', 10 => 'ETH', 43114 => 'AVAX', 8453 => 'ETH'];
    return $symbols[(int) ($contract['chain_id'] ?? 0)] ?? 'TOKEN';
}

function reviewEligibilityMonitoringRule(PDO $db, $project_id, $contract_id = 0) {
    reviewEligibilityMonitoringEnsureSchema($db);
    $sql = "SELECT pc.*, p.name AS project_name, p.min_holding_amount AS project_min_amount,
                   p.required_holding_days AS project_holding_days
            FROM project_contracts pc JOIN projects p ON p.id = pc.project_id
            WHERE pc.project_id = ? AND pc.is_active = 1";
    $params = [(int) $project_id];
    if ($contract_id > 0) { $sql .= " AND pc.id = ?"; $params[] = (int) $contract_id; }
    $sql .= " ORDER BY pc.is_primary DESC, pc.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['eligibility_min_amount'] = trim((string) ($row['eligibility_min_amount'] ?? '')) !== ''
            ? trim((string) $row['eligibility_min_amount'])
            : trim((string) ($row['project_min_amount'] ?? '0'));
        $row['eligibility_holding_minutes'] = (int) ($row['eligibility_holding_minutes'] ?? 0) > 0
            ? (int) $row['eligibility_holding_minutes']
            : max(1, (int) ($row['project_holding_days'] ?? 1)) * 1440;
        $row['token_decimals'] = isset($row['decimals']) ? (int) $row['decimals'] : 18;
        $row['token_symbol'] = strtoupper(trim((string) ($row['token_symbol'] ?? '')));
        if ($row['token_symbol'] === '' && strtoupper((string) $row['token_type']) === 'NATIVE') {
            $row['token_symbol'] = reviewEligibilityMonitoringNativeSymbol($row);
        }
    }
    unset($row);
    return $rows;
}

function reviewEligibilityMonitoringExplorer(array $contract, array $params) {
    $api_key = reviewEligibilityEnv('ETHERSCAN_API_KEY', reviewEligibilityEnv('EXPLORER_API_KEY', ''));
    if ($api_key === '') return ['ok' => false, 'status' => 'provider_unavailable', 'message' => 'Blockchain provider API key is not configured.'];
    $params['chainid'] = (int) $contract['chain_id'];
    $params['apikey'] = $api_key;
    return reviewEligibilityExplorerRequest(reviewEligibilityEnv('ETHERSCAN_API_BASE_URL', 'https://api.etherscan.io/v2/api'), $params);
}

function reviewEligibilityMonitoringLatestBlock(array $contract) {
    $response = reviewEligibilityMonitoringExplorer($contract, ['module' => 'proxy', 'action' => 'eth_blockNumber']);
    if (!$response['ok']) return $response;
    $hex = (string) ($response['result'] ?? '0x0');
    return ['ok' => true, 'block' => max(0, (int) hexdec(substr($hex, 0, 18)))];
}

function reviewEligibilityMonitoringBalance(array $contract, $wallet) {
    $type = strtoupper((string) ($contract['token_type'] ?? 'ERC20'));
    $params = ['module' => 'account', 'address' => strtolower((string) $wallet), 'tag' => 'latest'];
    if ($type === 'NATIVE') {
        $params['action'] = 'balance';
    } elseif ($type === 'ERC20') {
        $params['action'] = 'tokenbalance';
        $params['contractaddress'] = strtolower((string) $contract['contract_address']);
    } else {
        return ['ok' => false, 'status' => 'unsupported_token', 'message' => 'Continuous monitoring currently supports native and ERC-20 tokens.'];
    }
    $response = reviewEligibilityMonitoringExplorer($contract, $params);
    if (!$response['ok']) return $response;
    return ['ok' => true, 'balance_raw' => reviewEligibilityMonitoringDigits($response['result'] ?? '0')];
}

function reviewEligibilityMonitoringTransactions(array $contract, $wallet, $start_block = 0, $end_block = 999999999, $sort = 'asc') {
    $type = strtoupper((string) ($contract['token_type'] ?? 'ERC20'));
    $params = [
        'module' => 'account', 'address' => strtolower((string) $wallet),
        'startblock' => max(0, (int) $start_block), 'endblock' => max(0, (int) $end_block),
        'page' => 1, 'offset' => 10000, 'sort' => $sort,
    ];
    if ($type === 'NATIVE') {
        $params['action'] = 'txlist';
    } elseif ($type === 'ERC20') {
        $params['action'] = 'tokentx';
        $params['contractaddress'] = strtolower((string) $contract['contract_address']);
    } else {
        return ['ok' => false, 'status' => 'unsupported_token', 'message' => 'Continuous monitoring currently supports native and ERC-20 tokens.'];
    }
    $response = reviewEligibilityMonitoringExplorer($contract, $params);
    if (!$response['ok']) return $response;
    return ['ok' => true, 'transactions' => is_array($response['result'] ?? null) ? $response['result'] : []];
}

function reviewEligibilityMonitoringQueue(PDO $db, $session_id, $event_key, $title, $message, $action_url = null) {
    $stmt = $db->prepare("INSERT IGNORE INTO review_eligibility_notification_outbox
        (monitoring_session_id, event_key, title, message, action_url, next_attempt_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())");
    $stmt->execute([(int) $session_id, substr((string) $event_key, 0, 120), substr((string) $title, 0, 180), (string) $message, $action_url]);
}

function reviewEligibilityMonitoringFindIncoming(array $transactions, $wallet, $verified_at) {
    $wallet = strtolower((string) $wallet);
    $verified_ts = strtotime((string) $verified_at) ?: 0;
    foreach (array_reverse($transactions) as $tx) {
        $successful = (string) ($tx['isError'] ?? '0') !== '1' && (string) ($tx['txreceipt_status'] ?? '1') !== '0';
        if (!$successful || strtolower((string) ($tx['to'] ?? '')) !== $wallet) continue;
        if ((int) ($tx['timeStamp'] ?? 0) < $verified_ts) continue;
        return $tx;
    }
    return null;
}

function reviewEligibilityMonitoringGetLatest(PDO $db, $user_id, $project_id, $wallet = '') {
    reviewEligibilityMonitoringEnsureSchema($db);
    $sql = "SELECT s.*, p.name AS project_name, pc.chain_id, pc.contract_address
            FROM review_eligibility_monitoring_sessions s
            JOIN projects p ON p.id = s.project_id
            JOIN project_contracts pc ON pc.id = s.project_contract_id
            WHERE s.user_id = ? AND s.project_id = ?";
    $params = [(int) $user_id, (int) $project_id];
    if ($wallet !== '') { $sql .= " AND s.wallet_address = ?"; $params[] = strtolower((string) $wallet); }
    $sql .= " ORDER BY s.id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function reviewEligibilityMonitoringStart(PDO $db, $user_id, $project_id, $wallet, $ownership_verified_at) {
    reviewEligibilityMonitoringEnsureSchema($db);
    $wallet = strtolower(trim((string) $wallet));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet)) throw new InvalidArgumentException('Connect a valid EVM wallet first.');

    $existing = reviewEligibilityMonitoringGetLatest($db, $user_id, $project_id, $wallet);
    if ($existing && (string) $existing['status'] === 'eligible' && !empty($existing['expires_at']) && strtotime((string) $existing['expires_at']) <= time()) {
        $db->prepare('UPDATE review_eligibility_monitoring_sessions SET status=\'expired\', reason_code=\'eligibility_expired\', reason=\'Eligibility expired before review submission.\', updated_at=NOW() WHERE id=? AND status=\'eligible\'')
            ->execute([(int) $existing['id']]);
        $existing = null;
    }
    if ($existing && in_array((string) $existing['status'], ['active', 'provider_delayed', 'eligible'], true)) return $existing;

    $rules = reviewEligibilityMonitoringRule($db, $project_id);
    if (!$rules) throw new RuntimeException('This project has no active token requirement. Please contact support.');
    $provider_error = null;
    foreach ($rules as $rule) {
        if ((float) $rule['eligibility_min_amount'] <= 0) continue;
        $latest = reviewEligibilityMonitoringLatestBlock($rule);
        if (!$latest['ok']) { $provider_error = $latest['message'] ?? 'Blockchain provider unavailable.'; continue; }
        $recent = reviewEligibilityMonitoringTransactions($rule, $wallet, 0, $latest['block'], 'desc');
        if (!$recent['ok']) { $provider_error = $recent['message'] ?? 'Blockchain provider unavailable.'; continue; }
        $incoming = reviewEligibilityMonitoringFindIncoming($recent['transactions'], $wallet, $ownership_verified_at);
        if (!$incoming) continue;

        $decimals = isset($rule['decimals']) && $rule['decimals'] !== null ? (int) $rule['decimals'] : (int) ($incoming['tokenDecimal'] ?? 18);
        $symbol = $rule['token_symbol'] ?: strtoupper((string) ($incoming['tokenSymbol'] ?? 'TOKEN'));
        $required_raw = reviewEligibilityMonitoringDecimalToRaw($rule['eligibility_min_amount'], $decimals);
        $balance = reviewEligibilityMonitoringBalance($rule, $wallet);
        if (!$balance['ok']) { $provider_error = $balance['message'] ?? 'Blockchain provider unavailable.'; continue; }
        if (reviewEligibilityMonitoringCompare($balance['balance_raw'], $required_raw) < 0) {
            throw new RuntimeException(sprintf('More %s is required. We detected %s %s; this project requires at least %s %s before monitoring can start.', $symbol, reviewEligibilityMonitoringRawToDecimal($balance['balance_raw'], $decimals), $symbol, $rule['eligibility_min_amount'], $symbol));
        }

        $minutes = max(1, (int) $rule['eligibility_holding_minutes']);
        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT id FROM review_eligibility_monitoring_sessions WHERE user_id = ? AND project_id = ? AND wallet_address = ? AND status IN ('active','provider_delayed','eligible') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $lock->execute([(int) $user_id, (int) $project_id, $wallet]);
            $locked = $lock->fetch();
            if ($locked) {
                $db->commit();
                return reviewEligibilityMonitoringGetLatest($db, $user_id, $project_id, $wallet);
            }
            $reason = sprintf('%s %s detected. Maintain at least %s %s continuously for %s.', reviewEligibilityMonitoringRawToDecimal($balance['balance_raw'], $decimals), $symbol, $rule['eligibility_min_amount'], $symbol, reviewEligibilityMonitoringFormatDuration($minutes));
            $stmt = $db->prepare("INSERT INTO review_eligibility_monitoring_sessions
                (user_id, project_id, project_contract_id, wallet_address, status, reason_code, reason, token_type, token_symbol, token_decimals,
                 required_amount, required_amount_raw, start_balance_raw, last_balance_raw, qualifying_tx_hash, start_block, last_checked_block,
                 ownership_verified_at, started_at, eligible_at, next_check_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'active', 'monitoring_active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), NOW())");
            $stmt->execute([(int) $user_id, (int) $project_id, (int) $rule['id'], $wallet, $reason,
                strtoupper((string) $rule['token_type']), $symbol, $decimals, (string) $rule['eligibility_min_amount'], $required_raw,
                $balance['balance_raw'], $balance['balance_raw'], (string) ($incoming['hash'] ?? ''), (int) $latest['block'], (int) $latest['block'],
                date('Y-m-d H:i:s', strtotime((string) $ownership_verified_at) ?: time()), $minutes]);
            $session_id = (int) $db->lastInsertId();
            reviewEligibilityMonitoringQueue($db, $session_id, 'started', 'Holding verification started', $reason, '/public/submit-review.php?project_id=' . (int) $project_id);
            $db->commit();
            return reviewEligibilityMonitoringGetLatest($db, $user_id, $project_id, $wallet);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    if ($provider_error) throw new RuntimeException('Blockchain data is temporarily unavailable. Your wallet is not marked ineligible; please try again shortly.');
    throw new RuntimeException('No qualifying incoming project-token transaction was found after wallet verification. Receive the project token, then press Start Verification.');
}

function reviewEligibilityMonitoringFormatDuration($minutes) {
    $minutes = max(1, (int) $minutes);
    if ($minutes === 1440) return '24 hours';
    if ($minutes % 1440 === 0) return ($minutes / 1440) . ' day' . ($minutes === 1440 ? '' : 's');
    if ($minutes % 60 === 0) return ($minutes / 60) . ' hour' . ($minutes === 60 ? '' : 's');
    return $minutes . ' minutes';
}

function reviewEligibilityMonitoringPayload(array $session) {
    $started = strtotime((string) ($session['started_at'] ?? '')) ?: time();
    $eligible_at = strtotime((string) ($session['eligible_at'] ?? '')) ?: $started;
    $required_seconds = max(0, $eligible_at - $started);
    $remaining = max(0, $eligible_at - time());
    return [
        'monitoring_session_id' => (int) ($session['id'] ?? 0),
        'status' => (string) ($session['status'] ?? 'not_started'),
        'reason_code' => (string) ($session['reason_code'] ?? ''),
        'reason' => (string) ($session['reason'] ?? ''),
        'wallet_address' => (string) ($session['wallet_address'] ?? ''),
        'token_symbol' => (string) ($session['token_symbol'] ?? ''),
        'required_amount' => (string) ($session['required_amount'] ?? ''),
        'current_balance' => reviewEligibilityMonitoringRawToDecimal($session['last_balance_raw'] ?? '0', (int) ($session['token_decimals'] ?? 18)),
        'started_at' => (string) ($session['started_at'] ?? ''),
        'eligible_at' => (string) ($session['eligible_at'] ?? ''),
        'expires_at' => (string) ($session['expires_at'] ?? ''),
        'last_checked_at' => (string) ($session['last_checked_at'] ?? ''),
        'remaining_seconds' => $remaining,
        'project_name' => (string) ($session['project_name'] ?? ''),
        'chain_id' => (int) ($session['chain_id'] ?? 0),
        'contract_address' => (string) ($session['contract_address'] ?? ''),
        'required_days' => (int) ceil($required_seconds / 86400),
        'holding_days' => round(min($required_seconds, max(0, time() - $started)) / 86400, 2),
    ];
}

function reviewEligibilityMonitoringProcess(PDO $db, $session_id) {
    reviewEligibilityMonitoringEnsureSchema($db);
    $stmt = $db->prepare("SELECT s.*, p.name AS project_name, pc.chain_id, pc.contract_address, pc.network_name
        FROM review_eligibility_monitoring_sessions s JOIN projects p ON p.id=s.project_id
        JOIN project_contracts pc ON pc.id=s.project_contract_id WHERE s.id=? LIMIT 1");
    $stmt->execute([(int) $session_id]);
    $session = $stmt->fetch();
    if (!$session || !in_array((string) $session['status'], ['active', 'provider_delayed'], true)) return $session ?: null;

    $latest = reviewEligibilityMonitoringLatestBlock($session);
    if (!$latest['ok']) return reviewEligibilityMonitoringDelay($db, $session, 'Blockchain data is temporarily delayed. Monitoring will resume automatically.');
    $from_block = max((int) $session['start_block'] + 1, (int) $session['last_checked_block'] + 1);
    $transactions = $from_block <= (int) $latest['block']
        ? reviewEligibilityMonitoringTransactions($session, $session['wallet_address'], $from_block, $latest['block'], 'asc')
        : ['ok' => true, 'transactions' => []];
    if (!$transactions['ok']) return reviewEligibilityMonitoringDelay($db, $session, 'Blockchain data is temporarily delayed. Monitoring will resume automatically.');

    $balance_raw = reviewEligibilityMonitoringDigits($session['last_balance_raw']);
    $wallet = strtolower((string) $session['wallet_address']);
    $insert_event = $db->prepare("INSERT IGNORE INTO review_eligibility_monitoring_events
        (monitoring_session_id, tx_hash, log_index, block_number, block_hash, event_at, direction, amount_raw, balance_after_raw, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($transactions['transactions'] as $tx) {
        $successful = (string) ($tx['isError'] ?? '0') !== '1' && (string) ($tx['txreceipt_status'] ?? '1') !== '0';
        if (!$successful) continue;
        $from = strtolower((string) ($tx['from'] ?? ''));
        $to = strtolower((string) ($tx['to'] ?? ''));
        if ($from === $wallet && $to === $wallet) continue;
        $direction = $to === $wallet ? 'incoming' : ($from === $wallet ? 'outgoing' : 'ignored');
        if ($direction === 'ignored') continue;
        $amount_raw = reviewEligibilityMonitoringDigits($tx['value'] ?? '0');
        if ($direction === 'incoming') {
            $balance_raw = reviewEligibilityMonitoringAdd($balance_raw, $amount_raw);
        } else {
            $deduction = $amount_raw;
            if (strtoupper((string) $session['token_type']) === 'NATIVE') {
                $gas = reviewEligibilityMonitoringMultiply($tx['gasUsed'] ?? '0', $tx['gasPrice'] ?? '0');
                $deduction = reviewEligibilityMonitoringAdd($deduction, $gas);
            }
            $balance_raw = reviewEligibilityMonitoringSubtract($balance_raw, $deduction);
        }
        $insert_event->execute([(int) $session['id'], (string) ($tx['hash'] ?? ''), (int) ($tx['logIndex'] ?? $tx['transactionIndex'] ?? 0),
            (int) ($tx['blockNumber'] ?? 0), (string) ($tx['blockHash'] ?? ''), date('Y-m-d H:i:s', (int) ($tx['timeStamp'] ?? time())),
            $direction, $amount_raw, $balance_raw]);
        if (reviewEligibilityMonitoringCompare($balance_raw, $session['required_amount_raw']) < 0) {
            return reviewEligibilityMonitoringDisqualify($db, $session, $balance_raw, (string) ($tx['hash'] ?? ''), (int) ($tx['timeStamp'] ?? time()));
        }
    }

    $current = reviewEligibilityMonitoringBalance($session, $wallet);
    if (!$current['ok']) return reviewEligibilityMonitoringDelay($db, $session, 'Blockchain data is temporarily delayed. Monitoring will resume automatically.');
    $balance_raw = $current['balance_raw'];
    if (reviewEligibilityMonitoringCompare($balance_raw, $session['required_amount_raw']) < 0) {
        return reviewEligibilityMonitoringDisqualify($db, $session, $balance_raw, '', time());
    }

    $eligible_now = time() >= (strtotime((string) $session['eligible_at']) ?: PHP_INT_MAX);
    if ($eligible_now) {
        $reason = sprintf('You maintained at least %s %s for the full holding period. You can now submit your review for %s.', $session['required_amount'], $session['token_symbol'], $session['project_name']);
        $update = $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='eligible', reason_code='eligible', reason=?, last_balance_raw=?, last_checked_block=?, last_checked_at=NOW(), completed_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 24 HOUR), next_check_at=DATE_ADD(NOW(), INTERVAL 24 HOUR), updated_at=NOW() WHERE id=? AND status IN ('active','provider_delayed')");
        $update->execute([$reason, $balance_raw, (int) $latest['block'], (int) $session['id']]);
        reviewEligibilityMonitoringQueue($db, (int) $session['id'], 'completed', 'You are eligible to review ' . $session['project_name'], $reason, '/public/submit-review.php?project_id=' . (int) $session['project_id']);
    } else {
        $remaining = max(0, (strtotime((string) $session['eligible_at']) ?: time()) - time());
        $next_minutes = $remaining <= 600 ? 1 : 10;
        $reason = sprintf('%s %s confirmed. Keep at least %s %s until %s.', reviewEligibilityMonitoringRawToDecimal($balance_raw, (int) $session['token_decimals']), $session['token_symbol'], $session['required_amount'], $session['token_symbol'], date('M j, Y g:i A', strtotime((string) $session['eligible_at'])));
        $update = $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='active', reason_code='monitoring_active', reason=?, last_balance_raw=?, last_checked_block=?, last_checked_at=NOW(), next_check_at=DATE_ADD(NOW(), INTERVAL ? MINUTE), updated_at=NOW() WHERE id=? AND status IN ('active','provider_delayed')");
        $update->execute([$reason, $balance_raw, (int) $latest['block'], $next_minutes, (int) $session['id']]);
    }
    $stmt->execute([(int) $session['id']]);
    return $stmt->fetch() ?: null;
}

function reviewEligibilityMonitoringDelay(PDO $db, array $session, $reason) {
    $stmt = $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='provider_delayed', reason_code='provider_unavailable', reason=?, next_check_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at=NOW() WHERE id=? AND status IN ('active','provider_delayed')");
    $stmt->execute([(string) $reason, (int) $session['id']]);
    reviewEligibilityMonitoringQueue($db, (int) $session['id'], 'provider_delayed', 'Blockchain monitoring delayed', $reason, '/public/submit-review.php?project_id=' . (int) $session['project_id']);
    return reviewEligibilityMonitoringGetLatest($db, (int) $session['user_id'], (int) $session['project_id'], (string) $session['wallet_address']);
}

function reviewEligibilityMonitoringDisqualify(PDO $db, array $session, $balance_raw, $tx_hash, $event_ts) {
    $display = reviewEligibilityMonitoringRawToDecimal($balance_raw, (int) $session['token_decimals']);
    $reason = sprintf('Verification stopped: your %s balance dropped to %s %s at %s. This project requires at least %s %s continuously. Restore the balance and start again.', $session['token_symbol'], $display, $session['token_symbol'], date('M j, Y g:i A', $event_ts), $session['required_amount'], $session['token_symbol']);
    $stmt = $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='disqualified', reason_code='balance_below_required', reason=?, last_balance_raw=?, failure_tx_hash=?, disqualified_at=NOW(), last_checked_at=NOW(), updated_at=NOW() WHERE id=? AND status IN ('active','provider_delayed')");
    $stmt->execute([$reason, $balance_raw, $tx_hash !== '' ? $tx_hash : null, (int) $session['id']]);
    reviewEligibilityMonitoringQueue($db, (int) $session['id'], 'stopped', 'Holding verification stopped', $reason, '/public/submit-review.php?project_id=' . (int) $session['project_id']);
    return reviewEligibilityMonitoringGetLatest($db, (int) $session['user_id'], (int) $session['project_id'], (string) $session['wallet_address']);
}

function reviewEligibilityMonitoringValidateForSubmission(PDO $db, $user_id, $project_id, $wallet) {
    $session = reviewEligibilityMonitoringGetLatest($db, $user_id, $project_id, $wallet);
    if (!$session) throw new RuntimeException('Start holding verification before submitting this review.');
    if (in_array((string) $session['status'], ['active', 'provider_delayed'], true)) {
        $session = reviewEligibilityMonitoringProcess($db, (int) $session['id']);
    }
    if (!$session || (string) $session['status'] !== 'eligible') {
        throw new RuntimeException((string) ($session['reason'] ?? 'Holding verification is not complete yet.'));
    }
    if (!empty($session['expires_at']) && strtotime((string) $session['expires_at']) <= time()) {
        $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='expired', reason_code='eligibility_expired', reason='Eligibility expired before the review was submitted.', updated_at=NOW() WHERE id=? AND status='eligible'")->execute([(int) $session['id']]);
        throw new RuntimeException('Your eligibility window expired. Start a new holding verification.');
    }
    $fresh = reviewEligibilityMonitoringProcessEligibleBalance($db, $session);
    return $fresh;
}

function reviewEligibilityMonitoringProcessEligibleBalance(PDO $db, array $session) {
    $balance = reviewEligibilityMonitoringBalance($session, $session['wallet_address']);
    if (!$balance['ok']) throw new RuntimeException('Blockchain data is temporarily unavailable. Your eligibility has not been removed; please try submitting again shortly.');
    if (reviewEligibilityMonitoringCompare($balance['balance_raw'], $session['required_amount_raw']) < 0) {
        reviewEligibilityMonitoringDisqualify($db, $session, $balance['balance_raw'], '', time());
        throw new RuntimeException(sprintf('Your %s balance is now below the required %s %s. Restore the balance and start verification again.', $session['token_symbol'], $session['required_amount'], $session['token_symbol']));
    }
    $session['last_balance_raw'] = $balance['balance_raw'];
    return $session;
}

function reviewEligibilityMonitoringConsume(PDO $db, $session_id, $review_id) {
    $stmt = $db->prepare("UPDATE review_eligibility_monitoring_sessions SET status='consumed', reason_code='review_submitted', reason=?, consumed_at=NOW(), updated_at=NOW() WHERE id=? AND status='eligible'");
    $stmt->execute(['Eligibility used by review #' . (int) $review_id . '.', (int) $session_id]);
    return $stmt->rowCount() === 1;
}

function reviewEligibilityMonitoringDeliverOutbox(PDO $db, $limit = 25) {
    reviewEligibilityMonitoringEnsureSchema($db);
    $limit = max(1, min(100, (int) $limit));
    $rows = $db->query("SELECT o.*, s.user_id, s.project_id, p.name AS project_name, u.email, u.full_name, u.username
        FROM review_eligibility_notification_outbox o
        JOIN review_eligibility_monitoring_sessions s ON s.id=o.monitoring_session_id
        JOIN projects p ON p.id=s.project_id JOIN users u ON u.id=s.user_id
        WHERE o.next_attempt_at <= NOW() AND (o.in_app_delivered_at IS NULL OR o.email_delivered_at IS NULL)
        ORDER BY o.id ASC LIMIT " . $limit)->fetchAll();
    foreach ($rows as $row) {
        $errors = [];
        if (empty($row['in_app_delivered_at'])) {
            try {
                createNotification('user', (int) $row['user_id'], [
                    'event_key' => 'review.eligibility.' . $row['event_key'], 'title' => $row['title'], 'message' => $row['message'],
                    'action_url' => $row['action_url'], 'priority' => in_array($row['event_key'], ['stopped','completed'], true) ? 'high' : 'normal',
                    'meta' => ['monitoring_session_id' => (int) $row['monitoring_session_id'], 'project_id' => (int) $row['project_id']],
                ], $db);
                $db->prepare("UPDATE review_eligibility_notification_outbox SET in_app_delivered_at=NOW() WHERE id=?")->execute([(int) $row['id']]);
            } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
        if (empty($row['email_delivered_at'])) {
            $mail = sendSmtpEmail((string) $row['email'], (string) ($row['full_name'] ?: $row['username']), (string) $row['title'],
                '<p>' . nl2br(htmlspecialchars((string) $row['message'], ENT_QUOTES, 'UTF-8')) . '</p><p><a href="' . htmlspecialchars((defined('BASE_URL') ? BASE_URL : '') . (string) $row['action_url'], ENT_QUOTES, 'UTF-8') . '">Open CoinRex</a></p>',
                (string) $row['message']);
            if (!empty($mail['success'])) $db->prepare("UPDATE review_eligibility_notification_outbox SET email_delivered_at=NOW() WHERE id=?")->execute([(int) $row['id']]);
            else $errors[] = (string) ($mail['message'] ?? 'Email delivery failed.');
        }
        $db->prepare("UPDATE review_eligibility_notification_outbox SET attempts=attempts+1, last_error=?, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), updated_at=NOW() WHERE id=?")
            ->execute([$errors ? implode(' ', $errors) : null, (int) $row['id']]);
    }
    return count($rows);
}
