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
 *  2. Pastikan database MySQL sudah tersedia & tabel sudah ada
 *  3. Buka di browser → langsung login
 *
 * Database : MySQL 10.1.11.21 / assist_gw
 * User     : AssistGateway
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

// ─── CEK KONEKSI DATABASE ───────────────────────────────────────────
// Database & tabel sudah ada di server MySQL — tidak perlu instalasi.
// Fungsi ini hanya memverifikasi koneksi berhasil; jika gagal tampilkan
// halaman error yang jelas agar mudah di-debug.
function checkDbConnection(): void {
    try {
        DB::get(); // Coba buka koneksi
    } catch (PDOException $e) {
        $host = DB_HOST;
        $db   = DB_NAME;
        $user = DB_USER;
        $msg  = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        http_response_code(503);
        echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Error — Assist Gateway</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-lg border border-red-100 max-w-lg w-full p-8">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
      </div>
      <h1 class="text-lg font-bold text-red-700">Koneksi Database Gagal</h1>
    </div>
    <p class="text-sm text-gray-600 mb-4">Aplikasi tidak dapat terhubung ke server MySQL. Periksa konfigurasi berikut:</p>
    <div class="bg-gray-50 rounded-lg p-4 text-sm font-mono space-y-1 mb-4">
      <div><span class="text-gray-400">Host :</span> <span class="text-gray-800">{$host}</span></div>
      <div><span class="text-gray-400">Database :</span> <span class="text-gray-800">{$db}</span></div>
      <div><span class="text-gray-400">Username :</span> <span class="text-gray-800">{$user}</span></div>
    </div>
    <div class="bg-red-50 rounded-lg p-3 text-xs text-red-700 font-mono break-all">{$msg}</div>
    <p class="mt-4 text-xs text-gray-400">Pastikan server MySQL aktif, host dapat dijangkau, dan user memiliki akses ke database.</p>
  </div>
</body>
</html>
HTML;
        exit;
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
checkDbConnection(); // Verifikasi koneksi MySQL — tidak install tabel

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

    case 'trx_danamon':
        handleTrxDanamon(); break;

    case 'trx_dwallet':
        handleTrxDwallet(); break;

    case 'trx_pulsa':
        handleTrxPulsa(); break;

    case 'trx_qris':
        handleTrxQris(); break;

    case 'trx_create':
        handleTrxCreate(); break;

    case 'trx_approve':
        handleTrxApprove(); break;

    case 'danamon_configs':
        handleDanamonConfigs(); break;

    case 'api_json':
        handleApiJson(); break;

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
// Tabel `security_keys` production dipakai modul lain (kolom berbeda).
// App ini memakai tabel `assist_security_keys` khusus RSA Key Manager.
function ensureAssistSecurityKeysTable(): void {
    DB::get()->exec("CREATE TABLE IF NOT EXISTS `assist_security_keys` (
        `id`          int unsigned    NOT NULL AUTO_INCREMENT,
        `identifier`  varchar(100)    NOT NULL,
        `description` text            DEFAULT NULL,
        `public_key`  text            NOT NULL,
        `private_key` text            NOT NULL,
        `created_at`  int unsigned    NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_identifier` (`identifier`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function handleSecurityKeys(): void {
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); }

    ensureAssistSecurityKeysTable(); // buat tabel jika belum ada

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
            DB::exec("REPLACE INTO `assist_security_keys` (identifier,description,public_key,private_key,created_at) VALUES (?,?,?,?,?)",
                [$identifier, $description, $publicKey, $privateKey, time()]);
            logActivity('KEY_GENERATED', ['identifier' => $identifier]);
            flash('success', "RSA Key '{$identifier}' berhasil dibuat.");
        } else {
            flash('error', 'Gagal generate RSA key. Pastikan OpenSSL tersedia.');
        }
        redirect('?page=security_keys');
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        DB::exec("DELETE FROM `assist_security_keys` WHERE id=?", [(int)$_GET['id']]);
        flash('success', 'Key dihapus.');
        redirect('?page=security_keys');
    }

    if ($action === 'download' && isset($_GET['id'])) {
        $key  = DB::row("SELECT * FROM `assist_security_keys` WHERE id=?", [(int)$_GET['id']]);
        $type = $_GET['type'] ?? 'public';
        if ($key) {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$key['identifier']}_{$type}.pem\"");
            echo $type === 'private' ? $key['private_key'] : $key['public_key'];
            exit;
        }
    }

    $keys = DB::query("SELECT id,identifier,description,created_at,SUBSTR(public_key,1,60) as public_key_preview FROM `assist_security_keys` ORDER BY id DESC");
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
// TRANSAKSI HANDLERS
// ══════════════════════════════════════════════════════════════════════

// ─── HELPER: format rupiah ───────────────────────────────────────────
function rupiah(mixed $n, bool $compact = false): string {
    $n = (float)$n;
    if ($compact && abs($n) >= 1_000_000_000) return 'Rp ' . number_format($n / 1_000_000_000, 1) . 'M';
    if ($compact && abs($n) >= 1_000_000)     return 'Rp ' . number_format($n / 1_000_000, 1) . 'Jt';
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ─── HELPER: status badge ───────────────────────────────────────────
function statusBadge(string $status, array $map = []): string {
    $default = [
        'SUCCESS' => 'green', 'success' => 'green', 'S' => 'green',
        'PENDING' => 'amber', 'pending' => 'amber', 'P' => 'amber',
        'FAILED'  => 'red',   'failed'  => 'red',   'G' => 'red',
        'PROCESSED'=> 'blue', 'processed'=> 'blue',
        'active'  => 'green', 'inactive'=> 'slate',
        'sent'    => 'green', 'queued'  => 'amber',
    ];
    $colorMap = array_merge($default, $map);
    $color = $colorMap[$status] ?? 'slate';
    $label = htmlspecialchars($status, ENT_QUOTES);
    return "<span class=\"px-2 py-0.5 text-xs font-semibold rounded-full bg-{$color}-100 text-{$color}-700 dark:bg-{$color}-900/30 dark:text-{$color}-300\">{$label}</span>";
}

// ── TRANSAKSI BANK (danamon_transactions) ────────────────────────────
function handleTrxDanamon(): void {
    requireLogin();

    $page_n  = max(1, (int)($_GET['p']      ?? 1));
    $search  = trim($_GET['search']          ?? '');
    $status  = trim($_GET['status']          ?? '');
    $proto   = trim($_GET['protocol']        ?? '');
    $dateFrom= trim($_GET['date_from']       ?? '');
    $dateTo  = trim($_GET['date_to']         ?? '');
    $limit   = 20;
    $offset  = ($page_n - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = '(faktur LIKE ? OR ref_no LIKE ? OR source_account LIKE ? OR destination_account LIKE ?)';
        $params   = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    if ($proto) {
        $where[]  = 'protocol = ?';
        $params[] = $proto;
    }
    if ($dateFrom) {
        $where[]  = 'created_at >= ?';
        $params[] = strtotime($dateFrom . ' 00:00:00');
    }
    if ($dateTo) {
        $where[]  = 'created_at <= ?';
        $params[] = strtotime($dateTo . ' 23:59:59');
    }

    $whereStr = implode(' AND ', $where);
    $total    = (int)(DB::row("SELECT COUNT(*) as c FROM danamon_transactions WHERE {$whereStr}", $params)['c'] ?? 0);
    $rows     = DB::query("SELECT * FROM danamon_transactions WHERE {$whereStr} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);

    // Summary stats
    $summary  = DB::query("SELECT status, COUNT(*) as cnt, SUM(amount) as total FROM danamon_transactions GROUP BY status ORDER BY cnt DESC");
    $protocols= DB::query("SELECT DISTINCT protocol FROM danamon_transactions ORDER BY protocol");

    $totalPages = max(1, (int)ceil($total / $limit));

    // Detail view
    if (isset($_GET['id'])) {
        $row = DB::row("SELECT * FROM danamon_transactions WHERE id=?", [(int)$_GET['id']]);
        renderLayout('Detail Transaksi Bank', function() use ($row) { renderTrxDanamonDetail($row); });
        return;
    }

    renderLayout('Transaksi Bank', function() use ($rows,$total,$page_n,$totalPages,$summary,$protocols,$search,$status,$proto,$dateFrom,$dateTo,$limit) {
        renderTrxDanamonPage($rows,$total,$page_n,$totalPages,$summary,$protocols,$search,$status,$proto,$dateFrom,$dateTo,$limit);
    });
}

// ── D-WALLET ─────────────────────────────────────────────────────────
function handleTrxDwallet(): void {
    requireLogin();

    $tab    = $_GET['tab']  ?? 'wallets';
    $page_n = max(1, (int)($_GET['p'] ?? 1));
    $search = trim($_GET['search'] ?? '');
    $limit  = 20;
    $offset = ($page_n - 1) * $limit;

    if ($tab === 'wallets') {
        $where  = '1=1';
        $params = [];
        if ($search) {
            $where    = '(customer_code LIKE ? OR account_number LIKE ?)';
            $params   = ["%$search%", "%$search%"];
        }
        $total      = (int)(DB::row("SELECT COUNT(*) as c FROM dwallet_wallets WHERE {$where}", $params)['c'] ?? 0);
        $rows       = DB::query("SELECT * FROM dwallet_wallets WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);
        $totalPages = max(1, (int)ceil($total / $limit));
        $totalBalance = (float)(DB::row("SELECT SUM(balance) as s FROM dwallet_wallets WHERE status='active'")['s'] ?? 0);

        renderLayout('D-Wallet', function() use ($rows,$total,$page_n,$totalPages,$search,$totalBalance,$tab,$limit) {
            renderDwalletPage($rows,$total,$page_n,$totalPages,$search,$totalBalance,$tab,$limit,'wallets');
        });
        return;
    }

    if ($tab === 'transactions') {
        $jenis  = trim($_GET['jenis'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $where  = ['1=1'];
        $params = [];
        if ($search) {
            $where[]  = '(faktur LIKE ? OR kode_sender LIKE ? OR kode_receiver LIKE ? OR keterangan LIKE ?)';
            $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
        }
        if ($jenis)  { $where[] = 'jenis = ?';  $params[] = $jenis; }
        if ($status) { $where[] = 'status = ?'; $params[] = $status; }
        $whereStr   = implode(' AND ', $where);
        $total      = (int)(DB::row("SELECT COUNT(*) as c FROM dwallet_transactions WHERE {$whereStr}", $params)['c'] ?? 0);
        $rows       = DB::query("SELECT * FROM dwallet_transactions WHERE {$whereStr} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);
        $totalPages = max(1, (int)ceil($total / $limit));
        $sumByJenis = DB::query("SELECT jenis, COUNT(*) as cnt, SUM(gross_amount) as total FROM dwallet_transactions GROUP BY jenis");

        renderLayout('D-Wallet — Transaksi', function() use ($rows,$total,$page_n,$totalPages,$search,$jenis,$status,$sumByJenis,$tab,$limit) {
            renderDwalletPage($rows,$total,$page_n,$totalPages,$search,0,$tab,$limit,'transactions',$jenis,$status,$sumByJenis);
        });
        return;
    }

    if ($tab === 'journal') {
        $where  = '1=1';
        $params = [];
        if ($search) {
            $where    = '(faktur LIKE ? OR rekening LIKE ? OR keterangan LIKE ?)';
            $params   = ["%$search%","%$search%","%$search%"];
        }
        $total      = (int)(DB::row("SELECT COUNT(*) as c FROM dwallet_journal WHERE {$where}", $params)['c'] ?? 0);
        $rows       = DB::query("SELECT * FROM dwallet_journal WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);
        $totalPages = max(1, (int)ceil($total / $limit));

        renderLayout('D-Wallet — Jurnal', function() use ($rows,$total,$page_n,$totalPages,$search,$tab,$limit) {
            renderDwalletPage($rows,$total,$page_n,$totalPages,$search,0,$tab,$limit,'journal');
        });
        return;
    }

    redirect('?page=trx_dwallet&tab=wallets');
}

// ── PENJUALAN PULSA (pulsa_penjualan) ────────────────────────────────
function handleTrxPulsa(): void {
    requireLogin();

    $page_n  = max(1, (int)($_GET['p']       ?? 1));
    $search  = trim($_GET['search']           ?? '');
    $status  = trim($_GET['status']           ?? '');
    $dateFrom= trim($_GET['date_from']        ?? '');
    $dateTo  = trim($_GET['date_to']          ?? '');
    $limit   = 20;
    $offset  = ($page_n - 1) * $limit;

    // Gunakan tabel utama pulsa_penjualan (tanpa partisi tahun)
    $tbl = 'pulsa_penjualan';

    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[]  = '(Kode LIKE ? OR HP LIKE ? OR KodeCustomer LIKE ? OR TrxID LIKE ?)';
        $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
    }
    if ($status) {
        $where[]  = 'Status = ?';
        $params[] = $status;
    }
    if ($dateFrom) {
        $where[]  = 'Tgl >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[]  = 'Tgl <= ?';
        $params[] = $dateTo;
    }

    $whereStr = implode(' AND ', $where);
    $total    = (int)(DB::row("SELECT COUNT(*) as c FROM `{$tbl}` WHERE {$whereStr}", $params)['c'] ?? 0);
    $rows     = DB::query("SELECT ID,Tgl,KodeCustomer,JenisTrx,Kode,HP,HJ_Nasabah,HJ,HB,Status,SN,TrxID,DateTime FROM `{$tbl}` WHERE {$whereStr} ORDER BY ID DESC LIMIT {$limit} OFFSET {$offset}", $params);

    $summary  = DB::query("SELECT Status, COUNT(*) as cnt, SUM(HJ) as total_hj FROM `{$tbl}` GROUP BY Status");
    $totalPages = max(1, (int)ceil($total / $limit));

    renderLayout('Penjualan Pulsa', function() use ($rows,$total,$page_n,$totalPages,$summary,$search,$status,$dateFrom,$dateTo,$tbl,$limit) {
        renderTrxPulsaPage($rows,$total,$page_n,$totalPages,$summary,$search,$status,$dateFrom,$dateTo,$tbl,$limit);
    });
}

// ── TRANSAKSI QRIS ───────────────────────────────────────────────────
function handleTrxQris(): void {
    requireLogin();

    $page_n  = max(1, (int)($_GET['p']      ?? 1));
    $search  = trim($_GET['search']          ?? '');
    $status  = trim($_GET['status']          ?? '');
    $dateFrom= trim($_GET['date_from']       ?? '');
    $dateTo  = trim($_GET['date_to']         ?? '');
    $limit   = 20;
    $offset  = ($page_n - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[]  = '(external_id LIKE ? OR reference_no LIKE ? OR partner_reference_no LIKE ? OR merchant_id LIKE ?)';
        $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
    }
    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    if ($dateFrom) {
        $where[]  = 'DATE(transaction_date) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[]  = 'DATE(transaction_date) <= ?';
        $params[] = $dateTo;
    }

    $whereStr   = implode(' AND ', $where);
    $total      = (int)(DB::row("SELECT COUNT(*) as c FROM qris_transactions WHERE {$whereStr}", $params)['c'] ?? 0);
    $rows       = DB::query("SELECT * FROM qris_transactions WHERE {$whereStr} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);
    $summary    = DB::query("SELECT status, COUNT(*) as cnt, SUM(amount) as total FROM qris_transactions GROUP BY status ORDER BY cnt DESC");
    $totalPages = max(1, (int)ceil($total / $limit));

    renderLayout('Transaksi QRIS', function() use ($rows,$total,$page_n,$totalPages,$summary,$search,$status,$dateFrom,$dateTo,$limit) {
        renderTrxQrisPage($rows,$total,$page_n,$totalPages,$summary,$search,$status,$dateFrom,$dateTo,$limit);
    });
}

// ── API JSON (Auto-fill data endpoint) ───────────────────────────────
function handleApiJson(): void {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');

    $action = trim($_GET['action'] ?? '');

    try {
        switch ($action) {

            // ── Semua customer aktif ──────────────────────────────────
            case 'customers':
                $rows = DB::query(
                    "SELECT id, name, KodePro, user_h2h, phone, email, Kode, TipeAkun
                     FROM customer
                     WHERE status = 'active'
                     ORDER BY name ASC",
                    []
                );
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // ── Detail satu customer (by id) ──────────────────────────
            case 'customer_detail':
                $id  = (int)($_GET['id'] ?? 0);
                $row = DB::row(
                    "SELECT id, name, KodePro, user_h2h, phone, email, Kode, TipeAkun, limit_ppob
                     FROM customer WHERE id = ? LIMIT 1",
                    [$id]
                );
                if (!$row) { echo json_encode(['success' => false, 'message' => 'Customer tidak ditemukan']); break; }
                echo json_encode(['success' => true, 'data' => $row]);
                break;

            // ── Rekening Danamon (distinct source_account) ────────────
            case 'danamon_accounts':
                $rows = DB::query(
                    "SELECT DISTINCT source_account AS account
                     FROM danamon_transactions
                     WHERE source_account IS NOT NULL AND source_account <> ''
                     ORDER BY source_account ASC
                     LIMIT 200",
                    []
                );
                echo json_encode(['success' => true, 'data' => array_column($rows, 'account')]);
                break;

            // ── DWallet wallets (customer_code + account_number) ──────
            case 'dwallet_wallets':
                $rows = DB::query(
                    "SELECT w.id, w.customer_code, w.account_number, w.balance, w.status,
                            COALESCE(c.name,'') AS customer_name
                     FROM dwallet_wallets w
                     LEFT JOIN customer c ON c.KodePro = w.customer_code
                     WHERE w.status = 'active'
                     ORDER BY w.customer_code ASC
                     LIMIT 500",
                    []
                );
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // ── QRIS merchants (distinct merchant_id) ─────────────────
            case 'qris_merchants':
                $rows = DB::query(
                    "SELECT DISTINCT merchant_id AS merchant_id, '' AS terminal_id
                     FROM qris_transactions
                     WHERE merchant_id IS NOT NULL AND merchant_id <> ''
                     ORDER BY merchant_id ASC
                     LIMIT 200",
                    []
                );
                // Juga ambil terminal_id per merchant
                $merchants = [];
                foreach ($rows as $r) {
                    $term = DB::row(
                        "SELECT terminal_id FROM qris_transactions
                         WHERE merchant_id = ? AND terminal_id IS NOT NULL AND terminal_id <> ''
                         ORDER BY created_at DESC LIMIT 1",
                        [$r['merchant_id']]
                    );
                    $merchants[] = [
                        'merchant_id' => $r['merchant_id'],
                        'terminal_id' => $term['terminal_id'] ?? '',
                    ];
                }
                echo json_encode(['success' => true, 'data' => $merchants]);
                break;

            // ── Produk Pulsa (dari stock table, fallback ke pulsa history) ──
            case 'pulsa_products':
                $products = [];
                // Coba dari tabel stock (master produk)
                try {
                    $stockRows = DB::query(
                        "SELECT Kode, KodeProduk, Nama, HB, HJ, Margin, Tipe, Kategori, Status_Aktif
                         FROM stock
                         WHERE Status_Aktif IN ('1','A','Y','0') AND Kode IS NOT NULL AND Kode <> ''
                         ORDER BY Kategori, Kode ASC
                         LIMIT 500",
                        []
                    );
                    if ($stockRows) {
                        foreach ($stockRows as $s) {
                            $products[] = [
                                'Kode'      => $s['Kode'],
                                'Nama'      => $s['Nama'] ?? $s['Kode'],
                                'JenisTrx'  => '', // kosong dari stock, akan di-map
                                'HB'        => (float)($s['HB'] ?? 0),
                                'HJ'        => (float)($s['HJ'] ?? 0),
                                'Kategori'  => $s['Kategori'] ?? '',
                                'Tipe'      => $s['Tipe'] ?? '',
                                'src'       => 'stock',
                            ];
                        }
                    }
                } catch (Throwable $e) { /* tabel stock mungkin kosong */ }

                // Ambil dari tabel utama pulsa_penjualan (tanpa tahun)
                $historyProducts = [];
                try {
                    $tbl2 = 'pulsa_penjualan';
                    $hRows = DB::query(
                        "SELECT Kode, JenisTrx,
                                MAX(HB) AS HB, MAX(HJ) AS HJ, MAX(HJ_Nasabah) AS HJ_Nasabah,
                                COUNT(*) AS cnt
                         FROM `{$tbl2}`
                         WHERE Kode IS NOT NULL AND Kode <> ''
                         GROUP BY Kode, JenisTrx
                         ORDER BY cnt DESC
                         LIMIT 300",
                        []
                    );
                    foreach ($hRows as $h) {
                            $key = $h['Kode'].'|'.$h['JenisTrx'];
                            if (!isset($historyProducts[$key])) {
                                $historyProducts[$key] = [
                                    'Kode'      => $h['Kode'],
                                    'Nama'      => $h['Kode'],
                                    'JenisTrx'  => $h['JenisTrx'] ?? '',
                                    'HB'        => (float)($h['HB'] ?? 0),
                                    'HJ'        => (float)($h['HJ'] ?? 0),
                                    'HJ_Nasabah'=> (float)($h['HJ_Nasabah'] ?? 0),
                                    'Kategori'  => '',
                                    'Tipe'      => '',
                                    'src'       => 'history',
                                    'cnt'       => (int)($h['cnt'] ?? 0),
                                ];
                            } else {
                                // Update cnt dari tahun lebih baru
                                $historyProducts[$key]['cnt'] += (int)($h['cnt'] ?? 0);
                            }
                        }
                } catch (Throwable $e) { /* tabel mungkin kosong */ }

                // Merge: history products enriched dengan nama dari stock
                $stockMap = [];
                foreach ($products as $p) { $stockMap[$p['Kode']] = $p; }

                $merged = [];
                foreach ($historyProducts as $key => $h) {
                    $kode = $h['Kode'];
                    $merged[$key] = [
                        'Kode'      => $kode,
                        'Nama'      => isset($stockMap[$kode]) ? $stockMap[$kode]['Nama'] : $kode,
                        'JenisTrx'  => $h['JenisTrx'],
                        'HB'        => $h['HB'] > 0 ? $h['HB'] : (isset($stockMap[$kode]) ? $stockMap[$kode]['HB'] : 0),
                        'HJ'        => $h['HJ'] > 0 ? $h['HJ'] : (isset($stockMap[$kode]) ? $stockMap[$kode]['HJ'] : 0),
                        'HJ_Nasabah'=> $h['HJ_Nasabah'] ?? 0,
                        'Kategori'  => isset($stockMap[$kode]) ? $stockMap[$kode]['Kategori'] : '',
                        'cnt'       => $h['cnt'],
                        'src'       => 'history',
                    ];
                }

                // Tambah produk dari stock yang tidak ada di history
                foreach ($products as $p) {
                    $found = false;
                    foreach ($merged as $m) { if ($m['Kode'] === $p['Kode']) { $found=true; break; } }
                    if (!$found) {
                        $merged[$p['Kode'].'|'] = $p;
                        $merged[$p['Kode'].'|']['JenisTrx'] = '';
                        $merged[$p['Kode'].'|']['cnt'] = 0;
                    }
                }

                // Sort by cnt desc (produk paling sering dipakai di atas)
                usort($merged, function($a,$b){ return ($b['cnt']??0) - ($a['cnt']??0); });

                echo json_encode(['success' => true, 'data' => array_values($merged)]);
                break;

            // ── Supplier list (untuk dropdown supplier pulsa) ─────────
            case 'suppliers':
                $rows = DB::query(
                    "SELECT Kode, Nama FROM supplier
                     WHERE Status = '1' AND Kode IS NOT NULL AND Kode <> ''
                     ORDER BY Kode ASC
                     LIMIT 100",
                    []
                );
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── BUAT TRANSAKSI BARU ───────────────────────────────────────────────
function handleTrxCreate(): void {
    requireLogin();

    $type  = trim($_POST['trx_type'] ?? $_GET['type'] ?? 'danamon');
    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trx_type'])) {
        $ts   = time();
        $rand = random_int(1000, 9999);

        try {
            switch ($type) {
                // ── Danamon ────────────────────────────────────────────
                case 'danamon':
                    $protocol = trim($_POST['protocol'] ?? 'INQUIRY');
                    $srcAcc   = trim($_POST['source_account'] ?? '');
                    $dstAcc   = trim($_POST['destination_account'] ?? '');
                    $amount   = (float)($_POST['amount'] ?? 0);
                    $refNo    = trim($_POST['ref_no'] ?? '');
                    $remark   = trim($_POST['remark'] ?? '');

                    if (!$srcAcc) throw new RuntimeException('Rekening asal wajib diisi.');

                    $prefix  = match($protocol) {
                        'INQUIRY'        => 'INQ',
                        'TRANSFER_INTRA' => 'TRF-INTRA',
                        'TRANSFER_INTER' => 'TRF-INTER',
                        default          => 'TRX',
                    };
                    $faktur  = "{$prefix}-{$ts}-{$rand}";
                    $payload = json_encode([
                        'account_number'     => $srcAcc,
                        'partnerReferenceNo' => $refNo ?: $faktur,
                        'amount_value'       => $amount,
                        'beneficiaryAccountNo' => $dstAcc,
                        'sourceAccountNo'    => $srcAcc,
                        'remark'             => $remark,
                    ]);

                    DB::exec(
                        "INSERT INTO danamon_transactions (faktur,ref_no,module,protocol,source_account,destination_account,amount,status,req_payload,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [$faktur, $refNo ?: $faktur, 'danamon', $protocol, $srcAcc, $dstAcc ?: null, $amount, 'PENDING', $payload, $ts, $ts]
                    );
                    $success = "Transaksi Bank berhasil dibuat dengan faktur <strong>{$faktur}</strong>, status PENDING menunggu persetujuan.";
                    logActivity('trx_create', ['table'=>'danamon_transactions','faktur'=>$faktur,'protocol'=>$protocol]);
                    break;

                // ── D-Wallet ───────────────────────────────────────────
                case 'dwallet':
                    $jenis     = trim($_POST['jenis'] ?? 'CASHIN');
                    $sender    = trim($_POST['kode_sender'] ?? '');
                    $receiver  = trim($_POST['kode_receiver'] ?? '');
                    $amount    = (float)($_POST['amount'] ?? 0);
                    $fee       = (float)($_POST['fee'] ?? 0);
                    $keterangan = trim($_POST['keterangan'] ?? '');

                    if ($amount <= 0) throw new RuntimeException('Nominal harus lebih dari 0.');
                    if (!$sender && !$receiver) throw new RuntimeException('Kode pengirim atau penerima wajib diisi.');

                    $faktur  = "DW-{$ts}-{$rand}";
                    $gross   = $amount + $fee;
                    $nowDt   = date('Y-m-d H:i:s');

                    DB::exec(
                        "INSERT INTO dwallet_transactions (faktur,jenis,kode_sender,kode_receiver,amount,fee,gross_amount,keterangan,status,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [$faktur, $jenis, $sender ?: null, $receiver ?: null, $amount, $fee, $gross, $keterangan ?: null, 'P', $nowDt, $nowDt]
                    );
                    $success = "Transaksi D-Wallet berhasil dibuat dengan faktur <strong>{$faktur}</strong>, status PENDING menunggu persetujuan.";
                    logActivity('trx_create', ['table'=>'dwallet_transactions','faktur'=>$faktur,'jenis'=>$jenis]);
                    break;

                // ── QRIS ───────────────────────────────────────────────
                case 'qris':
                    $extId     = trim($_POST['external_id'] ?? '');
                    $refNo     = trim($_POST['reference_no'] ?? '');
                    $merchantId = trim($_POST['merchant_id'] ?? '');
                    $terminalId = trim($_POST['terminal_id'] ?? '');
                    $amount    = (float)($_POST['amount'] ?? 0);
                    $currency  = trim($_POST['amount_currency'] ?? 'IDR');

                    if ($amount <= 0) throw new RuntimeException('Nominal harus lebih dari 0.');
                    if (!$merchantId) throw new RuntimeException('Merchant ID wajib diisi.');

                    $extId  = $extId ?: "QRIS-{$ts}-{$rand}";
                    $refNo  = $refNo ?: $extId;
                    $nowDt  = date('Y-m-d H:i:s');

                    DB::exec(
                        "INSERT INTO qris_transactions (external_id,reference_no,partner_reference_no,merchant_id,terminal_id,amount,amount_currency,transaction_date,status,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?)",
                        [$extId, $refNo, $refNo, $merchantId, $terminalId ?: null, $amount, $currency, $nowDt, 'PENDING', $nowDt]
                    );
                    $success = "Transaksi QRIS berhasil dibuat, status PENDING menunggu persetujuan.";
                    logActivity('trx_create', ['table'=>'qris_transactions','external_id'=>$extId,'merchant_id'=>$merchantId]);
                    break;

                // ── Penjualan Pulsa ────────────────────────────────────
                case 'pulsa':
                    $jenisTrx      = trim($_POST['JenisTrx']      ?? '');
                    $kode          = trim($_POST['Kode']           ?? '');
                    $hp            = trim($_POST['HP']             ?? '');
                    $hpSender      = trim($_POST['HPSender']       ?? '');
                    $kodeCustomer  = trim($_POST['KodeCustomer']   ?? '');
                    $hjNasabah     = (float)($_POST['HJ_Nasabah']  ?? 0);
                    $hj            = (float)($_POST['HJ']          ?? 0);
                    $hb            = (float)($_POST['HB']          ?? 0);
                    $supplier      = trim($_POST['Supplier']       ?? '0001');
                    $mbanking      = trim($_POST['MBanking']       ?? '0');
                    $protocol      = trim($_POST['Protocol']       ?? 'H');
                    $sender        = trim($_POST['Sender']         ?? '');
                    $jenisTrxBayar = trim($_POST['Jenis']          ?? 'P');
                    // Kolom khusus transfer (RekTujuanTFDana / PAYBIFAST / PAYTFDANA)
                    $rekTujuan       = trim($_POST['RekTujuanTFDana']      ?? '');
                    $bankTujuan      = trim($_POST['BankTujuanTFDana']     ?? '');
                    $bankNamaTujuan  = trim($_POST['BankNamaTujuanTFDana'] ?? '');
                    $namaTujuan      = trim($_POST['NamaTujuanTFDana']     ?? '');
                    $sourceAccNo     = trim($_POST['SourceAccountNo']      ?? '');
                    $transferDesc    = trim($_POST['TransferDescription']  ?? '');
                    $trxPurposeCode  = trim($_POST['TrxPurposeCode']       ?? '99');
                    $trxType         = trim($_POST['TrxType']              ?? '02');
                    // Nomor dokumen
                    $nomor           = trim($_POST['Nomor']                ?? '');
                    $seri            = trim($_POST['Seri']                 ?? '');
                    $idtrxOrder      = trim($_POST['IDTRXOrder']           ?? '');
                    $keterangan      = trim($_POST['keterangan_pulsa']     ?? '');
                    $tgl           = trim($_POST['Tgl']            ?? date('Y-m-d'));

                    if (!$hp) throw new RuntimeException('Nomor HP Tujuan wajib diisi.');
                    if (!$kode) throw new RuntimeException('Kode Produk wajib diisi.');
                    if (!$kodeCustomer) throw new RuntimeException('Kode Customer wajib diisi.');
                    if ($hjNasabah <= 0) throw new RuntimeException('HJ Nasabah (Harga Jual ke Pelanggan) harus lebih dari 0.');

                    // Gunakan tabel utama pulsa_penjualan (tanpa tahun)
                    $tblPulsa = 'pulsa_penjualan';
                    $nowTs = time();

                    // Auto-generate Nomor jika kosong: format mirip SL0xxxxx
                    if (!$nomor) {
                        $nomor = 'SL0' . substr(md5(uniqid('', true)), 0, 6);
                    }

                    // Build TRXOrder dalam format SNAP API (Danamon BiF)
                    $partnerRefNo   = $idtrxOrder ?: $nomor;
                    $amountVal      = number_format($hjNasabah, 2, '.', '');
                    $txDate         = date('Y-m-d\TH:i:sP');
                    // transferDescription: gunakan field khusus, fallback ke idtrxOrder + '-'
                    $txDesc         = $transferDesc ?: ($idtrxOrder ? $idtrxOrder . '-' : '');

                    $trxOrderPayload = json_encode([
                        'partnerReferenceNo'   => $partnerRefNo,
                        'amount'               => ['value' => $amountVal, 'currency' => 'IDR'],
                        'beneficiaryAccountName' => $namaTujuan,
                        'beneficiaryAccountNo'   => $rekTujuan,
                        'beneficiaryBankCode'    => $bankTujuan,
                        'beneficiaryBankName'    => $bankNamaTujuan,
                        'sourceAccountNo'        => $sourceAccNo,
                        'transactionDate'        => $txDate,
                        'additionalInfo'         => [
                            'transferDescription' => $txDesc,
                            'trxPurposeCode'      => $trxPurposeCode ?: '99',
                            'trxType'             => $trxType ?: '02',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                    // Build TRXOrderResponse skeleton (PENDING — akan diisi saat eksekusi ke Danamon)
                    $trxOrderResponse = json_encode([
                        'responseCode'         => '',
                        'responseMessage'      => 'PENDING',
                        'referenceNo'          => '',
                        'partnerReferenceNo'   => $partnerRefNo,
                        'amount'               => ['value' => $amountVal, 'currency' => 'IDR'],
                        'beneficiaryAccountNo' => $rekTujuan,
                        'beneficiaryBankCode'  => $bankTujuan,
                        'sourceAccountNo'      => $sourceAccNo,
                        'additionalInfo'       => [
                            'transferDescription' => $txDesc,
                            'trxPurposeCode'      => $trxPurposeCode ?: '99',
                            'bifReferenceNo'      => '',
                            'transactionDate'     => $txDate,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                    DB::exec(
                        "INSERT INTO `{$tblPulsa}`
                         (Jenis, Nomor, Tgl, KodeCustomer, MBanking, JenisTrx, Kode, HP, HPSender,
                          HJ_Nasabah, HJ, HB, BonusCustomer, Discount, Supplier, Status,
                          SN, DateTime, DateTimeClose, Protocol, IMID, Sender,
                          StatusReplay, StatusNotifikasiPending, RO, Response, ResendOrderTime,
                          TrxID, Fastpay_Ref, IDTRXOrder, IDTRXJawaban, SMSBanking, ID_SMSBanking,
                          TRXOrder, TRXOrderResponse, AdditionalData,
                          Telkom_IDPEL, BPJS_IDPEL, Speedy_IDPEL, PDAM_IDPEL, PDAM_Kota,
                          PLN_IDPEL, PLN_Nama, PLN_Token,
                          Finance_IDPEL, Finance_Kode, OpenDenom_IDPEL, OpenDenom_Kode,
                          RekTujuanTFDana, BankTujuanTFDana, NamaTujuanTFDana,
                          CustomerMasking, SolusiGagal, KonfirmasiGagal,
                          Nomor_Tagihan, Jenis_Tagihan, Seri,
                          NomorDepositCustomer, NomorDepositSupplier, is_payment)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [
                            $jenisTrxBayar, $nomor, $tgl, $kodeCustomer, $mbanking, $jenisTrx, $kode, $hp, $hpSender ?: $hp,
                            $hjNasabah, $hj, $hb, 0, 0, $supplier, 'P',
                            '', $nowTs, 0, $protocol, '', $sender ?: '',
                            '0', '0', 0, '', $nowTs,
                            '', '', $idtrxOrder ?: '', 0, '0', '',
                            $trxOrderPayload, $trxOrderResponse, '',
                            '', '', '', '', '',
                            '', '', '',
                            '', '', '', '',
                            $rekTujuan, $bankTujuan, $namaTujuan,
                            '', '', '',
                            '', 'DP', $seri,
                            '', '', 0,
                        ]
                    );
                    $success = "Penjualan Pulsa berhasil dibuat di tabel <strong>{$tblPulsa}</strong>, nomor <strong>{$nomor}</strong>, status PENDING menunggu persetujuan.";
                    logActivity('trx_create', ['table'=>$tblPulsa,'nomor'=>$nomor,'kode'=>$kode,'hp'=>$hp]);
                    break;

                default:
                    throw new RuntimeException('Jenis transaksi tidak dikenal.');
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }

    renderLayout('Buat Transaksi', function() use ($type, $error, $success) {
        renderTrxCreatePage($type, $error, $success);
    });
}

// ══════════════════════════════════════════════════════════════════════
// DANAMON CONFIG MANAGER
// ══════════════════════════════════════════════════════════════════════
function handleDanamonConfigs(): void {
    requireLogin();
    if (!isAdmin()) { flash('error', 'Akses ditolak'); redirect('?page=home'); return; }

    $action = $_GET['action'] ?? 'list';
    $method = $_SERVER['REQUEST_METHOD'];

    // ── Aktivasi / Non-aktif ──
    if ($action === 'toggle' && isset($_GET['id'])) {
        $cfg = DB::row("SELECT * FROM `danamon_configs` WHERE `id`=?", [(int)$_GET['id']]);
        if ($cfg) {
            $newActive = $cfg['is_active'] ? 0 : 1;
            DB::exec("UPDATE `danamon_configs` SET `is_active`=? WHERE `id`=?", [$newActive, (int)$_GET['id']]);
            flash('success', "Config '{$cfg['name']}' " . ($newActive ? 'diaktifkan' : 'dinonaktifkan') . '.');
        }
        redirect('?page=danamon_configs');
        return;
    }

    // ── Set sebagai default (aktifkan satu, nonaktifkan lainnya) ──
    if ($action === 'set_default' && isset($_GET['id'])) {
        DB::exec("UPDATE `danamon_configs` SET `is_active`=0");
        DB::exec("UPDATE `danamon_configs` SET `is_active`=1 WHERE `id`=?", [(int)$_GET['id']]);
        flash('success', 'Config default berhasil diatur.');
        redirect('?page=danamon_configs');
        return;
    }

    // ── Hapus ──
    if ($action === 'delete' && isset($_GET['id']) && $method === 'POST') {
        DB::exec("DELETE FROM `danamon_configs` WHERE `id`=?", [(int)$_GET['id']]);
        flash('success', 'Config dihapus.');
        redirect('?page=danamon_configs');
        return;
    }

    // ── Edit / Tambah ──
    if ($method === 'POST' && in_array($action, ['add','edit'])) {
        $id          = (int)($_POST['id']           ?? 0);
        $name        = trim($_POST['name']           ?? '');
        $baseUrl     = trim($_POST['base_url']       ?? '');
        $partnerId   = trim($_POST['partner_id']     ?? '');
        $channelId   = trim($_POST['channel_id']     ?? '');
        $clientId    = trim($_POST['client_id']      ?? '');
        $clientSec   = trim($_POST['client_secret']  ?? '');
        $isActive    = (int)($_POST['is_active']     ?? 1);

        if (!$name || !$baseUrl || !$partnerId) {
            flash('error', 'Nama, Base URL, dan Partner ID wajib diisi.');
            redirect("?page=danamon_configs&action={$action}" . ($id ? "&id={$id}" : ''));
            return;
        }

        if ($action === 'add') {
            DB::exec(
                "INSERT INTO `danamon_configs` (`name`,`base_url`,`partner_id`,`channel_id`,`client_id`,`client_secret`,`is_active`) VALUES (?,?,?,?,?,?,?)",
                [$name, $baseUrl, $partnerId, $channelId, $clientId, $clientSec, $isActive]
            );
            flash('success', "Config '{$name}' berhasil ditambahkan.");
        } else {
            $sets = "`name`=?,`base_url`=?,`partner_id`=?,`channel_id`=?,`is_active`=?";
            $params = [$name, $baseUrl, $partnerId, $channelId, $isActive];
            if ($clientSec !== '') { $sets .= ",`client_secret`=?"; $params[] = $clientSec; }
            if ($clientId  !== '') { $sets .= ",`client_id`=?";     $params[] = $clientId; }
            $params[] = $id;
            DB::exec("UPDATE `danamon_configs` SET {$sets} WHERE `id`=?", $params);
            flash('success', "Config '{$name}' berhasil disimpan.");
        }
        redirect('?page=danamon_configs');
        return;
    }

    $configs = [];
    try {
        $configs = DB::query("SELECT id,name,base_url,partner_id,channel_id,client_id,is_active,created_at FROM `danamon_configs` ORDER BY id ASC");
    } catch (Throwable) {}

    $editItem = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $editItem = DB::row("SELECT * FROM `danamon_configs` WHERE `id`=?", [(int)$_GET['id']]);
    }

    renderLayout('Danamon Config', fn() => renderDanamonConfigsPage($configs, $editItem, $action));
}

function renderDanamonConfigsPage(array $configs, ?array $editItem, string $action): void { ?>
<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
      <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.608 3.292 0z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Danamon Config Manager
    </h1>
    <p class="text-sm text-slate-500 mt-0.5">Konfigurasi koneksi ke Transporter Danamon SNAP API</p>
  </div>
  <?php if ($action !== 'add'): ?>
  <a href="?page=danamon_configs&action=add" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    Tambah Config
  </a>
  <?php endif; ?>
</div>

<?php if (in_array($action, ['add','edit'])): ?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm mb-6 p-6">
  <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-4"><?= $action === 'add' ? '➕ Tambah Config Baru' : '✏️ Edit Config' ?></h2>
  <form method="POST" action="?page=danamon_configs&action=<?= $action ?><?= $editItem ? '&id='.(int)$editItem['id'] : '' ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nama Config <span class="text-red-400">*</span></label>
      <input type="text" name="name" value="<?= h($editItem['name'] ?? '') ?>" placeholder="cth: Production BDI SNAP"
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Base URL Transporter <span class="text-red-400">*</span></label>
      <input type="text" name="base_url" value="<?= h($editItem['base_url'] ?? '') ?>" placeholder="http://10.2.3.117:8099/transporter/transaksi"
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Partner ID (X-PARTNER-ID) <span class="text-red-400">*</span></label>
      <input type="text" name="partner_id" value="<?= h($editItem['partner_id'] ?? '') ?>" placeholder="482d0496-8949-4f04-993a-b8a3ae098df9"
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Channel ID</label>
      <input type="text" name="channel_id" value="<?= h($editItem['channel_id'] ?? '') ?>" placeholder="95221"
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Client ID</label>
      <input type="text" name="client_id" value="<?= h($editItem['client_id'] ?? '') ?>" placeholder="ecc069f8-5b9f-4046-9c49-..."
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Client Secret <?= $editItem ? '<span class="font-normal text-slate-400">(kosong = tidak ubah)</span>' : '' ?></label>
      <input type="password" name="client_secret" value="" placeholder="<?= $editItem ? '(tidak diubah)' : 'client secret...' ?>"
        class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500">
    </div>
    <div class="sm:col-span-2 flex items-center gap-3">
      <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" <?= ($editItem['is_active'] ?? 1) ? 'checked' : '' ?> class="rounded"> Aktif
      </label>
      <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
        Simpan
      </button>
      <a href="?page=danamon_configs" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 transition-colors">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
          <th class="px-4 py-3 text-left">ID</th>
          <th class="px-4 py-3 text-left">Nama</th>
          <th class="px-4 py-3 text-left">Base URL</th>
          <th class="px-4 py-3 text-left">Partner ID</th>
          <th class="px-4 py-3 text-left">Channel</th>
          <th class="px-4 py-3 text-center">Status</th>
          <th class="px-4 py-3 text-center">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
        <?php if (empty($configs)): ?>
        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400 italic">Belum ada config. <a href="?page=danamon_configs&action=add" class="text-indigo-600 hover:underline">Tambah sekarang</a>.</td></tr>
        <?php else: foreach ($configs as $c): ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
          <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= (int)$c['id'] ?></td>
          <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200"><?= h($c['name']) ?></td>
          <td class="px-4 py-3 font-mono text-xs text-slate-500 max-w-[200px] truncate" title="<?= h($c['base_url']) ?>"><?= h($c['base_url']) ?></td>
          <td class="px-4 py-3 font-mono text-xs text-slate-500 max-w-[160px] truncate" title="<?= h($c['partner_id']) ?>"><?= h(substr($c['partner_id'],0,20)).'...' ?></td>
          <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= h($c['channel_id']) ?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($c['is_active']): ?>
            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">✓ Aktif</span>
            <?php else: ?>
            <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-500 dark:bg-gray-700">Non-aktif</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
              <a href="?page=danamon_configs&action=set_default&id=<?= (int)$c['id'] ?>" class="text-xs px-2 py-1 rounded border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 hover:border-indigo-300 dark:hover:bg-indigo-900/20 transition-colors" title="Jadikan Default">⭐</a>
              <a href="?page=danamon_configs&action=edit&id=<?= (int)$c['id'] ?>" class="text-xs px-2 py-1 rounded border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">Edit</a>
              <form method="POST" action="?page=danamon_configs&action=delete&id=<?= (int)$c['id'] ?>" class="inline" onsubmit="return confirm('Hapus config \'<?= h($c['name']) ?>\'?')">
                <button type="submit" class="text-xs px-2 py-1 rounded border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php }

// ══════════════════════════════════════════════════════════════════════
// SNAP / DANAMON TRANSPORTER HELPERS
// ══════════════════════════════════════════════════════════════════════

/**
 * Ambil config Danamon aktif dari tabel danamon_configs.
 * Jika $configId diberikan, ambil berdasarkan id; jika null ambil is_active=1 pertama.
 */
function getActiveDanamonConfig(?int $configId = null): ?array {
    try {
        if ($configId) {
            return DB::row("SELECT * FROM `danamon_configs` WHERE `id` = ? AND `is_active` = 1", [$configId]);
        }
        return DB::row("SELECT * FROM `danamon_configs` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1");
    } catch (Throwable) {
        return null;
    }
}

/**
 * Panggil Transporter untuk transaksi BiF / Online Transfer.
 *
 * Payload ke Transporter (format dari danamon_dispatch_logs production):
 *   {
 *     "module": "danamon",
 *     "protocol": "bifast",          ← atau "online" untuk PAYTFDANA trxType=01
 *     "signature": "<sha256(partner_id+channel_id+client_secret)>",
 *     "event_data": {
 *       "config": {
 *         "base_url":      "<transporter_url>",
 *         "channel_id":    "<channel_id>",
 *         "partner_id":    "<partner_id>",
 *         "client_secret": "<client_secret>",
 *         "client_id":     "<client_id>"
 *       },
 *       "faktur":       "<partnerReferenceNo>",
 *       "access_token": "",                    ← Transporter ambil sendiri
 *       "trx_order":    <TRXOrder object>
 *     }
 *   }
 *
 * Return: ['success'=>bool, 'response'=>array, 'http_code'=>int, 'raw'=>string]
 */
function snapCallTransporter(array $cfg, array $trxOrder, string $protocol = 'bifast'): array {
    $baseUrl      = rtrim($cfg['base_url'] ?? '', '/');
    $partnerId    = $cfg['partner_id']    ?? '';
    $channelId    = $cfg['channel_id']    ?? '';
    $clientSecret = $cfg['client_secret'] ?? '';
    $clientId     = $cfg['client_id']     ?? '';

    // Signature: sha256(partner_id + channel_id + client_secret)
    $signature = hash('sha256', $partnerId . $channelId . $clientSecret);

    $payload = json_encode([
        'module'     => 'danamon',
        'protocol'   => $protocol,
        'signature'  => $signature,
        'event_data' => [
            'config' => [
                'base_url'      => $baseUrl,
                'channel_id'    => $channelId,
                'partner_id'    => $partnerId,
                'client_secret' => $clientSecret,
                'client_id'     => $clientId,
            ],
            'faktur'       => $trxOrder['partnerReferenceNo'] ?? '',
            'access_token' => '',
            'trx_order'    => $trxOrder,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $raw      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $raw === false) {
        return [
            'success'   => false,
            'response'  => ['responseCode' => '5000000', 'responseMessage' => 'cURL Error: ' . $curlErr],
            'http_code' => $httpCode,
            'raw'       => (string)$raw,
        ];
    }

    $decoded = json_decode($raw, true);
    // Transporter async: {"status":"accepted","message":"...","faktur":"PJ000xxx"}
    // Transporter sync / SNAP direct: {"responseCode":"2001800","responseMessage":"Successful",...}
    $success = in_array($httpCode, [200, 201, 202]) && is_array($decoded) &&
               !isset($decoded['error']) &&
               (
                   ($decoded['status'] ?? '') === 'accepted' ||
                   str_starts_with((string)($decoded['responseCode'] ?? ''), '2')
               );

    return [
        'success'   => $success,
        'response'  => $decoded ?? ['responseMessage' => $raw],
        'http_code' => $httpCode,
        'raw'       => $raw,
    ];
}

// ── PERSETUJUAN TRANSAKSI ─────────────────────────────────────────────
function handleTrxApprove(): void {
    requireLogin();

    // ── POST: proses approve/reject individual + bulk ─────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $table      = trim($_POST['table']       ?? '');
        $id         = (int)($_POST['id']         ?? 0);
        $action     = trim($_POST['action']      ?? '');
        $bulkAction = trim($_POST['bulk_action'] ?? '');
        $bulkIds    = array_map('intval', (array)($_POST['bulk_ids'] ?? []));
        $back       = trim($_POST['back']        ?? 'trx_approve');
        $pk         = trim($_POST['pk']          ?? 'id');
        $statusCol  = trim($_POST['status_col']  ?? 'status');

        // Whitelist tabel yang boleh diubah
        $allowedTables = [
            'danamon_transactions'  => ['pk'=>'id',   'status_col'=>'status',   'approve'=>'SUCCESS', 'reject'=>'FAILED',   'ts_col'=>'updated_at', 'ts_type'=>'unix'],
            'dwallet_transactions'  => ['pk'=>'id',   'status_col'=>'status',   'approve'=>'S',       'reject'=>'G',        'ts_col'=>'updated_at', 'ts_type'=>'datetime'],
            'qris_transactions'     => ['pk'=>'id',   'status_col'=>'status',   'approve'=>'SUCCESS', 'reject'=>'FAILED',   'ts_col'=>null,         'ts_type'=>null],
        ];

        // Tabel pulsa_penjualan (tanpa suffix tahun)
        if ($table === 'pulsa_penjualan') {
            $allowedTables[$table] = ['pk'=>'ID', 'status_col'=>'Status', 'approve'=>'S', 'reject'=>'G', 'ts_col'=>'DateTimeClose', 'ts_type'=>'unix'];
        }

        if (!isset($allowedTables[$table]) || !$id || !in_array($action, ['approve','reject'])) {
            // ── Bulk approve/reject ─────────────────────────────────
            if ($bulkAction === 'approve' && !empty($bulkIds) && isset($allowedTables[$table])) {
                $cfg       = $allowedTables[$table];
                $pkCol     = $cfg['pk'];
                $stCol     = $cfg['status_col'];
                $newStatus = $cfg['approve'];
                $count     = 0;
                foreach ($bulkIds as $bid) {
                    try {
                        $sql    = "UPDATE `{$table}` SET `{$stCol}` = ?";
                        $params = [$newStatus];
                        if ($cfg['ts_col']) {
                            $tsVal    = $cfg['ts_type'] === 'unix' ? time() : date('Y-m-d H:i:s');
                            $sql     .= ", `{$cfg['ts_col']}` = ?";
                            $params[] = $tsVal;
                        }
                        $sql     .= " WHERE `{$pkCol}` = ? AND `{$stCol}` IN ('PENDING','P','pending')";
                        $params[] = $bid;
                        DB::exec($sql, $params);
                        $count++;
                    } catch (PDOException) {}
                }
                flash('success', "{$count} transaksi berhasil disetujui secara massal.");
                logActivity('trx_approve', ['action'=>'bulk_approve','table'=>$table,'count'=>$count]);
                redirect("?page={$back}");
                return;
            }
            flash('error', 'Parameter tidak valid.');
            redirect("?page={$back}");
            return;
        }

        $cfg       = $allowedTables[$table];
        $newStatus = $action === 'approve' ? $cfg['approve'] : $cfg['reject'];
        $pkCol     = $cfg['pk'];
        $stCol     = $cfg['status_col'];

        // Cek record ada dan masih PENDING
        $row = DB::row("SELECT * FROM `{$table}` WHERE `{$pkCol}` = ?", [$id]);
        if (!$row) {
            flash('error', 'Transaksi tidak ditemukan.');
            redirect("?page={$back}");
            return;
        }
        $curStatus = (string)($row[$stCol] ?? '');
        $pendingStates = ['PENDING','P','pending'];
        if (!in_array($curStatus, $pendingStates)) {
            flash('warning', "Transaksi sudah diproses sebelumnya (status: {$curStatus}).");
            redirect("?page={$back}");
            return;
        }

        // ── Khusus: pulsa_penjualan PAYBIFAST/PAYTFDANA → panggil Transporter ──
        $snapExecuted = false;
        if (
            $action === 'approve' &&
            preg_match('/^pulsa_penjualan/', $table) &&
            in_array(strtoupper((string)($row['Kode'] ?? '')), ['PAYBIFAST','PAYTFDANA'])
        ) {
            $kode        = strtoupper((string)($row['Kode'] ?? ''));
            $configId    = (int)($_POST['danamon_config_id'] ?? 0) ?: null;
            $danamonCfg  = getActiveDanamonConfig($configId);

            if (!$danamonCfg) {
                flash('error', 'Konfigurasi Danamon aktif tidak ditemukan. Silakan tambahkan di menu Danamon Config.');
                redirect("?page={$back}");
                return;
            }

            // Parse TRXOrder yang sudah tersimpan di DB
            $trxOrderRaw  = (string)($row['TRXOrder'] ?? '{}');
            $trxOrderData = json_decode($trxOrderRaw, true) ?? [];

            // Tentukan protocol: PAYBIFAST = bifast (trxType=02), PAYTFDANA = online (trxType=01)
            $trxType  = (string)($trxOrderData['additionalInfo']['trxType'] ?? '02');
            $protocol = ($kode === 'PAYBIFAST' || $trxType === '02') ? 'bifast' : 'online';

            // Panggil Transporter
            $result = snapCallTransporter($danamonCfg, $trxOrderData, $protocol);

            // Bangun TRXOrderResponse dari hasil
            $respData       = $result['response'];
            $snapSuccess    = $result['success'];
            $bifRef         = $respData['additionalInfo']['bifReferenceNo'] ?? ($respData['faktur'] ?? '');
            $responseCode   = $respData['responseCode']   ?? ($snapSuccess ? '2001800' : '5000000');
            $responseMsg    = $respData['responseMessage'] ?? ($snapSuccess ? 'Successful' : 'FAILED');

            $trxOrderResp = array_merge(
                // Skeleton sebelumnya
                json_decode((string)($row['TRXOrderResponse'] ?? '{}'), true) ?? [],
                // Timpa dengan data response nyata
                [
                    'responseCode'         => $responseCode,
                    'responseMessage'      => $responseMsg,
                    'referenceNo'          => $respData['referenceNo'] ?? $trxOrderData['partnerReferenceNo'] ?? '',
                    'partnerReferenceNo'   => $trxOrderData['partnerReferenceNo'] ?? '',
                    'amount'               => $trxOrderData['amount'] ?? [],
                    'beneficiaryAccountNo' => $trxOrderData['beneficiaryAccountNo'] ?? '',
                    'beneficiaryBankCode'  => $trxOrderData['beneficiaryBankCode'] ?? '',
                    'sourceAccountNo'      => $trxOrderData['sourceAccountNo'] ?? '',
                    'additionalInfo'       => [
                        'transferDescription' => $trxOrderData['additionalInfo']['transferDescription'] ?? '',
                        'trxPurposeCode'      => $trxOrderData['additionalInfo']['trxPurposeCode'] ?? '99',
                        'bifReferenceNo'      => $bifRef,
                        'transactionDate'     => $trxOrderData['transactionDate'] ?? date('Y-m-d\TH:i:sP'),
                    ],
                ]
            );

            // Jika Transporter mengembalikan SN / referenceNo di response langsung, simpan ke SN
            $snValue = $respData['referenceNo'] ?? $bifRef ?? $row['SN'] ?? '';

            if ($snapSuccess) {
                // Sukses: update Status=S, TRXOrderResponse=response, SN=ref, DateTimeClose=now
                DB::exec(
                    "UPDATE `{$table}` SET
                        `Status` = 'S',
                        `TRXOrderResponse` = ?,
                        `SN` = ?,
                        `DateTimeClose` = ?
                     WHERE `{$pkCol}` = ?",
                    [
                        json_encode($trxOrderResp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        $snValue,
                        time(),
                        $id,
                    ]
                );
                flash('success', "Transaksi #{$id} ({$kode}) berhasil dikirim ke Transporter. ResponseCode: {$responseCode} | Ref: {$bifRef}");
                logActivity('trx_approve_snap', [
                    'table'     => $table,
                    'id'        => $id,
                    'kode'      => $kode,
                    'protocol'  => $protocol,
                    'http_code' => $result['http_code'],
                    'response'  => $responseCode . ' ' . $responseMsg,
                    'bif_ref'   => $bifRef,
                ]);
            } else {
                // Gagal: update Status=G, TRXOrderResponse=response error
                DB::exec(
                    "UPDATE `{$table}` SET
                        `Status` = 'G',
                        `TRXOrderResponse` = ?,
                        `DateTimeClose` = ?
                     WHERE `{$pkCol}` = ?",
                    [
                        json_encode($trxOrderResp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        time(),
                        $id,
                    ]
                );
                flash('error', "Transaksi #{$id} ({$kode}) GAGAL. HTTP {$result['http_code']}: {$responseCode} – {$responseMsg}");
                logActivity('trx_approve_snap_failed', [
                    'table'     => $table,
                    'id'        => $id,
                    'kode'      => $kode,
                    'protocol'  => $protocol,
                    'http_code' => $result['http_code'],
                    'response'  => $responseCode . ' ' . $responseMsg,
                    'raw'       => substr($result['raw'], 0, 500),
                ]);
            }
            redirect("?page={$back}");
            return;
        }
        // ── /Khusus SNAP ──────────────────────────────────────────────────

        // Update status (non-SNAP atau reject)
        $sql    = "UPDATE `{$table}` SET `{$stCol}` = ?";
        $params = [$newStatus];

        if ($cfg['ts_col']) {
            $tsVal = $cfg['ts_type'] === 'unix' ? time() : date('Y-m-d H:i:s');
            $sql  .= ", `{$cfg['ts_col']}` = ?";
            $params[] = $tsVal;
        }
        $sql .= " WHERE `{$pkCol}` = ?";
        $params[] = $id;

        try {
            DB::exec($sql, $params);
            $label = $action === 'approve' ? 'disetujui' : 'ditolak';
            flash('success', "Transaksi #{$id} berhasil {$label}.");
            logActivity('trx_approve', ['table'=>$table,'id'=>$id,'action'=>$label,'new_status'=>$newStatus]);
        } catch (PDOException $e) {
            flash('error', 'Gagal update: ' . $e->getMessage());
        }

        redirect("?page={$back}");
        return;
    }

    // ── GET: tampilkan antrian persetujuan ───────────────────────────
    // Ambil semua PENDING dari setiap tabel transaksi
    $pendingDanamon = DB::query(
        "SELECT id AS trx_id, faktur, protocol AS info, source_account AS src, destination_account AS dst, amount, status, created_at AS ts
         FROM danamon_transactions WHERE status IN ('PENDING','P') ORDER BY id DESC LIMIT 50"
    );
    $pendingDwallet = DB::query(
        "SELECT id AS trx_id, faktur, jenis AS info, kode_sender AS src, kode_receiver AS dst, amount, status, created_at AS ts
         FROM dwallet_transactions WHERE status = 'P' ORDER BY id DESC LIMIT 50"
    );
    $pendingQris = DB::query(
        "SELECT id AS trx_id, external_id AS faktur, merchant_id AS info, '' AS src, '' AS dst, amount, status, created_at AS ts
         FROM qris_transactions WHERE status IN ('PENDING','P','pending') ORDER BY id DESC LIMIT 50"
    );

    // Pulsa: gunakan tabel pulsa_penjualan (tanpa suffix tahun)
    $pulsaPending = [];
    try {
        $tbl = 'pulsa_penjualan';
        $rows = DB::query(
            "SELECT ID AS trx_id, Nomor AS faktur, Kode AS kode, JenisTrx AS info, KodeCustomer AS src, HP AS dst, HJ_Nasabah AS amount, Status AS status, DateTime AS ts, '{$tbl}' AS _table
             FROM `{$tbl}` WHERE Status = 'P' ORDER BY ID DESC LIMIT 50"
        );
        foreach ($rows as $r) {
            $r['_table'] = $tbl;
            $pulsaPending[] = $r;
        }
    } catch (PDOException $e) {}

    $totalPending = count($pendingDanamon) + count($pendingDwallet) + count($pendingQris) + count($pulsaPending);

    $danamonConfigs = [];
    try {
        $danamonConfigs = DB::query("SELECT id, name, base_url, partner_id, channel_id, is_active FROM `danamon_configs` WHERE `is_active` = 1 ORDER BY id ASC");
    } catch (Throwable) {}

    renderLayout('Antrian Persetujuan', function() use ($pendingDanamon,$pendingDwallet,$pendingQris,$pulsaPending,$totalPending,$danamonConfigs) {
        renderTrxApprovePage($pendingDanamon,$pendingDwallet,$pendingQris,$pulsaPending,$totalPending,$danamonConfigs);
    });
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
        ['page' => 'trx_danamon',  'icon' => 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z', 'text' => 'Transaksi Bank'],
        ['page' => 'trx_dwallet',  'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'text' => 'D-Wallet'],
        ['page' => 'trx_pulsa',    'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z', 'text' => 'Penjualan Pulsa'],
        ['page' => 'trx_qris',     'icon' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3a2 2 0 00-2 2v8a2 2 0 002 2h4.01M9 8V5a2 2 0 012-2h2a2 2 0 012 2v3m-3 0h.01', 'text' => 'Transaksi QRIS'],
        ['page' => 'trx_create',   'icon' => 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Buat Transaksi'],
        ['page' => 'trx_approve',     'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Antrian Persetujuan'],
        ['page' => 'danamon_configs', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.608 3.292 0z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'text' => 'Danamon Config', 'admin' => true],
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
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($m['template_name'] ?? '—') ?></td>
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

// ─── RENDER: TRANSAKSI BANK (danamon_transactions) ───────────────────
function renderTrxDanamonPage(array $rows, int $total, int $page_n, int $totalPages, array $summary, array $protocols, string $search, string $status, string $proto, string $dateFrom, string $dateTo, int $limit): void {
    $qBase = '?page=trx_danamon' . ($search ? '&search=' . urlencode($search) : '') . ($status ? '&status=' . urlencode($status) : '') . ($proto ? '&protocol=' . urlencode($proto) : '') . ($dateFrom ? '&date_from=' . urlencode($dateFrom) : '') . ($dateTo ? '&date_to=' . urlencode($dateTo) : '');
    ?>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
          Transaksi Bank (Danamon)
        </h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> transaksi ditemukan</p>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <?php foreach ($summary as $s):
        $c = ['SUCCESS'=>'green','PENDING'=>'amber','FAILED'=>'red','PROCESSED'=>'blue'][$s['status']] ?? 'slate'; ?>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm">
        <div class="text-xs text-slate-400 mb-1"><?= h($s['status']) ?></div>
        <div class="text-lg font-bold text-<?= $c ?>-600 dark:text-<?= $c ?>-400"><?= number_format((int)$s['cnt']) ?></div>
        <div class="text-xs text-slate-500 mt-0.5"><?= rupiah($s['total'] ?? 0, true) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm mb-4 flex flex-wrap gap-3 items-end">
      <input type="hidden" name="page" value="trx_danamon">
      <div class="flex-1 min-w-[160px]">
        <label class="block text-xs text-slate-500 mb-1">Cari</label>
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Faktur / Ref No / Rekening..."
          class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Status</label>
        <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
          <option value="">Semua Status</option>
          <?php foreach (['PENDING','SUCCESS','FAILED','PROCESSED'] as $st): ?>
          <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[140px]">
        <label class="block text-xs text-slate-500 mb-1">Protokol</label>
        <select name="protocol" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
          <option value="">Semua</option>
          <?php foreach ($protocols as $p): ?>
          <option value="<?= h($p['protocol']) ?>" <?= $proto === $p['protocol'] ? 'selected' : '' ?>><?= h($p['protocol']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Dari Tanggal</label>
        <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Sampai Tanggal</label>
        <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">Filter</button>
      <a href="?page=trx_danamon" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors whitespace-nowrap">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">Faktur</th>
              <th class="px-4 py-3 text-left">Protokol</th>
              <th class="px-4 py-3 text-left">Rekening Asal</th>
              <th class="px-4 py-3 text-left">Rekening Tujuan</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Tanggal</th>
              <th class="px-4 py-3 text-center">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada data transaksi.</td></tr>
            <?php else: foreach ($rows as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 whitespace-nowrap"><?= h($r['faktur']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium"><?= h($r['protocol']) ?></span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['source_account'] ?? '—') ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['destination_account'] ?? '—') ?></td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap"><?= $r['amount'] > 0 ? rupiah($r['amount']) : '<span class="text-slate-400">—</span>' ?></td>
              <td class="px-4 py-3 text-center"><?= statusBadge((string)$r['status']) ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap"><?= $r['created_at'] ? formatDate((int)$r['created_at']) : '—' ?></td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <a href="?page=trx_danamon&id=<?= $r['id'] ?>" class="text-xs px-2 py-1 rounded border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">Detail</a>
                  <?php if (in_array((string)$r['status'], ['PENDING','P'])): ?>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi ini?')">
                    <input type="hidden" name="table" value="danamon_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_danamon">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors">✓ Setuju</button>
                  </form>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi ini?')">
                    <input type="hidden" name="table" value="danamon_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="trx_danamon">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">✕ Tolak</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>
    <?php
}

function renderTrxDanamonDetail(?array $row): void {
    if (!$row) { echo '<div class="p-8 text-center text-slate-400">Transaksi tidak ditemukan.</div>'; return; }
    $req = @json_decode($row['req_payload'] ?? '', true);
    $res = @json_decode($row['res_payload'] ?? '', true);
    ?>
    <div class="max-w-3xl">
      <div class="mb-4 flex items-center gap-3">
        <a href="?page=trx_danamon" class="text-sm text-blue-600 hover:underline">&larr; Kembali</a>
        <span class="text-slate-400">/</span>
        <span class="text-sm font-mono text-slate-700 dark:text-slate-300"><?= h($row['faktur']) ?></span>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white"><?= h($row['faktur']) ?></h2>
          <?= statusBadge((string)$row['status']) ?>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <?php $fields = ['Protokol'=>$row['protocol'],'Modul'=>$row['module'],'Ref No'=>$row['ref_no'],'Rekening Asal'=>$row['source_account'],'Rekening Tujuan'=>$row['destination_account'],'Nominal'=>rupiah($row['amount'] ?? 0),'Dibuat'=>formatDate((int)$row['created_at']),'Diperbarui'=>formatDate((int)$row['updated_at'])];
          foreach ($fields as $label => $val): ?>
          <div><div class="text-xs text-slate-400 mb-0.5"><?= $label ?></div><div class="font-medium text-slate-800 dark:text-slate-200"><?= h((string)($val ?? '—')) ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php if ($req): ?>
        <div>
          <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Request Payload</div>
          <pre class="bg-slate-50 dark:bg-gray-900 rounded-lg p-4 text-xs font-mono text-slate-700 dark:text-slate-300 overflow-x-auto"><?= h(json_encode($req, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
        <?php endif; ?>
        <?php if ($res): ?>
        <div>
          <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Response Payload</div>
          <pre class="bg-slate-50 dark:bg-gray-900 rounded-lg p-4 text-xs font-mono text-slate-700 dark:text-slate-300 overflow-x-auto"><?= h(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

// ─── RENDER: D-WALLET ────────────────────────────────────────────────
function renderDwalletPage(array $rows, int $total, int $page_n, int $totalPages, string $search, float $totalBalance, string $activeTab, int $limit, string $tab, string $jenis = '', string $statusFilter = '', array $sumByJenis = []): void {
    $tabs = ['wallets' => 'Dompet', 'transactions' => 'Transaksi', 'journal' => 'Jurnal'];
    $qBase = "?page=trx_dwallet&tab={$tab}" . ($search ? '&search=' . urlencode($search) : '') . ($jenis ? '&jenis=' . urlencode($jenis) : '') . ($statusFilter ? '&status=' . urlencode($statusFilter) : '');
    ?>
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        D-Wallet
      </h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-slate-100 dark:bg-gray-900 rounded-xl p-1 w-fit">
      <?php foreach ($tabs as $t => $label): ?>
      <a href="?page=trx_dwallet&tab=<?= $t ?>" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $activeTab === $t ? 'bg-white dark:bg-gray-800 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'wallets'): ?>
    <!-- Wallets Tab -->
    <?php if ($totalBalance > 0): ?>
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl p-5 text-white mb-4 shadow-sm">
      <div class="text-xs opacity-80 mb-1">Total Saldo Aktif</div>
      <div class="text-2xl font-bold"><?= rupiah($totalBalance) ?></div>
      <div class="text-xs opacity-70 mt-1"><?= number_format($total) ?> wallet aktif</div>
    </div>
    <?php endif; ?>
    <form method="GET" class="flex gap-3 mb-4">
      <input type="hidden" name="page" value="trx_dwallet">
      <input type="hidden" name="tab" value="wallets">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Kode Customer / Nomor Rekening..."
        class="flex-1 px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Cari</button>
      <a href="?page=trx_dwallet&tab=wallets" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">Reset</a>
    </form>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">Kode Customer</th>
              <th class="px-4 py-3 text-left">No. Rekening</th>
              <th class="px-4 py-3 text-right">Saldo</th>
              <th class="px-4 py-3 text-right">Hold</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Dibuat</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada wallet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white font-mono"><?= h($r['customer_code']) ?></td>
              <td class="px-4 py-3 font-mono text-blue-600 dark:text-blue-400"><?= h($r['account_number']) ?></td>
              <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400"><?= rupiah($r['balance']) ?></td>
              <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400"><?= $r['hold_balance'] > 0 ? rupiah($r['hold_balance']) : '<span class="text-slate-300">—</span>' ?></td>
              <td class="px-4 py-3 text-center"><?= statusBadge($r['status']) ?></td>
              <td class="px-4 py-3 text-xs text-slate-400"><?= h(substr((string)$r['created_at'], 0, 16)) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>

    <?php elseif ($tab === 'transactions'): ?>
    <!-- Transactions Tab -->
    <?php if (!empty($sumByJenis)): ?>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
      <?php foreach ($sumByJenis as $s):
        $c = ['CASHIN'=>'green','CASHOUT'=>'red','TRANSFER'=>'blue','PAYMENT'=>'purple','REFUND'=>'amber'][$s['jenis']] ?? 'slate'; ?>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-3 shadow-sm text-center">
        <div class="text-[10px] text-slate-400 mb-1"><?= h($s['jenis']) ?></div>
        <div class="text-base font-bold text-<?= $c ?>-600"><?= number_format((int)$s['cnt']) ?></div>
        <div class="text-[10px] text-slate-400"><?= rupiah($s['total'], true) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
      <input type="hidden" name="page" value="trx_dwallet">
      <input type="hidden" name="tab" value="transactions">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Faktur / Kode / Keterangan..."
        class="flex-1 min-w-[200px] px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      <select name="jenis" class="px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
        <option value="">Semua Jenis</option>
        <?php foreach (['CASHIN','CASHOUT','TRANSFER','PAYMENT','REFUND'] as $j): ?>
        <option value="<?= $j ?>" <?= $jenis === $j ? 'selected' : '' ?>><?= $j ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
        <option value="">Semua Status</option>
        <?php foreach (['P'=>'Pending','S'=>'Success','G'=>'Gagal','R'=>'Reversed'] as $sv => $sl): ?>
        <option value="<?= $sv ?>" <?= $statusFilter === $sv ? 'selected' : '' ?>><?= $sl ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Filter</button>
      <a href="?page=trx_dwallet&tab=transactions" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">Reset</a>
    </form>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">Faktur</th>
              <th class="px-4 py-3 text-left">Jenis</th>
              <th class="px-4 py-3 text-left">Pengirim</th>
              <th class="px-4 py-3 text-left">Penerima</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-right">Fee</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Tanggal</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada transaksi.</td></tr>
            <?php else: foreach ($rows as $r):
              $statusMap = ['P'=>'PENDING','S'=>'SUCCESS','G'=>'FAILED','R'=>'REVERSED'];
              $displayStatus = $statusMap[$r['status']] ?? $r['status'];
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400"><?= h($r['faktur']) ?></td>
              <td class="px-4 py-3">
                <?php $jColor = ['CASHIN'=>'green','CASHOUT'=>'red','TRANSFER'=>'blue','PAYMENT'=>'purple','REFUND'=>'amber'][$r['jenis']] ?? 'slate'; ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-<?= $jColor ?>-100 text-<?= $jColor ?>-700 dark:bg-<?= $jColor ?>-900/30 dark:text-<?= $jColor ?>-300 font-medium"><?= h($r['jenis']) ?></span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['kode_sender'] ?? '—') ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['kode_receiver'] ?? '—') ?></td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap"><?= rupiah($r['amount']) ?></td>
              <td class="px-4 py-3 text-right text-xs text-slate-500"><?= $r['fee'] > 0 ? rupiah($r['fee']) : '—' ?></td>
              <td class="px-4 py-3 text-center"><?= statusBadge($displayStatus) ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap"><?= h(substr((string)$r['created_at'], 0, 16)) ?></td>
              <td class="px-4 py-3 text-center">
                <?php if ($r['status'] === 'P'): ?>
                <div class="flex items-center justify-center gap-1">
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi ini?')">
                    <input type="hidden" name="table" value="dwallet_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_dwallet&tab=transactions">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors">✓</button>
                  </form>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi ini?')">
                    <input type="hidden" name="table" value="dwallet_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="trx_dwallet&tab=transactions">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">✕</button>
                  </form>
                </div>
                <?php else: ?>
                <span class="text-slate-300 text-xs">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>

    <?php else: // journal ?>
    <!-- Journal Tab -->
    <form method="GET" class="flex gap-3 mb-4">
      <input type="hidden" name="page" value="trx_dwallet">
      <input type="hidden" name="tab" value="journal">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Faktur / Rekening / Keterangan..."
        class="flex-1 px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Cari</button>
      <a href="?page=trx_dwallet&tab=journal" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">Reset</a>
    </form>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">Faktur</th>
              <th class="px-4 py-3 text-left">Jenis Trx</th>
              <th class="px-4 py-3 text-left">Rekening</th>
              <th class="px-4 py-3 text-center">Urut</th>
              <th class="px-4 py-3 text-right">Debet</th>
              <th class="px-4 py-3 text-right">Kredit</th>
              <th class="px-4 py-3 text-left">Keterangan</th>
              <th class="px-4 py-3 text-left">Tanggal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada data jurnal.</td></tr>
            <?php else: foreach ($rows as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400"><?= h($r['faktur']) ?></td>
              <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"><?= h($r['jenis_transaksi']) ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-300"><?= h($r['rekening']) ?></td>
              <td class="px-4 py-3 text-center text-xs text-slate-400"><?= h((string)$r['urut']) ?></td>
              <td class="px-4 py-3 text-right font-semibold <?= $r['debet'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-300' ?>"><?= $r['debet'] > 0 ? rupiah($r['debet']) : '—' ?></td>
              <td class="px-4 py-3 text-right font-semibold <?= $r['kredit'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300' ?>"><?= $r['kredit'] > 0 ? rupiah($r['kredit']) : '—' ?></td>
              <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate"><?= h($r['keterangan'] ?? '—') ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap"><?= h((string)$r['tgl']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>
    <?php endif; ?>
    <?php
}

// ─── RENDER: PENJUALAN PULSA ─────────────────────────────────────────
function renderTrxPulsaPage(array $rows, int $total, int $page_n, int $totalPages, array $summary, string $search, string $status, string $dateFrom, string $dateTo, string $tbl, int $limit): void {
    $qBase = '?page=trx_pulsa' . ($search ? '&search=' . urlencode($search) : '') . ($status ? '&status=' . urlencode($status) : '') . ($dateFrom ? '&date_from=' . urlencode($dateFrom) : '') . ($dateTo ? '&date_to=' . urlencode($dateTo) : '');
    $statusLabels = ['S'=>'Sukses','P'=>'Pending','G'=>'Gagal','0'=>'Open'];
    $statusColors = ['S'=>'green','P'=>'amber','G'=>'red','0'=>'slate'];
    ?>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          Penjualan Pulsa
        </h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> transaksi &mdash; Tabel: <code class="text-xs bg-slate-100 dark:bg-gray-700 px-1 rounded"><?= h($tbl) ?></code></p>
      </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <?php foreach ($summary as $s):
        $c = $statusColors[$s['Status']] ?? 'slate';
        $l = $statusLabels[$s['Status']] ?? $s['Status']; ?>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm">
        <div class="text-xs text-slate-400 mb-1"><?= h($l) ?></div>
        <div class="text-lg font-bold text-<?= $c ?>-600 dark:text-<?= $c ?>-400"><?= number_format((int)$s['cnt']) ?></div>
        <div class="text-xs text-slate-500 mt-0.5"><?= rupiah($s['total_hj'] ?? 0, true) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm mb-4 flex flex-wrap gap-3 items-end">
      <input type="hidden" name="page" value="trx_pulsa">
      <div class="flex-1 min-w-[160px]">
        <label class="block text-xs text-slate-500 mb-1">Cari</label>
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Kode / HP / Customer / TrxID..."
          class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[120px]">
        <label class="block text-xs text-slate-500 mb-1">Status</label>
        <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
          <option value="">Semua</option>
          <?php foreach ($statusLabels as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $status === $sv ? 'selected' : '' ?>><?= $sl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Dari Tanggal</label>
        <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Sampai Tanggal</label>
        <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Filter</button>
      <a href="?page=trx_pulsa" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">ID / Tanggal</th>
              <th class="px-4 py-3 text-left">Customer</th>
              <th class="px-4 py-3 text-left">Jenis / Kode</th>
              <th class="px-4 py-3 text-left">HP Tujuan</th>
              <th class="px-4 py-3 text-right">HJ Nasabah</th>
              <th class="px-4 py-3 text-right">HJ / HB</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">SN / TrxID</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada data penjualan.</td></tr>
            <?php else: foreach ($rows as $r):
              $sc = $statusColors[$r['Status']] ?? 'slate';
              $sl = $statusLabels[$r['Status']] ?? $r['Status'];
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3">
                <div class="font-mono text-xs text-blue-600 dark:text-blue-400">#<?= $r['ID'] ?></div>
                <div class="text-xs text-slate-400"><?= h((string)$r['Tgl']) ?></div>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-300"><?= h($r['KodeCustomer'] ?? '—') ?></td>
              <td class="px-4 py-3">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300"><?= h($r['JenisTrx'] ?? '—') ?></div>
                <div class="font-mono text-xs text-slate-500"><?= h($r['Kode'] ?? '—') ?></div>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['HP'] ?? '—') ?></td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap"><?= rupiah($r['HJ_Nasabah'] ?? 0) ?></td>
              <td class="px-4 py-3 text-right text-xs">
                <div class="text-slate-700 dark:text-slate-300"><?= rupiah($r['HJ'] ?? 0) ?></div>
                <div class="text-slate-400"><?= rupiah($r['HB'] ?? 0) ?></div>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-<?= $sc ?>-100 text-<?= $sc ?>-700 dark:bg-<?= $sc ?>-900/30 dark:text-<?= $sc ?>-300"><?= h($sl) ?></span>
              </td>
              <td class="px-4 py-3">
                <?php if (!empty($r['SN'])): ?>
                <div class="font-mono text-xs text-slate-500 truncate max-w-[150px]" title="<?= h($r['SN']) ?>"><?= h(substr($r['SN'], 0, 20)) ?>...</div>
                <?php endif; ?>
                <?php if (!empty($r['TrxID'])): ?>
                <div class="font-mono text-xs text-slate-400 truncate max-w-[150px]"><?= h($r['TrxID']) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if (($r['Status'] ?? '') === 'P'): ?>
                <div class="flex items-center justify-center gap-1">
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi pulsa ini?')">
                    <input type="hidden" name="table" value="<?= h($tbl) ?>">
                    <input type="hidden" name="pk" value="ID">
                    <input type="hidden" name="status_col" value="Status">
                    <input type="hidden" name="id" value="<?= $r['ID'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_pulsa">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors">✓</button>
                  </form>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi pulsa ini?')">
                    <input type="hidden" name="table" value="<?= h($tbl) ?>">
                    <input type="hidden" name="pk" value="ID">
                    <input type="hidden" name="status_col" value="Status">
                    <input type="hidden" name="id" value="<?= $r['ID'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="trx_pulsa">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">✕</button>
                  </form>
                </div>
                <?php else: ?>
                <span class="text-slate-300 text-xs">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>
    <?php
}

// ─── RENDER: TRANSAKSI QRIS ──────────────────────────────────────────
function renderTrxQrisPage(array $rows, int $total, int $page_n, int $totalPages, array $summary, string $search, string $status, string $dateFrom, string $dateTo, int $limit): void {
    $qBase = '?page=trx_qris' . ($search ? '&search=' . urlencode($search) : '') . ($status ? '&status=' . urlencode($status) : '') . ($dateFrom ? '&date_from=' . urlencode($dateFrom) : '') . ($dateTo ? '&date_to=' . urlencode($dateTo) : '');
    ?>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3a2 2 0 00-2 2v8a2 2 0 002 2h4.01M9 8V5a2 2 0 012-2h2a2 2 0 012 2v3m-3 0h.01"/></svg>
          Transaksi QRIS
        </h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> transaksi ditemukan</p>
      </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <?php
      $allStatuses = ['00'=>'green','SUCCESS'=>'green','PENDING'=>'amber','FAILED'=>'red','EXPIRED'=>'slate'];
      foreach ($summary as $s):
        $c = $allStatuses[$s['status']] ?? 'slate'; ?>
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm">
        <div class="text-xs text-slate-400 mb-1"><?= h($s['status'] ?? 'UNKNOWN') ?></div>
        <div class="text-lg font-bold text-<?= $c ?>-600 dark:text-<?= $c ?>-400"><?= number_format((int)$s['cnt']) ?></div>
        <div class="text-xs text-slate-500 mt-0.5"><?= rupiah($s['total'] ?? 0, true) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-4 shadow-sm mb-4 flex flex-wrap gap-3 items-end">
      <input type="hidden" name="page" value="trx_qris">
      <div class="flex-1 min-w-[180px]">
        <label class="block text-xs text-slate-500 mb-1">Cari</label>
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="External ID / Ref No / Merchant ID..."
          class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Status</label>
        <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
          <option value="">Semua Status</option>
          <?php foreach (array_unique(array_column($summary, 'status')) as $st): ?>
          <option value="<?= h($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= h($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Dari Tanggal</label>
        <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Sampai Tanggal</label>
        <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 focus:outline-none focus:border-blue-500">
      </div>
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Filter</button>
      <a href="?page=trx_qris" class="px-4 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 dark:bg-gray-750 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">ID</th>
              <th class="px-4 py-3 text-left">External ID</th>
              <th class="px-4 py-3 text-left">Reference No</th>
              <th class="px-4 py-3 text-left">Merchant</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Tgl Transaksi</th>
              <th class="px-4 py-3 text-left">Dibuat</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400 italic">Tidak ada transaksi QRIS.</td></tr>
            <?php else: foreach ($rows as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= $r['id'] ?></td>
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 max-w-[150px] truncate" title="<?= h($r['external_id'] ?? '') ?>"><?= h($r['external_id'] ?? '—') ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= h($r['reference_no'] ?? '—') ?></td>
              <td class="px-4 py-3 text-xs">
                <div class="font-semibold text-slate-700 dark:text-slate-300"><?= h($r['merchant_id'] ?? '—') ?></div>
                <div class="text-slate-400"><?= h($r['terminal_id'] ?? '') ?></div>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap"><?= rupiah($r['amount'] ?? 0) ?> <span class="text-xs text-slate-400"><?= h($r['amount_currency'] ?? 'IDR') ?></span></td>
              <td class="px-4 py-3 text-center"><?= statusBadge((string)($r['status'] ?? '')) ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap"><?= $r['transaction_date'] ? h(substr((string)$r['transaction_date'], 0, 16)) : '—' ?></td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap"><?= $r['created_at'] ? h(substr((string)$r['created_at'], 0, 16)) : '—' ?></td>
              <td class="px-4 py-3 text-center">
                <?php if (in_array((string)($r['status'] ?? ''), ['PENDING','P','pending'])): ?>
                <div class="flex items-center justify-center gap-1">
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi QRIS ini?')">
                    <input type="hidden" name="table" value="qris_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_qris">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors">✓</button>
                  </form>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi QRIS ini?')">
                    <input type="hidden" name="table" value="qris_transactions">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="trx_qris">
                    <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">✕</button>
                  </form>
                </div>
                <?php else: ?>
                <span class="text-slate-300 text-xs">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php renderPagination($page_n, $totalPages, $qBase); ?>
    </div>
    <?php
}

// ─── RENDER: BUAT TRANSAKSI ───────────────────────────────────────────
function renderTrxCreatePage(string $activeType, string $error, string $success): void {
    $types = [
        'danamon' => ['label'=>'Transfer Bank (Danamon)', 'icon'=>'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4'],
        'dwallet' => ['label'=>'D-Wallet',                'icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        'qris'    => ['label'=>'QRIS',                    'icon'=>'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01'],
        'pulsa'   => ['label'=>'Penjualan Pulsa',         'icon'=>'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
    ];
    ?>
    <div class="max-w-3xl mx-auto">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Buat Transaksi Baru
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Isi formulir di bawah untuk membuat transaksi baru. Transaksi akan berstatus <strong>PENDING</strong> hingga disetujui.</p>
      </div>

      <!-- Alert -->
      <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 text-sm flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?= h($error) ?></span>
      </div>
      <?php elseif ($success): ?>
      <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300 text-sm flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?= $success ?></span>
      </div>
      <?php endif; ?>

      <!-- Tab Selector -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm mb-6">
        <div class="flex border-b border-slate-200 dark:border-gray-700 overflow-x-auto">
          <?php foreach ($types as $key => $t): ?>
          <a href="?page=trx_create&type=<?= $key ?>"
             class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition-colors border-b-2 <?= $activeType === $key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="<?= h($t['icon']) ?>"/>
            </svg>
            <?= h($t['label']) ?>
          </a>
          <?php endforeach; ?>
        </div>

        <div class="p-6">
          <?php if ($activeType === 'danamon'): ?>
          <!-- ── Danamon Form ── -->
          <form method="POST" action="?page=trx_create" class="space-y-5" id="form-danamon">
            <input type="hidden" name="trx_type" value="danamon">

            <!-- Customer Selector -->
            <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-700">
              <label class="block text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-1.5">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Pilih Customer (Auto-fill)
              </label>
              <select id="dan-customer-sel" class="w-full px-3 py-2.5 text-sm border border-indigo-300 dark:border-indigo-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <option value="">— Pilih customer untuk auto-fill —</option>
              </select>
              <p class="text-[10px] text-indigo-500 mt-1">Pilih customer untuk otomatis mengisi Rekening Asal</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Tipe Protokol <span class="text-red-500">*</span></label>
                <select name="protocol" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                  <option value="INQUIRY">INQUIRY</option>
                  <option value="TRANSFER_INTRA">TRANSFER_INTRA</option>
                  <option value="TRANSFER_INTER">TRANSFER_INTER</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Rekening Asal <span class="text-red-500">*</span></label>
                <div class="relative">
                  <select id="dan-src-sel" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 mb-1">
                    <option value="">— Pilih rekening asal —</option>
                  </select>
                  <input type="text" name="source_account" id="dan-src-input" required placeholder="Ketik manual atau pilih di atas"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Rekening Tujuan</label>
                <input type="text" name="destination_account" placeholder="cth: 003600350346"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nominal (Rp)</label>
                <input type="number" name="amount" min="0" step="100" placeholder="0"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Referensi No</label>
                <input type="text" name="ref_no" placeholder="Biarkan kosong untuk auto-generate"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan</label>
                <input type="text" name="remark" placeholder="Keterangan transfer"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-gray-700">
              <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Buat Transaksi Bank
              </button>
              <a href="?page=trx_danamon" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium transition-colors">Lihat Daftar</a>
            </div>
          </form>

          <?php elseif ($activeType === 'dwallet'): ?>
          <!-- ── D-Wallet Form ── -->
          <form method="POST" action="?page=trx_create" class="space-y-5" id="form-dwallet">
            <input type="hidden" name="trx_type" value="dwallet">

            <!-- Customer Selector -->
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
              <label class="block text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Pilih Customer (Auto-fill)
              </label>
              <select id="dw-customer-sel" class="w-full px-3 py-2.5 text-sm border border-blue-300 dark:border-blue-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">— Pilih customer untuk auto-fill —</option>
              </select>
              <p id="dw-customer-info" class="text-[10px] text-blue-500 mt-1">Pilih customer untuk otomatis mengisi Kode Pengirim</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jenis Transaksi <span class="text-red-500">*</span></label>
                <select name="jenis" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                  <option value="CASHIN">CASHIN</option>
                  <option value="CASHOUT">CASHOUT</option>
                  <option value="TRANSFER">TRANSFER</option>
                  <option value="PAYMENT">PAYMENT</option>
                  <option value="REFUND">REFUND</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nominal (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" min="1" step="100" required placeholder="10000"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Pengirim (Sender)</label>
                <div class="space-y-1">
                  <select id="dw-sender-sel" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">— Pilih wallet pengirim —</option>
                  </select>
                  <input type="text" name="kode_sender" id="dw-sender-input" placeholder="cth: A-000255"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Penerima (Receiver)</label>
                <div class="space-y-1">
                  <select id="dw-receiver-sel" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">— Pilih wallet penerima —</option>
                  </select>
                  <input type="text" name="kode_receiver" id="dw-receiver-input" placeholder="cth: A-000234"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Biaya / Fee (Rp)</label>
                <input type="number" name="fee" min="0" step="100" placeholder="0"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan</label>
                <input type="text" name="keterangan" placeholder="Keterangan transaksi"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-gray-700">
              <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Buat Transaksi D-Wallet
              </button>
              <a href="?page=trx_dwallet" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium transition-colors">Lihat Daftar</a>
            </div>
          </form>

          <?php elseif ($activeType === 'qris'): ?>
          <!-- ── QRIS Form ── -->
          <form method="POST" action="?page=trx_create" class="space-y-5" id="form-qris">
            <input type="hidden" name="trx_type" value="qris">

            <!-- Merchant Selector -->
            <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700">
              <label class="block text-xs font-semibold text-orange-700 dark:text-orange-300 mb-1.5">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Pilih Merchant (Auto-fill)
              </label>
              <select id="qris-merchant-sel" class="w-full px-3 py-2.5 text-sm border border-orange-300 dark:border-orange-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                <option value="">— Pilih merchant untuk auto-fill —</option>
              </select>
              <p class="text-[10px] text-orange-500 mt-1">Pilih merchant untuk otomatis mengisi Merchant ID dan Terminal ID</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Merchant ID <span class="text-red-500">*</span></label>
                <input type="text" name="merchant_id" id="qris-merchant-id" required placeholder="cth: MCHT001"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Terminal ID</label>
                <input type="text" name="terminal_id" id="qris-terminal-id" placeholder="cth: TRM001"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nominal (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" min="1" step="100" required placeholder="50000"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Mata Uang</label>
                <select name="amount_currency" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                  <option value="IDR">IDR</option>
                  <option value="USD">USD</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">External ID</label>
                <input type="text" name="external_id" placeholder="Biarkan kosong untuk auto-generate"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Reference No</label>
                <input type="text" name="reference_no" placeholder="Biarkan kosong untuk auto-generate"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-gray-700">
              <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Buat Transaksi QRIS
              </button>
              <a href="?page=trx_qris" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium transition-colors">Lihat Daftar</a>
            </div>
          </form>
          <?php elseif ($activeType === 'pulsa'): ?>
          <!-- ── Penjualan Pulsa Form ── -->
          <form method="POST" action="?page=trx_create" class="space-y-6" id="form-pulsa">
            <input type="hidden" name="trx_type" value="pulsa">

            <!-- Info Banner -->
            <div class="flex items-start gap-2 p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 text-purple-700 dark:text-purple-300 text-xs">
              <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Data akan disimpan ke tabel <strong>pulsa_penjualan</strong>. Status awal: <strong>P (Pending)</strong> menunggu persetujuan.</span>
            </div>

            <!-- Customer Selector (Pulsa) -->
            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
              <label class="block text-xs font-semibold text-purple-700 dark:text-purple-300 mb-1.5">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Pilih Customer (Auto-fill)
              </label>
              <select id="pulsa-customer-sel" class="w-full px-3 py-2.5 text-sm border border-purple-300 dark:border-purple-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                <option value="">— Pilih customer untuk auto-fill —</option>
              </select>
              <div id="pulsa-customer-badge" class="hidden mt-2 p-2 rounded bg-purple-100 dark:bg-purple-800/30 text-xs text-purple-700 dark:text-purple-300"></div>
            </div>

            <!-- SEKSI 1: Info Dasar -->
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Informasi Dasar</h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Tanggal Transaksi <span class="text-red-500">*</span></label>
                  <input type="date" name="Tgl" value="<?= date('Y-m-d') ?>" required
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Customer <span class="text-red-500">*</span></label>
                  <input type="text" name="KodeCustomer" id="pulsa-kode-customer" required placeholder="cth: A-000300"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jenis Bayar</label>
                  <select name="Jenis" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="P">P – Penjualan</option>
                    <option value="B">B – Bayar</option>
                    <option value="R">R – Refund</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- SEKSI 2: Produk & Tujuan -->
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Produk & Tujuan</h3>

              <!-- Produk Selector -->
              <div class="mb-4 p-3 bg-purple-50/60 dark:bg-purple-900/10 rounded-lg border border-purple-200 dark:border-purple-700/50">
                <label class="block text-xs font-semibold text-purple-700 dark:text-purple-300 mb-1.5">
                  <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                  Cari &amp; Pilih Produk dari Database
                </label>
                <div class="flex gap-2 mb-2">
                  <input type="text" id="pulsa-prod-search"
                    placeholder="Ketik kode / nama produk: PAYBIFAST, XL10, PLN, BPJS..."
                    class="flex-1 px-3 py-2 text-sm border border-purple-300 dark:border-purple-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                  <select id="pulsa-jenist-filter"
                    class="w-36 px-2 py-2 text-sm border border-purple-300 dark:border-purple-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500">
                    <option value="">Semua JenisTrx</option>
                  </select>
                </div>
                <select id="pulsa-prod-sel" size="5"
                  class="w-full text-sm border border-purple-300 dark:border-purple-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500">
                  <option value="">— Memuat produk... —</option>
                </select>
                <div id="pulsa-prod-badge" class="hidden mt-2 p-2 rounded-lg bg-purple-100 dark:bg-purple-800/30 text-xs text-purple-800 dark:text-purple-200 grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Produk <span class="text-red-500">*</span></label>
                  <input type="text" name="Kode" id="pulsa-kode" required placeholder="cth: PAYBIFAST, PAYTFDANA, XL10"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                  <p id="pulsa-kode-hint" class="text-[10px] text-purple-500 mt-0.5 hidden"></p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jenis Transaksi (JenisTrx) <span class="text-red-500">*</span></label>
                  <select name="JenisTrx" id="pulsa-jenist-input"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="">— Pilih atau ketik —</option>
                    <option value="13">13 – Transfer Dana</option>
                    <option value="PP">PP – Pulsa</option>
                    <option value="PL">PL – PLN / Listrik</option>
                    <option value="TF">TF – Transfer Bank</option>
                    <option value="BJ">BJ – BPJS</option>
                    <option value="PD">PD – PDAM</option>
                    <option value="TK">TK – Telkom</option>
                    <option value="IN">IN – Internet</option>
                    <option value="TV">TV – TV Kabel</option>
                    <option value="FN">FN – Finance / Cicilan</option>
                    <option value="OD">OD – Open Denom</option>
                  </select>
                  <p class="text-[10px] text-slate-400 mt-1">Auto-diisi saat pilih produk dari DB di atas</p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nomor HP Tujuan <span class="text-red-500">*</span></label>
                  <input type="text" name="HP" id="pulsa-hp" required placeholder="cth: 085259070588"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nomor HP Sender</label>
                  <input type="text" name="HPSender" id="pulsa-hp-sender" placeholder="Sama dengan HP Tujuan jika kosong"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Supplier</label>
                  <select name="Supplier" id="pulsa-supplier-sel"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="0024" selected>0024 – SNAP FT BDI</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Sender (Agent)</label>
                  <input type="text" name="Sender" id="pulsa-sender" placeholder="cth: bpr_pas"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
              </div>
            </div>

            <!-- SEKSI 3: Harga -->
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Harga & Nominal</h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">HJ Nasabah (Harga Jual) <span class="text-red-500">*</span></label>
                  <input type="number" name="HJ_Nasabah" id="pulsa-hj-nasabah" min="1" step="100" required placeholder="500000"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                  <p class="text-[10px] text-slate-400 mt-1">Harga jual kepada pelanggan/nasabah</p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">HJ (Harga Jual ke Supplier)</label>
                  <input type="number" name="HJ" id="pulsa-hj" min="0" step="100" placeholder="500"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                  <p class="text-[10px] text-slate-400 mt-1">Biaya/fee ke supplier</p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">HB (Harga Beli)</label>
                  <input type="number" name="HB" id="pulsa-hb" min="0" step="100" placeholder="0"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                  <p class="text-[10px] text-slate-400 mt-1">Harga beli dari supplier</p>
                </div>
              </div>
            </div>

            <!-- SEKSI 4: Transfer Dana (opsional) -->
            <div id="pulsa-seksi-tf">
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                Data Transfer Dana
                <span class="text-slate-300 font-normal normal-case">(otomatis tampil jika JenisTrx = 13 / Kode PAYBIFAST / PAYTFDANA)</span>
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">No. Rekening Tujuan <span class="text-red-400">*</span></label>
                  <input type="text" name="RekTujuanTFDana" placeholder="cth: 614701019064534"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode Bank Tujuan <span class="text-red-400">*</span></label>
                  <input type="text" name="BankTujuanTFDana" placeholder="cth: BRINIDJA, 008, 014"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nama Bank Tujuan</label>
                  <input type="text" name="BankNamaTujuanTFDana" placeholder="cth: BANK RAKYAT INDONESIA"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nama Pemilik Rekening <span class="text-red-400">*</span></label>
                  <input type="text" name="NamaTujuanTFDana" placeholder="cth: AJENG RATMAWATI"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">No. Rekening Sumber <span class="text-red-400">*</span></label>
                  <input type="text" name="SourceAccountNo" placeholder="cth: 003684883329"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">IDTRXOrder / partnerReferenceNo <span class="text-red-400">*</span></label>
                  <input type="text" name="IDTRXOrder" placeholder="cth: STPYB202606170000057"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Transfer Description</label>
                  <input type="text" name="TransferDescription" placeholder="cth: JB012026061700000037-"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Trx Purpose Code</label>
                  <input type="text" name="TrxPurposeCode" value="99" placeholder="default: 99"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Trx Type</label>
                  <select name="TrxType" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="02">02 – BiF Transfer (default)</option>
                    <option value="01">01 – Online Transfer</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- SEKSI 5: Opsional -->
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Data Tambahan <span class="text-slate-300 font-normal normal-case">(opsional)</span></h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nomor Surat/Ref</label>
                  <input type="text" name="Nomor" placeholder="Auto-generate jika kosong"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Seri</label>
                  <input type="text" name="Seri" placeholder="Nomor seri (opsional)"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Protocol</label>
                  <select name="Protocol" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="H">H – HTTP</option>
                    <option value="S">S – SMS</option>
                    <option value="A">A – API</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">M-Banking</label>
                  <select name="MBanking" class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    <option value="1">1 – Ya</option>
                    <option value="0">0 – Tidak</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan</label>
                  <input type="text" name="keterangan_pulsa" placeholder="Catatan tambahan"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-gray-700">
              <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Buat Penjualan Pulsa
              </button>
              <a href="?page=trx_pulsa" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium transition-colors">Lihat Daftar</a>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="grid grid-cols-4 gap-3">
        <a href="?page=trx_approve" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 transition-all group">
          <svg class="w-6 h-6 text-green-500 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="text-xs font-medium text-slate-600 dark:text-slate-300 text-center">Antrian Persetujuan</span>
        </a>
        <a href="?page=trx_danamon" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 transition-all group">
          <svg class="w-6 h-6 text-indigo-500 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4"/></svg>
          <span class="text-xs font-medium text-slate-600 dark:text-slate-300 text-center">Riwayat Bank</span>
        </a>
        <a href="?page=trx_dwallet" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 transition-all group">
          <svg class="w-6 h-6 text-blue-500 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          <span class="text-xs font-medium text-slate-600 dark:text-slate-300 text-center">Riwayat D-Wallet</span>
        </a>
        <a href="?page=trx_pulsa" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm hover:border-purple-300 dark:hover:border-purple-600 transition-all group">
          <svg class="w-6 h-6 text-purple-500 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span class="text-xs font-medium text-slate-600 dark:text-slate-300 text-center">Riwayat Pulsa</span>
        </a>
      </div>
    </div>

    <!-- ═══ AUTO-FILL JAVASCRIPT ═══════════════════════════════════════ -->
    <script>
    (function(){
      'use strict';

      /* ── Helpers ─────────────────────────────────────────────────── */
      function qs(sel){ return document.querySelector(sel); }
      function fmtRp(n){ return 'Rp '+Number(n||0).toLocaleString('id-ID'); }

      /* ── Searchable filter di atas <select> ─────────────────────── */
      function makeSearchable(selectEl, placeholder){
        if(!selectEl) return;
        var search = document.createElement('input');
        search.type = 'text';
        search.placeholder = placeholder || 'Cari...';
        search.className = 'w-full px-3 py-2 text-sm border border-slate-300 dark:border-gray-600 rounded-lg bg-slate-50 dark:bg-gray-750 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 mb-1';
        selectEl.parentNode.insertBefore(search, selectEl);
        search.addEventListener('input', function(){
          var q = this.value.toLowerCase();
          Array.from(selectEl.options).forEach(function(opt){
            if(opt.value === ''){ opt.hidden = false; return; }
            opt.hidden = !opt.text.toLowerCase().includes(q);
          });
        });
      }

      /* ── API JSON helper ─────────────────────────────────────────── */
      function apiGet(action, params, cb){
        var qstr = Object.keys(params||{}).map(function(k){
          return encodeURIComponent(k)+'='+encodeURIComponent(params[k]);
        }).join('&');
        fetch('?page=api_json&action='+action+(qstr?'&'+qstr:''), {
          headers: {'X-Requested-With':'XMLHttpRequest'}
        })
        .then(function(r){ return r.json(); })
        .then(function(d){ if(d.success) cb(d.data); else console.warn('[api_json] '+action+':', d.message); })
        .catch(function(e){ console.error('[api_json] fetch error:', e); });
      }

      /* ── Isi select dengan opsi ──────────────────────────────────── */
      function fillSelect(sel, items, valFn, txtFn, emptyTxt){
        if(!sel) return;
        var cur = sel.value;
        sel.innerHTML = '<option value="">'+emptyTxt+'</option>';
        (items||[]).forEach(function(item){
          var opt = document.createElement('option');
          opt.value = valFn(item);
          opt.textContent = txtFn(item);
          sel.appendChild(opt);
        });
        if(cur) sel.value = cur;
      }

      /* ════════════════════════════════════════════════════════════
         TAB DANAMON
         ════════════════════════════════════════════════════════════ */
      var danCustSel  = qs('#dan-customer-sel');
      var danSrcSel   = qs('#dan-src-sel');
      var danSrcInput = qs('#dan-src-input');

      if(danCustSel){
        apiGet('customers', {}, function(data){
          fillSelect(danCustSel, data,
            function(c){ return c.id; },
            function(c){ return c.name + (c.KodePro ? ' ['+c.KodePro+']' : ''); },
            '— Pilih customer —'
          );
          makeSearchable(danCustSel, 'Cari customer...');
        });
        /* Danamon tidak punya FK customer di tabel — tapi kita bisa log info */
        danCustSel.addEventListener('change', function(){
          /* optional: tampilkan info customer di console */
        });
      }

      if(danSrcSel && danSrcInput){
        apiGet('danamon_accounts', {}, function(data){
          fillSelect(danSrcSel, data,
            function(a){ return a; },
            function(a){ return a; },
            '— Pilih rekening asal —'
          );
          makeSearchable(danSrcSel, 'Cari nomor rekening...');
        });
        danSrcSel.addEventListener('change', function(){
          if(this.value) danSrcInput.value = this.value;
        });
        danSrcInput.addEventListener('input', function(){
          if(danSrcSel.value !== this.value) danSrcSel.value = '';
        });
      }

      /* ════════════════════════════════════════════════════════════
         TAB D-WALLET
         ════════════════════════════════════════════════════════════ */
      var dwCustSel       = qs('#dw-customer-sel');
      var dwSenderSel     = qs('#dw-sender-sel');
      var dwSenderInput   = qs('#dw-sender-input');
      var dwReceiverSel   = qs('#dw-receiver-sel');
      var dwReceiverInput = qs('#dw-receiver-input');
      var dwCustInfo      = qs('#dw-customer-info');

      if(dwCustSel){
        apiGet('customers', {}, function(data){
          fillSelect(dwCustSel, data,
            function(c){ return c.id; },
            function(c){ return c.name + (c.KodePro ? ' ['+c.KodePro+']' : ''); },
            '— Pilih customer —'
          );
          makeSearchable(dwCustSel, 'Cari customer...');
        });

        dwCustSel.addEventListener('change', function(){
          var id = this.value;
          if(!id){
            if(dwCustInfo) dwCustInfo.textContent = 'Pilih customer untuk otomatis mengisi Kode Pengirim';
            return;
          }
          apiGet('customer_detail', {id: id}, function(c){
            if(dwSenderInput && c.KodePro){
              dwSenderInput.value = c.KodePro;
              if(dwSenderSel) dwSenderSel.value = c.KodePro;
            }
            if(dwCustInfo) dwCustInfo.innerHTML =
              '<strong>'+c.name+'</strong>'
              +' &nbsp;·&nbsp; KodePro: <code>'+c.KodePro+'</code>'
              +' &nbsp;·&nbsp; Sender: <code>'+(c.user_h2h||'—')+'</code>';
          });
        });
      }

      if(dwSenderSel || dwReceiverSel){
        apiGet('dwallet_wallets', {}, function(data){
          var fmt = function(w){
            return (w.customer_name || w.customer_code)
              + ' ['+w.customer_code+'] – Rek: '+w.account_number
              + ' ('+fmtRp(w.balance)+')';
          };
          fillSelect(dwSenderSel, data,
            function(w){ return w.customer_code; }, fmt,
            '— Pilih wallet pengirim —'
          );
          fillSelect(dwReceiverSel, data,
            function(w){ return w.customer_code; }, fmt,
            '— Pilih wallet penerima —'
          );
          makeSearchable(dwSenderSel,   'Cari wallet pengirim...');
          makeSearchable(dwReceiverSel, 'Cari wallet penerima...');
        });

        if(dwSenderSel && dwSenderInput){
          dwSenderSel.addEventListener('change', function(){
            if(this.value) dwSenderInput.value = this.value;
          });
          dwSenderInput.addEventListener('input', function(){
            if(dwSenderSel.value !== this.value) dwSenderSel.value = '';
          });
        }
        if(dwReceiverSel && dwReceiverInput){
          dwReceiverSel.addEventListener('change', function(){
            if(this.value) dwReceiverInput.value = this.value;
          });
          dwReceiverInput.addEventListener('input', function(){
            if(dwReceiverSel.value !== this.value) dwReceiverSel.value = '';
          });
        }
      }

      /* ════════════════════════════════════════════════════════════
         TAB QRIS
         ════════════════════════════════════════════════════════════ */
      var qrisMerchantSel = qs('#qris-merchant-sel');
      var qrisMerchantId  = qs('#qris-merchant-id');
      var qrisTerminalId  = qs('#qris-terminal-id');

      if(qrisMerchantSel){
        apiGet('qris_merchants', {}, function(data){
          fillSelect(qrisMerchantSel, data,
            function(m){ return JSON.stringify(m); },
            function(m){ return m.merchant_id + (m.terminal_id ? '  (terminal: '+m.terminal_id+')' : ''); },
            '— Pilih merchant —'
          );
          makeSearchable(qrisMerchantSel, 'Cari merchant ID...');
        });

        qrisMerchantSel.addEventListener('change', function(){
          if(!this.value) return;
          try {
            var m = JSON.parse(this.value);
            if(qrisMerchantId)             qrisMerchantId.value = m.merchant_id || '';
            if(qrisTerminalId && m.terminal_id) qrisTerminalId.value  = m.terminal_id;
          } catch(e){}
        });
      }

      /* ════════════════════════════════════════════════════════════
         TAB PULSA
         ════════════════════════════════════════════════════════════ */
      var pulsaCustSel  = qs('#pulsa-customer-sel');
      var pulsaKodeCust = qs('#pulsa-kode-customer');
      var pulsaSender   = qs('#pulsa-sender');
      var pulsaHpSender = qs('#pulsa-hp-sender');
      var pulsaBadge    = qs('#pulsa-customer-badge');

      /* Produk elements */
      var pulsaProdSearch  = qs('#pulsa-prod-search');
      var pulsaJenistFilter= qs('#pulsa-jenist-filter');
      var pulsaProdSel     = qs('#pulsa-prod-sel');
      var pulsaProdBadge   = qs('#pulsa-prod-badge');
      var pulsaKode        = qs('#pulsa-kode');
      var pulsaKodeHint    = qs('#pulsa-kode-hint');
      var pulsaJenistInput = qs('#pulsa-jenist-input');
      var pulsaHjNasabah   = qs('#pulsa-hj-nasabah');
      var pulsaHj          = qs('#pulsa-hj');
      var pulsaHb          = qs('#pulsa-hb');
      var pulsaSeksiTf     = qs('#pulsa-seksi-tf');
      var pulsaSupplierSel = qs('#pulsa-supplier-sel');

      /* Map JenisTrx kode → label */
      var JENIS_TRX_LABELS = {
        '13':'Transfer Dana','PP':'Pulsa','PL':'PLN/Listrik','TF':'Transfer Bank',
        'BJ':'BPJS','PD':'PDAM','TK':'Telkom','IN':'Internet',
        'TV':'TV Kabel','FN':'Finance/Cicilan','OD':'Open Denom'
      };

      /* Produk yang memerlukan seksi Transfer Dana */
      var TF_KODES    = ['PAYBIFAST','PAYTFDANA','PAYBIINST','PAYTF'];
      var TF_JENIST   = ['13','TF'];

      function isTfKode(kode, jenis){
        if(!kode && !jenis) return false;
        var k = (kode||'').toUpperCase();
        var j = (jenis||'').toUpperCase();
        return TF_KODES.some(function(x){ return k.indexOf(x) >= 0; })
            || TF_JENIST.indexOf(j) >= 0
            || TF_JENIST.indexOf((jenis||'')) >= 0;
      }

      function updateTfVisibility(){
        var kode  = pulsaKode  ? pulsaKode.value  : '';
        var jenis = pulsaJenistInput ? pulsaJenistInput.value : '';
        if(pulsaSeksiTf){
          if(isTfKode(kode, jenis)){
            pulsaSeksiTf.style.display = '';
            pulsaSeksiTf.style.borderLeft = '3px solid #a855f7';
            pulsaSeksiTf.style.paddingLeft = '12px';
          } else {
            pulsaSeksiTf.style.display = 'none';
          }
        }
      }

      /* ─── Customer auto-fill ─────────────────────────────────── */
      if(pulsaCustSel){
        apiGet('customers', {}, function(data){
          fillSelect(pulsaCustSel, data,
            function(c){ return c.id; },
            function(c){
              return c.name
                + (c.KodePro  ? ' ['+c.KodePro+']'   : '')
                + (c.user_h2h ? ' – '+c.user_h2h      : '');
            },
            '— Pilih customer —'
          );
          makeSearchable(pulsaCustSel, 'Cari nama / kode customer...');
        });

        pulsaCustSel.addEventListener('change', function(){
          var id = this.value;
          if(!id){
            if(pulsaBadge){ pulsaBadge.classList.add('hidden'); pulsaBadge.innerHTML=''; }
            return;
          }
          apiGet('customer_detail', {id: id}, function(c){
            if(pulsaKodeCust) pulsaKodeCust.value = c.KodePro  || '';
            if(pulsaSender)   pulsaSender.value   = c.user_h2h || '';
            if(pulsaHpSender && c.phone) pulsaHpSender.value = c.phone;
            if(pulsaBadge){
              pulsaBadge.classList.remove('hidden');
              pulsaBadge.innerHTML =
                '✅ <strong>'+c.name+'</strong>'
                +' &nbsp;|&nbsp; KodePro: <code>'+c.KodePro+'</code>'
                +' &nbsp;|&nbsp; Sender: <code>'+(c.user_h2h||'—')+'</code>'
                +' &nbsp;|&nbsp; HP: <code>'+(c.phone||'—')+'</code>'
                +(c.TipeAkun ? ' &nbsp;|&nbsp; Tipe: '+c.TipeAkun : '');
            }
          });
        });
      }

      /* ─── Produk: load semua produk dari API ──────────────────── */
      var _allProds = [];

      function fmtRp2(n){
        n = parseFloat(n)||0;
        if(n===0) return '—';
        return 'Rp '+n.toLocaleString('id-ID');
      }

      function renderProdOptions(list){
        if(!pulsaProdSel) return;
        pulsaProdSel.innerHTML = '';
        if(!list.length){
          var empty = document.createElement('option');
          empty.value = '';
          empty.textContent = '— Tidak ada produk cocok —';
          pulsaProdSel.appendChild(empty);
          return;
        }
        list.forEach(function(p, i){
          var opt = document.createElement('option');
          opt.value = i; // index ke _allProds
          var jt = p.JenisTrx ? ' [JenisTrx:'+p.JenisTrx+']' : '';
          var harga = p.HJ_Nasabah > 0 ? fmtRp2(p.HJ_Nasabah) : (p.HJ > 0 ? fmtRp2(p.HJ) : '');
          opt.textContent = p.Kode + (p.Nama && p.Nama !== p.Kode ? ' – '+p.Nama : '') + jt + (harga ? ' | '+harga : '');
          opt.dataset.prod = JSON.stringify(p);
          pulsaProdSel.appendChild(opt);
        });
      }

      function filterProds(){
        var q  = (pulsaProdSearch   ? pulsaProdSearch.value.toLowerCase()   : '');
        var jt = (pulsaJenistFilter ? pulsaJenistFilter.value.toLowerCase() : '');
        var list = _allProds.filter(function(p){
          var match  = !q  || p.Kode.toLowerCase().includes(q) || (p.Nama||'').toLowerCase().includes(q);
          var matchJ = !jt || (p.JenisTrx||'').toLowerCase() === jt;
          return match && matchJ;
        });
        renderProdOptions(list);
      }

      if(pulsaProdSel){
        // Load produk dari API
        apiGet('pulsa_products', {}, function(data){
          _allProds = data || [];

          // Populate filter JenisTrx unik
          if(pulsaJenistFilter){
            var jts = {};
            _allProds.forEach(function(p){ if(p.JenisTrx) jts[p.JenisTrx] = true; });
            Object.keys(jts).sort().forEach(function(jt){
              var o = document.createElement('option');
              o.value = jt;
              o.textContent = jt + (JENIS_TRX_LABELS[jt] ? ' – '+JENIS_TRX_LABELS[jt] : '');
              pulsaJenistFilter.appendChild(o);
            });
          }

          // Populate supplier dropdown
          if(pulsaSupplierSel){
            apiGet('suppliers', {}, function(supData){
              if(!supData || !supData.length) return;
              pulsaSupplierSel.innerHTML = '';
              supData.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.Kode;
                o.textContent = s.Kode + ' – ' + s.Nama;
                if(s.Kode === '0024') o.selected = true;
                pulsaSupplierSel.appendChild(o);
              });
            });
          }

          renderProdOptions(_allProds.slice(0, 100)); // tampil 100 pertama
        });

        // Search filter
        if(pulsaProdSearch){
          pulsaProdSearch.addEventListener('input', filterProds);
        }
        if(pulsaJenistFilter){
          pulsaJenistFilter.addEventListener('change', filterProds);
        }

        // On produk select → auto-fill semua field terkait
        pulsaProdSel.addEventListener('change', function(){
          var opt = this.options[this.selectedIndex];
          if(!opt || opt.value === '') return;
          var p;
          try { p = JSON.parse(opt.dataset.prod || '{}'); } catch(e){ return; }

          /* Kode */
          if(pulsaKode){ pulsaKode.value = p.Kode || ''; }
          if(pulsaKodeHint && p.Nama && p.Nama !== p.Kode){
            pulsaKodeHint.textContent = '📦 ' + p.Nama;
            pulsaKodeHint.classList.remove('hidden');
          } else if(pulsaKodeHint){
            pulsaKodeHint.classList.add('hidden');
          }

          /* JenisTrx */
          if(pulsaJenistInput && p.JenisTrx){
            pulsaJenistInput.value = p.JenisTrx;
          }

          /* Harga – hanya isi jika kosong atau user belum ketik */
          if(pulsaHjNasabah && (!pulsaHjNasabah.value || pulsaHjNasabah.value === '0')){
            var hj = p.HJ_Nasabah > 0 ? p.HJ_Nasabah : (p.HJ > 0 ? p.HJ : 0);
            if(hj > 0) pulsaHjNasabah.value = hj;
          }
          if(pulsaHj && (!pulsaHj.value || pulsaHj.value === '0') && p.HJ > 0){
            pulsaHj.value = p.HJ;
          }
          if(pulsaHb && (!pulsaHb.value || pulsaHb.value === '0') && p.HB > 0){
            pulsaHb.value = p.HB;
          }

          /* Show/hide Transfer Dana section */
          updateTfVisibility();

          /* Badge produk */
          if(pulsaProdBadge){
            pulsaProdBadge.classList.remove('hidden');
            pulsaProdBadge.innerHTML =
              '<div><span class="font-semibold text-purple-700 dark:text-purple-300">Kode</span><br><code class="text-sm">'+(p.Kode||'—')+'</code></div>'
              +'<div><span class="font-semibold text-purple-700 dark:text-purple-300">JenisTrx</span><br><code class="text-sm">'+(p.JenisTrx||'—')+'</code> '+(JENIS_TRX_LABELS[p.JenisTrx]||'')+'</div>'
              +'<div><span class="font-semibold text-purple-700 dark:text-purple-300">HJ Nasabah</span><br><code class="text-sm">'+fmtRp2(p.HJ_Nasabah||p.HJ)+'</code></div>'
              +'<div><span class="font-semibold text-purple-700 dark:text-purple-300">HB Beli</span><br><code class="text-sm">'+fmtRp2(p.HB)+'</code>'+(p.src==='history'?'<br><span class="text-[10px] text-slate-400">dari history</span>':p.cnt?' ('+p.cnt+'x)':'')+'</div>';
          }
        });
      }

      /* manual edit kode / jenist → update TF visibility */
      if(pulsaKode){
        pulsaKode.addEventListener('input', updateTfVisibility);
      }
      if(pulsaJenistInput){
        pulsaJenistInput.addEventListener('change', updateTfVisibility);
      }

      /* Inisialisasi visibility Transfer Dana */
      updateTfVisibility();

    })();
    </script>
    <?php
}

// ─── RENDER: ANTRIAN PERSETUJUAN ──────────────────────────────────────
function renderTrxApprovePage(array $pendingDanamon, array $pendingDwallet, array $pendingQris, array $pulsaPending, int $totalPending, array $danamonConfigs = []): void {
    ?>
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Antrian Persetujuan Transaksi
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          <?php if ($totalPending === 0): ?>
            Semua transaksi sudah diproses. Tidak ada yang menunggu persetujuan.
          <?php else: ?>
            Terdapat <strong class="text-amber-600"><?= $totalPending ?></strong> transaksi menunggu persetujuan.
          <?php endif; ?>
        </p>
      </div>
      <div class="flex gap-2">
        <a href="?page=trx_create" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Buat Transaksi
        </a>
        <a href="?page=trx_approve" class="flex items-center gap-1 px-3 py-2 border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 text-sm rounded-lg hover:bg-slate-50 dark:hover:bg-gray-750 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Refresh
        </a>
      </div>
    </div>

    <?php if ($totalPending === 0): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm p-12 text-center">
      <svg class="w-12 h-12 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-slate-500 dark:text-slate-400 text-sm">Tidak ada transaksi yang menunggu persetujuan saat ini.</p>
    </div>
    <?php else: ?>

    <?php
    // Helper render approval table
    $renderApprovalTable = function(string $title, string $tableId, array $rows, string $dbTable, string $pkField, string $backPage, string $colorClass, string $iconPath) {
        if (empty($rows)) return;
        ?>
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-750">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 <?= $colorClass ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= h($iconPath) ?>"/>
          </svg>
          <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= h($title) ?></h2>
          <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"><?= count($rows) ?></span>
        </div>
        <!-- Bulk approve all -->
        <form method="POST" action="?page=trx_approve" onsubmit="return confirm('Setujui SEMUA transaksi <?= h($title) ?> yang pending?')" class="flex gap-2">
          <?php foreach ($rows as $r): ?>
          <input type="hidden" name="bulk_ids[]" value="<?= (int)$r['trx_id'] ?>">
          <?php endforeach; ?>
          <input type="hidden" name="table" value="<?= h($dbTable) ?>">
          <input type="hidden" name="pk" value="<?= h($pkField) ?>">
          <input type="hidden" name="bulk_action" value="approve">
          <input type="hidden" name="back" value="trx_approve">
          <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium transition-colors">
            ✓ Setujui Semua
          </button>
        </form>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">ID</th>
              <th class="px-4 py-3 text-left">Faktur / Ref</th>
              <th class="px-4 py-3 text-left">Info</th>
              <th class="px-4 py-3 text-left">Asal → Tujuan</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-left">Waktu</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= (int)$r['trx_id'] ?></td>
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 max-w-[140px] truncate" title="<?= h((string)($r['faktur'] ?? '')) ?>"><?= h((string)($r['faktur'] ?? '—')) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium"><?= h((string)($r['info'] ?? '—')) ?></span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">
                <?php $src = (string)($r['src'] ?? ''); $dst = (string)($r['dst'] ?? ''); ?>
                <?= $src ? h($src) : '<span class="text-slate-300">—</span>' ?>
                <?php if ($dst): ?><span class="mx-1 text-slate-300">→</span><?= h($dst) ?><?php endif; ?>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                <?= ($r['amount'] ?? 0) > 0 ? rupiah((float)$r['amount']) : '<span class="text-slate-400">—</span>' ?>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                <?php
                $ts = $r['ts'] ?? null;
                if (is_numeric($ts) && $ts > 1000000000) {
                    echo date('d/m/Y H:i', (int)$ts);
                } elseif ($ts) {
                    echo h(substr((string)$ts, 0, 16));
                } else {
                    echo '—';
                }
                ?>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi #<?= (int)$r['trx_id'] ?>?')">
                    <input type="hidden" name="table" value="<?= h($dbTable) ?>">
                    <input type="hidden" name="pk" value="<?= h($pkField) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['trx_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="<?= h($backPage) ?>">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-100 text-green-700 hover:bg-green-600 hover:text-white dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-600 dark:hover:text-white transition-colors">
                      ✓ Setuju
                    </button>
                  </form>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi #<?= (int)$r['trx_id'] ?>?')">
                    <input type="hidden" name="table" value="<?= h($dbTable) ?>">
                    <input type="hidden" name="pk" value="<?= h($pkField) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['trx_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="<?= h($backPage) ?>">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-600 hover:text-white dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-600 dark:hover:text-white transition-colors">
                      ✕ Tolak
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    };

    $renderApprovalTable('Transaksi Bank (Danamon)', 'danamon', $pendingDanamon,
        'danamon_transactions', 'id', 'trx_danamon',
        'text-indigo-600', 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4');

    $renderApprovalTable('D-Wallet', 'dwallet', $pendingDwallet,
        'dwallet_transactions', 'id', 'trx_dwallet',
        'text-blue-600', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z');

    $renderApprovalTable('Transaksi QRIS', 'qris', $pendingQris,
        'qris_transactions', 'id', 'trx_qris',
        'text-orange-600', 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01');

    // Pulsa: custom render with danamon_config_id dropdown for PAYBIFAST/PAYTFDANA
    if (!empty($pulsaPending)):
        $byTable = [];
        foreach ($pulsaPending as $r) {
            $t = $r['_table'];
            $byTable[$t][] = $r;
        }
        foreach ($byTable as $tbl => $tRows): ?>
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-750">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Penjualan Pulsa</h2>
          <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"><?= count($tRows) ?></span>
        </div>
        <?php if (!empty($danamonConfigs)): ?>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.608 3.292 0z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Config aktif: <strong class="text-slate-700 dark:text-slate-300"><?= h($danamonConfigs[0]['name'] ?? '-') ?></strong>
        </div>
        <?php else: ?>
        <a href="?page=danamon_configs" class="text-xs text-red-500 hover:underline">⚠ Belum ada Danamon Config aktif</a>
        <?php endif; ?>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-gray-700">
              <th class="px-4 py-3 text-left">ID</th>
              <th class="px-4 py-3 text-left">Nomor / Ref</th>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Customer → HP</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-left">Waktu</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
            <?php foreach ($tRows as $r):
                // Deteksi SNAP: utamakan Kode produk (PAYBIFAST/PAYTFDANA),
                // fallback ke JenisTrx=13 jika Kode tidak tersedia
                $rKode  = strtoupper((string)($r['kode'] ?? ''));
                $isSnap = in_array($rKode, ['PAYBIFAST','PAYTFDANA'])
                          || (string)($r['info'] ?? '') === '13';
            ?>
            <tr class="hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= (int)$r['trx_id'] ?></td>
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 max-w-[140px] truncate"><?= h((string)($r['faktur'] ?? '—')) ?></td>
              <td class="px-4 py-3">
                <?php if ($rKode): ?>
                <span class="px-2 py-0.5 text-xs rounded-full <?= $isSnap ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-slate-300' ?> font-medium"><?= h($rKode) ?></span>
                <?php if ($r['info'] ?? ''): ?><span class="text-slate-400 text-xs ml-1">JT<?= h((string)$r['info']) ?></span><?php endif; ?>
                <?php else: ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-slate-300 font-medium">JenisTrx <?= h((string)($r['info'] ?? '—')) ?></span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">
                <?= h((string)($r['src'] ?? '')) ?>
                <?php if ($r['dst']): ?><span class="mx-1 text-slate-300">→</span><?= h((string)$r['dst']) ?><?php endif; ?>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                <?= ($r['amount'] ?? 0) > 0 ? rupiah((float)$r['amount']) : '<span class="text-slate-400">—</span>' ?>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                <?php echo is_numeric($r['ts']) && $r['ts'] > 1e9 ? date('d/m/Y H:i', (int)$r['ts']) : h(substr((string)($r['ts'] ?? ''), 0, 16)); ?>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-col items-center gap-1.5">
                  <?php if ($isSnap && !empty($danamonConfigs)): ?>
                  <!-- SNAP: dropdown config + tombol Kirim ke Transporter -->
                  <form method="POST" action="?page=trx_approve" class="flex flex-col gap-1" onsubmit="return confirm('Kirim <?= h($rKode ?: 'SNAP') ?> #<?= (int)$r['trx_id'] ?> ke Transporter Danamon?')">
                    <input type="hidden" name="table" value="<?= h($tbl) ?>">
                    <input type="hidden" name="pk" value="ID">
                    <input type="hidden" name="id" value="<?= (int)$r['trx_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_approve">
                    <?php if (count($danamonConfigs) > 1): ?>
                    <select name="danamon_config_id" class="text-xs px-2 py-1 border border-slate-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-slate-700 dark:text-slate-200 mb-0.5">
                      <?php foreach ($danamonConfigs as $dc): ?>
                      <option value="<?= (int)$dc['id'] ?>"><?= h($dc['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="danamon_config_id" value="<?= (int)($danamonConfigs[0]['id'] ?? 0) ?>">
                    <?php endif; ?>
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-600 hover:bg-purple-700 text-white transition-colors whitespace-nowrap">
                      ⚡ Kirim SNAP
                    </button>
                  </form>
                  <?php elseif ($isSnap): ?>
                  <a href="?page=danamon_configs" class="px-3 py-1.5 text-xs rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition-colors">⚠ Setup Config</a>
                  <?php else: ?>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Setujui transaksi #<?= (int)$r['trx_id'] ?>?')">
                    <input type="hidden" name="table" value="<?= h($tbl) ?>">
                    <input type="hidden" name="pk" value="ID">
                    <input type="hidden" name="id" value="<?= (int)$r['trx_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="back" value="trx_approve">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-100 text-green-700 hover:bg-green-600 hover:text-white transition-colors">✓ Setuju</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" action="?page=trx_approve" class="inline" onsubmit="return confirm('Tolak transaksi #<?= (int)$r['trx_id'] ?>?')">
                    <input type="hidden" name="table" value="<?= h($tbl) ?>">
                    <input type="hidden" name="pk" value="ID">
                    <input type="hidden" name="id" value="<?= (int)$r['trx_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="back" value="trx_approve">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-600 hover:text-white transition-colors">✕ Tolak</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
        <?php endforeach;
    endif;
    ?>
    <?php endif; ?>
    <?php
}
