<?php
/**
 * 9 Minecraft Shop - API Backend
 * File: api/api.php
 * 
 * تمام عملیات سایت از این فایل مدیریت می‌شود
 * داده‌ها در فایل‌های JSON در پوشه data/ ذخیره می‌شوند
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── مسیر فایل‌های داده ───────────────────────────────
define('DATA_DIR', __DIR__ . '/../data/');
define('USERS_FILE',    DATA_DIR . 'users.json');
define('ORDERS_FILE',   DATA_DIR . 'orders.json');
define('TICKETS_FILE',  DATA_DIR . 'tickets.json');
define('PAYMENT_FILE',  DATA_DIR . 'payment.json');
define('STAFF_FILE',    DATA_DIR . 'staff_apps.json');

// ─── توابع خواندن / نوشتن JSON ───────────────────────
function readJSON($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

function writeJSON($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

function readJSONObj($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

// ─── پاسخ JSON ────────────────────────────────────────
function ok($data = [], $msg = '') {
    echo json_encode(['ok' => true, 'data' => $data, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── دریافت ورودی ─────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

// ─── روتر اصلی ────────────────────────────────────────
switch ($action) {

    // ══ احراز هویت ══
    case 'login':       doLogin($input);        break;
    case 'register':    doRegister($input);     break;
    case 'logout':      doLogout();             break;
    case 'me':          getMe();                break;

    // ══ پروفایل کاربر ══
    case 'update_profile':  updateProfile($input);  break;
    case 'change_pw':       changePw($input);        break;
    case 'link_mc':         linkMC($input);          break;
    case 'unlink_mc':       unlinkMC();              break;

    // ══ سفارشات ══
    case 'create_order':    createOrder($input);    break;
    case 'my_orders':       myOrders();             break;

    // ══ تیکت‌ها ══
    case 'create_ticket':   createTicket($input);   break;
    case 'my_tickets':      myTickets();            break;
    case 'ticket_reply':    ticketReply($input);    break;
    case 'get_ticket':      getTicket($input);      break;

    // ══ پرداخت ══
    case 'get_payment_info': getPaymentInfo();      break;

    // ══ ادمین ══
    case 'admin_stats':         adminStats();               break;
    case 'admin_users':         adminUsers($input);         break;
    case 'admin_all_orders':    adminAllOrders($input);     break;
    case 'admin_order_action':  adminOrderAction($input);   break;
    case 'admin_all_tickets':   adminAllTickets($input);    break;
    case 'admin_ticket_reply':  adminTicketReply($input);   break;
    case 'admin_ticket_status': adminTicketStatus($input);  break;
    case 'admin_set_rank':      adminSetRank($input);       break;
    case 'admin_ban_user':      adminBanUser($input);       break;
    case 'admin_pay_settings':  adminPaySettings($input);   break;
    case 'admin_add_admin':     adminAddAdmin($input);      break;
    case 'admin_remove_admin':  adminRemoveAdmin($input);   break;
    case 'admin_staff_apps':    adminStaffApps();           break;

    default: err('عملیات نامعتبر است');
}

// ══════════════════════════════════════════════════════
// ── توابع احراز هویت ──────────────────────────────────
// ══════════════════════════════════════════════════════

function doLogin($d) {
    $u = trim($d['u'] ?? '');
    $pw = $d['pw'] ?? '';
    if (!$u || !$pw) err('نام کاربری و رمز عبور الزامی است');
    
    $users = readJSON(USERS_FILE);
    $found = null;
    foreach ($users as $user) {
        if ($user['u'] === $u && ($user['pw_plain'] === $pw || $user['pw'] === md5($pw))) {
            $found = $user; break;
        }
    }
    if (!$found) err('نام کاربری یا رمز عبور اشتباه است');
    if (!empty($found['banned'])) err('حساب شما مسدود شده است');
    
    $_SESSION['uid'] = $found['id'];
    $safe = safeUser($found);
    ok($safe, 'خوش آمدید ' . $found['fn']);
}

function doRegister($d) {
    $fn = trim($d['fn'] ?? '');
    $ln = trim($d['ln'] ?? '');
    $u  = strtolower(trim($d['u'] ?? ''));
    $e  = trim($d['email'] ?? '');
    $pw = $d['pw'] ?? '';
    
    if (!$u || !$pw || !$e) err('همه فیلدها الزامی است');
    if (strlen($pw) < 6) err('رمز عبور حداقل ۶ کاراکتر باشد');
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) err('ایمیل معتبر نیست');
    if (!preg_match('/^[a-z0-9_]+$/', $u)) err('نام کاربری فقط حرف انگلیسی، عدد و _ مجاز است');
    
    $users = readJSON(USERS_FILE);
    foreach ($users as $user) {
        if ($user['u'] === $u) err('این نام کاربری قبلاً ثبت شده است');
    }
    
    $ids = array_column($users, 'id');
    $newId = $ids ? max($ids) + 1 : 1;
    
    $newUser = [
        'id' => $newId, 'u' => $u, 'pw' => md5($pw), 'pw_plain' => $pw,
        'fn' => $fn, 'ln' => $ln, 'email' => $e, 'ph' => '', 'pv' => '',
        'rank' => '—', 'coins' => 0, 'mc' => '', 'isAdmin' => false,
        'lvl' => '', 'banned' => false, 'created' => date('Y-m-d H:i:s')
    ];
    $users[] = $newUser;
    writeJSON(USERS_FILE, $users);
    
    $_SESSION['uid'] = $newId;
    ok(safeUser($newUser), 'ثبت‌نام موفق');
}

function doLogout() {
    session_destroy();
    ok([], 'خروج موفق');
}

function getMe() {
    requireAuth();
    $user = getCurrentUser();
    ok(safeUser($user));
}

// ══════════════════════════════════════════════════════
// ── توابع پروفایل ─────────────────────────────────────
// ══════════════════════════════════════════════════════

function updateProfile($d) {
    requireAuth();
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $_SESSION['uid']);
    if ($idx === -1) err('کاربر یافت نشد');
    
    $users[$idx]['fn'] = trim($d['fn'] ?? $users[$idx]['fn']);
    $users[$idx]['ln'] = trim($d['ln'] ?? $users[$idx]['ln']);
    $users[$idx]['email'] = trim($d['email'] ?? $users[$idx]['email']);
    $users[$idx]['ph'] = trim($d['ph'] ?? $users[$idx]['ph']);
    $users[$idx]['pv'] = trim($d['pv'] ?? $users[$idx]['pv']);
    writeJSON(USERS_FILE, $users);
    ok(safeUser($users[$idx]), 'پروفایل ذخیره شد');
}

function changePw($d) {
    requireAuth();
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $_SESSION['uid']);
    $u = $users[$idx];
    
    $old = $d['old'] ?? '';
    $new = $d['new'] ?? '';
    $cf  = $d['cf'] ?? '';
    
    if ($u['pw_plain'] !== $old && $u['pw'] !== md5($old)) err('رمز فعلی اشتباه است');
    if ($new !== $cf) err('رمزهای جدید مطابقت ندارند');
    if (strlen($new) < 6) err('رمز جدید حداقل ۶ کاراکتر باشد');
    
    $users[$idx]['pw'] = md5($new);
    $users[$idx]['pw_plain'] = $new;
    writeJSON(USERS_FILE, $users);
    ok([], 'رمز عبور تغییر یافت');
}

function linkMC($d) {
    requireAuth();
    $mc = trim($d['mc'] ?? '');
    if (!$mc) err('یوزرنیم ماینکرفت الزامی است');
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $_SESSION['uid']);
    $users[$idx]['mc'] = $mc;
    writeJSON(USERS_FILE, $users);
    ok(['mc' => $mc], 'اکانت ماینکرفت متصل شد');
}

function unlinkMC() {
    requireAuth();
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $_SESSION['uid']);
    $users[$idx]['mc'] = '';
    writeJSON(USERS_FILE, $users);
    ok([], 'اتصال قطع شد');
}

// ══════════════════════════════════════════════════════
// ── توابع سفارش ───────────────────────────────────────
// ══════════════════════════════════════════════════════

function createOrder($d) {
    requireAuth();
    $item  = trim($d['item'] ?? '');
    $price = intval($d['price'] ?? 0);
    $ref   = trim($d['ref'] ?? '');
    if (!$item || !$price || !$ref) err('اطلاعات سفارش ناقص است');
    
    $orders = readJSON(ORDERS_FILE);
    $ids = array_column($orders, 'id');
    $newId = $ids ? max($ids) + 1 : 1001;
    
    $user = getCurrentUser();
    $order = [
        'id' => $newId, 'uid' => $user['id'], 'user' => $user['u'],
        'item' => $item, 'price' => $price, 'ref' => $ref,
        'date' => date('Y-m-d H:i:s'), 'st' => 'pending'
    ];
    $orders[] = $order;
    writeJSON(ORDERS_FILE, $orders);
    ok($order, 'سفارش ثبت شد');
}

function myOrders() {
    requireAuth();
    $orders = readJSON(ORDERS_FILE);
    $uid = $_SESSION['uid'];
    $mine = array_values(array_filter($orders, fn($o) => $o['uid'] == $uid));
    rsort($mine);
    ok($mine);
}

// ══════════════════════════════════════════════════════
// ── توابع تیکت ────────────────────────────────────────
// ══════════════════════════════════════════════════════

function createTicket($d) {
    requireAuth();
    $cat  = trim($d['cat'] ?? '');
    $subj = trim($d['subj'] ?? '');
    $body = trim($d['body'] ?? '');
    if (!$cat || !$subj || !$body) err('همه فیلدها الزامی است');
    
    $user = getCurrentUser();
    $tickets = readJSON(TICKETS_FILE);
    $ids = array_column($tickets, 'id');
    $newId = $ids ? max($ids) + 1 : 1;
    
    $tkt = [
        'id' => $newId, 'uid' => $user['id'], 'uname' => $user['u'],
        'fn' => $user['fn'], 'cat' => $cat, 'subj' => $subj,
        'st' => 'open', 'date' => date('Y-m-d H:i:s'),
        'msgs' => [[
            'from' => 'user', 'name' => $user['fn'] ?: $user['u'],
            'text' => $body, 'time' => date('Y-m-d H:i:s')
        ]]
    ];
    $tickets[] = $tkt;
    writeJSON(TICKETS_FILE, $tickets);
    ok($tkt, 'تیکت ارسال شد');
}

function myTickets() {
    requireAuth();
    $tickets = readJSON(TICKETS_FILE);
    $uid = $_SESSION['uid'];
    $mine = array_values(array_filter($tickets, fn($t) => $t['uid'] == $uid));
    usort($mine, fn($a, $b) => strcmp($b['date'], $a['date']));
    ok($mine);
}

function getTicket($d) {
    requireAuth();
    $id = intval($d['id'] ?? 0);
    $tickets = readJSON(TICKETS_FILE);
    foreach ($tickets as $t) {
        if ($t['id'] == $id) {
            $uid = $_SESSION['uid'];
            $user = getCurrentUser();
            if ($t['uid'] != $uid && !$user['isAdmin']) err('دسترسی غیرمجاز', 403);
            ok($t);
        }
    }
    err('تیکت یافت نشد', 404);
}

function ticketReply($d) {
    requireAuth();
    $id   = intval($d['id'] ?? 0);
    $text = trim($d['text'] ?? '');
    if (!$id || !$text) err('اطلاعات ناقص است');
    
    $user = getCurrentUser();
    $tickets = readJSON(TICKETS_FILE);
    foreach ($tickets as &$t) {
        if ($t['id'] == $id && $t['uid'] == $user['id']) {
            $t['msgs'][] = ['from' => 'user', 'name' => $user['fn'] ?: $user['u'], 'text' => $text, 'time' => date('Y-m-d H:i:s')];
            $t['st'] = 'pending';
            writeJSON(TICKETS_FILE, $tickets);
            ok($t);
        }
    }
    err('تیکت یافت نشد');
}

// ══════════════════════════════════════════════════════
// ── اطلاعات پرداخت (عمومی) ────────────────────────────
// ══════════════════════════════════════════════════════

function getPaymentInfo() {
    $ps = readJSONObj(PAYMENT_FILE);
    if (empty($ps['active'])) err('پرداخت در حال حاضر غیرفعال است');
    $card = $ps['card'] ?? '';
    if (strlen($card) == 16) {
        $card = implode('-', str_split($card, 4));
    }
    ok(['card' => $card, 'owner' => $ps['owner'] ?? '', 'bank' => $ps['bank'] ?? '']);
}

// ══════════════════════════════════════════════════════
// ── توابع ادمین ───────────────────────────────────────
// ══════════════════════════════════════════════════════

function adminStats() {
    requireAdmin();
    $users   = readJSON(USERS_FILE);
    $orders  = readJSON(ORDERS_FILE);
    $tickets = readJSON(TICKETS_FILE);
    ok([
        'users'       => count($users),
        'admins'      => count(array_filter($users, fn($u) => $u['isAdmin'])),
        'mc_linked'   => count(array_filter($users, fn($u) => !empty($u['mc']))),
        'orders'      => count($orders),
        'pending_orders' => count(array_filter($orders, fn($o) => $o['st'] === 'pending')),
        'open_tickets'   => count(array_filter($tickets, fn($t) => $t['st'] === 'open')),
        'total_revenue'  => array_sum(array_column(array_filter($orders, fn($o) => $o['st'] === 'done'), 'price')),
    ]);
}

function adminUsers($d) {
    requireAdmin();
    $q = strtolower(trim($d['q'] ?? ''));
    $users = readJSON(USERS_FILE);
    if ($q) $users = array_values(array_filter($users, fn($u) => str_contains(strtolower($u['u']), $q) || str_contains(strtolower($u['email']), $q)));
    $safe = array_map('safeUser', $users);
    ok($safe);
}

function adminAllOrders($d) {
    requireAdmin();
    $filter = $d['filter'] ?? 'all';
    $orders = readJSON(ORDERS_FILE);
    if ($filter !== 'all') $orders = array_values(array_filter($orders, fn($o) => $o['st'] === $filter));
    usort($orders, fn($a, $b) => strcmp($b['date'], $a['date']));
    ok($orders);
}

function adminOrderAction($d) {
    requireAdmin();
    $id     = intval($d['id'] ?? 0);
    $action = $d['act'] ?? '';
    if (!in_array($action, ['done', 'rejected'])) err('عملیات نامعتبر');
    
    $orders = readJSON(ORDERS_FILE);
    $found = false;
    foreach ($orders as &$o) {
        if ($o['id'] == $id) { $o['st'] = $action; $found = true; break; }
    }
    if (!$found) err('سفارش یافت نشد');
    writeJSON(ORDERS_FILE, $orders);
    ok([], $action === 'done' ? 'سفارش تأیید شد' : 'سفارش رد شد');
}

function adminAllTickets($d) {
    requireAdmin();
    $filter = $d['filter'] ?? 'all';
    $tickets = readJSON(TICKETS_FILE);
    if ($filter !== 'all') $tickets = array_values(array_filter($tickets, fn($t) => $t['st'] === $filter));
    usort($tickets, fn($a, $b) => strcmp($b['date'], $a['date']));
    ok($tickets);
}

function adminTicketReply($d) {
    requireAdmin();
    $id   = intval($d['id'] ?? 0);
    $text = trim($d['text'] ?? '');
    if (!$id || !$text) err('اطلاعات ناقص');
    $admin = getCurrentUser();
    $tickets = readJSON(TICKETS_FILE);
    foreach ($tickets as &$t) {
        if ($t['id'] == $id) {
            $t['msgs'][] = ['from' => 'admin', 'name' => 'پشتیبانی 9MC', 'text' => $text, 'time' => date('Y-m-d H:i:s')];
            $t['st'] = 'open';
            writeJSON(TICKETS_FILE, $tickets);
            ok($t);
        }
    }
    err('تیکت یافت نشد');
}

function adminTicketStatus($d) {
    requireAdmin();
    $id = intval($d['id'] ?? 0);
    $st = $d['st'] ?? '';
    if (!in_array($st, ['open', 'pending', 'closed'])) err('وضعیت نامعتبر');
    $tickets = readJSON(TICKETS_FILE);
    foreach ($tickets as &$t) {
        if ($t['id'] == $id) { $t['st'] = $st; writeJSON(TICKETS_FILE, $tickets); ok([]); }
    }
    err('تیکت یافت نشد');
}

function adminSetRank($d) {
    requireAdmin();
    $uid  = intval($d['uid'] ?? 0);
    $rank = trim($d['rank'] ?? '');
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $uid);
    if ($idx === -1) err('کاربر یافت نشد');
    $users[$idx]['rank'] = $rank;
    writeJSON(USERS_FILE, $users);
    ok([], 'رنک تغییر یافت');
}

function adminBanUser($d) {
    requireAdmin();
    $uid = intval($d['uid'] ?? 0);
    $ban = (bool)($d['ban'] ?? true);
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $uid);
    if ($idx === -1) err('کاربر یافت نشد');
    if (!empty($users[$idx]['isAdmin'])) err('نمی‌توانید ادمین را بن کنید');
    $users[$idx]['banned'] = $ban;
    writeJSON(USERS_FILE, $users);
    ok([], $ban ? 'کاربر بن شد' : 'بن کاربر برداشته شد');
}

function adminPaySettings($d) {
    requireAdmin();
    $ps = ['card' => trim($d['card'] ?? ''), 'owner' => trim($d['owner'] ?? ''), 'bank' => trim($d['bank'] ?? ''), 'active' => (bool)($d['active'] ?? false)];
    writeJSON(PAYMENT_FILE, $ps);
    ok($ps, 'تنظیمات پرداخت ذخیره شد');
}

function adminAddAdmin($d) {
    requireAdmin();
    requireSuperAdmin();
    $fn = trim($d['fn'] ?? '');
    $ln = trim($d['ln'] ?? '');
    $u  = strtolower(trim($d['u'] ?? ''));
    $e  = trim($d['email'] ?? '');
    $pw = $d['pw'] ?? '';
    $lv = $d['lv'] ?? 'support';
    if (!$u || !$pw || !$e) err('همه فیلدها الزامی است');
    if (strlen($pw) < 6) err('رمز حداقل ۶ کاراکتر');
    $users = readJSON(USERS_FILE);
    foreach ($users as $usr) { if ($usr['u'] === $u) err('این نام کاربری وجود دارد'); }
    $ids = array_column($users, 'id');
    $newId = $ids ? max($ids) + 1 : 1;
    $newAdmin = ['id' => $newId, 'u' => $u, 'pw' => md5($pw), 'pw_plain' => $pw, 'fn' => $fn, 'ln' => $ln, 'email' => $e, 'ph' => '', 'pv' => '', 'rank' => '—', 'coins' => 0, 'mc' => '', 'isAdmin' => true, 'lvl' => $lv, 'banned' => false, 'created' => date('Y-m-d H:i:s')];
    $users[] = $newAdmin;
    writeJSON(USERS_FILE, $users);
    ok(safeUser($newAdmin), 'ادمین جدید اضافه شد');
}

function adminRemoveAdmin($d) {
    requireAdmin();
    requireSuperAdmin();
    $uid = intval($d['uid'] ?? 0);
    if ($uid == 1) err('ادمین اصلی قابل حذف نیست');
    $users = readJSON(USERS_FILE);
    $idx = findUserIdx($users, $uid);
    if ($idx === -1) err('کاربر یافت نشد');
    $users[$idx]['isAdmin'] = false;
    $users[$idx]['lvl'] = '';
    writeJSON(USERS_FILE, $users);
    ok([], 'دسترسی ادمین حذف شد');
}

function adminStaffApps() {
    requireAdmin();
    $apps = readJSON(STAFF_FILE);
    usort($apps, fn($a, $b) => strcmp($b['date'], $a['date']));
    ok($apps);
}

// ══════════════════════════════════════════════════════
// ── توابع کمکی ────────────────────────────────────────
// ══════════════════════════════════════════════════════

function requireAuth() {
    if (empty($_SESSION['uid'])) err('لطفاً ابتدا وارد شوید', 401);
}

function requireAdmin() {
    requireAuth();
    $user = getCurrentUser();
    if (!$user['isAdmin']) err('دسترسی غیرمجاز', 403);
}

function requireSuperAdmin() {
    $user = getCurrentUser();
    if ($user['lvl'] !== 'superadmin') err('این عملیات نیاز به دسترسی سوپر ادمین دارد', 403);
}

function getCurrentUser() {
    $users = readJSON(USERS_FILE);
    $uid = $_SESSION['uid'];
    foreach ($users as $u) { if ($u['id'] == $uid) return $u; }
    err('کاربر یافت نشد', 404);
}

function findUserIdx($users, $uid) {
    foreach ($users as $i => $u) { if ($u['id'] == $uid) return $i; }
    return -1;
}

function safeUser($u) {
    unset($u['pw'], $u['pw_plain']);
    return $u;
}
