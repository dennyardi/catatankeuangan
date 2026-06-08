<?php
// includes/functions.php

// 1. Security: Mencegah XSS (Output Sanitization)
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// 2. Format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// 3. Cek Login (Middleware sederhana)
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// 4. Redirect Helper
function redirect($url) {
    header("Location: " . filterRedirectUrl($url));
    exit;
}

function filterRedirectUrl($url) {
    $url = (string)$url;
    if (preg_match('/[\r\n]/', $url) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        return 'index.php';
    }
    return $url;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function ensurePocketSchema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pockets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            group_id VARCHAR(191) NULL,
            budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_pocket_name (user_id, name),
            KEY idx_pockets_group_id (group_id),
            KEY idx_pockets_user_active (user_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'budget_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER group_id',
        'budget_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER budget_amount'
    ] as $column => $definition) {
        $stmtBudget = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pockets'
              AND COLUMN_NAME = ?
        ");
        $stmtBudget->execute([$column]);
        if ((int)$stmtBudget->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE pockets ADD COLUMN $column $definition");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS category_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pocket_id INT NULL,
            category_id INT NOT NULL,
            keyword VARCHAR(120) NOT NULL,
            priority INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_rules_user_pocket_active (user_id, pocket_id, is_active),
            KEY idx_rules_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pocket_id INT NULL,
            name VARCHAR(120) NOT NULL,
            group_id VARCHAR(191) NOT NULL,
            weekly_enabled TINYINT(1) NOT NULL DEFAULT 0,
            weekly_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
            monthly_enabled TINYINT(1) NOT NULL DEFAULT 0,
            monthly_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_weekly_sent_at DATETIME NULL,
            last_monthly_sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_notifications_user_active (user_id, is_active),
            KEY idx_notifications_pocket (pocket_id),
            KEY idx_notifications_group_id (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_setting_id INT NULL,
            user_id INT NOT NULL,
            period VARCHAR(20) NOT NULL,
            group_id VARCHAR(191) NOT NULL,
            status VARCHAR(20) NOT NULL,
            gateway_status INT NULL,
            error_message TEXT NULL,
            message_preview TEXT NULL,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            sent_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notification_logs_user_sent (user_id, sent_at),
            KEY idx_notification_logs_setting (notification_setting_id),
            KEY idx_notification_logs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'user_id' => 'INT NULL AFTER id',
        'pocket_id' => 'INT NULL AFTER user_id',
        'budget_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER name',
        'budget_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER budget_amount'
    ] as $column => $definition) {
        $stmtCategoryColumn = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'categories'
              AND COLUMN_NAME = ?
        ");
        $stmtCategoryColumn->execute([$column]);
        if ((int)$stmtCategoryColumn->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN $column $definition");
        }
    }

    $stmtCategoryIndex = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'categories'
          AND INDEX_NAME = 'idx_categories_scope'
    ");
    $stmtCategoryIndex->execute();
    if ((int)$stmtCategoryIndex->fetchColumn() === 0) {
        $pdo->exec("CREATE INDEX idx_categories_scope ON categories (user_id, pocket_id)");
    }

    $stmtUniqueCategoryName = $pdo->prepare("
        SELECT s.INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS s
        JOIN (
            SELECT INDEX_NAME, COUNT(*) AS column_count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'categories'
            GROUP BY INDEX_NAME
        ) grouped ON grouped.INDEX_NAME = s.INDEX_NAME
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME = 'categories'
          AND s.COLUMN_NAME = 'name'
          AND s.NON_UNIQUE = 0
          AND s.INDEX_NAME <> 'PRIMARY'
          AND grouped.column_count = 1
        LIMIT 1
    ");
    $stmtUniqueCategoryName->execute();
    $uniqueCategoryNameIndex = $stmtUniqueCategoryName->fetchColumn();
    if ($uniqueCategoryNameIndex) {
        $pdo->exec("ALTER TABLE categories DROP INDEX `$uniqueCategoryNameIndex`");
    }

    $stmtCategoryNameIndex = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'categories'
          AND INDEX_NAME = 'idx_categories_name'
    ");
    $stmtCategoryNameIndex->execute();
    if ((int)$stmtCategoryNameIndex->fetchColumn() === 0) {
        $pdo->exec("CREATE INDEX idx_categories_name ON categories (name)");
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'expenses' 
          AND COLUMN_NAME = 'pocket_id'
    ");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN pocket_id INT NULL AFTER user_id");
        $pdo->exec("CREATE INDEX idx_expenses_user_pocket_date ON expenses (user_id, pocket_id, date)");
    }

    $users = $pdo->query("SELECT id FROM users")->fetchAll();
    foreach ($users as $user) {
        getDefaultPocketId($pdo, (int)$user['id']);
    }

    $pdo->exec("
        UPDATE expenses e
        JOIN pockets p ON p.user_id = e.user_id
        SET e.pocket_id = p.id
        WHERE e.pocket_id IS NULL
          AND p.name = 'Uang Belanja Ibu'
    ");
}

function getDefaultPocketId(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id FROM pockets WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([(int)$userId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return (int)$existing;

    $stmt = $pdo->prepare("INSERT INTO pockets (user_id, name, is_active) VALUES (?, 'Uang Belanja Ibu', 1)");
    $stmt->execute([(int)$userId]);
    return (int)$pdo->lastInsertId();
}

function getUserPockets(PDO $pdo, $userId, $activeOnly = false) {
    $sql = "SELECT * FROM pockets WHERE user_id = ?";
    if ($activeOnly) $sql .= " AND is_active = 1";
    $sql .= " ORDER BY is_active DESC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$userId]);
    return $stmt->fetchAll();
}

function getSelectedPocketId(PDO $pdo, $userId, $requestedPocketId = null, $allowAll = false) {
    if ($allowAll && ((string)$requestedPocketId === 'all' || (string)$requestedPocketId === '0')) {
        return null;
    }

    $requestedPocketId = filter_var($requestedPocketId, FILTER_VALIDATE_INT);
    if ($requestedPocketId) {
        $stmt = $pdo->prepare("SELECT id FROM pockets WHERE id = ? AND user_id = ? AND is_active = 1");
        $stmt->execute([$requestedPocketId, (int)$userId]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }

    return getDefaultPocketId($pdo, (int)$userId);
}

function findPocketByGroupId(PDO $pdo, $groupId) {
    $groupId = trim((string)$groupId);
    if ($groupId === '') return null;

    $stmt = $pdo->prepare("SELECT * FROM pockets WHERE group_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

function sanitizeGroupId($groupId) {
    $groupId = trim((string)$groupId);
    if ($groupId === '') return null;
    return substr($groupId, 0, 191);
}

function normalizeMoneyInput($value) {
    $value = trim((string)$value);
    if ($value === '') return 0;
    $value = preg_replace('/[^\d.,]/', '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return max(0, (float)$value);
}

function getAvailableCategories(PDO $pdo, $userId, $pocketId = null) {
    $sql = "
        SELECT *
        FROM categories
        WHERE (user_id IS NULL OR user_id = ?)
          AND (pocket_id IS NULL" . ($pocketId ? " OR pocket_id = ?" : "") . ")
        ORDER BY name ASC
    ";

    $params = [(int)$userId];
    if ($pocketId) $params[] = (int)$pocketId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function categoryIsAvailableForPocket(PDO $pdo, $categoryId, $userId, $pocketId = null) {
    $params = [(int)$categoryId, (int)$userId];
    $sql = "
        SELECT id
        FROM categories
        WHERE id = ?
          AND (user_id IS NULL OR user_id = ?)
          AND (pocket_id IS NULL" . ($pocketId ? " OR pocket_id = ?" : "") . ")
        LIMIT 1
    ";
    if ($pocketId) $params[] = (int)$pocketId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function seedDefaultCategoryRules(PDO $pdo, $userId) {
    $defaultRules = [
        'Belanja' => ['belanja', 'beras', 'sayur', 'telur', 'ayam', 'ikan', 'daging', 'buah', 'susu', 'roti', 'sabun', 'shampoo', 'deterjen', 'minyak', 'gula', 'tepung', 'pasar', 'mart', 'indomaret', 'alfamart'],
        'Kesehatan' => ['obat', 'dokter', 'klinik', 'rs', 'rumah sakit', 'vitamin', 'apotek', 'periksa', 'terapi'],
        'Lainnya' => ['lainnya', 'misc', 'tak terduga', 'biaya admin', 'admin', 'transfer'],
        'Makanan' => ['makan', 'minum', 'kopi', 'warung', 'sarapan', 'siang', 'malam', 'nasi', 'bakso', 'mie', 'ayam geprek', 'gofood', 'grabfood', 'resto', 'jajan'],
        'Pemasukan' => ['gaji', 'bonus', 'transfer masuk', 'refund', 'cashback', 'komisi', 'honor', 'pemasukan'],
        'Transportasi' => ['bensin', 'pertalite', 'pertamax', 'solar', 'parkir', 'tol', 'gojek', 'grab', 'ojek', 'taxi', 'angkot', 'kereta', 'bus'],
        'Utilitas' => ['listrik', 'token', 'air', 'pdam', 'pulsa', 'kuota', 'internet', 'wifi', 'indihome', 'gas', 'elpiji', 'pln', 'bpjs']
    ];

    $stmtFindCategory = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE name = ?
          AND (user_id IS NULL OR user_id = ?)
        ORDER BY user_id DESC, id ASC
        LIMIT 1
    ");
    $stmtExists = $pdo->prepare("
        SELECT id
        FROM category_rules
        WHERE user_id = ?
          AND category_id = ?
          AND pocket_id IS NULL
          AND keyword = ?
        LIMIT 1
    ");
    $stmtInsert = $pdo->prepare("
        INSERT INTO category_rules (user_id, pocket_id, category_id, keyword, priority, is_active)
        VALUES (?, NULL, ?, ?, ?, 1)
    ");

    $inserted = 0;
    foreach ($defaultRules as $categoryName => $keywords) {
        $stmtFindCategory->execute([$categoryName, (int)$userId]);
        $categoryId = $stmtFindCategory->fetchColumn();
        if (!$categoryId) continue;

        foreach ($keywords as $index => $keyword) {
            $keyword = strtolower(trim($keyword));
            $stmtExists->execute([(int)$userId, (int)$categoryId, $keyword]);
            if ($stmtExists->fetchColumn()) continue;

            $stmtInsert->execute([(int)$userId, (int)$categoryId, $keyword, 10 + $index]);
            $inserted++;
        }
    }

    return $inserted;
}

function guessCategoryForExpense(PDO $pdo, $userId, $pocketId, $description) {
    $description = strtolower((string)$description);

    $stmt = $pdo->prepare("
        SELECT r.keyword, c.id, c.name
        FROM category_rules r
        JOIN categories c ON r.category_id = c.id
        WHERE r.user_id = ?
          AND r.is_active = 1
          AND (r.pocket_id IS NULL OR r.pocket_id = ?)
          AND (c.pocket_id IS NULL OR c.pocket_id = ?)
        ORDER BY r.priority ASC, r.pocket_id DESC, CHAR_LENGTH(r.keyword) DESC
    ");
    $stmt->execute([(int)$userId, (int)$pocketId, (int)$pocketId]);
    foreach ($stmt->fetchAll() as $rule) {
        if ($rule['keyword'] !== '' && strpos($description, strtolower($rule['keyword'])) !== false) {
            return ['id' => (int)$rule['id'], 'name' => $rule['name']];
        }
    }

    $fallbackKeywords = [
        'makan' => 'Makanan', 'minum' => 'Makanan', 'kopi' => 'Makanan', 'warung' => 'Makanan',
        'bensin' => 'Transportasi', 'gojek' => 'Transportasi', 'grab' => 'Transportasi', 'parkir' => 'Transportasi',
        'pulsa' => 'Utilitas', 'listrik' => 'Utilitas', 'token' => 'Utilitas', 'air' => 'Utilitas', 'kuota' => 'Utilitas',
        'obat' => 'Kesehatan', 'dokter' => 'Kesehatan',
        'gaji' => 'Pemasukan',
        'belanja' => 'Belanja', 'mart' => 'Belanja', 'indo' => 'Belanja', 'alpha' => 'Belanja'
    ];

    foreach ($fallbackKeywords as $keyword => $categoryName) {
        if (strpos($description, $keyword) !== false) {
            $stmtCat = $pdo->prepare("
                SELECT id, name
                FROM categories
                WHERE name LIKE ?
                  AND (user_id IS NULL OR user_id = ?)
                  AND (pocket_id IS NULL OR pocket_id = ?)
                LIMIT 1
            ");
            $stmtCat->execute(['%' . $categoryName . '%', (int)$userId, (int)$pocketId]);
            $category = $stmtCat->fetch();
            if ($category) {
                return ['id' => (int)$category['id'], 'name' => $category['name']];
            }
        }
    }

    $stmtFallback = $pdo->prepare("
        SELECT id, name
        FROM categories
        WHERE name LIKE ?
          AND (user_id IS NULL OR user_id = ?)
          AND (pocket_id IS NULL OR pocket_id = ?)
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmtFallback->execute(['%Lainnya%', (int)$userId, (int)$pocketId]);
    $category = $stmtFallback->fetch();
    if ($category) {
        return ['id' => (int)$category['id'], 'name' => $category['name']];
    }

    $stmtFallback = $pdo->prepare("
        SELECT id, name
        FROM categories
        WHERE (user_id IS NULL OR user_id = ?)
          AND (pocket_id IS NULL OR pocket_id = ?)
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmtFallback->execute([(int)$userId, (int)$pocketId]);
    $category = $stmtFallback->fetch();
    return $category ? ['id' => (int)$category['id'], 'name' => $category['name']] : null;
}

function calculatePeriodRange($cutoffDay, $timestamp = null) {
    $timestamp = $timestamp ?: time();
    $cutoffDay = (int)$cutoffDay;
    if ($cutoffDay < 1 || $cutoffDay > 28) $cutoffDay = 1;

    if ($cutoffDay === 1) {
        return [
            date('Y-m-01', $timestamp),
            date('Y-m-t', $timestamp),
            date('F Y', $timestamp)
        ];
    }

    $currentDay = (int)date('d', $timestamp);
    if ($currentDay < $cutoffDay) {
        $start = date('Y-m-', strtotime('-1 month', $timestamp)) . sprintf('%02d', $cutoffDay);
        $end = date('Y-m-', $timestamp) . sprintf('%02d', $cutoffDay - 1);
    } else {
        $start = date('Y-m-', $timestamp) . sprintf('%02d', $cutoffDay);
        $end = date('Y-m-', strtotime('+1 month', $timestamp)) . sprintf('%02d', $cutoffDay - 1);
    }

    return [$start, $end, date('d M', strtotime($start)) . ' - ' . date('d M', strtotime($end))];
}

function getSummaryRange($period, $timestamp = null, $cutoffDay = 1) {
    $timestamp = $timestamp ?: time();
    if ($period === 'weekly') {
        $end = date('Y-m-d', strtotime('yesterday', $timestamp));
        $start = date('Y-m-d', strtotime($end . ' -6 days'));
        return [$start, $end, 'Mingguan ' . date('d M', strtotime($start)) . ' - ' . date('d M Y', strtotime($end))];
    }

    $cutoffDay = (int)$cutoffDay;
    if ($cutoffDay < 1 || $cutoffDay > 28) $cutoffDay = 1;

    [$currentStart] = calculatePeriodRange($cutoffDay, $timestamp);
    $end = date('Y-m-d', strtotime($currentStart . ' -1 day'));

    if ($cutoffDay === 1) {
        $start = date('Y-m-01', strtotime($end));
    } else {
        $start = date('Y-m-', strtotime($currentStart . ' -1 month')) . sprintf('%02d', $cutoffDay);
    }

    $label = ((int)$cutoffDay === 1)
        ? 'Bulanan ' . date('F Y', strtotime($start))
        : 'Bulanan ' . date('d M', strtotime($start)) . ' - ' . date('d M Y', strtotime($end));

    return [$start, $end, $label];
}

function buildFinancialSummaryMessage(PDO $pdo, array $setting, $period) {
    $userId = (int)$setting['user_id'];
    $pocketId = !empty($setting['pocket_id']) ? (int)$setting['pocket_id'] : null;

    $stmtUser = $pdo->prepare("SELECT start_date_calculation FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$userId]);
    $cutoffDay = (int)($stmtUser->fetchColumn() ?: 1);

    [$startDate, $endDate, $periodLabel] = getSummaryRange($period, null, $cutoffDay);

    $scopeSql = $pocketId ? " AND e.pocket_id = ? " : "";
    $scopeParams = $pocketId ? [$pocketId] : [];

    $stmtTotal = $pdo->prepare("
        SELECT COALESCE(SUM(e.amount), 0) AS total, COUNT(*) AS trx_count
        FROM expenses e
        WHERE e.user_id = ?
          AND e.date BETWEEN ? AND ?
          $scopeSql
    ");
    $stmtTotal->execute(array_merge([$userId, $startDate, $endDate], $scopeParams));
    $totalRow = $stmtTotal->fetch() ?: ['total' => 0, 'trx_count' => 0];
    $total = (float)$totalRow['total'];
    $trxCount = (int)$totalRow['trx_count'];

    $stmtCategory = $pdo->prepare("
        SELECT c.name, COALESCE(SUM(e.amount), 0) AS total
        FROM expenses e
        JOIN categories c ON c.id = e.category_id
        WHERE e.user_id = ?
          AND e.date BETWEEN ? AND ?
          $scopeSql
        GROUP BY c.id, c.name
        ORDER BY total DESC
        LIMIT 3
    ");
    $stmtCategory->execute(array_merge([$userId, $startDate, $endDate], $scopeParams));
    $topCategories = $stmtCategory->fetchAll();

    $stmtTransactions = $pdo->prepare("
        SELECT e.amount, e.description, e.date, c.name AS category_name, p.name AS pocket_name
        FROM expenses e
        JOIN categories c ON c.id = e.category_id
        LEFT JOIN pockets p ON p.id = e.pocket_id
        WHERE e.user_id = ?
          AND e.date BETWEEN ? AND ?
          $scopeSql
        ORDER BY e.amount DESC
        LIMIT 3
    ");
    $stmtTransactions->execute(array_merge([$userId, $startDate, $endDate], $scopeParams));
    $topTransactions = $stmtTransactions->fetchAll();

    $pocketName = 'Semua Pocket';
    $budgetLines = [];
    if ($pocketId) {
        $stmtPocket = $pdo->prepare("SELECT name, budget_amount, budget_enabled FROM pockets WHERE id = ? AND user_id = ? LIMIT 1");
        $stmtPocket->execute([$pocketId, $userId]);
        $pocket = $stmtPocket->fetch();
        if ($pocket) {
            $pocketName = $pocket['name'];
            if (!empty($pocket['budget_enabled']) && (float)$pocket['budget_amount'] > 0) {
                $budget = (float)$pocket['budget_amount'];
                $percentage = min(999, ($total / $budget) * 100);
                $budgetLines[] = 'Limit: ' . formatRupiah($budget) . ' | Terpakai ' . number_format($percentage, 1, ',', '.') . '%';
                $budgetLines[] = 'Sisa: ' . formatRupiah(max(0, $budget - $total));
            }
        }
    } else {
        $stmtPockets = $pdo->prepare("
            SELECT p.name, COALESCE(SUM(e.amount), 0) AS total
            FROM pockets p
            LEFT JOIN expenses e ON e.pocket_id = p.id
                AND e.user_id = p.user_id
                AND e.date BETWEEN ? AND ?
            WHERE p.user_id = ?
              AND p.is_active = 1
            GROUP BY p.id, p.name
            ORDER BY total DESC, p.name ASC
            LIMIT 5
        ");
        $stmtPockets->execute([$startDate, $endDate, $userId]);
        foreach ($stmtPockets->fetchAll() as $row) {
            $budgetLines[] = '- ' . $row['name'] . ': ' . formatRupiah((float)$row['total']);
        }
    }

    $lines = [
        'Ringkasan Keuangan',
        $periodLabel,
        'Pocket: ' . $pocketName,
        '',
        'Total pengeluaran: ' . formatRupiah($total),
        'Jumlah transaksi: ' . $trxCount,
    ];

    if ($budgetLines) {
        $lines[] = '';
        $lines[] = $pocketId ? 'Status Limit' : 'Ringkasan Per Pocket';
        $lines = array_merge($lines, $budgetLines);
    }

    $lines[] = '';
    $lines[] = 'Top Kategori';
    if ($topCategories) {
        foreach ($topCategories as $category) {
            $lines[] = '- ' . $category['name'] . ': ' . formatRupiah((float)$category['total']);
        }
    } else {
        $lines[] = '- Belum ada transaksi.';
    }

    $lines[] = '';
    $lines[] = 'Pengeluaran Terbesar';
    if ($topTransactions) {
        foreach ($topTransactions as $transaction) {
            $label = date('d M', strtotime($transaction['date'])) . ' - ' . $transaction['description'];
            if (!$pocketId && !empty($transaction['pocket_name'])) {
                $label .= ' (' . $transaction['pocket_name'] . ')';
            }
            $lines[] = '- ' . $label . ': ' . formatRupiah((float)$transaction['amount']);
        }
    } else {
        $lines[] = '- Belum ada transaksi.';
    }

    return implode("\n", $lines);
}

function getWhatsAppGatewayConfig() {
    $localConfigPath = __DIR__ . '/../config/local.php';
    $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
    if (!is_array($localConfig)) $localConfig = [];

    return [
        'url' => getenv('WA_GATEWAY_URL') ?: ($localConfig['wa_gateway_url'] ?? 'https://gateway.dennyardi.com/api/proxy.php?endpoint=/message/send-text'),
        'token' => getenv('WA_GATEWAY_TOKEN') ?: ($localConfig['wa_gateway_token'] ?? ''),
        'session' => getenv('WA_GATEWAY_SESSION') ?: ($localConfig['wa_gateway_session'] ?? 'notifikasidenny')
    ];
}

function sendWhatsAppMessage($to, $text, $url = null, $token = null, $session = null) {
    $config = getWhatsAppGatewayConfig();
    $url = $url ?: $config['url'];
    $token = $token ?: $config['token'];
    $session = $session ?: $config['session'];

    $missingConfig = [];
    if (!$token) $missingConfig[] = 'WA gateway token kosong';
    if (!$session) $missingConfig[] = 'WA gateway session kosong';
    if (!$to) $missingConfig[] = 'Group ID tujuan kosong';
    if (!$text) $missingConfig[] = 'Isi pesan kosong';

    if ($missingConfig) {
        return [
            'ok' => false,
            'status' => 0,
            'response' => null,
            'error' => implode(', ', $missingConfig)
        ];
    }

    $payload = [
        'session' => $session,
        'to' => $to,
        'text' => $text
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'ok' => $result !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'response' => $result,
        'error' => $error
    ];
}

function recordNotificationLog(PDO $pdo, array $setting, $period, $status, $gatewayStatus = null, $errorMessage = null, $messagePreview = null, $isTest = false) {
    $stmt = $pdo->prepare("
        INSERT INTO notification_logs
            (notification_setting_id, user_id, period, group_id, status, gateway_status, error_message, message_preview, is_test, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $setting['id'] ?? null,
        (int)$setting['user_id'],
        $period,
        $setting['group_id'] ?? '',
        $status,
        $gatewayStatus,
        $errorMessage ? substr((string)$errorMessage, 0, 1000) : null,
        $messagePreview ? substr((string)$messagePreview, 0, 2000) : null,
        $isTest ? 1 : 0
    ]);
}

function sendSummaryNotification(PDO $pdo, array $setting, $period, $isTest = false) {
    $text = buildFinancialSummaryMessage($pdo, $setting, $period);
    $result = sendWhatsAppMessage($setting['group_id'], $text);
    $ok = is_array($result) ? (bool)$result['ok'] : (bool)$result;
    $gatewayStatus = is_array($result) ? ($result['status'] ?? null) : null;
    $errorMessage = null;

    if (!$ok) {
        $errorMessage = is_array($result)
            ? (($result['error'] ?? '') ?: ($result['response'] ?? 'Gateway mengembalikan status gagal.'))
            : 'Gateway WhatsApp tidak mengembalikan respons sukses.';
    }

    recordNotificationLog(
        $pdo,
        $setting,
        $period,
        $ok ? 'sent' : 'failed',
        $gatewayStatus,
        $errorMessage,
        $text,
        $isTest
    );

    return [
        'ok' => $ok,
        'message' => $text,
        'gateway_status' => $gatewayStatus,
        'error' => $errorMessage,
        'result' => $result
    ];
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensurePocketSchema($pdo);
}
?>
