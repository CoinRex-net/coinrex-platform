<?php
/**
 * On-chain review eligibility helpers.
 */

function reviewEligibilityEnv($key, $default = '') {
    $value = getenv((string) $key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    static $dotenv = null;
    if ($dotenv === null) {
        $dotenv = [];
        $env_path = dirname(__DIR__, 2) . '/.env';
        if (is_readable($env_path)) {
            foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$env_key, $env_value] = explode('=', $line, 2);
                $dotenv[trim($env_key)] = trim($env_value, " \t\n\r\0\x0B\"'");
            }
        }
    }

    $file_value = $dotenv[(string) $key] ?? '';
    return is_string($file_value) && trim($file_value) !== '' ? trim($file_value) : $default;
}

function ensureReviewEligibilitySchema(PDO $db = null) {
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS project_contracts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            network_name VARCHAR(100) NOT NULL,
            network_slug VARCHAR(80) NULL,
            chain_id INT UNSIGNED NOT NULL,
            contract_address VARCHAR(100) NOT NULL,
            token_type VARCHAR(20) NOT NULL DEFAULT 'ERC20',
            token_name VARCHAR(120) NULL,
            token_symbol VARCHAR(40) NULL,
            decimals TINYINT UNSIGNED NULL,
            eligibility_min_amount VARCHAR(120) NULL,
            eligibility_holding_minutes INT UNSIGNED NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            verification_status VARCHAR(30) NOT NULL DEFAULT 'needs_check',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_project_contract_chain_address (chain_id, contract_address),
            KEY idx_project_contracts_project (project_id, is_active),
            KEY idx_project_contracts_primary (project_id, is_primary),
            CONSTRAINT fk_project_contracts_project
                FOREIGN KEY (project_id) REFERENCES projects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_eligibility_checks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            project_id INT UNSIGNED NOT NULL,
            wallet_address VARCHAR(100) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'not_eligible',
            matched_project_contract_id INT UNSIGNED NULL,
            matched_chain_id INT UNSIGNED NULL,
            balance_raw VARCHAR(120) NULL,
            balance_display VARCHAR(120) NULL,
            reason TEXT NULL,
            checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            raw_result_json JSON NULL,
            PRIMARY KEY (id),
            KEY idx_review_eligibility_lookup (user_id, project_id, wallet_address, expires_at),
            KEY idx_review_eligibility_status (status, checked_at),
            CONSTRAINT fk_review_eligibility_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_review_eligibility_project
                FOREIGN KEY (project_id) REFERENCES projects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_review_eligibility_contract
                FOREIGN KEY (matched_project_contract_id) REFERENCES project_contracts(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $review_columns = [
        'eligibility_check_id' => "ALTER TABLE reviews ADD COLUMN eligibility_check_id INT UNSIGNED NULL AFTER proof_status",
        'eligibility_status' => "ALTER TABLE reviews ADD COLUMN eligibility_status VARCHAR(30) NULL AFTER eligibility_check_id",
        'eligibility_wallet_address' => "ALTER TABLE reviews ADD COLUMN eligibility_wallet_address VARCHAR(100) NULL AFTER eligibility_status",
        'eligibility_chain_id' => "ALTER TABLE reviews ADD COLUMN eligibility_chain_id INT UNSIGNED NULL AFTER eligibility_wallet_address",
        'eligibility_contract_address' => "ALTER TABLE reviews ADD COLUMN eligibility_contract_address VARCHAR(100) NULL AFTER eligibility_chain_id",
    ];
    foreach ($review_columns as $column => $sql) {
        if (!tableHasColumn('reviews', $column)) {
            $db->exec($sql);
        }
    }
    try {
        $db->exec("ALTER TABLE reviews MODIFY tx_hash VARCHAR(255) NULL");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE projects MODIFY contract_address VARCHAR(255) NULL");
        $db->exec("UPDATE projects SET contract_address = NULL WHERE TRIM(COALESCE(contract_address, '')) = ''");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE project_contracts MODIFY contract_address VARCHAR(100) NULL");
        $db->exec("UPDATE project_contracts SET contract_address = NULL WHERE token_type = 'NATIVE' AND TRIM(COALESCE(contract_address, '')) = ''");
    } catch (Throwable $e) {}

    $ready = true;
}

function reviewEligibilityFindWalletReviewUsage(PDO $db, $wallet_address, $exclude_review_id = 0, $project_id = 0) {
    $wallet_address = strtolower(trim((string) $wallet_address));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        return null;
    }

    $conditions = [];
    $params = [];

    if (tableHasColumn('reviews', 'eligibility_wallet_address')) {
        $conditions[] = 'LOWER(eligibility_wallet_address) = ?';
        $params[] = $wallet_address;
    }

    if (tableHasColumn('reviews', 'wallet_address')) {
        $conditions[] = 'LOWER(wallet_address) = ?';
        $params[] = $wallet_address;
    }

    if (!$conditions) {
        return null;
    }

    $sql = "
        SELECT id, user_id, project_id, status, wallet_address, eligibility_wallet_address
        FROM reviews
        WHERE (" . implode(' OR ', $conditions) . ")
    ";
    if ((int) $exclude_review_id > 0) {
        $sql .= " AND id <> ?";
        $params[] = (int) $exclude_review_id;
    }
    if ((int) $project_id > 0) {
        $sql .= " AND project_id = ?";
        $params[] = (int) $project_id;
    }
    if (tableHasColumn('reviews', 'status')) {
        $sql .= " AND LOWER(COALESCE(status, '')) NOT IN ('rejected', 'deleted', 'cancelled')";
    }
    $sql .= " ORDER BY id DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function reviewEligibilityNormalizeNetworkSlug($name) {
    $slug = strtolower(trim((string) $name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string) $slug, '-');
}

