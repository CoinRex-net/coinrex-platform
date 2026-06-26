<?php
/**
 * On-chain review eligibility helpers.
 */

function reviewEligibilityEnv($key, $default = '') {
    $value = getenv((string) $key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
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
        $db->exec("UPDATE projects SET contract_address = '' WHERE contract_address IS NULL");
    } catch (Throwable $e) {}
    try {
        $db->exec("UPDATE project_contracts SET contract_address = '' WHERE contract_address IS NULL");
        $db->exec("ALTER TABLE project_contracts MODIFY contract_address VARCHAR(100) NOT NULL");
    } catch (Throwable $e) {}

    $ready = true;
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
    $chain_ids = $source['contract_chain_id'] ?? [];
    $addresses = $source['contract_address_multi'] ?? [];
    $types = $source['contract_token_type'] ?? [];
    $primary = (int) ($source['primary_contract_index'] ?? 0);
    $active = $source['contract_is_active'] ?? [];
    $has_active_inputs = is_array($active) && count($active) > 0;

    if (!is_array($names)) {
        $names = [];
    }
    $count = max(count($names), is_array($addresses) ? count($addresses) : 0);
    for ($i = 0; $i < $count; $i++) {
        $network_name = trim((string) ($names[$i] ?? ''));
        $address = strtolower(trim((string) ($addresses[$i] ?? '')));
        $chain_id = (int) ($chain_ids[$i] ?? 0);
        $token_type = strtoupper(trim((string) ($types[$i] ?? 'ERC20')));
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
        $is_active = !$has_active_inputs || (array_key_exists($i, $active) && (string) $active[$i] === '1');
        $rows[] = [
            'network_name' => $network_name,
            'network_slug' => $networks[$network_name]['slug'] ?? reviewEligibilityNormalizeNetworkSlug($network_name),
            'chain_id' => $chain_id,
            'contract_address' => $address,
            'token_type' => $token_type,
            'is_primary' => $i === $primary ? 1 : 0,
            'is_active' => $is_active ? 1 : 0,
        ];
    }

    $seen = [];
    $primary_count = 0;
    foreach ($rows as $row) {
        if ((int) $row['is_active'] === 1) {
            $key = $row['chain_id'] . ':' . ($row['token_type'] === 'NATIVE' ? 'native' : $row['contract_address']);
            if (isset($seen[$key])) {
                $errors['contracts'] = 'Duplicate chain + contract rows are not allowed.';
            }
            $seen[$key] = true;
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
            is_primary, is_active, verification_status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'needs_check', NOW(), NOW())
    ");
    foreach ($rows as $row) {
        $insert->execute([
            $project_id,
            $row['network_name'],
            $row['network_slug'],
            (int) $row['chain_id'],
            $row['token_type'] === 'NATIVE' ? '' : strtolower((string) $row['contract_address']),
            strtoupper((string) $row['token_type']),
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
    $stmt = $db->prepare("
        SELECT *
        FROM review_eligibility_checks
        WHERE user_id = ?
          AND project_id = ?
          AND wallet_address = ?
          AND status = ?
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY checked_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $user_id, (int) $project_id, strtolower((string) $wallet_address), (string) $required_status]);
    return $stmt->fetch() ?: null;
}

function reviewEligibilityExplorerRequest($base_url, array $params) {
    $url = rtrim((string) $base_url, '?') . '?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
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
    if (($decoded['status'] ?? '') === '0' && strpos($message, 'no transactions') === false && !isset($decoded['result'])) {
        return ['ok' => false, 'status' => strpos($message, 'rate') !== false ? 'rate_limited' : 'unavailable', 'message' => (string) ($decoded['message'] ?? 'Explorer API error.'), 'raw' => $decoded];
    }
    return ['ok' => true, 'status' => 'ok', 'message' => 'OK', 'raw' => $decoded, 'result' => $decoded['result'] ?? '0'];
}

function reviewEligibilityCheckProject(PDO $db, $user_id, $project_id, $wallet_address) {
    ensureReviewEligibilitySchema($db);
    $wallet_address = strtolower(trim((string) $wallet_address));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        throw new InvalidArgumentException('Valid EVM wallet address is required.');
    }
    $cached = reviewEligibilityGetFreshCheck($db, $user_id, $project_id, $wallet_address, 'eligible');
    if ($cached) {
        return ['status' => 'eligible', 'cached' => true, 'check' => $cached];
    }

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
            usleep(350000);
            $response = reviewEligibilityExplorerRequest($api_base, [
                'chainid' => (int) $contract['chain_id'],
                'module' => 'proxy',
                'action' => 'eth_getBalance',
                'address' => $wallet_address,
                'tag' => 'latest',
                'apikey' => $api_key,
            ]);
            $balance_raw = strtolower(trim((string) ($response['result'] ?? '0x0')));
            $has_balance = reviewEligibilityHexGreaterThanZero($balance_raw);
            $results[] = [
                'contract_id' => (int) $contract['id'],
                'chain_id' => (int) $contract['chain_id'],
                'contract_address' => null,
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
                    'reason' => 'Native token balance found on ' . (string) $contract['network_name'] . '.',
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
            continue;
        }
        if ($token_type === 'ERC1155') {
            $results[] = ['contract_id' => (int) $contract['id'], 'status' => 'unsupported', 'reason' => 'ERC1155 requires token ID support.'];
            continue;
        }
        usleep(350000);
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
?>
