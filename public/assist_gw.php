<?php
/**
 * =====================================================================
 * ASSIST GATEWAY v2 — Single-File Production App
 * =====================================================================
 * Fitur lengkap: Login, Dashboard, Manajemen User, Customer, 
 * Template Notifikasi, Activity Log, Settings, dan API Key Manager
 * 
 * Cara pakai:
 *  1. Upload file ini ke server PHP (7.4+)
 *  2. Buka di browser → ikuti wizard instalasi
 *  3. Login dengan admin / admin123
 * =====================================================================
 */

// ─── KONFIGURASI AWAL ──────────────────────────────────────────────
define('APP_VERSION', '2.0.0');
define('APP_NAME', 'Assist Gateway');
// ─── MySQL Database Credentials ────────────────────────────────────
define('DB_HOST', '10.1.11.21');
define('DB_NAME', 'assist_gw');
define('DB_USER', 'AssistGateway');
define('DB_PASS', 'vN8kP3xZ6mQ1wLyF');
define('SESSION_NAME', 'assist_gw_sess');

// ─── SESSION & BOOTSTRAP ────────────────────────────────────────────
session_name(SESSION_NAME);
session_start();

// ─── DATABASE (MySQL PDO) ────────────────────────────────────────────
class DB {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array {
        $rows = self::query($sql, $params);
        return $rows[0] ?? null;
    }