function reviewEligibilityKnownNetworks() {
    return [
        'Ethereum' => ['slug' => 'ethereum', 'chain_id' => 1],
        'BNB Smart Chain' => ['slug' => 'bsc', 'chain_id' => 56],
        'Polygon' => ['slug' => 'polygon', 'chain_id' => 137],
        'Arbitrum' => ['slug' => 'arbitrum', 'chain_id' => 42161],
        'Optimism' => ['slug' => 'optimism', 'chain_id' => 10],
        'Avalanche' => ['slug' => 'avalanche', 'chain_id' => 43114],
        'Base' => ['slug' => 'base', 'chain_id' => 8453],
    ];
}

function reviewEligibilityNormalizeContractRows(array $source, array &$errors = []) {
    $rows = [];
    $networks = reviewEligibilityKnownNetworks();
    $names = $source['contract_network_name'] ?? [];
    $other_names = $source['contract_network_other'] ?? [];
    $chain_ids = $source['contract_chain_id'] ?? [];
    $addresses = $source['contract_address_multi'] ?? [];
    $types = $source['contract_token_type'] ?? [];
    $symbols = $source['contract_token_symbol'] ?? [];
    $decimals = $source['contract_decimals'] ?? [];
    $minimum_amounts = $source['contract_min_amount'] ?? [];
    $holding_values = $source['contract_holding_value'] ?? [];
    $holding_units = $source['contract_holding_unit'] ?? [];
    $primary = (int) ($source['primary_contract_index'] ?? 0);
    $active = $source['contract_is_active'] ?? [];
    $has_active_inputs = is_array($active) && count($active) > 0;

    if (!is_array($names)) {
        $names = [];
    }
    if (!is_array($other_names)) {
        $other_names = [];
    }
    $count = max(count($names), is_array($addresses) ? count($addresses) : 0);
    for ($i = 0; $i < $count; $i++) {
        $network_name = trim((string) ($names[$i] ?? ''));
        if ($network_name === '__other__') {
            $network_name = trim((string) ($other_names[$i] ?? ''));
        }
        $address = strtolower(trim((string) ($addresses[$i] ?? '')));
        $chain_id = (int) ($chain_ids[$i] ?? 0);
        $token_type = strtoupper(trim((string) ($types[$i] ?? 'ERC20')));
        $token_symbol = strtoupper(trim((string) ($symbols[$i] ?? '')));
        $token_decimals = trim((string) ($decimals[$i] ?? ''));
        $minimum_amount = trim((string) ($minimum_amounts[$i] ?? ''));
        $holding_value = (int) ($holding_values[$i] ?? 0);
        $holding_unit = strtolower(trim((string) ($holding_units[$i] ?? 'hours')));
        if ($token_type === 'NATIVE_TOKEN') {
            $token_type = 'NATIVE';
        }
        if ($network_name === '' && $address === '' && $chain_id <= 0) {
            continue;
        }
        if ($network_name === '') {
            $errors['contracts'] = 'Network is required for every contract row.';
            continue;
        }
        if ($chain_id <= 0 && isset($networks[$network_name])) {
            $chain_id = (int) $networks[$network_name]['chain_id'];
        }
        if ($chain_id <= 0) {
            $errors['contracts'] = 'Chain ID must be a positive number for every contract row.';
            continue;
        }
        if (!in_array($token_type, ['NATIVE', 'ERC20', 'ERC721', 'ERC1155'], true)) {
            $errors['contracts'] = 'Token type must be Native, ERC20, ERC721, or ERC1155.';
            continue;
        }
        if ($token_type === 'NATIVE') {
            $address = '';
        } elseif (!preg_match('/^0x[a-f0-9]{40}$/', $address)) {
            $errors['contracts'] = 'Every contract address must be a valid EVM address.';
            continue;
        }
        if ($token_symbol === '' || !preg_match('/^[A-Z0-9._-]{1,40}$/', $token_symbol)) {
            $errors['contracts'] = 'A valid token symbol is required for every eligibility contract.';
            continue;
        }
        if ($token_decimals === '' || !ctype_digit($token_decimals) || (int) $token_decimals > 36) {
            $errors['contracts'] = 'Token decimals must be between 0 and 36.';
            continue;
        }
        if ($minimum_amount === '' || !preg_match('/^\d+(?:\.\d{1,36})?$/', $minimum_amount) || (float) $minimum_amount <= 0) {
            $errors['contracts'] = 'A positive token-specific minimum amount is required.';
            continue;
        }
        if ($holding_value <= 0 || !in_array($holding_unit, ['hours', 'days'], true)) {
            $errors['contracts'] = 'Holding duration must be a positive number of hours or days.';
            continue;
        }
        $is_active = !$has_active_inputs || (array_key_exists($i, $active) && (string) $active[$i] === '1');
        $rows[] = [
            'network_name' => $network_name,
            'network_slug' => $networks[$network_name]['slug'] ?? reviewEligibilityNormalizeNetworkSlug($network_name),
            'chain_id' => $chain_id,
            'contract_address' => $address,
            'token_type' => $token_type,
            'token_symbol' => $token_symbol,
            'decimals' => (int) $token_decimals,
            'eligibility_min_amount' => $minimum_amount,
            'eligibility_holding_minutes' => $holding_value * ($holding_unit === 'days' ? 1440 : 60),
            'is_primary' => $i === $primary ? 1 : 0,
            'is_active' => $is_active ? 1 : 0,
        ];
    }

    $seen = [];
    $primary_count = 0;
    foreach ($rows as $row) {
        if ((int) $row['is_active'] === 1) {
            if ($row['token_type'] !== 'NATIVE') {
                $key = $row['chain_id'] . ':' . $row['contract_address'];
                if (isset($seen[$key])) {
                    $errors['contracts'] = 'Duplicate chain + contract rows are not allowed.';
                }
                $seen[$key] = true;
            }
            $primary_count += (int) $row['is_primary'] === 1 ? 1 : 0;
        }
    }
    if (count($rows) > 0 && $primary_count !== 1) {
        $errors['contracts'] = 'Exactly one active primary contract is required.';
    }

    return $rows;
}

function reviewEligibilityParseBulkRows($bulk_text) {
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $bulk_text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', str_getcsv($line));
        if (count($parts) < 3) {
            continue;
        }
        $token_type = strtoupper($parts[3] ?? 'ERC20');
        if ($token_type === 'NATIVE') {
            $parts[2] = '';
        }
        $rows[] = [
            'network_name' => $parts[0],
            'chain_id' => $parts[1],
            'contract_address' => $parts[2],
            'token_type' => $token_type,
            'token_symbol' => strtoupper((string) ($parts[4] ?? 'TOKEN')),
            'decimals' => (string) ($parts[5] ?? '18'),
            'minimum_amount' => (string) ($parts[6] ?? '1'),
            'holding_value' => (string) ($parts[7] ?? '24'),
            'holding_unit' => strtolower((string) ($parts[8] ?? 'hours')),
        ];
    }
    return $rows;
}

function reviewEligibilitySaveProjectContracts(PDO $db, $project_id, array $rows) {
    ensureReviewEligibilitySchema($db);
    $project_id = (int) $project_id;
    $db->prepare("DELETE FROM project_contracts WHERE project_id = ?")->execute([$project_id]);
    if (empty($rows)) {
        return;
    }
    $insert = $db->prepare("
        INSERT INTO project_contracts (
            project_id, network_name, network_slug, chain_id, contract_address, token_type,
            token_symbol, decimals, eligibility_min_amount, eligibility_holding_minutes,
            is_primary, is_active, verification_status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'needs_check', NOW(), NOW())
    ");
    foreach ($rows as $row) {
        $insert->execute([
            $project_id,
            $row['network_name'],
            $row['network_slug'],
            (int) $row['chain_id'],
            $row['token_type'] === 'NATIVE' ? null : strtolower((string) $row['contract_address']),
            strtoupper((string) $row['token_type']),
            strtoupper((string) $row['token_symbol']),
            (int) $row['decimals'],
            (string) $row['eligibility_min_amount'],
            (int) $row['eligibility_holding_minutes'],
            (int) $row['is_primary'],
            (int) $row['is_active'],
        ]);
    }
}

function reviewEligibilityGetProjectContracts(PDO $db, $project_id, $active_only = true) {
    ensureReviewEligibilitySchema($db);
    $sql = "SELECT * FROM project_contracts WHERE project_id = ?";
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY is_primary DESC, id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([(int) $project_id]);
    return $stmt->fetchAll();
}

function reviewEligibilityGetFreshCheck(PDO $db, $user_id, $project_id, $wallet_address, $required_status = 'eligible') {
    ensureReviewEligibilitySchema($db);
    $status_clause = $required_status === null || $required_status === ''
        ? ''
        : "          AND status = ?\n";
    $stmt = $db->prepare("
        SELECT *
        FROM review_eligibility_checks
        WHERE user_id = ?
          AND project_id = ?
          AND wallet_address = ?
{$status_clause}
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY checked_at DESC, id DESC
        LIMIT 1
    ");
    $params = [(int) $user_id, (int) $project_id, strtolower((string) $wallet_address)];
    if ($status_clause !== '') {
        $params[] = (string) $required_status;
    }
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function reviewEligibilityExplorerRequest($base_url, array $params) {
    $url = rtrim((string) $base_url, '?') . '?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'header' => "Accept: application/json\r\nUser-Agent: CoinRexReviewEligibility/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return ['ok' => false, 'status' => 'unavailable', 'message' => 'Explorer API unavailable.', 'raw' => null];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => 'unavailable', 'message' => 'Explorer API returned invalid JSON.', 'raw' => $raw];
    }
    $message = strtolower((string) ($decoded['message'] ?? ''));
    $result_text = strtolower(trim(is_scalar($decoded['result'] ?? null) ? (string) $decoded['result'] : ''));
    $is_empty_txlist = strpos($message, 'no transactions') !== false || strpos($result_text, 'no transactions') !== false;
    if (($decoded['status'] ?? '') === '0' && !$is_empty_txlist) {
        return ['ok' => false, 'status' => strpos($message, 'rate') !== false ? 'rate_limited' : 'unavailable', 'message' => (string) ($decoded['message'] ?? 'Explorer API error.'), 'raw' => $decoded];
    }
    return ['ok' => true, 'status' => 'ok', 'message' => 'OK', 'raw' => $decoded, 'result' => $is_empty_txlist ? [] : ($decoded['result'] ?? '0')];
}

function reviewEligibilityTokenSymbol(array $contract) {
    $symbol = strtoupper(trim((string) ($contract['token_symbol'] ?? '')));
    if ($symbol !== '') {
        return $symbol;
    }
    $network = strtolower((string) ($contract['network_name'] ?? $contract['network_slug'] ?? ''));
    if (strpos($network, 'plasma') !== false || (int) ($contract['chain_id'] ?? 0) === 9745) {
        return 'XPL';
    }
    return strtoupper((string) ($contract['token_type'] ?? 'TOKEN')) === 'NATIVE' ? 'NATIVE' : 'TOKEN';
}

function reviewEligibilityDecimalToFloat($value, $decimals = 18) {
    $value = preg_replace('/\D/', '', (string) $value);
    if ($value === '') {
        return 0.0;
    }
    $decimals = max(0, (int) $decimals);
    if ($decimals === 0) {
        return (float) $value;
    }
    $length = strlen($value);
    if ($length <= $decimals) {
        $value = str_pad($value, $decimals + 1, '0', STR_PAD_LEFT);
        $length = strlen($value);
    }
    $whole = substr($value, 0, $length - $decimals);
    $fraction = rtrim(substr($value, $length - $decimals), '0');
    return (float) ($whole . ($fraction !== '' ? '.' . $fraction : ''));
}

function reviewEligibilityNativePriceUsd($symbol) {
    $symbol = strtoupper(trim((string) $symbol));
    $coin_ids = [
        'XPL' => 'plasma',
    ];
    if (!isset($coin_ids[$symbol])) {
        return ['price' => null, 'status' => 'unsupported'];
    }
    $url = 'https://api.coingecko.com/api/v3/simple/price?' . http_build_query([
        'ids' => $coin_ids[$symbol],
        'vs_currencies' => 'usd',
        'include_last_updated_at' => 'true',
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
            'header' => "Accept: application/json\r\nUser-Agent: CoinRexReviewEligibility/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $coin_id = $coin_ids[$symbol];
    $price = is_array($decoded) ? ($decoded[$coin_id]['usd'] ?? null) : null;
    if (!is_numeric($price)) {
        return ['price' => null, 'status' => 'unavailable'];
    }
    return [
        'price' => (float) $price,
        'status' => 'ok',
        'last_updated_at' => (int) ($decoded[$coin_id]['last_updated_at'] ?? 0),
    ];
}

function reviewEligibilityAnalyzeNativeTransactions(array $transactions, $wallet_address, $current_balance_raw, $window_days) {
    $wallet_address = strtolower((string) $wallet_address);
    $window_days = max(1, (int) $window_days);
    $now = time();
    $window_start = $now - ($window_days * 86400);
    $current_balance = reviewEligibilityDecimalToFloat($current_balance_raw, 18);
    $running_balance = $current_balance;
    $cursor = $now;
    $weighted_seconds = 0.0;
    $total_in = 0.0;
    $total_out = 0.0;
    $gas_paid = 0.0;
    $tx_count = 0;

    usort($transactions, static function ($a, $b) {
        return (int) ($b['timeStamp'] ?? 0) <=> (int) ($a['timeStamp'] ?? 0);
    });

    foreach ($transactions as $tx) {
        $timestamp = (int) ($tx['timeStamp'] ?? 0);
        if ($timestamp <= 0 || $timestamp > $now) {
            continue;
        }
        if ($timestamp < $window_start) {
            break;
        }
        $duration = max(0, $cursor - $timestamp);
        $weighted_seconds += $running_balance * $duration;
        $cursor = $timestamp;

        $from = strtolower((string) ($tx['from'] ?? ''));
        $to = strtolower((string) ($tx['to'] ?? ''));
        $is_sender = $from === $wallet_address;
        $is_receiver = $to === $wallet_address;
        if (!$is_sender && !$is_receiver) {
            continue;
        }

        $value = reviewEligibilityDecimalToFloat((string) ($tx['value'] ?? '0'), 18);
        $gas_fee = 0.0;
        if ($is_sender) {
            $gas_used = (string) ($tx['gasUsed'] ?? '0');
            $gas_price = (string) ($tx['gasPrice'] ?? '0');
            if (ctype_digit($gas_used) && ctype_digit($gas_price)) {
                $gas_fee = ((float) $gas_used * (float) $gas_price) / 1000000000000000000;
            }
        }
        $successful = (string) ($tx['isError'] ?? '0') !== '1' && (string) ($tx['txreceipt_status'] ?? '1') !== '0';
        if ($is_receiver && $successful) {
            $running_balance -= $value;
            $total_in += $value;
        }
        if ($is_sender) {
            if ($successful) {
                $running_balance += $value;
                $total_out += $value;
            }
            $running_balance += $gas_fee;
            $gas_paid += $gas_fee;
        }
        $running_balance = max(0.0, $running_balance);
        $tx_count++;
    }

    if ($cursor > $window_start) {
        $weighted_seconds += $running_balance * ($cursor - $window_start);
    }
    $window_seconds = max(1, $now - $window_start);
    return [
        'current_balance' => $current_balance,
        'average_balance' => $weighted_seconds / $window_seconds,
        'total_in' => $total_in,
        'total_out' => $total_out,
        'gas_paid' => $gas_paid,
        'tx_count' => $tx_count,
        'window_start' => gmdate('c', $window_start),
        'window_end' => gmdate('c', $now),
    ];
}

function reviewEligibilityCheckNativeToken(PDO $db, array $contract, array $project, $wallet_address, $api_base, $api_key) {
    $chain_id = (int) ($contract['chain_id'] ?? 0);
    $symbol = reviewEligibilityTokenSymbol($contract);
    $required_balance = max(0.0, (float) ($project['min_holding_amount'] ?? 0));
    $window_days = max(1, (int) ($project['required_holding_days'] ?? 1));

    $balance_response = reviewEligibilityExplorerRequest($api_base, [
        'chainid' => $chain_id,
        'module' => 'account',
        'action' => 'balance',
        'address' => $wallet_address,
        'tag' => 'latest',
        'apikey' => $api_key,
    ]);
    if (!$balance_response['ok']) {
        return [
            'blocked' => true,
            'status' => $balance_response['status'],
            'reason' => $balance_response['status'] === 'rate_limited' ? 'Explorer API is rate limited. Recheck later.' : 'Explorer API is unavailable. Recheck later.',
            'result' => [
                'contract_id' => (int) ($contract['id'] ?? 0),
                'chain_id' => $chain_id,
                'contract_address' => null,
                'token_type' => 'NATIVE',
                'api_status' => $balance_response['status'],
            ],
        ];
    }

    usleep(50000);
    $tx_response = reviewEligibilityExplorerRequest($api_base, [
        'chainid' => $chain_id,
        'module' => 'account',
        'action' => 'txlist',
        'address' => $wallet_address,
        'startblock' => 0,
        'endblock' => 99999999,
        'page' => 1,
        'offset' => 10000,
        'sort' => 'desc',
        'apikey' => $api_key,
    ]);
    if (!$tx_response['ok']) {
        return [
            'blocked' => true,
            'status' => $tx_response['status'],
            'reason' => $tx_response['status'] === 'rate_limited' ? 'Explorer API is rate limited. Recheck later.' : 'Explorer API is unavailable. Recheck later.',
            'result' => [
                'contract_id' => (int) ($contract['id'] ?? 0),
                'chain_id' => $chain_id,
                'contract_address' => null,
                'token_type' => 'NATIVE',
                'api_status' => $tx_response['status'],
                'balance_raw' => (string) ($balance_response['result'] ?? '0'),
            ],
        ];
    }

    $balance_raw = preg_replace('/\D/', '', (string) ($balance_response['result'] ?? '0'));
    $transactions = is_array($tx_response['result'] ?? null) ? $tx_response['result'] : [];
    $activity = reviewEligibilityAnalyzeNativeTransactions($transactions, $wallet_address, $balance_raw, $window_days);
    $price = reviewEligibilityNativePriceUsd($symbol);
    $price_value = is_numeric($price['price'] ?? null) ? (float) $price['price'] : null;
    $eligible = $required_balance <= 0.0 || (float) $activity['average_balance'] >= $required_balance;
    $balance_display = rtrim(rtrim(number_format((float) $activity['average_balance'], 8, '.', ''), '0'), '.');
    if ($balance_display === '') {
        $balance_display = '0';
    }

    return [
        'blocked' => false,
        'eligible' => $eligible,
        'balance_raw' => $balance_raw,
        'balance_display' => $balance_display . ' ' . $symbol . ' avg',
        'reason' => $eligible
            ? sprintf('Average %s balance %.8f meets required %.8f over %d day(s).', $symbol, (float) $activity['average_balance'], $required_balance, $window_days)
            : sprintf('Average %s balance %.8f is below required %.8f over %d day(s).', $symbol, (float) $activity['average_balance'], $required_balance, $window_days),
        'result' => [
            'contract_id' => (int) ($contract['id'] ?? 0),
            'chain_id' => $chain_id,
            'contract_address' => null,
            'token_type' => 'NATIVE',
            'token_symbol' => $symbol,
            'api_status' => 'ok',
            'balance_raw' => $balance_raw,
            'requirement' => [
                'min_holding_amount' => $required_balance,
                'required_holding_days' => $window_days,
                'unit' => 'token',
                'token_symbol' => $symbol,
            ],
            'balances' => [
                'current_balance' => (float) $activity['current_balance'],
                'average_balance' => (float) $activity['average_balance'],
                'current_balance_usd' => $price_value !== null ? (float) $activity['current_balance'] * $price_value : null,
                'average_balance_usd' => $price_value !== null ? (float) $activity['average_balance'] * $price_value : null,
                'price_usd' => $price_value,
                'price_status' => (string) ($price['status'] ?? 'unavailable'),
            ],
            'activity' => [
                'total_in' => (float) $activity['total_in'],
                'total_out' => (float) $activity['total_out'],
                'gas_paid' => (float) $activity['gas_paid'],
                'tx_count' => (int) $activity['tx_count'],
                'window_start' => (string) $activity['window_start'],
                'window_end' => (string) $activity['window_end'],
            ],
            'decision' => [
                'status' => $eligible ? 'eligible' : 'not_eligible',
                'required_balance' => $required_balance,
                'average_balance' => (float) $activity['average_balance'],
                'passed' => $eligible,
            ],
        ],
    ];
}

function reviewEligibilityCheckProject(PDO $db, $user_id, $project_id, $wallet_address) {
    ensureReviewEligibilitySchema($db);
    $wallet_address = strtolower(trim((string) $wallet_address));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        throw new InvalidArgumentException('Valid EVM wallet address is required.');
    }
    $cached = reviewEligibilityGetFreshCheck($db, $user_id, $project_id, $wallet_address, null);
    if ($cached) {
        return ['status' => (string) ($cached['status'] ?? 'not_eligible'), 'cached' => true, 'check' => $cached];
    }

    $project_stmt = $db->prepare("SELECT id, name, min_holding_amount, required_holding_days FROM projects WHERE id = ? LIMIT 1");
    $project_stmt->execute([(int) $project_id]);
    $project = $project_stmt->fetch() ?: [];

    $contracts = reviewEligibilityGetProjectContracts($db, $project_id, true);
    if (empty($contracts)) {
        return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
            'status' => 'blocked',
            'reason' => 'No active project contracts are configured for automatic eligibility.',
            'raw_result_json' => ['contracts' => 0],
        ]);
    }

    $api_key = reviewEligibilityEnv('ETHERSCAN_API_KEY', reviewEligibilityEnv('EXPLORER_API_KEY', ''));
    if ($api_key === '') {
        return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
            'status' => 'blocked',
            'reason' => 'Eligibility API key is not configured.',
        ]);
    }
    $api_base = reviewEligibilityEnv('ETHERSCAN_API_BASE_URL', 'https://api.etherscan.io/v2/api');
    $results = [];
    foreach ($contracts as $contract) {
        $token_type = strtoupper((string) ($contract['token_type'] ?? 'ERC20'));
        if ($token_type === 'NATIVE') {
            usleep(50000);
            $native_check = reviewEligibilityCheckNativeToken($db, $contract, $project, $wallet_address, $api_base, $api_key);
            $results[] = $native_check['result'];
            if (!empty($native_check['eligible'])) {
                return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                    'status' => 'eligible',
                    'matched_project_contract_id' => (int) $contract['id'],
                    'matched_chain_id' => (int) $contract['chain_id'],
                    'balance_raw' => (string) ($native_check['balance_raw'] ?? '0'),
                    'balance_display' => (string) ($native_check['balance_display'] ?? ''),
                    'reason' => (string) ($native_check['reason'] ?? 'Native token balance requirement met.'),
                    'raw_result_json' => ['results' => $results],
                ]);
            }
            if (!empty($native_check['blocked'])) {
                return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                    'status' => 'blocked',
                    'reason' => (string) ($native_check['reason'] ?? 'Explorer API is unavailable. Recheck later.'),
                    'raw_result_json' => ['results' => $results],
                ]);
            }
            continue;
        }
        if ($token_type === 'ERC1155') {
            $results[] = ['contract_id' => (int) $contract['id'], 'status' => 'unsupported', 'reason' => 'ERC1155 requires token ID support.'];
            continue;
        }
        usleep(50000);
        $balance_call_data = '0x70a08231' . str_pad(substr($wallet_address, 2), 64, '0', STR_PAD_LEFT);
        $response = reviewEligibilityExplorerRequest($api_base, [
            'chainid' => (int) $contract['chain_id'],
            'module' => 'proxy',
            'action' => 'eth_call',
            'to' => strtolower((string) $contract['contract_address']),
            'data' => $balance_call_data,
            'tag' => 'latest',
            'apikey' => $api_key,
        ]);
        $balance_raw = strtolower(trim((string) ($response['result'] ?? '0x0')));
        $has_balance = reviewEligibilityHexGreaterThanZero($balance_raw);
        $results[] = [
            'contract_id' => (int) $contract['id'],
            'chain_id' => (int) $contract['chain_id'],
            'contract_address' => strtolower((string) $contract['contract_address']),
            'token_type' => $token_type,
            'api_status' => $response['status'],
            'balance_raw' => $balance_raw,
        ];
        if ($response['ok'] && $has_balance) {
            return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                'status' => 'eligible',
                'matched_project_contract_id' => (int) $contract['id'],
                'matched_chain_id' => (int) $contract['chain_id'],
                'balance_raw' => $balance_raw,
                'balance_display' => $balance_raw,
                'reason' => 'Token/NFT holder balance found on ' . (string) $contract['network_name'] . '.',
                'raw_result_json' => ['results' => $results],
            ]);
        }
        if (!$response['ok'] && in_array($response['status'], ['rate_limited', 'unavailable'], true)) {
            return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                'status' => 'blocked',
                'reason' => $response['status'] === 'rate_limited' ? 'Explorer API is rate limited. Recheck later.' : 'Explorer API is unavailable. Recheck later.',
                'raw_result_json' => ['results' => $results],
            ]);
        }
    }

    return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
        'status' => 'not_eligible',
        'reason' => 'No holder balance found on the supported project contracts.',
        'raw_result_json' => ['results' => $results],
    ]);
}