    public static function exec(string $sql, array $params = []): bool {
        $stmt = self::get()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function lastId(): string {
        return self::get()->lastInsertId();
    }

    public static function count(string $table, string $where = '1', array $params = []): int {
        $row = self::row("SELECT COUNT(*) as c FROM {$table} WHERE {$where}", $params);
        return (int)($row['c'] ?? 0);
    }
}

// ─── INSTALASI DATABASE ─────────────────────────────────────────────
function setupDatabase(): void {
    $db = DB::get();

    // Tabel: user (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `user` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
        `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `theme` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
        `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
        `reset_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `last_login_at` int unsigned NOT NULL DEFAULT '0',
        `reset_token_expires_at` int unsigned NOT NULL DEFAULT '0',
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_username_unique` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: customer (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `customer` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `tenant` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `api_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
        `use_custom_webhook` tinyint(1) NOT NULL DEFAULT '0',
        `custom_webhook_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `custom_webhook_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `custom_webhook_services` json DEFAULT NULL,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_tenant_unique` (`tenant`),
        UNIQUE KEY `idx_api_token_unique` (`api_token`),
        KEY `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: template (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `template` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `template_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `protocol` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `priority` enum('high','middle','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'middle',
        `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `ttl` int unsigned NOT NULL DEFAULT '0',
        `parse_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_protocol_template_code_unique` (`protocol`,`template_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: activity_log (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `user_id` int unsigned DEFAULT NULL,
        `payload` json DEFAULT NULL,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `idx_action_created_at` (`action`,`created_at`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: setting (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `setting` (
        `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: message_stats (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `message_stats` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `timestamp` int unsigned NOT NULL DEFAULT '0',
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `protocol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
        `recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `template_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `kode_persetujuan` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `kode_aktivasi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `message_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `duration_seconds` decimal(10,4) DEFAULT NULL,
        `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `retry_count` int unsigned NOT NULL DEFAULT '0',
        `worker_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `sender_account` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `approval_timestamp` int unsigned NOT NULL DEFAULT '0',
        `approval_access_timestamp` int unsigned NOT NULL DEFAULT '0',
        `approval_access_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `approval_access_device` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `event_data` json DEFAULT NULL,
        `login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `login_device` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `login_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `approval_device_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_timestamp` (`timestamp`),
        KEY `idx_customer_id` (`customer_id`),
        KEY `idx_protocol` (`protocol`),
        KEY `idx_module` (`module`),
        KEY `idx_kode_persetujuan` (`kode_persetujuan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: security_keys — RSA Key Manager (tabel khusus app ini)
    $db->exec("CREATE TABLE IF NOT EXISTS `security_keys` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `identifier` varchar(100) NOT NULL,
        `description` text DEFAULT NULL,
        `public_key` text NOT NULL,
        `private_key` text NOT NULL,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `identifier` (`identifier`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: user_role (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `user_role` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
        `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
        `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_role_slug_unique` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel: scheduled_task (sesuai schema production)
    $db->exec("CREATE TABLE IF NOT EXISTS `scheduled_task` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `job_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `cron_expression` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
        `is_running` tinyint(1) NOT NULL DEFAULT '0',
        `last_run_at` int unsigned NOT NULL DEFAULT '0',
        `next_run_at` int unsigned NOT NULL DEFAULT '0',
        `created_at` int unsigned NOT NULL DEFAULT '0',
        `updated_at` int unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `idx_task_scheduling` (`status`,`next_run_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Seed: default admin user ──────────────────────────────────────
    $adminExists = DB::row("SELECT id FROM `user` WHERE username='admin'");
    if (!$adminExists) {
        DB::exec("INSERT INTO `user` (name,username,password,role,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?)",
            ['Administrator', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'active', time(), time()]);
    }

    // ── Seed: default settings ────────────────────────────────────────
    $defaults = [
        'app_name'        => 'Assist Gateway',
        'theme'           => 'light',
        'timezone'        => 'Asia/Jakarta',
        'smtp_host'       => '',
        'smtp_port'       => '587',
        'smtp_user'       => '',
        'smtp_pass'       => '',
        'smtp_from'       => '',
        'telegram_token'  => '',
        'telegram_chatid' => '',
    ];
    foreach ($defaults as $k => $v) {
        DB::exec("INSERT IGNORE INTO `setting` (setting_key,setting_value,created_at,updated_at) VALUES (?,?,?,?)", [$k, $v, time(), time()]);
    }

    // ── Seed: default roles ───────────────────────────────────────────
    $roles = [
        ['Administrator', 'admin',     'Akses penuh ke semua fitur'],
        ['Operator',      'operator',  'Akses terbatas operasional'],
        ['User',          'user',      'Akses dasar'],
    ];
    foreach ($roles as [$n, $s, $d]) {
        DB::exec("INSERT IGNORE INTO `user_role` (name,slug,description,created_at,updated_at) VALUES (?,?,?,?,?)", [$n, $s, $d, time(), time()]);
    }
}

// ─── HELPER FUNCTIONS ──────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    $row = DB::row("SELECT setting_value FROM `setting` WHERE setting_key=?", [$key]);
    return $row ? (string)$row['setting_value'] : $default;
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('?page=login');
    }
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function logActivity(string $action, ?array $payload = null): void {
    $uid = currentUser()['id'] ?? null;
    DB::exec("INSERT INTO `activity_log` (action,user_id,payload,created_at) VALUES (?,?,?,?)",
        [$action, $uid, $payload ? json_encode($payload) : null, time()]);
}

function generateToken(int $len = 32): string {
    return bin2hex(random_bytes($len));
}

function isAdmin(): bool {
    return (currentUser()['role'] ?? '') === 'admin';
}

function formatDate(int $ts): string {
    if ($ts <= 0) return '—';
    return date('d M Y H:i', $ts);
}

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

// ─── ROUTING ────────────────────────────────────────────────────────
setupDatabase();

$page   = $_GET['page']   ?? 'home';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─── CONTROLLER LOGIC ───────────────────────────────────────────────

// LOGIN
if ($page === 'login') {
    if (isLoggedIn()) redirect('?page=home');

    $error = '';
    if ($method === 'POST') {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';

        if (empty($u) || empty($p)) {
            $error = 'Username dan password wajib diisi.';
        } else {
            $user = DB::row("SELECT * FROM `user` WHERE username=?", [$u]);
            if (!$user || !password_verify($p, $user['password'])) {
                $error = 'Username atau password salah.';
            } elseif ($user['status'] === 'suspended') {
                $error = 'Akun Anda ditangguhkan. Hubungi administrator.';
            } elseif ($user['status'] === 'locked') {
                $error = 'Akun Anda dikunci. Hubungi administrator.';
            } else {
                DB::exec("UPDATE `user` SET last_login_at=? WHERE id=?", [time(), $user['id']]);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'name'     => $user['name'],
                    'role'     => $user['role'],
                    'theme'    => $user['theme'],
                ];
                logActivity('USER_LOGIN', ['username' => $u]);
                if (isAjax()) jsonResponse(['redirect' => '?page=home']);
                redirect('?page=home');
            }
        }
        if (isAjax()) jsonResponse(['error' => $error], 401);
    }
    renderLogin($error);
    exit;
}

// LOGOUT
if ($page === 'logout') {
    logActivity('USER_LOGOUT');
    session_destroy();
    redirect('?page=login');
}

// ─── API ENDPOINTS (no auth required, uses API token) ───────────────
if ($page === 'api') {
    handleApi($action);
    exit;
}

// Proteksi semua halaman lain
requireLogin();

$user = currentUser();
$theme = $user['theme'] === 'auto' ? getSetting('theme', 'light') : $user['theme'];

// ─── HALAMAN UTAMA ──────────────────────────────────────────────────
switch ($page) {
    case 'home':
    case 'dashboard':
        renderDashboard(); break;

    case 'users':
        handleUsers(); break;

    case 'customers':
        handleCustomers(); break;

    case 'templates':
        handleTemplates(); break;

    case 'activity_log':
        renderActivityLog(); break;

    case 'message_stats':
        renderMessageStats(); break;

    case 'scheduler':
        handleScheduler(); break;

    case 'security_keys':
        handleSecurityKeys(); break;

    case 'settings':
        handleSettings(); break;

    case 'profile':
        handleProfile(); break;

    case 'roles':
        handleRoles(); break;

    default:
        renderDashboard();
}

exit;

// ══════════════════════════════════════════════════════════════════════
// CONTROLLER HANDLERS
// ══════════════════════════════════════════════════════════════════════

function handleApi(string $action): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? '';

    if ($action === 'send') {
        // Validasi token
        $cust = DB::row("SELECT * FROM `customer` WHERE api_token=? AND status='active'", [$token]);
        if (!$cust) jsonResponse(['error' => 'Invalid API token'], 401);

        $templateCode = $body['template_code'] ?? '';
        $recipient    = $body['recipient'] ?? '';
        $protocol     = $body['protocol'] ?? 'email';
        $variables    = $body['variables'] ?? [];

        if (empty($templateCode) || empty($recipient)) {
            jsonResponse(['error' => 'template_code and recipient required'], 400);
        }

        $tpl = DB::row("SELECT * FROM `template` WHERE template_code=? AND protocol=?", [$templateCode, $protocol]);
        if (!$tpl) jsonResponse(['error' => 'Template not found'], 404);

        // Ganti placeholder
        $body_text = $tpl['body'];
        foreach ($variables as $k => $v) {
            $body_text = str_replace('{{' . $k . '}}', h($v), $body_text);
        }

        // Catat ke message_stats
        DB::exec("INSERT INTO message_stats (customer_id,template_name,protocol,status,recipient,timestamp,created_at) VALUES (?,?,?,?,?,?,?)",
            [(string)$cust['id'], $tpl['name'], $protocol, 'sent', $recipient, time(), time()]);

        jsonResponse([
            'success'   => true,
            'message'   => 'Notification queued',
            'reference' => DB::lastId(),
            'preview'   => substr(strip_tags($body_text), 0, 100) . '...'
        ]);
    }

    if ($action === 'status') {
        $cust = DB::row("SELECT * FROM `customer` WHERE api_token=? AND status='active'", [$token]);
        if (!$cust) jsonResponse(['error' => 'Invalid API token'], 401);
        $stats = DB::row("SELECT COUNT(*) as total, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed FROM message_stats WHERE customer_id=?", [(string)$cust['id']]);
        jsonResponse(['customer' => $cust['name'], 'stats' => $stats]);
    }

    jsonResponse(['error' => 'Unknown action'], 404);
}

// ── USERS ────────────────────────────────────────────────────────────
function handleUsers(): void {
    if (!isAdmin()) { flash('error','Akses ditolak'); redirect('?page=home'); }

    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'add' || ($action === 'edit' && isset($_GET['id']))) {
        if ($method === 'POST') {
            $id    = (int)($_GET['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $uname = trim($_POST['username'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $role  = $_POST['role'] ?? 'user';

            $errors = [];
            if (empty($name))  $errors[] = 'Nama wajib diisi.';
            if (empty($uname)) $errors[] = 'Username wajib diisi.';
            if (!$id && empty($pass)) $errors[] = 'Password wajib diisi.';

            if (empty($errors)) {
                if ($id) {
                    $upd = "UPDATE `user` SET name=?,role=?,updated_at=? WHERE id=?";
                    $prm = [$name, $role, time(), $id];
                    if (!empty($pass)) {
                        $upd = "UPDATE `user` SET name=?,role=?,password=?,updated_at=? WHERE id=?";
                        $prm = [$name, $role, password_hash($pass, PASSWORD_DEFAULT), time(), $id];
                    }
                    DB::exec($upd, $prm);
                    logActivity('USER_UPDATED', ['id' => $id]);
                    flash('success', 'User berhasil diperbarui.');
                } else {
                    $exists = DB::row("SELECT id FROM `user` WHERE username=?", [$uname]);
                    if ($exists) {
                        flash('error', "Username '{$uname}' sudah digunakan.");
                        redirect("?page=users&action=add");
                    }
                    DB::exec("INSERT INTO `user` (name,username,password,role,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?)",
                        [$name, $uname, password_hash($pass, PASSWORD_DEFAULT), $role, 'active', time(), time()]);
                    logActivity('USER_CREATED', ['username' => $uname]);
                    flash('success', 'User baru berhasil ditambahkan.');
                }
                redirect('?page=users');
            }
            // show form with errors
            renderUserForm($id, $errors);
            return;
        }
        $editUser = isset($_GET['id']) ? DB::row("SELECT * FROM `user` WHERE id=?", [(int)$_GET['id']]) : null;
        renderUserForm($editUser ? (int)$editUser['id'] : 0, [], $editUser);
        return;
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $u  = DB::row("SELECT * FROM `user` WHERE id=?", [$id]);
        if ($u && $u['username'] !== 'admin' && $u['id'] !== (currentUser()['id'] ?? 0)) {
            DB::exec("DELETE FROM `user` WHERE id=?", [$id]);
            logActivity('USER_DELETED', ['id' => $id]);
            if (isAjax()) jsonResponse(['message' => 'User dihapus']);
            flash('success', 'User berhasil dihapus.');
        }
        redirect('?page=users');
    }

    if ($action === 'toggle_status' && $method === 'POST') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = DB::row("SELECT status FROM `user` WHERE id=?", [$id]);
        $st  = ($cur && $cur['status'] === 'active') ? 'suspended' : 'active';
        DB::exec("UPDATE `user` SET status=?,updated_at=? WHERE id=?", [$st, time(), $id]);
        jsonResponse(['success' => true, 'status' => $st]);
    }

    // LIST
    $search = trim($_GET['search'] ?? '');
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 10;
    $offset = ($page_n - 1) * $limit;

    $where = '1=1';
    $params = [];
    if ($search) {
        $where = "(username LIKE ? OR name LIKE ? OR role LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $total = DB::count('user', $where, $params);
    $users = DB::query("SELECT id,name,username,role,status,created_at,last_login_at FROM `user` WHERE $where ORDER BY username LIMIT $limit OFFSET $offset", $params);
    $roles = DB::query("SELECT * FROM `user_role` ORDER BY id");

    renderLayout('Manajemen User', fn() => renderUsersPage($users, $total, $page_n, $limit, $search, $roles));
}

// ── CUSTOMERS ────────────────────────────────────────────────────────
function handleCustomers(): void {
    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'add' || ($action === 'edit' && isset($_GET['id']))) {
        if ($method === 'POST') {
            $id      = (int)($_GET['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $status  = $_POST['status'] ?? 'active';

            if (empty($name)) {
                flash('error', 'Nama customer wajib diisi.');
                redirect("?page=customers&action=" . ($id ? "edit&id=$id" : 'add'));
            }

            if ($id) {
                DB::exec("UPDATE `customer` SET name=?,email=?,phone=?,address=?,status=?,updated_at=? WHERE id=?",
                    [$name, $email ?: null, $phone ?: null, $address ?: null, $status, time(), $id]);
                logActivity('CUSTOMER_UPDATED', ['id' => $id]);
                flash('success', 'Customer berhasil diperbarui.');
            } else {
                $tenant = 'tenant_' . generateToken(8);
                $token  = generateToken(32);
                DB::exec("INSERT INTO `customer` (name,tenant,email,phone,address,api_token,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$name, $tenant, $email ?: null, $phone ?: null, $address ?: null, $token, $status, time(), time()]);
                logActivity('CUSTOMER_CREATED', ['name' => $name]);
                flash('success', 'Customer baru berhasil ditambahkan.');
            }
            redirect('?page=customers');
        }

        $cust = isset($_GET['id']) ? DB::row("SELECT * FROM `customer` WHERE id=?", [(int)$_GET['id']]) : null;
        renderLayout($cust ? 'Edit Customer' : 'Tambah Customer',
            fn() => renderCustomerForm($cust));
        return;
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM `customer` WHERE id=?", [(int)$_GET['id']]);
        logActivity('CUSTOMER_DELETED');
        if (isAjax()) jsonResponse(['message' => 'Customer dihapus']);
        flash('success', 'Customer dihapus.');
        redirect('?page=customers');
    }

    if ($action === 'regen_token' && isset($_GET['id'])) {
        $newToken = generateToken(32);
        DB::exec("UPDATE `customer` SET api_token=?,updated_at=? WHERE id=?", [$newToken, time(), (int)$_GET['id']]);
        flash('success', 'API Token berhasil diperbarui.');
        redirect('?page=customers');
    }

    $search = trim($_GET['search'] ?? '');
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 10;
    $offset = ($page_n - 1) * $limit;

    $where  = '1=1';
    $params = [];
    if ($search) {
        $where  = "(name LIKE ? OR email LIKE ? OR tenant LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $total     = DB::count('customer', $where, $params);
    $customers = DB::query("SELECT * FROM `customer` WHERE $where ORDER BY name LIMIT $limit OFFSET $offset", $params);

    renderLayout('Daftar Customer', fn() => renderCustomersPage($customers, $total, $page_n, $limit, $search));
}

// ── TEMPLATES ────────────────────────────────────────────────────────
function handleTemplates(): void {
    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'add' || ($action === 'edit' && isset($_GET['id']))) {
        if ($method === 'POST') {
            $id       = (int)($_GET['id'] ?? 0);
            $code     = trim($_POST['template_code'] ?? '');
            $name     = trim($_POST['name'] ?? '');
            $subject  = trim($_POST['subject'] ?? '');
            $protocol = $_POST['protocol'] ?? 'email';
            $priority = $_POST['priority'] ?? 'middle';
            $body     = $_POST['body'] ?? '';

            if (empty($code) || empty($name) || empty($body)) {
                flash('error', 'Kode, nama, dan isi template wajib diisi.');
                redirect("?page=templates&action=" . ($id ? "edit&id=$id" : 'add'));
            }

            if ($id) {
                DB::exec("UPDATE `template` SET template_code=?,name=?,subject=?,protocol=?,priority=?,body=?,updated_at=? WHERE id=?",
                    [$code, $name, $subject, $protocol, $priority, $body, time(), $id]);
                logActivity('TEMPLATE_UPDATED', ['id' => $id, 'code' => $code]);
                flash('success', 'Template berhasil diperbarui.');
            } else {
                try {
                    DB::exec("INSERT INTO `template` (template_code,name,subject,protocol,priority,body,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)",
                        [$code, $name, $subject, $protocol, $priority, $body, time(), time()]);
                    logActivity('TEMPLATE_CREATED', ['code' => $code]);
                    flash('success', 'Template baru berhasil ditambahkan.');
                } catch (Exception $e) {
                    flash('error', 'Kombinasi kode dan protokol sudah ada.');
                    redirect("?page=templates&action=add");
                }
            }
            redirect('?page=templates');
        }

        $tpl = isset($_GET['id']) ? DB::row("SELECT * FROM `template` WHERE id=?", [(int)$_GET['id']]) : null;
        renderLayout($tpl ? 'Edit Template' : 'Tambah Template',
            fn() => renderTemplateForm($tpl));
        return;
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM `template` WHERE id=?", [(int)$_GET['id']]);
        logActivity('TEMPLATE_DELETED');
        if (isAjax()) jsonResponse(['message' => 'Template dihapus']);
        flash('success', 'Template dihapus.');
        redirect('?page=templates');
    }

    if ($action === 'preview' && isAjax()) {
        $tpl = DB::row("SELECT * FROM `template` WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        jsonResponse($tpl ?: ['error' => 'Not found'], $tpl ? 200 : 404);
    }

    $search = trim($_GET['search'] ?? '');
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 15;
    $offset = ($page_n - 1) * $limit;

    $where  = '1=1';
    $params = [];
    if ($search) {
        $where  = "(template_code LIKE ? OR name LIKE ? OR protocol LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $total     = DB::count('template', $where, $params);
    $templates = DB::query("SELECT * FROM `template` WHERE $where ORDER BY protocol,template_code LIMIT $limit OFFSET $offset", $params);

    renderLayout('Template Notifikasi', fn() => renderTemplatesPage($templates, $total, $page_n, $limit, $search));
}

// ── SCHEDULER ────────────────────────────────────────────────────────
function handleScheduler(): void {
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); }

    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'add' && $method === 'POST') {
        $name  = trim($_POST['name'] ?? '');
        $job   = trim($_POST['job_class'] ?? '');
        $cron  = trim($_POST['cron_expression'] ?? '');

        if ($name && $job && $cron) {
            DB::exec("INSERT INTO `scheduled_task` (name,job_class,cron_expression,status,created_at,updated_at) VALUES (?,?,?,'active',?,?)",
                [$name, $job, $cron, time(), time()]);
            logActivity('SCHEDULER_CREATED', ['name' => $name]);
            flash('success', 'Tugas terjadwal ditambahkan.');
        }
        redirect('?page=scheduler');
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM `scheduled_task` WHERE id=?", [(int)$_GET['id']]);
        flash('success', 'Tugas dihapus.');
        redirect('?page=scheduler');
    }

    if ($action === 'toggle' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $t  = DB::row("SELECT status FROM `scheduled_task` WHERE id=?", [$id]);
        if ($t) {
            $ns = $t['status'] === 'active' ? 'inactive' : 'active';
            DB::exec("UPDATE `scheduled_task` SET status=? WHERE id=?", [$ns, $id]);
            jsonResponse(['status' => $ns]);
        }
    }

    $tasks = DB::query("SELECT * FROM `scheduled_task` ORDER BY id");
    renderLayout('Scheduler', fn() => renderSchedulerPage($tasks));
}

// ── SECURITY KEYS ────────────────────────────────────────────────────
function handleSecurityKeys(): void {
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); }

    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'generate' && $method === 'POST') {
        $identifier  = trim($_POST['identifier'] ?? 'key_' . time());
        $description = trim($_POST['description'] ?? '');
        
        // Generate RSA key pair
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($config);
        if ($res) {
            openssl_pkey_export($res, $privateKey);
            $pubKeyDetails = openssl_pkey_get_details($res);
            $publicKey = $pubKeyDetails['key'];
            DB::exec("REPLACE INTO security_keys (identifier,description,public_key,private_key,created_at) VALUES (?,?,?,?,?)",
                [$identifier, $description, $publicKey, $privateKey, time()]);
            logActivity('KEY_GENERATED', ['identifier' => $identifier]);
            flash('success', "RSA Key '{$identifier}' berhasil dibuat.");
        } else {
            flash('error', 'Gagal generate RSA key. Pastikan OpenSSL tersedia.');
        }
        redirect('?page=security_keys');
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM security_keys WHERE id=?", [(int)$_GET['id']]);
        flash('success', 'Key dihapus.');
        redirect('?page=security_keys');
    }

    if ($action === 'download' && isset($_GET['id'])) {
        $key  = DB::row("SELECT * FROM security_keys WHERE id=?", [(int)$_GET['id']]);
        $type = $_GET['type'] ?? 'public';
        if ($key) {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$key['identifier']}_{$type}.pem\"");
            echo $type === 'private' ? $key['private_key'] : $key['public_key'];
            exit;
        }
    }

    $keys = DB::query("SELECT id,identifier,description,created_at,SUBSTR(public_key,1,60) as public_key_preview FROM security_keys ORDER BY id DESC");
    renderLayout('RSA Key Manager', fn() => renderSecurityKeysPage($keys));
}

// ── SETTINGS ─────────────────────────────────────────────────────────
function handleSettings(): void {
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $allowed = ['app_name', 'theme', 'timezone', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'telegram_token', 'telegram_chatid'];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) {
                DB::exec("REPLACE INTO `setting` (setting_key,setting_value,created_at,updated_at) VALUES (?,?,IFNULL((SELECT created_at FROM `setting` s2 WHERE s2.setting_key=?),?),?)",
                    [$k, trim($_POST[$k]), $k, time(), time()]);
            }
        }
        logActivity('SETTINGS_UPDATED');
        flash('success', 'Pengaturan berhasil disimpan.');
        redirect('?page=settings');
    }

    $settings = [];
    foreach (DB::query("SELECT * FROM `setting`") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    renderLayout('Pengaturan', fn() => renderSettingsPage($settings));
}

// ── PROFILE ──────────────────────────────────────────────────────────
function handleProfile(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid  = currentUser()['id'];
        $name = trim($_POST['name'] ?? '');
        $pass = $_POST['password'] ?? '';
        $conf = $_POST['password_confirmation'] ?? '';
        $them = $_POST['theme'] ?? 'auto';

        if (empty($name)) {
            flash('error', 'Nama tidak boleh kosong.');
            redirect('?page=profile');
        }

        if (!empty($pass) && $pass !== $conf) {
            flash('error', 'Password baru dan konfirmasi tidak cocok.');
            redirect('?page=profile');
        }

        if (!empty($pass)) {
            DB::exec("UPDATE `user` SET name=?,theme=?,password=?,updated_at=? WHERE id=?",
                [$name, $them, password_hash($pass, PASSWORD_DEFAULT), time(), $uid]);
        } else {
            DB::exec("UPDATE `user` SET name=?,theme=?,updated_at=? WHERE id=?",
                [$name, $them, time(), $uid]);
        }

        // Update session
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['theme'] = $them;
        logActivity('PROFILE_UPDATED');
        flash('success', 'Profil berhasil diperbarui.');
        redirect('?page=profile');
    }

    $userData = DB::row("SELECT * FROM `user` WHERE id=?", [currentUser()['id']]);
    renderLayout('Profil Saya', fn() => renderProfilePage($userData));
}

// ── ROLES ─────────────────────────────────────────────────────────────
function handleRoles(): void {
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); }
    $action = $_GET['action'] ?? 'list';

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(preg_replace('/\s+/', '_', trim($_POST['slug'] ?? '')));
        $desc = trim($_POST['description'] ?? '');
        if ($name && $slug) {
            try {
                DB::exec("INSERT INTO `user_role` (name,slug,description,created_at,updated_at) VALUES (?,?,?,?,?)", [$name,$slug,$desc,time(),time()]);
                flash('success', 'Role ditambahkan.');
            } catch (Exception $e) {
                flash('error', 'Slug sudah digunakan.');
            }
        }
        redirect('?page=roles');
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM `user_role` WHERE id=? AND slug NOT IN ('admin','user')", [(int)$_GET['id']]);
        flash('success', 'Role dihapus.');
        redirect('?page=roles');
    }

    $roles = DB::query("SELECT * FROM `user_role` ORDER BY id");
    renderLayout('Master Peran', fn() => renderRolesPage($roles));
}

// ══════════════════════════════════════════════════════════════════════
// RENDER FUNCTIONS
// ══════════════════════════════════════════════════════════════════════

function renderLogin(string $error = ''): void {
    $appName = getSetting('app_name', APP_NAME);
    ?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= h($appName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
  <div class="flex flex-col items-center mb-8">
    <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center text-white mb-4">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-slate-900"><?= h($appName) ?></h1>
    <p class="text-xs text-slate-500 mt-1 uppercase tracking-wider font-semibold">Masuk ke akun Anda</p>
  </div>

  <?php if ($error): ?>
  <div class="mb-4 p-3 border-l-4 border-red-500 bg-red-50 text-red-800 text-sm rounded-r-lg"><?= h($error) ?></div>
  <?php endif; ?>

  <form id="login-form" method="POST" action="?page=login" class="space-y-5">
    <div>
      <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Username</label>
      <input type="text" name="username" required autofocus placeholder="admin"
        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Password</label>
      <input type="password" name="password" required placeholder="••••••••"
        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>
    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-sm transition-colors">
      Masuk
    </button>
    <p class="text-center text-xs text-slate-400 pt-2">Default: admin / admin123</p>
  </form>
</div>
</body>
</html>
<?php
}

// ─── LAYOUT WRAPPER ─────────────────────────────────────────────────
function renderLayout(string $title, callable $content): void {
    global $theme;
    $user     = currentUser();
    $appName  = getSetting('app_name', APP_NAME);
    $flash    = getFlash();
    $isDark   = $theme === 'dark';
    $htmlClass = $isDark ? 'dark' : '';
    $page     = $_GET['page'] ?? 'home';
    ?>
<!DOCTYPE html>
<html lang="id" class="h-full <?= $htmlClass ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> — <?= h($appName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] }, colors: { gray: { 850: '#17202e' } } } }
}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; }
  .sidebar-link { @apply flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors; }
  .sidebar-link:hover { @apply bg-blue-600/10 text-blue-400; }
  .sidebar-link.active { @apply bg-blue-600/20 text-blue-400; }
  .no-scrollbar::-webkit-scrollbar { display: none; }
  .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  [x-cloak] { display: none !important; }
</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white h-screen overflow-hidden flex flex-col antialiased">

<!-- TOP BAR -->
<header class="h-14 bg-white dark:bg-[#111214] border-b border-slate-200 dark:border-[#1e2023] flex items-center px-4 gap-3 z-50 flex-shrink-0">
  <button id="sidebar-toggle" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-gray-800 transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
  </button>
  <a href="?page=home" class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
    <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
    </div>
    <span><?= h($appName) ?></span>
    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-mono"><?= APP_VERSION ?></span>
  </a>

  <div class="flex-1"></div>

  <span class="text-xs text-slate-400 hidden sm:block"><?= date('d M Y H:i') ?></span>

  <!-- User Menu -->
  <div class="relative" id="user-menu-container">
    <button id="user-menu-btn" class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-gray-800 transition-colors text-sm font-medium">
      <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold">
        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
      </div>
      <span class="hidden sm:block text-slate-700 dark:text-slate-300"><?= h($user['name'] ?? '') ?></span>
      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div id="user-dropdown" class="hidden absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl shadow-lg py-1 z-50">
      <a href="?page=profile" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profil Saya
      </a>
      <?php if(isAdmin()): ?>
      <a href="?page=settings" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.608 3.292 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Pengaturan
      </a>
      <?php endif; ?>
      <div class="border-t border-slate-100 dark:border-gray-700 my-1"></div>
      <a href="?page=logout" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Keluar
      </a>
    </div>
  </div>
</header>

<div class="flex flex-1 overflow-hidden">
<!-- SIDEBAR -->
<aside id="sidebar" class="w-60 bg-[#111c2e] text-gray-300 border-r border-[#1e2b44] flex flex-col flex-shrink-0 overflow-y-auto no-scrollbar transition-all duration-300">
  <nav class="p-3 space-y-0.5 flex-1">
    <?php
    $menuItems = [
        ['page' => 'home',         'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'text' => 'Dashboard'],
        ['page' => 'customers',    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'text' => 'Customer'],
        ['page' => 'templates',    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', 'text' => 'Template Notifikasi'],
        ['page' => 'message_stats','icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'text' => 'Log Pengiriman'],
        'sep',
        ['page' => 'users',        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.653-.165-1.294-.478-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.653.165-1.294.478-1.857m0 0a5.002 5.002 0 019.044 0M9 13a2 2 0 11-4 0 2 2 0 014 0zm9 0a2 2 0 11-4 0 2 2 0 014 0z', 'text' => 'Pengguna', 'admin' => true],
        ['page' => 'roles',        'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'text' => 'Master Peran', 'admin' => true],
        ['page' => 'activity_log', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'text' => 'Log Aktivitas', 'admin' => true],
        ['page' => 'scheduler',    'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'text' => 'Scheduler', 'admin' => true],
        ['page' => 'security_keys','icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'text' => 'RSA Key Manager', 'admin' => true],
        ['page' => 'settings',     'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.608 3.292 0z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'text' => 'Pengaturan', 'admin' => true],
    ];

    foreach ($menuItems as $item) {
        if ($item === 'sep') {
            echo '<div class="border-t border-[#1e2b44] my-2"></div>';
            continue;
        }
        if (!empty($item['admin']) && !isAdmin()) continue;
        $isActive = ($page === $item['page']);
        $cls = $isActive ? 'sidebar-link active text-blue-400' : 'sidebar-link text-gray-400 hover:text-white';
        echo "<a href=\"?page={$item['page']}\" class=\"{$cls}\">
            <svg class=\"w-4 h-4 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\">
              <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"{$item['icon']}\"/>
            </svg>
            <span data-sidebar-text>{$item['text']}</span>
          </a>";
    }
    ?>
  </nav>
  <div class="p-3 border-t border-[#1e2b44]">
    <div class="text-[10px] text-gray-600 text-center">v<?= APP_VERSION ?> &copy; Assist GW</div>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900">

  <!-- Flash Message -->
  <?php if ($flash): ?>
  <div id="flash-msg" class="mx-6 mt-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2
    <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?php if ($flash['type'] === 'success'): ?>
    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php else: ?>
    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php endif; ?>
    <?= h($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <div class="p-6">
    <?php $content(); ?>
  </div>

  <!-- Footer -->
  <footer class="text-center p-3 border-t border-slate-200 dark:border-[#1e2023] bg-white dark:bg-[#111214]">
    <p class="text-[10px] text-slate-400 uppercase tracking-wider">&copy; <?= date('Y') ?> Assist Gateway v<?= APP_VERSION ?></p>
  </footer>
</main>
</div>

<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebar-toggle');
let sidebarOpen = true;

toggleBtn?.addEventListener('click', () => {
  sidebarOpen = !sidebarOpen;
  if (sidebarOpen) {
    sidebar.style.width = '15rem';
    sidebar.querySelectorAll('[data-sidebar-text]').forEach(el => el.classList.remove('hidden'));
  } else {
    sidebar.style.width = '3.5rem';
    sidebar.querySelectorAll('[data-sidebar-text]').forEach(el => el.classList.add('hidden'));
  }
});

// User dropdown
const userBtn = document.getElementById('user-menu-btn');
const userDrop = document.getElementById('user-dropdown');
userBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  userDrop.classList.toggle('hidden');
});
document.addEventListener('click', () => userDrop?.classList.add('hidden'));

// Auto-hide flash
setTimeout(() => {
  const f = document.getElementById('flash-msg');
  if (f) f.style.opacity = '0', f.style.transition = 'opacity 0.5s', setTimeout(() => f.remove(), 500);
}, 4000);
</script>
</body>
</html>
<?php
}

// ─── DASHBOARD PAGE ─────────────────────────────────────────────────
function renderDashboard(): void {
    $totalCustomers  = DB::count('customer');
    $totalTemplates  = DB::count('template');
    $totalSent       = DB::count('message_stats', "status='sent'");
    $totalFailed     = DB::count('message_stats', "status='failed'");
    $totalQueued     = DB::count('message_stats', "status='queued'");
    $totalUsers      = DB::count('user');
    $totalActive     = DB::count('customer', "status='active'");
    $recentLogs      = DB::query("SELECT al.*, u.name as user_name FROM `activity_log` al LEFT JOIN `user` u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 8");
    $dailyStats      = DB::query("SELECT DATE(FROM_UNIXTIME(created_at)) as day, COUNT(*) as total, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent FROM message_stats WHERE created_at > ? GROUP BY day ORDER BY day", [strtotime('-7 days')]);
    $appVersion      = APP_VERSION;

    renderLayout('Dashboard', function() use ($totalCustomers,$totalTemplates,$totalSent,$totalFailed,$totalQueued,$totalUsers,$totalActive,$recentLogs,$dailyStats,$appVersion) {
    ?>
    <!-- Headline -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          Welcome Back!
          <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-mono">v<?= h($appVersion) ?></span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Panel pusat kendali Assist Gateway</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping inline-block"></span>
        <span class="text-xs font-semibold text-slate-500">Gateway Online</span>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <?php
      $cards = [
          ['label'=>'Total Customer','value'=>$totalCustomers,'color'=>'blue','icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
          ['label'=>'Pesan Terkirim','value'=>$totalSent,'color'=>'emerald','icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
          ['label'=>'Pesan Terantre','value'=>$totalQueued,'color'=>'amber','icon'=>'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
          ['label'=>'Pesan Gagal','value'=>$totalFailed,'color'=>'rose','icon'=>'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
          ['label'=>'Templat Aktif','value'=>$totalTemplates,'color'=>'indigo','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
      ];
      foreach ($cards as $c): ?>
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-2xl p-5 flex items-center justify-between shadow-sm hover:shadow-md transition-shadow">
        <div>
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?= $c['label'] ?></div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-1.5"><?= number_format($c['value']) ?></div>
        </div>
        <div class="w-11 h-11 flex items-center justify-center bg-<?= $c['color'] ?>-50 dark:bg-<?= $c['color'] ?>-950/40 text-<?= $c['color'] ?>-600 dark:text-<?= $c['color'] ?>-400 rounded-xl">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $c['icon'] ?>"/>
          </svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Second Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

      <!-- Daily Chart (7 days) -->
      <div class="lg:col-span-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Statistik 7 Hari Terakhir</h3>
        <div class="flex items-end gap-2 h-32">
          <?php
          $maxVal = max(1, array_reduce($dailyStats, fn($c,$r) => max($c, $r['total']), 0));
          // Fill 7 days
          $days = [];
          for ($i=6; $i>=0; $i--) {
              $d = date('Y-m-d', strtotime("-$i days"));
              $days[$d] = ['total'=>0,'sent'=>0];
          }
          foreach ($dailyStats as $r) $days[$r['day']] = $r;
          foreach ($days as $d => $r):
              $h = max(4, round(($r['total']/$maxVal)*100));
          ?>
          <div class="flex-1 flex flex-col items-center gap-1">
            <span class="text-[9px] text-slate-400"><?= $r['total'] ?></span>
            <div class="w-full bg-blue-100 dark:bg-blue-900/30 rounded-t-sm hover:bg-blue-200 transition-colors" style="height:<?= $h ?>%;" title="<?= $d ?>: <?= $r['total'] ?> pesan"></div>
            <span class="text-[9px] text-slate-400"><?= date('d/m', strtotime($d)) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm flex flex-col gap-4">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Ringkasan Sistem</h3>
        <div class="space-y-3">
          <?php
          $summaries = [
              ['label'=>'Customer Aktif','value'=>$totalActive,'badge'=>'green'],
              ['label'=>'Total User','value'=>$totalUsers,'badge'=>'blue'],
              ['label'=>'Versi App','value'=>'v'.APP_VERSION,'badge'=>'indigo'],
              ['label'=>'PHP Version','value'=>PHP_VERSION,'badge'=>'gray'],
              ['label'=>'Database','value'=>'SQLite','badge'=>'gray'],
          ];
          foreach ($summaries as $s): ?>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-500 dark:text-slate-400"><?= $s['label'] ?></span>
            <span class="font-semibold px-2 py-0.5 rounded-full text-xs bg-<?= $s['badge'] ?>-100 dark:bg-<?= $s['badge'] ?>-900/30 text-<?= $s['badge'] ?>-700 dark:text-<?= $s['badge'] ?>-300"><?= $s['value'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-gray-700">
        <h3 class="font-bold text-slate-900 dark:text-white">Aktivitas Terbaru</h3>
        <a href="?page=activity_log" class="text-xs text-blue-500 hover:underline font-semibold">Lihat semua →</a>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-gray-700">
        <?php if (empty($recentLogs)): ?>
        <div class="px-6 py-4 text-sm text-slate-400 italic">Belum ada aktivitas.</div>
        <?php else: foreach ($recentLogs as $log): ?>
        <div class="flex items-center gap-3 px-6 py-3">
          <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-slate-900 dark:text-white truncate"><?= h($log['action']) ?></div>
            <div class="text-xs text-slate-400"><?= h($log['user_name'] ?? 'System') ?> · <?= formatDate((int)$log['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php
    });
}

// ─── USERS PAGE ─────────────────────────────────────────────────────
function renderUsersPage(array $users, int $total, int $page_n, int $limit, string $search, array $roles): void {
    $totalPages = ceil($total / $limit);
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Manajemen User</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> pengguna terdaftar</p>
      </div>
      <a href="?page=users&action=add" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Tambah User
      </a>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <form method="GET" class="flex gap-2">
        <input type="hidden" name="page" value="users">
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari username, nama, role..."
          class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Cari</button>
        <?php if ($search): ?><a href="?page=users" class="px-4 py-2 bg-slate-200 dark:bg-gray-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 transition-colors">Reset</a><?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-4 py-3 text-left">Username</th>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Role</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Dibuat</th>
              <th class="px-4 py-3 text-left">Last Login</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($users)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 italic">Tidak ada pengguna ditemukan.</td></tr>
            <?php else: foreach ($users as $u): 
                $stLabel = $u['status'] ?? 'active';
                $stColor = ['active'=>'green','suspended'=>'red','locked'=>'yellow'][$stLabel] ?? 'gray';
                $isMe = ((int)$u['id'] === (int)(currentUser()['id'] ?? 0));
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-blue-600 dark:text-blue-400">
                <?= h($u['username']) ?>
                <?php if ($isMe): ?><span class="ml-1 text-[9px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-bold">you</span><?php endif; ?>
              </td>
              <td class="px-4 py-3 text-slate-800 dark:text-slate-200"><?= h($u['name']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                  <?= $u['role']==='admin' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' :
                    ($u['role']==='operator' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300') ?>">
                  <?= h($u['role']) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-<?= $stColor ?>-100 text-<?= $stColor ?>-700 dark:bg-<?= $stColor ?>-900/30 dark:text-<?= $stColor ?>-300">
                  <?= $stLabel ?>
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= formatDate((int)$u['created_at']) ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= $u['last_login_at'] > 0 ? formatDate((int)$u['last_login_at']) : '<span class="italic text-slate-300">Never</span>' ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <?php if (!$isMe && $u['username'] !== 'admin'): ?>
                  <a href="?page=users&action=edit&id=<?= $u['id'] ?>" class="text-slate-400 hover:text-blue-600 transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </a>
                  <button onclick="if(confirm('Hapus user <?= h(addslashes($u['username'])) ?>?')) window.location='?page=users&action=delete&id=<?= $u['id'] ?>'"
                    class="text-slate-400 hover:text-red-600 transition-colors" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                  <?php else: ?>
                  <span class="text-[10px] text-slate-300 italic">protected</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, "?page=users" . ($search ? "&search=" . urlencode($search) : '')); ?>
    </div>
    <?php
}

function renderUserForm(int $id, array $errors = [], ?array $user = null): void {
    $title = $id ? 'Edit User' : 'Tambah User Baru';
    $roles = DB::query("SELECT * FROM `user_role` ORDER BY id");
    renderLayout($title, function() use ($id, $errors, $user, $roles, $title) {
    ?>
    <div class="max-w-xl">
      <div class="flex items-center gap-3 mb-6">
        <a href="?page=users" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= $title ?></h1>
      </div>

      <?php foreach ($errors as $e): ?>
      <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($e) ?></div>
      <?php endforeach; ?>

      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
        <form method="POST" action="?page=users&action=<?= $id ? "edit&id=$id" : 'add' ?>" class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Lengkap *</label>
            <input type="text" name="name" required value="<?= h($user['name'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
          </div>
          <?php if (!$id): ?>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Username *</label>
            <input type="text" name="username" required value="<?= h($user['username'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
          </div>
          <?php endif; ?>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Password <?= $id ? '(kosongkan jika tidak diubah)' : '*' ?></label>
            <input type="password" name="password" <?= !$id ? 'required' : '' ?> placeholder="••••••••"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Role</label>
            <select name="role" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
              <?php foreach ($roles as $r): ?>
              <option value="<?= h($r['slug']) ?>" <?= ($user['role'] ?? 'user') === $r['slug'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
              <?= $id ? 'Perbarui' : 'Tambahkan' ?>
            </button>
            <a href="?page=users" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">
              Batal
            </a>
          </div>
        </form>
      </div>
    </div>
    <?php
    });
}

// ─── CUSTOMERS PAGE ──────────────────────────────────────────────────
function renderCustomersPage(array $customers, int $total, int $page_n, int $limit, string $search): void {
    $totalPages = ceil($total / $limit);
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Daftar Customer</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> customer terdaftar</p>
      </div>
      <a href="?page=customers&action=add" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Tambah Customer
      </a>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-2 mb-4">
      <input type="hidden" name="page" value="customers">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari nama, email, tenant..."
        class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
      <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Cari</button>
      <?php if ($search): ?><a href="?page=customers" class="px-4 py-2 bg-slate-200 dark:bg-gray-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg">Reset</a><?php endif; ?>
    </form>

    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Tenant ID</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">API Token</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Dibuat</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($customers)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 italic">Tidak ada customer.</td></tr>
            <?php else: foreach ($customers as $c): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= h($c['name']) ?></td>
              <td class="px-4 py-3 text-xs font-mono text-slate-500 dark:text-slate-400"><?= h(substr($c['tenant'],0,18)).'...' ?></td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?= h($c['email'] ?? '—') ?></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <code class="text-[10px] font-mono bg-slate-100 dark:bg-gray-700 px-2 py-0.5 rounded text-slate-600 dark:text-slate-300"><?= h(substr($c['api_token'],0,16)).'...' ?></code>
                  <a href="?page=customers&action=regen_token&id=<?= $c['id'] ?>" onclick="return confirm('Regenerasi token? Token lama akan tidak berlaku.')" title="Regenerasi Token" class="text-amber-500 hover:text-amber-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                  </a>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $c['status']==='active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-700' ?>">
                  <?= h($c['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= formatDate((int)$c['created_at']) ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a href="?page=customers&action=edit&id=<?= $c['id'] ?>" class="text-slate-400 hover:text-blue-600 transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </a>
                  <button onclick="if(confirm('Hapus customer ini?')) window.location='?page=customers&action=delete&id=<?= $c['id'] ?>'"
                    class="text-slate-400 hover:text-red-600 transition-colors" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, "?page=customers" . ($search ? "&search=" . urlencode($search) : '')); ?>
    </div>

    <!-- API Usage Example -->
    <div class="mt-6 bg-slate-900 dark:bg-[#0d1117] border border-slate-700 rounded-xl p-5 text-sm">
      <h3 class="text-white font-semibold mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
        Contoh Penggunaan API
      </h3>
      <pre class="text-green-400 text-xs overflow-x-auto"><code>POST <?= h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']) ?>?page=api&action=send
X-Api-Token: {api_token}
Content-Type: application/json

{
  "template_code": "otp",
  "recipient": "user@example.com",
  "protocol": "email",
  "variables": {
    "nama_user": "John Doe",
    "kode_otp": "123456"
  }
}</code></pre>
    </div>
    <?php
}

function renderCustomerForm(?array $cust): void {
    $isEdit = !empty($cust);
    ?>
    <div class="max-w-xl">
      <div class="flex items-center gap-3 mb-6">
        <a href="?page=customers" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= $isEdit ? 'Edit Customer' : 'Tambah Customer Baru' ?></h1>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
        <form method="POST" action="?page=customers&action=<?= $isEdit ? "edit&id={$cust['id']}" : 'add' ?>" class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Customer *</label>
            <input type="text" name="name" required value="<?= h($cust['name'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
            <input type="email" name="email" value="<?= h($cust['email'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">No. Telepon</label>
            <input type="text" name="phone" value="<?= h($cust['phone'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Alamat</label>
            <textarea name="address" rows="3" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 resize-none"><?= h($cust['address'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Status</label>
            <select name="status" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
              <option value="active" <?= ($cust['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= ($cust['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
              <?= $isEdit ? 'Perbarui' : 'Tambahkan' ?>
            </button>
            <a href="?page=customers" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-gray-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php
}

// ─── TEMPLATES PAGE ──────────────────────────────────────────────────
function renderTemplatesPage(array $templates, int $total, int $page_n, int $limit, string $search): void {
    $totalPages = ceil($total / $limit);
    $priorityMap = ['high'=>['Tinggi','red'],'middle'=>['Sedang','yellow'],'low'=>['Rendah','blue']];
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Template Notifikasi</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> template terdaftar</p>
      </div>
      <a href="?page=templates&action=add" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Tambah Template
      </a>
    </div>

    <form method="GET" class="flex gap-2 mb-4">
      <input type="hidden" name="page" value="templates">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari kode, nama, protokol..."
        class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:border-blue-500">
      <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Cari</button>
      <?php if ($search): ?><a href="?page=templates" class="px-4 py-2 bg-slate-200 dark:bg-gray-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg">Reset</a><?php endif; ?>
    </form>

    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Kode Template</th>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Protokol</th>
              <th class="px-4 py-3 text-left">Prioritas</th>
              <th class="px-4 py-3 text-left">Diperbarui</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($templates)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">Tidak ada template.</td></tr>
            <?php else: foreach ($templates as $t):
                [$pLabel,$pColor] = $priorityMap[$t['priority']] ?? ['Sedang','yellow'];
                $protocolColors = ['email'=>'blue','telegram'=>'sky','sms'=>'green','whatsapp'=>'emerald'];
                $pc = $protocolColors[$t['protocol']] ?? 'slate';
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors" id="tpl-row-<?= $t['id'] ?>">
              <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-900 dark:text-white"><?= h($t['template_code']) ?></td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900 dark:text-white"><?= h($t['name']) ?></div>
                <?php if ($t['subject']): ?><div class="text-xs text-slate-400 truncate max-w-xs"><?= h($t['subject']) ?></div><?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-<?= $pc ?>-100 text-<?= $pc ?>-700 dark:bg-<?= $pc ?>-900/30 dark:text-<?= $pc ?>-300">
                  <?= h(ucfirst($t['protocol'])) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-<?= $pColor ?>-100 text-<?= $pColor ?>-700"><?= $pLabel ?></span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= formatDate((int)$t['updated_at']) ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button onclick="previewTemplate(<?= $t['id'] ?>)" class="text-slate-400 hover:text-indigo-600 transition-colors" title="Preview">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                  <a href="?page=templates&action=edit&id=<?= $t['id'] ?>" class="text-slate-400 hover:text-blue-600 transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </a>
                  <button onclick="if(confirm('Hapus template ini?')) window.location='?page=templates&action=delete&id=<?= $t['id'] ?>'"
                    class="text-slate-400 hover:text-red-600 transition-colors" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, "?page=templates" . ($search ? "&search=" . urlencode($search) : '')); ?>
    </div>

    <!-- Preview Modal -->
    <div id="preview-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-gray-700">
          <h3 class="font-bold text-slate-900 dark:text-white" id="preview-title">Preview Template</h3>
          <button onclick="closePreview()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div id="preview-content" class="p-6 text-sm text-slate-700 dark:text-slate-300 space-y-3"></div>
      </div>
    </div>

    <script>
    async function previewTemplate(id) {
      const modal = document.getElementById('preview-modal');
      const content = document.getElementById('preview-content');
      const title = document.getElementById('preview-title');
      content.innerHTML = '<div class="text-center py-4"><svg class="animate-spin w-6 h-6 mx-auto text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></div>';
      modal.classList.remove('hidden');
      
      try {
        const r = await fetch(`?page=templates&action=preview&id=${id}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
        const d = await r.json();
        if (d.error) { content.innerHTML = '<p class="text-red-500">'+d.error+'</p>'; return; }
        title.textContent = d.name;
        content.innerHTML = `
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div><span class="font-semibold">Kode:</span> <code class="bg-slate-100 dark:bg-gray-700 px-1 rounded">${d.template_code}</code></div>
            <div><span class="font-semibold">Protokol:</span> ${d.protocol}</div>
            <div><span class="font-semibold">Prioritas:</span> ${d.priority}</div>
            ${d.subject ? `<div><span class="font-semibold">Subject:</span> ${d.subject}</div>` : ''}
          </div>
          <div class="mt-3 p-3 bg-slate-50 dark:bg-gray-750 rounded-lg border border-slate-200 dark:border-gray-600">
            <div class="text-xs font-semibold text-slate-500 mb-2">Isi Template:</div>
            <div class="prose prose-sm dark:prose-invert max-w-none">${d.body}</div>
          </div>`;
      } catch(e) {
        content.innerHTML = '<p class="text-red-500">Gagal memuat preview.</p>';
      }
    }
    function closePreview() {
      document.getElementById('preview-modal').classList.add('hidden');
    }
    document.getElementById('preview-modal')?.addEventListener('click', function(e) {
      if(e.target === this) closePreview();
    });
    </script>
    <?php
}

function renderTemplateForm(?array $tpl): void {
    $isEdit = !empty($tpl);
    ?>
    <div class="max-w-2xl">
      <div class="flex items-center gap-3 mb-6">
        <a href="?page=templates" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= $isEdit ? 'Edit Template' : 'Tambah Template Baru' ?></h1>
      </div>
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
        <form method="POST" action="?page=templates&action=<?= $isEdit ? "edit&id={$tpl['id']}" : 'add' ?>" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Kode Template *</label>
              <input type="text" name="template_code" required value="<?= h($tpl['template_code'] ?? '') ?>"
                placeholder="contoh: otp, activation"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Protokol *</label>
              <select name="protocol" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                <?php foreach (['email'=>'Email','telegram'=>'Telegram','sms'=>'SMS','whatsapp'=>'WhatsApp'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($tpl['protocol'] ?? 'email') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Template *</label>
            <input type="text" name="name" required value="<?= h($tpl['name'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Subject (untuk Email)</label>
            <input type="text" name="subject" value="<?= h($tpl['subject'] ?? '') ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Prioritas</label>
            <select name="priority" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
              <option value="high" <?= ($tpl['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Tinggi (High)</option>
              <option value="middle" <?= ($tpl['priority'] ?? 'middle') === 'middle' ? 'selected' : '' ?>>Sedang (Middle)</option>
              <option value="low" <?= ($tpl['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Rendah (Low)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Isi Template * 
              <span class="font-normal text-slate-400">(gunakan {{placeholder}} untuk variabel dinamis)</span>
            </label>
            <textarea name="body" required rows="10"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500 resize-y"><?= h($tpl['body'] ?? '') ?></textarea>
            <p class="mt-1 text-xs text-slate-400">Contoh: Halo {{nama_user}}, kode OTP Anda adalah {{kode_otp}}</p>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
              <?= $isEdit ? 'Perbarui' : 'Tambahkan' ?>
            </button>
            <a href="?page=templates" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-gray-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg transition-colors">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php
}

// ─── ACTIVITY LOG ────────────────────────────────────────────────────
function renderActivityLog(): void {
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 20;
    $offset = ($page_n - 1) * $limit;
    $search = trim($_GET['search'] ?? '');

    $where  = '1=1';
    $params = [];
    if ($search) {
        $where  = "al.action LIKE ?";
        $params = ["%$search%"];
    }

    $total = DB::row("SELECT COUNT(*) as c FROM `activity_log` al WHERE $where", $params)['c'];
    $logs  = DB::query("SELECT al.*, u.name as user_name FROM `activity_log` al LEFT JOIN `user` u ON u.id=al.user_id WHERE $where ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset", $params);
    $totalPages = ceil($total / $limit);

    renderLayout('Log Aktivitas', function() use ($logs, $total, $page_n, $totalPages, $search, $limit) {
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Log Aktivitas</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> entri log</p>
      </div>
    </div>

    <form method="GET" class="flex gap-2 mb-4">
      <input type="hidden" name="page" value="activity_log">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Filter aksi..."
        class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:border-blue-500">
      <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Filter</button>
      <?php if ($search): ?><a href="?page=activity_log" class="px-4 py-2 bg-slate-200 dark:bg-gray-700 text-sm text-slate-700 dark:text-slate-300 font-medium rounded-lg">Reset</a><?php endif; ?>
    </form>

    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Waktu</th>
              <th class="px-4 py-3 text-left">Aksi</th>
              <th class="px-4 py-3 text-left">Pengguna</th>
              <th class="px-4 py-3 text-left">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($logs)): ?>
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 italic">Tidak ada log aktivitas.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 text-xs text-slate-400 font-mono whitespace-nowrap"><?= formatDate((int)$log['created_at']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-mono font-semibold rounded bg-slate-100 dark:bg-gray-700 text-slate-700 dark:text-slate-300"><?= h($log['action']) ?></span>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?= h($log['user_name'] ?? 'System') ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate">
                <?php if ($log['payload']): 
                    $p = json_decode($log['payload'], true);
                    echo h(is_array($p) ? implode(', ', array_map(fn($k,$v)=>"$k: $v", array_keys($p), $p)) : $log['payload']);
                endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, "?page=activity_log" . ($search ? "&search=" . urlencode($search) : '')); ?>
    </div>
    <?php
    });
}

// ─── MESSAGE STATS ───────────────────────────────────────────────────
function renderMessageStats(): void {
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 20;
    $offset = ($page_n - 1) * $limit;
    $filter = $_GET['status'] ?? '';

    $where  = '1=1';
    $params = [];
    if ($filter) { $where = "ms.status=?"; $params = [$filter]; }

    $total = DB::row("SELECT COUNT(*) as c FROM message_stats ms WHERE $where", $params)['c'];
    $msgs  = DB::query("SELECT ms.*, c.name as customer_name FROM message_stats ms LEFT JOIN `customer` c ON c.id=CAST(ms.customer_id AS UNSIGNED) WHERE $where ORDER BY ms.created_at DESC LIMIT $limit OFFSET $offset", $params);
    $summary = DB::query("SELECT status, COUNT(*) as cnt FROM message_stats GROUP BY status");
    $totalPages = ceil($total / $limit);

    renderLayout('Log Pengiriman', function() use ($msgs, $total, $page_n, $totalPages, $filter, $summary, $limit) {
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Log Pengiriman Pesan</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> pesan</p>
      </div>
    </div>

    <!-- Summary pills -->
    <div class="flex gap-3 mb-5 flex-wrap">
      <a href="?page=message_stats" class="px-3 py-1.5 rounded-full text-xs font-semibold border <?= !$filter ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-800 border-slate-200 dark:border-gray-700 text-slate-600 dark:text-slate-300 hover:border-blue-400' ?>">Semua</a>
      <?php
      $statusColors = ['sent'=>'green','queued'=>'amber','failed'=>'red'];
      foreach ($summary as $s):
          $sc = $statusColors[$s['status']] ?? 'slate';
          $active = $filter === $s['status'];
      ?>
      <a href="?page=message_stats&status=<?= $s['status'] ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold border
        <?= $active ? "bg-{$sc}-600 text-white border-{$sc}-600" : "bg-white dark:bg-gray-800 border-slate-200 dark:border-gray-700 text-slate-600 dark:text-slate-300 hover:border-{$sc}-400" ?>">
        <?= ucfirst($s['status']) ?> (<?= $s['cnt'] ?>)
      </a>
      <?php endforeach; ?>
    </div>

    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Waktu</th>
              <th class="px-4 py-3 text-left">Customer</th>
              <th class="px-4 py-3 text-left">Template</th>
              <th class="px-4 py-3 text-left">Protokol</th>
              <th class="px-4 py-3 text-left">Penerima</th>
              <th class="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($msgs)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">Tidak ada data pengiriman.</td></tr>
            <?php else: foreach ($msgs as $m):
                $sc = ['sent'=>'green','failed'=>'red','queued'=>'amber'][$m['status']] ?? 'slate';
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 text-xs text-slate-400 font-mono whitespace-nowrap"><?= formatDate((int)$m['created_at']) ?></td>
              <td class="px-4 py-3 text-slate-800 dark:text-slate-200"><?= h($m['customer_name'] ?? '—') ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($m['template_code']) ?></td>
              <td class="px-4 py-3 text-slate-500"><?= h(ucfirst($m['protocol'])) ?></td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300 max-w-xs truncate"><?= h($m['recipient']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-<?= $sc ?>-100 text-<?= $sc ?>-700 dark:bg-<?= $sc ?>-900/30 dark:text-<?= $sc ?>-300">
                  <?= ucfirst($m['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, "?page=message_stats" . ($filter ? "&status=$filter" : '')); ?>
    </div>
    <?php
    });
}

// ─── SCHEDULER ───────────────────────────────────────────────────────
function renderSchedulerPage(array $tasks): void {
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Tugas Terjadwal</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($tasks) ?> tugas terdaftar</p>
      </div>
    </div>

    <!-- Add Task Form -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-5 shadow-sm mb-6">
      <h3 class="font-semibold text-slate-900 dark:text-white mb-4">Tambah Tugas Baru</h3>
      <form method="POST" action="?page=scheduler&action=add" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <input type="text" name="name" required placeholder="Nama tugas"
          class="px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
        <input type="text" name="job_class" required placeholder="App\Console\Job\NamaJob"
          class="px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
        <div class="flex gap-2">
          <input type="text" name="cron_expression" required placeholder="* * * * *"
            class="flex-1 px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
          <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
            Tambah
          </button>
        </div>
      </form>
    </div>

    <!-- Tasks List -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Job Class</th>
              <th class="px-4 py-3 text-left">Jadwal (Cron)</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Last Run</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($tasks)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">Belum ada tugas terjadwal.</td></tr>
            <?php else: foreach ($tasks as $t): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= h($t['name']) ?></td>
              <td class="px-4 py-3 text-xs font-mono text-slate-500 dark:text-slate-400 max-w-xs truncate"><?= h($t['job_class']) ?></td>
              <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300"><?= h($t['cron_expression']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $t['status']==='active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-slate-400' ?>">
                  <?= h($t['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= $t['last_run_at'] > 0 ? formatDate((int)$t['last_run_at']) : '<span class="italic">Never</span>' ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button onclick="toggleTask(<?= $t['id'] ?>, this)"
                    class="text-xs px-2.5 py-1 rounded-lg border font-medium transition-colors
                      <?= $t['status']==='active' ? 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100' ?>">
                    <?= $t['status']==='active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                  <button onclick="if(confirm('Hapus tugas ini?')) window.location='?page=scheduler&action=delete&id=<?= $t['id'] ?>'"
                    class="text-slate-400 hover:text-red-600 transition-colors" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    async function toggleTask(id, btn) {
      const r = await fetch('?page=scheduler&action=toggle', {method:'POST',body:new URLSearchParams({id}),headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d = await r.json();
      location.reload();
    }
    </script>
    <?php
}

// ─── SECURITY KEYS ───────────────────────────────────────────────────
function renderSecurityKeysPage(array $keys): void {
    ?>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">RSA Key Manager</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($keys) ?> key pairs terdaftar</p>
      </div>
    </div>

    <!-- Generate Form -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-5 shadow-sm mb-6">
      <h3 class="font-semibold text-slate-900 dark:text-white mb-4">Generate RSA Key Pair</h3>
      <?php if (!function_exists('openssl_pkey_new')): ?>
      <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
        ⚠️ OpenSSL extension tidak tersedia. Aktifkan <code>ext-openssl</code> di PHP untuk menggunakan fitur ini.
      </div>
      <?php else: ?>
      <form method="POST" action="?page=security_keys&action=generate" class="flex gap-3 flex-wrap">
        <input type="text" name="identifier" required placeholder="Nama/ID key (contoh: main_key)"
          class="px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
        <input type="text" name="description" placeholder="Deskripsi (opsional)"
          class="flex-1 px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
          Generate RSA 2048
        </button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Keys List -->
    <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Identifier</th>
              <th class="px-4 py-3 text-left">Deskripsi</th>
              <th class="px-4 py-3 text-left">Public Key (preview)</th>
              <th class="px-4 py-3 text-left">Dibuat</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($keys)): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 italic">Belum ada key pair.</td></tr>
            <?php else: foreach ($keys as $k): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white font-mono"><?= h($k['identifier']) ?></td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?= h($k['description'] ?? '—') ?></td>
              <td class="px-4 py-3 text-xs font-mono text-slate-400 max-w-xs truncate"><?= h($k['public_key_preview'] ?? '') ?>...</td>
              <td class="px-4 py-3 text-xs text-slate-400 font-mono"><?= formatDate((int)$k['created_at']) ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a href="?page=security_keys&action=download&id=<?= $k['id'] ?>&type=public" class="text-xs px-2 py-1 rounded border border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 font-medium transition-colors" title="Download Public Key">Public</a>
                  <a href="?page=security_keys&action=download&id=<?= $k['id'] ?>&type=private" class="text-xs px-2 py-1 rounded border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 font-medium transition-colors" title="Download Private Key">Private</a>
                  <button onclick="if(confirm('Hapus key pair ini?')) window.location='?page=security_keys&action=delete&id=<?= $k['id'] ?>'"
                    class="text-slate-400 hover:text-red-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}

// ─── SETTINGS PAGE ───────────────────────────────────────────────────
function renderSettingsPage(array $settings): void {
    ?>
    <div class="max-w-2xl">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Pengaturan Aplikasi</h1>

      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">

        <!-- Tabs -->
        <div class="flex border-b border-slate-200 dark:border-gray-700">
          <?php foreach (['general'=>'Umum','email'=>'Email (SMTP)','telegram'=>'Telegram'] as $tab=>$label): ?>
          <button onclick="switchTab('<?= $tab ?>')" id="tab-<?= $tab ?>"
            class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors
              <?= $tab==='general' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">
            <?= $label ?>
          </button>
          <?php endforeach; ?>
        </div>

        <form method="POST" action="?page=settings" class="p-6">

          <!-- General Tab -->
          <div id="panel-general" class="tab-panel space-y-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Aplikasi</label>
              <input type="text" name="app_name" value="<?= h($settings['app_name'] ?? 'Assist Gateway') ?>"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tema Default</label>
              <select name="theme" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                <option value="light" <?= ($settings['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Zona Waktu</label>
              <select name="timezone" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                <?php
                $cur = $settings['timezone'] ?? 'Asia/Jakarta';
                $tzs = ['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','Asia/Singapore','America/New_York','Europe/London','UTC'];
                foreach ($tzs as $tz): ?>
                <option value="<?= $tz ?>" <?= $cur === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Email Tab -->
          <div id="panel-email" class="tab-panel hidden space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= h($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com"
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= h($settings['smtp_port'] ?? '587') ?>"
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Username</label>
              <input type="text" name="smtp_user" value="<?= h($settings['smtp_user'] ?? '') ?>"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Password</label>
              <input type="password" name="smtp_pass" value="<?= h($settings['smtp_pass'] ?? '') ?>"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">From Email</label>
              <input type="email" name="smtp_from" value="<?= h($settings['smtp_from'] ?? '') ?>" placeholder="noreply@example.com"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
            </div>
          </div>

          <!-- Telegram Tab -->
          <div id="panel-telegram" class="tab-panel hidden space-y-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Telegram Bot Token</label>
              <input type="text" name="telegram_token" value="<?= h($settings['telegram_token'] ?? '') ?>" placeholder="123456789:AAH..."
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
              <p class="mt-1 text-xs text-slate-400">Dapatkan token dari @BotFather di Telegram</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Default Chat ID</label>
              <input type="text" name="telegram_chatid" value="<?= h($settings['telegram_chatid'] ?? '') ?>" placeholder="-100123456789"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
            </div>
          </div>

          <div class="pt-5 border-t border-slate-100 dark:border-gray-700 mt-5">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
              Simpan Pengaturan
            </button>
          </div>
        </form>
      </div>
    </div>

    <script>
    function switchTab(tab) {
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
      document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-blue-600','text-blue-600','dark:text-blue-400');
        b.classList.add('border-transparent','text-slate-500');
      });
      document.getElementById('panel-'+tab).classList.remove('hidden');
      const btn = document.getElementById('tab-'+tab);
      btn.classList.add('border-blue-600','text-blue-600','dark:text-blue-400');
      btn.classList.remove('border-transparent','text-slate-500');
    }
    </script>
    <?php
}

// ─── PROFILE PAGE ────────────────────────────────────────────────────
function renderProfilePage(array $user): void {
    ?>
    <div class="max-w-xl">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Profil Saya</h1>
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
        <div class="flex items-center gap-4 mb-6 pb-5 border-b border-slate-100 dark:border-gray-700">
          <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xl font-bold">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
          <div>
            <div class="font-bold text-slate-900 dark:text-white"><?= h($user['name']) ?></div>
            <div class="text-sm text-slate-500">@<?= h($user['username']) ?></div>
            <span class="mt-1 inline-block px-2 py-0.5 text-xs rounded-full font-semibold bg-indigo-100 text-indigo-700"><?= h($user['role']) ?></span>
          </div>
        </div>

        <form method="POST" action="?page=profile" class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Lengkap</label>
            <input type="text" name="name" required value="<?= h($user['name']) ?>"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tema Tampilan</label>
            <select name="theme" class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
              <option value="auto" <?= $user['theme']==='auto' ? 'selected' : '' ?>>Auto (Ikuti Pengaturan Global)</option>
              <option value="light" <?= $user['theme']==='light' ? 'selected' : '' ?>>Light</option>
              <option value="dark" <?= $user['theme']==='dark' ? 'selected' : '' ?>>Dark</option>
            </select>
          </div>
          <div class="border-t border-slate-100 dark:border-gray-700 pt-4">
            <p class="text-xs text-slate-500 mb-3 font-semibold uppercase tracking-wider">Ubah Password (opsional)</p>
            <input type="password" name="password" placeholder="Password baru"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 mb-3">
            <input type="password" name="password_confirmation" placeholder="Konfirmasi password baru"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div class="pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
              Simpan Perubahan
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-5 shadow-sm mt-4">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Informasi Akun</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between text-slate-600 dark:text-slate-400"><span>Username</span><span class="font-mono font-semibold"><?= h($user['username']) ?></span></div>
          <div class="flex justify-between text-slate-600 dark:text-slate-400"><span>Dibuat</span><span class="font-mono"><?= formatDate((int)$user['created_at']) ?></span></div>
          <div class="flex justify-between text-slate-600 dark:text-slate-400"><span>Login Terakhir</span><span class="font-mono"><?= $user['last_login_at'] > 0 ? formatDate((int)$user['last_login_at']) : '—' ?></span></div>
        </div>
      </div>
    </div>
    <?php
}

// ─── ROLES PAGE ──────────────────────────────────────────────────────
function renderRolesPage(array $roles): void {
    ?>
    <div class="max-w-2xl">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Master Peran (Roles)</h1>
      </div>

      <!-- Add Role Form -->
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl p-5 shadow-sm mb-6">
        <h3 class="font-semibold text-slate-900 dark:text-white mb-4">Tambah Peran Baru</h3>
        <form method="POST" action="?page=roles&action=add" class="flex gap-3 flex-wrap">
          <input type="text" name="name" required placeholder="Nama peran"
            class="px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          <input type="text" name="slug" required placeholder="slug (lowercase)"
            class="px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm font-mono focus:outline-none focus:border-blue-500">
          <input type="text" name="description" placeholder="Deskripsi (opsional)"
            class="flex-1 px-3 py-2 bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500">
          <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">Tambah</button>
        </form>
      </div>

      <!-- Roles List -->
      <div class="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500">
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Slug</th>
              <th class="px-4 py-3 text-left">Deskripsi</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php foreach ($roles as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= h($r['name']) ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-transparent">
                <span class="bg-slate-100 dark:bg-gray-700 px-2 py-0.5 rounded"><?= h($r['slug']) ?></span>
              </td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= h($r['description'] ?? '—') ?></td>
              <td class="px-4 py-3 text-right">
                <?php if (!in_array($r['slug'], ['admin','user'])): ?>
                <button onclick="if(confirm('Hapus peran ini?')) window.location='?page=roles&action=delete&id=<?= $r['id'] ?>'"
                  class="text-slate-400 hover:text-red-600 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <?php else: ?>
                <span class="text-[10px] text-slate-300 italic">protected</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}

// ─── PAGINATION HELPER ───────────────────────────────────────────────
function renderPagination(int $current, int $total, string $baseUrl): void {
    if ($total <= 1) return;
    ?>
    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-gray-700">
      <span class="text-xs text-slate-400">Halaman <?= $current ?> dari <?= $total ?></span>
      <div class="flex gap-1">
        <?php if ($current > 1): ?>
        <a href="<?= $baseUrl ?>&p=<?= $current - 1 ?>" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
          &larr; Sebelumnya
        </a>
        <?php endif; ?>
        <?php
        $start = max(1, $current - 2);
        $end   = min($total, $current + 2);
        for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= $baseUrl ?>&p=<?= $i ?>" class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
          <?= $i === $current ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white dark:bg-gray-800 border-slate-200 dark:border-gray-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-gray-750' ?>">
          <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($current < $total): ?>
        <a href="<?= $baseUrl ?>&p=<?= $current + 1 ?>" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
          Berikutnya &rarr;
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php
}