function reviewEligibilityHexGreaterThanZero($hex) {
    $hex = strtolower(trim((string) $hex));
    if (strpos($hex, '0x') === 0) {
        $hex = substr($hex, 2);
    }
    $hex = ltrim($hex, '0');
    return $hex !== '' && preg_match('/^[a-f0-9]+$/', $hex);
}

function reviewEligibilityStoreCheck(PDO $db, $user_id, $project_id, $wallet_address, array $data) {
    $stmt = $db->prepare("
        INSERT INTO review_eligibility_checks (
            user_id, project_id, wallet_address, status, matched_project_contract_id,
            matched_chain_id, balance_raw, balance_display, reason, checked_at, expires_at, raw_result_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)
    ");
    $stmt->execute([
        (int) $user_id,
        (int) $project_id,
        strtolower((string) $wallet_address),
        (string) ($data['status'] ?? 'not_eligible'),
        $data['matched_project_contract_id'] ?? null,
        $data['matched_chain_id'] ?? null,
        $data['balance_raw'] ?? null,
        $data['balance_display'] ?? null,
        $data['reason'] ?? null,
        !empty($data['raw_result_json']) ? json_encode($data['raw_result_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
    $check_id = (int) $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM review_eligibility_checks WHERE id = ? LIMIT 1");
    $stmt->execute([$check_id]);
    return ['status' => (string) ($data['status'] ?? 'not_eligible'), 'cached' => false, 'check' => $stmt->fetch()];
}

/**
 * Instant verification: analyze the last 7 days of on-chain activity for a
 * wallet against a project's active contracts. Computes average balance and
 * holding days by tracking incoming/outgoing transfers of the same contract.
 *
 * Returns an array with status, matched contract, balances, holding analysis,
 * and a human-readable reason. Stores the result in review_eligibility_checks
 * with a 30-minute expiry so submission can validate against it.
 */
function reviewEligibilityInstantCheck(PDO $db, $user_id, $project_id, $wallet_address) {
    ensureReviewEligibilitySchema($db);
    $wallet_address = strtolower(trim((string) $wallet_address));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        throw new InvalidArgumentException('Valid EVM wallet address is required.');
    }

    $project_stmt = $db->prepare("SELECT id, name, min_holding_amount, required_holding_days FROM projects WHERE id = ? LIMIT 1");
    $project_stmt->execute([(int) $project_id]);
    $project = $project_stmt->fetch() ?: [];

    $contracts = reviewEligibilityGetProjectContracts($db, $project_id, true);
    if (empty($contracts)) {
        return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
            'status' => 'blocked',
            'reason' => 'No active project contracts are configured for automatic eligibility.',
            'raw_result_json' => ['contracts' => 0],
        ]);
    }

    $api_key = reviewEligibilityEnv('ETHERSCAN_API_KEY', reviewEligibilityEnv('EXPLORER_API_KEY', ''));
    if ($api_key === '') {
        return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
            'status' => 'blocked',
            'reason' => 'Eligibility API key is not configured.',
        ]);
    }
    $api_base = reviewEligibilityEnv('ETHERSCAN_API_BASE_URL', 'https://api.etherscan.io/v2/api');
    $window_days = max(1, (int) ($project['required_holding_days'] ?? 1));
    $required_amount = max(0.0, (float) ($project['min_holding_amount'] ?? 0));
    $now = time();
    $window_start = $now - ($window_days * 86400);
    $results = [];

    foreach ($contracts as $contract) {
        $token_type = strtoupper((string) ($contract['token_type'] ?? 'ERC20'));
        $decimals = isset($contract['decimals']) ? (int) $contract['decimals'] : 18;
        $symbol = strtoupper(trim((string) ($contract['token_symbol'] ?? '')));
        if ($symbol === '' && $token_type === 'NATIVE') {
            $symbol = reviewEligibilityTokenSymbol($contract);
        }
        $contract_min = trim((string) ($contract['eligibility_min_amount'] ?? ''));
        $contract_required = $contract_min !== '' ? (float) $contract_min : $required_amount;
        $contract_minutes = (int) ($contract['eligibility_holding_minutes'] ?? 0);
        $contract_days = $contract_minutes > 0 ? max(1, (int) ceil($contract_minutes / 1440)) : $window_days;

        if ($token_type === 'ERC1155') {
            $results[] = ['contract_id' => (int) $contract['id'], 'status' => 'unsupported', 'reason' => 'ERC1155 requires token ID support.'];
            continue;
        }

        $params = [
            'chainid' => (int) $contract['chain_id'],
            'module' => 'account',
            'address' => $wallet_address,
            'startblock' => 0,
            'endblock' => 99999999,
            'page' => 1,
            'offset' => 10000,
            'sort' => 'desc',
            'apikey' => $api_key,
        ];
        if ($token_type === 'NATIVE') {
            $params['action'] = 'txlist';
        } else {
            $params['action'] = 'tokentx';
            $params['contractaddress'] = strtolower((string) $contract['contract_address']);
        }

        usleep(50000);
        $response = reviewEligibilityExplorerRequest($api_base, $params);
        if (!$response['ok']) {
            $results[] = [
                'contract_id' => (int) $contract['id'],
                'chain_id' => (int) $contract['chain_id'],
                'contract_address' => strtolower((string) $contract['contract_address']),
                'token_type' => $token_type,
                'api_status' => $response['status'],
            ];
            if (in_array($response['status'], ['rate_limited', 'unavailable'], true)) {
                return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                    'status' => 'blocked',
                    'reason' => $response['status'] === 'rate_limited' ? 'Explorer API is rate limited. Recheck later.' : 'Explorer API is unavailable. Recheck later.',
                    'raw_result_json' => ['results' => $results],
                ]);
            }
            continue;
        }

        $transactions = is_array($response['result'] ?? null) ? $response['result'] : [];
        $analysis = reviewEligibilityInstantAnalyzeTransactions($transactions, $wallet_address, $contract_required, $contract_days, $decimals, $token_type);

        $eligible = $analysis['eligible'];
        $results[] = [
            'contract_id' => (int) $contract['id'],
            'chain_id' => (int) $contract['chain_id'],
            'contract_address' => strtolower((string) $contract['contract_address']),
            'token_type' => $token_type,
            'token_symbol' => $symbol,
            'api_status' => 'ok',
            'requirement' => [
                'min_holding_amount' => $contract_required,
                'required_holding_days' => $contract_days,
                'unit' => 'token',
                'token_symbol' => $symbol,
            ],
            'balances' => [
                'current_balance' => (float) $analysis['current_balance'],
                'average_balance' => (float) $analysis['average_balance'],
                'required_balance' => $contract_required,
            ],
            'holding' => [
                'holding_days' => (float) $analysis['holding_days'],
                'required_days' => $contract_days,
                'total_in' => (float) $analysis['total_in'],
                'total_out' => (float) $analysis['total_out'],
                'tx_count' => (int) $analysis['tx_count'],
                'window_start' => (string) $analysis['window_start'],
                'window_end' => (string) $analysis['window_end'],
            ],
            'decision' => [
                'status' => $eligible ? 'eligible' : 'not_eligible',
                'passed' => $eligible,
            ],
        ];

        if ($eligible) {
            $balance_display = rtrim(rtrim(number_format((float) $analysis['average_balance'], 8, '.', ''), '0'), '.');
            if ($balance_display === '') {
                $balance_display = '0';
            }
            return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
                'status' => 'eligible',
                'matched_project_contract_id' => (int) $contract['id'],
                'matched_chain_id' => (int) $contract['chain_id'],
                'balance_raw' => (string) $analysis['current_balance_raw'],
                'balance_display' => $balance_display . ' ' . $symbol . ' avg',
                'reason' => sprintf('Average %s balance %.8f meets required %.8f over %d day(s).', $symbol, (float) $analysis['average_balance'], $contract_required, $contract_days),
                'raw_result_json' => ['results' => $results],
            ]);
        }
    }

    return reviewEligibilityStoreCheck($db, $user_id, $project_id, $wallet_address, [
        'status' => 'not_eligible',
        'reason' => 'No qualifying holding period found in the last ' . $window_days . ' day(s). Try Live Verification or Manual Verification.',
        'raw_result_json' => ['results' => $results],
    ]);
}

/**
 * Analyze a wallet's transaction history for a single contract to compute
 * average balance and holding days. Tracks incoming transfers as balance
 * increases and outgoing transfers as decreases. A holding period is the
 * time between an incoming transfer and the next outgoing transfer that
 * drops the balance below the required minimum.
 */
function reviewEligibilityInstantAnalyzeTransactions(array $transactions, $wallet_address, $required_amount, $required_days, $decimals = 18, $token_type = 'ERC20') {
    $wallet_address = strtolower((string) $wallet_address);
    $required_amount = max(0.0, (float) $required_amount);
    $required_days = max(1, (int) $required_days);
    $now = time();
    $window_start = $now - ($required_days * 86400);
    $decimals = max(0, min(36, (int) $decimals));

    // Sort ascending by timestamp for chronological processing.
    usort($transactions, static function ($a, $b) {
        return (int) ($a['timeStamp'] ?? 0) <=> (int) ($b['timeStamp'] ?? 0);
    });

    $balance = 0.0;
    $current_balance_raw = '0';
    $total_in = 0.0;
    $total_out = 0.0;
    $tx_count = 0;
    $holding_seconds = 0.0;
    $weighted_seconds = 0.0;
    $period_start = null;
    $cursor = $window_start;
    $last_ts = $window_start;

    foreach ($transactions as $tx) {
        $timestamp = (int) ($tx['timeStamp'] ?? 0);
        if ($timestamp <= 0 || $timestamp < $window_start || $timestamp > $now) {
            continue;
        }
        $successful = (string) ($tx['isError'] ?? '0') !== '1' && (string) ($tx['txreceipt_status'] ?? '1') !== '0';
        if (!$successful) {
            continue;
        }
        $from = strtolower((string) ($tx['from'] ?? ''));
        $to = strtolower((string) ($tx['to'] ?? ''));
        $is_sender = $from === $wallet_address;
        $is_receiver = $to === $wallet_address;
        if (!$is_sender && !$is_receiver) {
            continue;
        }

        $value_raw = (string) ($tx['value'] ?? '0');
        $value = reviewEligibilityDecimalToFloat($value_raw, $decimals);
        $gas_fee = 0.0;
        if ($is_sender && $token_type === 'NATIVE') {
            $gas_used = (string) ($tx['gasUsed'] ?? '0');
            $gas_price = (string) ($tx['gasPrice'] ?? '0');
            if (ctype_digit($gas_used) && ctype_digit($gas_price)) {
                $gas_fee = ((float) $gas_used * (float) $gas_price) / 1000000000000000000;
            }
        }

        // Accumulate weighted balance for the time before this tx.
        $duration = max(0, $timestamp - $cursor);
        $weighted_seconds += $balance * $duration;
        if ($period_start !== null && $balance >= $required_amount) {
            $holding_seconds += $duration;
        }
        $cursor = $timestamp;
        $last_ts = $timestamp;

        if ($is_receiver) {
            $balance += $value;
            $total_in += $value;
            if ($period_start === null && $balance >= $required_amount) {
                $period_start = $timestamp;
            }
        }
        if ($is_sender) {
            $balance -= $value;
            $total_out += $value;
            if ($token_type === 'NATIVE') {
                $balance -= $gas_fee;
            }
            $balance = max(0.0, $balance);
            if ($balance < $required_amount) {
                $period_start = null;
            }
        }
        $current_balance_raw = $value_raw;
        $tx_count++;
    }

    // Final segment from last tx to now.
    $final_duration = max(0, $now - $cursor);
    $weighted_seconds += $balance * $final_duration;
    if ($period_start !== null && $balance >= $required_amount) {
        $holding_seconds += $final_duration;
    }

    $window_seconds = max(1, $now - $window_start);
    $average_balance = $weighted_seconds / $window_seconds;
    $holding_days = $holding_seconds / 86400;
    $eligible = $average_balance >= $required_amount && $holding_days >= $required_days;

    return [
        'eligible' => $eligible,
        'current_balance' => $balance,
        'current_balance_raw' => $current_balance_raw,
        'average_balance' => $average_balance,
        'holding_days' => $holding_days,
        'total_in' => $total_in,
        'total_out' => $total_out,
        'tx_count' => $tx_count,
        'window_start' => gmdate('c', $window_start),
        'window_end' => gmdate('c', $now),
    ];
}
?>
