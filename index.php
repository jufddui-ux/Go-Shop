<?php
// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set('Asia/Kolkata');

// ============================================================
// ERRORS
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0); ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_error.log');
set_time_limit(120); ob_start();

// ============================================================
// CONFIG
// ============================================================
$botToken = getenv('BOT_TOKEN') ?: getenv('TOKEN') ?: "8787930939:AAGJM3aZSqISF-AxWfmw0TjzDpqoXYeDuh4";
$website  = "https://api.telegram.org/bot" . $botToken;
$adminID  = getenv('OWNER_ID') ?: getenv('ADMIN_ID') ?: "8671458115"; // REAL OWNER

// ============================================================
// DATA
// ============================================================
$BASE = __DIR__; $DATA = $BASE . '/data';
if (!is_dir($DATA)) { @mkdir($DATA, 0777, true); @chmod($DATA, 0777); }
if (!is_dir($DATA) || !is_writable($DATA)) {
    @file_put_contents("$BASE/bot_error.log", date('Y-m-d H:i:s') . " | FATAL: data folder\n", FILE_APPEND);
    ob_end_clean(); http_response_code(500); exit;
}
$FILES = ["balances","orders","temp","users","settings","categories","products","payments","used_utrs"];
foreach ($FILES as $f) { $p = "$DATA/$f.json"; if (!file_exists($p)) { @file_put_contents($p, "{}"); @chmod($p, 0666); } }

function loadJson($file) { global $DATA; $p = "$DATA/$file.json"; if (!file_exists($p)) return []; $raw = @file_get_contents($p); if ($raw === false) return []; $j = json_decode($raw, true); return is_array($j) ? $j : []; }
function saveJson($file, $data) { global $DATA; $p = "$DATA/$file.json"; $r = @file_put_contents($p, json_encode($data), LOCK_EX); if ($r === false) @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s')." | FAILED write: $file\n", FILE_APPEND); return $r; }

$settings   = loadJson("settings");
$balances   = loadJson("balances");
$users      = loadJson("users");
$orders     = loadJson("orders");
$products   = loadJson("products");
$categories = loadJson("categories");
$payments   = loadJson("payments");
$used_utrs  = loadJson("used_utrs");
$temp       = loadJson("temp");

$def = [
    'currency' => '₹', 'usdt_rate' => 95,
    'auto_api_key' => 'YOUR_API_KEY',
    'auto_api_url' => 'https://vcgatewaypro.com/fampay.php',
    'auto_upi' => 'yourupi@upi', 'auto_name' => 'DigitalStore', 'use_auto' => true,
    'proof_link' => 'https://t.me/YourProofChannel',
    'howto_link' => 'https://t.me/YourHowToVideo',
    'support_user' => '@YourSupportUsername',
    'payment_methods' => [],
    'log_channel' => '',
    'admins' => ['8671458115', '8942668565'],
    'banned' => [],
    'maintenance' => false,
    'maintenance_msg' => "🛠 <b>Bot Under Maintenance</b>\n\n━━━━━━━━━━━\n\n⚠️ The bot is currently <b>under maintenance</b>.\n\n⏳ Please try again in a few minutes.\n\n🙏 Thank you for your patience!",
    'use_button_colors' => false,
    'custom_buttons' => [],
    'button_toggles' => [
        'shop' => true, 'orders' => true, 'profile' => true,
        'addbal' => true, 'proof' => true, 'howto' => true, 'support' => true
    ]
];
$changed = false;
foreach ($def as $k => $v) { if (!array_key_exists($k, $settings)) { $settings[$k] = $v; $changed = true; } }
if ($changed) saveJson("settings", $settings);

// ============================================================
// HTTP HELPERS
// ============================================================
// ============================================================
// HTTP
// ============================================================
function httpPost($url, $data, $timeout = 20) {
    $post = is_array($data) ? http_build_query($data) : $data;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        $r = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($r !== false && strlen($r) > 0) return $r;
        if (!empty($err)) {
            @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s')." | CURL POST error: $err
", FILE_APPEND);
        }
    }
    if (function_exists('shell_exec')) {
        $cmd = "curl -k -s -X POST -d " . escapeshellarg($post) . " --max-time {$timeout} " . escapeshellarg($url);
        $r = @shell_exec($cmd);
        if (!empty($r)) return $r;
    }
    $ctx = stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => $timeout, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    return @file_get_contents($url, false, $ctx);
}

function httpGet($url, $timeout = 25) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $r = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($r !== false && strlen($r) > 0) return $r;
        if (!empty($err)) {
            @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s')." | CURL GET error: $err
", FILE_APPEND);
        }
    }
    if (function_exists('shell_exec')) {
        $cmd = "curl -k -s -L --max-time {$timeout} " . escapeshellarg($url);
        $r = @shell_exec($cmd);
        if (!empty($r)) return $r;
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    return @file_get_contents($url, false, $ctx);
}
function httpGet($url, $timeout = 25) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $ctx);
}

// ============================================================
// WEBHOOK SETUP & AUTO-LINKING
// ============================================================
$webhookMarker = $DATA . '/.webhook_set';
if (!file_exists($webhookMarker)) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host) && strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
        $targetUrl = "https://{$host}/bot.php";
        $setRes = httpGet("https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($targetUrl));
        if ($setRes && stripos($setRes, '"ok":true') !== false) {
            @file_put_contents($webhookMarker, date('Y-m-d H:i:s') . " | " . $targetUrl);
        } else {
            @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s') . " | AUTO WEBHOOK FAILED: " . ($setRes ?: 'No response') . "
", FILE_APPEND);
        }
    }
}

if (isset($_GET['setwebhook'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $customUrl = $_GET['url'] ?? '';
    $targetUrl = !empty($customUrl) ? $customUrl : "{$proto}://{$host}/bot.php";
    $tgApi = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($targetUrl);
    $res = httpGet($tgApi);
    @file_put_contents($webhookMarker, date('Y-m-d H:i:s') . " | MANUAL | " . $targetUrl);
    if (empty($res)) {
        $res = json_encode(['ok' => false, 'description' => 'Empty response from Telegram API', 'tried' => $tgApi]);
    }
    echo $res;
    exit;
}

if (isset($_GET['deletewebhook'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $tgApi = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
    $res = httpGet($tgApi);
    @unlink($webhookMarker);
    echo $res ?: json_encode(['ok' => false, 'description' => 'Failed to reach Telegram API']);
    exit;
}

if (isset($_GET['webhookinfo'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $tgApi = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
    $res = httpGet($tgApi);
    echo $res ?: json_encode(['ok' => false, 'description' => 'Failed to reach Telegram API']);
    exit;
}

if (isset($_GET['errorlog'])) {
    ob_end_clean();
    header('Content-Type: text/plain');
    $logFile = __DIR__ . '/bot_error.log';
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    } else {
        echo "No errors logged yet.";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    header('Content-Type: application/json');
    $host = $_SERVER['HTTP_HOST'] ?? 'go-shop-tz28.onrender.com';
    echo json_encode([
        'ok' => true,
        'status' => 'online',
        'bot' => 'PHP Telegram Shop Bot',
        'webhook_auto_setup' => "https://{$host}/?setwebhook=1",
        'check_webhook' => "https://{$host}/?webhookinfo=1",
        'direct_telegram_link' => "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode("https://{$host}/bot.php")
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['deletewebhook'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $tgApi = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
    $res = @file_get_contents($tgApi);
    echo $res ?: json_encode(['ok' => false, 'description' => 'Failed to reach Telegram API']);
    exit;
}

if (isset($_GET['webhookinfo'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $tgApi = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
    $res = @file_get_contents($tgApi);
    echo $res ?: json_encode(['ok' => false, 'description' => 'Failed to reach Telegram API']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'status' => 'online',
        'message' => 'Telegram PHP Shop Bot is running successfully!',
        'setup_webhook' => '?setwebhook=1',
        'check_webhook' => '?webhookinfo=1'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// PARSE UPDATE
// ============================================================
$raw = @file_get_contents("php://input");
if (!$raw) { ob_end_clean(); echo '{"ok":true}'; exit; }
$upd = json_decode($raw, true);
if (!$upd) { ob_end_clean(); echo '{"ok":true}'; exit; }

$is_cb = isset($upd["callback_query"]);
if ($is_cb) {
    $cb = $upd["callback_query"];
    $chat_id = $cb["message"]["chat"]["id"] ?? 0;
    $mid     = $cb["message"]["message_id"] ?? 0;
    $uid     = $cb["from"]["id"] ?? 0;
    $fname   = $cb["from"]["first_name"] ?? "User";
    $uname   = $cb["from"]["username"] ?? "";
    $text    = ""; $data = $cb["data"] ?? ""; $cb_id = $cb["id"] ?? ""; $message = [];
} else {
    $message = $upd["message"] ?? [];
    $chat_id = $message["chat"]["id"] ?? 0;
    $mid     = $message["message_id"] ?? 0;
    $uid     = $message["from"]["id"] ?? 0;
    $fname   = $message["from"]["first_name"] ?? "User";
    $uname   = $message["from"]["username"] ?? "";
    $text    = $message["text"] ?? ""; $data = ""; $cb_id = "";
}
if (!$uid) { ob_end_clean(); echo '{"ok":true}'; exit; }

// ============================================================
// HELPERS: AUTH / BAN / MAINTENANCE
// ============================================================
function isOwner($u) { global $adminID; return (string)$u === (string)$adminID; }
function isAdmin($u) { global $adminID, $settings; if ((string)$u === (string)$adminID) return true; return in_array((string)$u, $settings['admins'] ?? [], true); }
function isBanned($u) { global $settings; return in_array((string)$u, $settings['banned'] ?? [], true); }
function isMaint() { global $settings; return !empty($settings['maintenance']); }
function btnOn($key) { global $settings; return !empty($settings['button_toggles'][$key]); }

// ============================================================
// MAINTENANCE CHECK — FIRST (before ban, before anything)
// ============================================================
if (isMaint() && !isAdmin($uid)) {
    if ($is_cb) { answerCbFast($cb_id); }
    sendMsg($chat_id, $settings['maintenance_msg']);
    ob_end_clean(); echo '{"ok":true}'; exit;
}

if (!isset($balances[$uid])) { $balances[$uid] = 0; saveJson("balances", $balances); }
if (!isset($users[$uid])) { $users[$uid] = ["name" => $fname, "username" => $uname, "join" => date("d M Y")]; saveJson("users", $users); }

// ============================================================
// BAN BLOCK
// ============================================================
if (isBanned($uid) && !isAdmin($uid)) {
    if ($is_cb) { answerCbFast($cb_id); }
    sendMsg($chat_id, "🚫 <b>Access Denied</b>\n\nYou have been banned from using this bot.\n\n💬 Contact support if you believe this is a mistake.");
    ob_end_clean(); echo '{"ok":true}'; exit;
}

// ============================================================
// STATE HELPERS
// ============================================================
function setState($uid, $s, $e = []) { global $temp; $temp[$uid] = array_merge(['waiting' => $s], $e); saveJson("temp", $temp); }
function clearState($uid) { global $temp; $temp[$uid] = ['waiting' => '']; saveJson("temp", $temp); }
function getState($uid) { global $temp; return $temp[$uid]['waiting'] ?? ''; }
function getExtra($uid, $k) { global $temp; return $temp[$uid][$k] ?? null; }


    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $ctx);
}

// ============================================================
// TELEGRAM API
// ============================================================
function tg($method, $params) {
    global $website;
    $res = httpPost("$website/$method", $params, 20);
    if ($res === false) { @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s')." | TG $method FAILED\n", FILE_APPEND); return $res; }
    if (stripos($res, '"ok":true') !== false || stripos($res, 'not modified') !== false) return $res;
    $j = json_decode($res, true);
    $desc = $j['description'] ?? substr($res, 0, 200);
    @file_put_contents(__DIR__."/bot_error.log", date('Y-m-d H:i:s')." | TG $method: $desc\n", FILE_APPEND);
    if (!empty($params['reply_markup']) && stripos($desc, 'style') !== false) {
        $rm = json_decode($params['reply_markup'], true);
        if (isset($rm['inline_keyboard'])) {
            $had = false;
            foreach ($rm['inline_keyboard'] as &$row) { foreach ($row as &$b) { if (isset($b['style'])) { unset($b['style']); $had = true; } } }
            if ($had) {
                $params['reply_markup'] = json_encode($rm);
                $s = loadJson('settings'); if (!empty($s['use_button_colors'])) { $s['use_button_colors'] = false; saveJson('settings', $s); }
                return tg($method, $params);
            }
        }
    }
    return $res;
}

function sendMsg($c, $t, $kb = null) { if (mb_strlen($t) > 4000) $t = mb_substr($t, 0, 3997) . "..."; $p = ['chat_id' => $c, 'text' => $t, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]; if ($kb) $p['reply_markup'] = $kb; return tg("sendMessage", $p); }
function editMsg($c, $mid, $t, $kb = null) { if (mb_strlen($t) > 4000) $t = mb_substr($t, 0, 3997) . "..."; $p = ['chat_id' => $c, 'message_id' => $mid, 'text' => $t, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]; if ($kb) $p['reply_markup'] = $kb; return tg("editMessageText", $p); }
function editCaption($c, $mid, $cap, $kb = null) { $p = ['chat_id' => $c, 'message_id' => $mid, 'caption' => $cap, 'parse_mode' => 'HTML']; if ($kb) $p['reply_markup'] = $kb; return tg("editMessageCaption", $p); }

function smartReply($c, $mid, $t, $kb = null) {
    if ($mid <= 0) return sendMsg($c, $t, $kb);
    if (mb_strlen($t) > 4000) $t = mb_substr($t, 0, 3997) . "...";
    $p = ['chat_id' => $c, 'message_id' => $mid, 'text' => $t, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($kb) $p['reply_markup'] = $kb;
    $r = tg("editMessageText", $p);
    if ($r && (stripos($r, '"ok":true') !== false || stripos($r, 'not modified') !== false)) return $r;
    $p2 = ['chat_id' => $c, 'message_id' => $mid, 'caption' => $t, 'parse_mode' => 'HTML'];
    if ($kb) $p2['reply_markup'] = $kb;
    $r2 = tg("editMessageCaption", $p2);
    if ($r2 && (stripos($r2, '"ok":true') !== false || stripos($r2, 'not modified') !== false)) return $r2;
    tg("deleteMessage", ['chat_id' => $c, 'message_id' => $mid]);
    return sendMsg($c, $t, $kb);
}
function sendPhoto($c, $p, $cap, $kb = null) { $d = ['chat_id' => $c, 'photo' => $p, 'caption' => $cap, 'parse_mode' => 'HTML']; if ($kb) $d['reply_markup'] = $kb; return tg("sendPhoto", $d); }
function sendVideo($c, $v, $cap, $kb = null) { $d = ['chat_id' => $c, 'video' => $v, 'caption' => $cap, 'parse_mode' => 'HTML']; if ($kb) $d['reply_markup'] = $kb; return tg("sendVideo", $d); }
function sendDoc($c, $d, $cap, $kb = null) { $p = ['chat_id' => $c, 'document' => $d, 'caption' => $cap, 'parse_mode' => 'HTML']; if ($kb) $p['reply_markup'] = $kb; return tg("sendDocument", $p); }
function sendVoice($c, $v, $cap, $kb = null) { $p = ['chat_id' => $c, 'voice' => $v, 'caption' => $cap, 'parse_mode' => 'HTML']; if ($kb) $p['reply_markup'] = $kb; return tg("sendVoice", $p); }
function answerCbFast($id) { global $website; return httpPost("$website/answerCallbackQuery", ['callback_query_id' => $id], 2); }
function delMsg($c, $mid) { if ($mid > 0) return tg("deleteMessage", ['chat_id' => $c, 'message_id' => $mid]); return null; }

// ============================================================
// QR
// ============================================================
function sendQrPhoto($c, $upi, $cap, $kb) {
    $enc = urlencode($upi);
    $srcs = ["https://quickchart.io/qr?text=$enc&size=500&margin=2", "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=$enc"];
    foreach ($srcs as $u) { $r = sendPhoto($c, $u, $cap, $kb); if ($r && stripos($r, '"ok":true') !== false) return true; }
    return false;
}

// ============================================================
// BUTTON BUILDER
// ============================================================
function isUrl($s) { return is_string($s) && (strpos($s, 'http://') === 0 || strpos($s, 'https://') === 0 || strpos($s, 'tg://') === 0); }
function btn($rows) {
    global $settings;
    $useColor = !empty($settings['use_button_colors']);
    $k = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        if (isset($r[0]) && is_array($r[0])) {
            $x = [];
            foreach ($r as $b) {
                if (!is_array($b)) continue;
                $bu = ["text" => $b[0]];
                if (isset($b[1])) { if (isUrl($b[1])) $bu['url'] = $b[1]; else $bu['callback_data'] = $b[1]; }
                if ($useColor && isset($b[2])) $bu['style'] = $b[2];
                $x[] = $bu;
            }
            $k[] = $x;
        } else {
            $bu = ["text" => $r[0]];
            if (isset($r[1])) { if (isUrl($r[1])) $bu['url'] = $r[1]; else $bu['callback_data'] = $r[1]; }
            if ($useColor && isset($r[2])) $bu['style'] = $r[2];
            $k[] = [$bu];
        }
    }
    return json_encode(["inline_keyboard" => $k]);
}

// ============================================================
// ROUTING
// ============================================================
if ($is_cb) { answerCbFast($cb_id); routeCallback($chat_id, $mid, $uid, $fname, $data); }
elseif ($text == "/start" || strpos($text, "/start") === 0) { showMainMenu($chat_id, 0, $uid, $fname); }
elseif ($text == "/admin" || strpos($text, "/admin") === 0) { if (!isAdmin($uid)) sendMsg($chat_id, "❌ Access denied"); else showAdmin($chat_id, 0); }
elseif ($text == "/cancel") { clearState($uid); sendMsg($chat_id, "✅ Cancelled", btn([[["⬅️ Menu", "back", "danger"]]])); }
elseif ($text != "") { handleTextState($chat_id, 0, $uid, $text); }
elseif (!empty($message)) { handleMediaState($chat_id, 0, $uid, $message); }

ob_end_clean(); echo '{"ok":true}'; exit;

// ============================================================
// CALLBACK ROUTER
// ============================================================
function routeCallback($c, $mid, $uid, $fname, $data) {
    global $adminID, $settings, $balances, $products, $categories, $orders, $payments, $users, $used_utrs;

    $current_state = getState($uid);
    $back_callbacks = ['back', 'backshop', 'backadmin'];
    $continue_callbacks = ['utr_prompt'];

    $should_delete = in_array($data, $back_callbacks);
    if (!empty($current_state) && !in_array($data, $continue_callbacks)) {
        $should_delete = true;
        clearState($uid);
    }
    if ($should_delete && $mid > 0) { delMsg($c, $mid); $mid = 0; }

    // USER NAV
    if ($data == "back") { showMainMenu($c, $mid, $uid, $fname); return; }
    if ($data == "shop") { showCategories($c, $mid); return; }
    if ($data == "profile") { showProfile($c, $mid, $uid); return; }
    if ($data == "orders") { showOrders($c, $mid, $uid); return; }
    if ($data == "proof") { showProof($c, $mid); return; }
    if ($data == "howto") { showHowTo($c, $mid); return; }
    if ($data == "support") { showSupport($c, $mid); return; }
    if ($data == "addbal") { showAddFund($c, $mid, $uid); return; }
    if ($data == "backshop") { showCategories($c, $mid); return; }
    if (strpos($data, "cat_") === 0) { showProducts($c, $mid, substr($data, 4)); return; }
    if (strpos($data, "buy_") === 0) { showProductDetail($c, $mid, $uid, substr($data, 4)); return; }
    if (strpos($data, "confirmbuy_") === 0) { doPurchase($c, $mid, $uid, substr($data, 11)); return; }

    // PAYMENT
    if (strpos($data, "pm_") === 0 && strpos($data, "pm_menu") !== 0 && strpos($data, "pm_users") !== 0) { selectManualPM($c, $mid, substr($data, 3)); return; }
    if ($data == "auto_pay") { startAutoPayment($c, $mid, $uid); return; }
    if (strpos($data, "manual_amt_") === 0) { startManualAmount($c, $mid, $uid, substr($data, 11)); return; }
    if ($data == "utr_prompt") {
        $amt = getExtra($uid, 'amount');
        if (!$amt) { smartReply($c, $mid, "❌ Session expired.", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
        setState($uid, "auto_utr", ["amount" => $amt]);
        smartReply($c, $mid, "🔢 <b>Enter UTR / Transaction ID</b>\n\nExample: 1234567890123", btn([[["❌ Cancel", "addbal", "danger"]]]));
        return;
    }

    // ADMIN GATE
    if (!isAdmin($uid)) { smartReply($c, $mid, "❌ Unknown action", btn([[["⬅️ Menu", "back", "danger"]]])); return; }

    if ($data == "backadmin") { showAdmin($c, $mid); return; }
    if ($data == "addcat") { setState($uid, "addcat"); smartReply($c, $mid, "📁 <b>Send Category Name</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
    if ($data == "delcat") { showCatDeleteList($c, $mid); return; }
    if (strpos($data, "delcat_") === 0) {
        $cid = substr($data, 7);
        if (isset($categories[$cid])) { $n = $categories[$cid]['name']; unset($categories[$cid]); saveJson("categories", $categories); smartReply($c, $mid, "✅ <b>Category deleted:</b> $n", btn([[["⬅️ Admin", "backadmin", "danger"]]])); }
        return;
    }
    if ($data == "addprod") { showSelectCatForProduct($c, $mid); return; }
    if (strpos($data, "addprod_cat_") === 0) { setState($uid, "addprod_name", ["cat" => substr($data, 12)]); smartReply($c, $mid, "📦 <b>Send Product Name</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
    if ($data == "delprod") { showProductDeleteList($c, $mid); return; }
    if (strpos($data, "delprod_") === 0) {
        $pid = substr($data, 8);
        if (isset($products[$pid])) { $n = $products[$pid]['name']; unset($products[$pid]); saveJson("products", $products); smartReply($c, $mid, "✅ <b>Product deleted:</b> $n", btn([[["⬅️ Admin", "backadmin", "danger"]]])); }
        return;
    }
    if ($data == "pm_menu") { showPMManager($c, $mid); return; }
    if ($data == "addpm") { setState($uid, "addpm_name"); smartReply($c, $mid, "💳 <b>Send Payment Method Name</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
    if (strpos($data, "delpm_") === 0) {
        $pid = substr($data, 6);
        if (isset($settings['payment_methods'][$pid])) { $n = $settings['payment_methods'][$pid]['name']; unset($settings['payment_methods'][$pid]); saveJson("settings", $settings); smartReply($c, $mid, "✅ <b>Deleted:</b> $n", btn([[["⬅️ Back", "pm_menu", "danger"]]])); }
        return;
    }
    if ($data == "approvals") { showApprovals($c, $mid); return; }
    if (strpos($data, "approve_") === 0) { approvePayment($c, $mid, substr($data, 8)); return; }
    if (strpos($data, "reject_") === 0) { rejectPayment($c, $mid, substr($data, 7)); return; }

    // SETTINGS SUBMENU
    if ($data == "settings") { showSettings($c, $mid); return; }
    if (strpos($data, "sets_") === 0) { handleSetting($c, $mid, $uid, substr($data, 5)); return; }
    if ($data == "toggleauto") { $settings['use_auto'] = !$settings['use_auto']; saveJson("settings", $settings); showSettings($c, $mid); return; }
    if ($data == "togglecolors") { $settings['use_button_colors'] = !$settings['use_button_colors']; saveJson("settings", $settings); showSettings($c, $mid); return; }

    // MAINTENANCE
    if ($data == "maint") { showMaintPanel($c, $mid); return; }
    if ($data == "maint_toggle") { $settings['maintenance'] = !$settings['maintenance']; saveJson("settings", $settings); showMaintPanel($c, $mid); return; }
    if ($data == "setlogchannel") { setState($uid, "set_log_channel"); smartReply($c, $mid, "📢 <b>Send Log Channel ID</b>\n\nExample: <code>-1001234567890</code>\n\n/skip = disable", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }

    // BUTTON MANAGEMENT
    if ($data == "bmgmt") { showButtonMgmt($c, $mid); return; }
    if (strpos($data, "btgl_") === 0) {
        $key = substr($data, 5);
        $settings['button_toggles'][$key] = !(btnOn($key));
        saveJson("settings", $settings); showButtonMgmt($c, $mid); return;
    }
    if (strpos($data, "bcust_tgl_") === 0) {
        $bid = substr($data, 10);
        foreach ($settings['custom_buttons'] as &$cb) { if ($cb['id'] === $bid) { $cb['enabled'] = !$cb['enabled']; break; } }
        saveJson("settings", $settings); showButtonMgmt($c, $mid); return;
    }
    if (strpos($data, "bcust_del_") === 0) {
        $bid = substr($data, 10);
        $settings['custom_buttons'] = array_values(array_filter($settings['custom_buttons'], fn($x) => $x['id'] !== $bid));
        saveJson("settings", $settings); showButtonMgmt($c, $mid); return;
    }
    if ($data == "bcust_add") {
        if (count($settings['custom_buttons']) >= 8) { smartReply($c, $mid, "❌ Max 8 custom buttons allowed.", btn([[["⬅️ Back", "bmgmt", "danger"]]])); return; }
        setState($uid, "add_btn_label"); smartReply($c, $mid, "➕ <b>Send Button Label</b>\n\nExample: 📢 Our Channel", btn([[["❌ Cancel", "bmgmt", "danger"]]])); return;
    }

    // STATS
    if ($data == "stats") { showStats($c, $mid); return; }
    if ($data == "userlist") { showUserList($c, $mid); return; }
    if ($data == "userbal") { setState($uid, "adduserbal_id"); smartReply($c, $mid, "💰 <b>Send User ID</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }

    // BAN MANAGEMENT
    if ($data == "banmenu") { showBanMenu($c, $mid); return; }
    if ($data == "ban_user") { setState($uid, "ban_user_id"); smartReply($c, $mid, "🚫 <b>Send User ID to Ban</b>", btn([[["❌ Cancel", "banmenu", "danger"]]])); return; }
    if ($data == "unban_list") { showUnbanList($c, $mid); return; }
    if (strpos($data, "unban_") === 0) {
        $bid = substr($data, 6);
        $settings['banned'] = array_values(array_filter($settings['banned'], fn($x) => (string)$x !== (string)$bid));
        saveJson("settings", $settings); showUnbanList($c, $mid);
        sendMsg($bid, "✅ <b>You have been unbanned.</b>\n\nYou can now use the bot again.");
        return;
    }

    // PM
    if ($data == "pmuser") { setState($uid, "pm_user_id"); smartReply($c, $mid, "📨 <b>Send User ID</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }

    // BROADCAST
    if ($data == "broadcast") { setState($uid, "broadcast"); smartReply($c, $mid, "📢 <b>Send Broadcast Message</b>\n\nText / Photo / Video / Voice / Document", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }

    // ADMIN MGMT (OWNER ONLY)
    if ($data == "adminmgmt") {
        if (!isOwner($uid)) { smartReply($c, $mid, "❌ Owner only.", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
        showAdminMgmt($c, $mid); return;
    }
    if ($data == "admin_add") {
        if (!isOwner($uid)) return;
        setState($uid, "admin_add_id"); smartReply($c, $mid, "👑 <b>Send User ID to make Admin</b>", btn([[["❌ Cancel", "adminmgmt", "danger"]]])); return;
    }
    if (strpos($data, "admin_del_") === 0) {
        if (!isOwner($uid)) return;
        $aid = substr($data, 10);
        $settings['admins'] = array_values(array_filter($settings['admins'], fn($x) => (string)$x !== (string)$aid));
        saveJson("settings", $settings); showAdminMgmt($c, $mid);
        sendMsg($aid, "⚠️ <b>Your admin access has been removed.</b>");
        return;
    }

    smartReply($c, $mid, "❌ Unknown action", btn([[["⬅️ Menu", "back", "danger"]]]));
}

// ============================================================
// SETTINGS SUBMENU
// ============================================================
function showSettings($c, $mid) {
    global $settings;
    $msg = "⚙️ <b>BOT SETTINGS</b>\n━━━━━━━━━━━\n\n" .
        "🌍 <b>Currency Symbol:</b> {$settings['currency']}\n" .
        "💵 <b>USDT Rate:</b> {$settings['currency']}{$settings['usdt_rate']}\n" .
        "💳 <b>Auto UPI:</b> <code>{$settings['auto_upi']}</code>\n" .
        "🔑 <b>API Key:</b> <code>" . substr($settings['auto_api_key'], 0, 15) . "...</code>\n" .
        "⚡ <b>Auto Payment:</b> " . ($settings['use_auto'] ? "✅ ON" : "❌ OFF") . "\n" .
        "🎨 <b>Button Colors:</b> " . (!empty($settings['use_button_colors']) ? "✅ ON" : "❌ OFF") . "\n" .
        "📄 <b>Proof:</b> <code>" . substr($settings['proof_link'], 0, 30) . "</code>\n" .
        "📖 <b>HowTo:</b> <code>" . substr($settings['howto_link'], 0, 30) . "</code>\n" .
        "💬 <b>Support:</b> {$settings['support_user']}";
    $kb = btn([
        [["🌍 Currency Symbol", "sets_currency", "primary"], ["💵 USDT Rate", "sets_usdt", "primary"]],
        [["💳 Auto UPI", "sets_autoupi", "primary"], ["🔑 API Key", "sets_autokey", "primary"]],
        [["⚡ Toggle Auto", "toggleauto", "success"], ["🎨 Toggle Colors", "togglecolors", "primary"]],
        [["📄 Proof Link", "sets_proof", "primary"], ["📖 HowTo Link", "sets_howto", "primary"]],
        [["💬 Support User", "sets_support", "primary"]],
        [["🔧 Maint. Message", "sets_maintmsg", "primary"]],
        [["⬅️ Back", "backadmin", "danger"]]
    ]);
    smartReply($c, $mid, $msg, $kb);
}

function handleSetting($c, $mid, $uid, $key) {
    $map = [
        'currency' => ['🌍 <b>SET GLOBAL CURRENCY SYMBOL</b>
━━━━━━━━━━━

This is the <b>global currency symbol</b> — it will be applied automatically across the entire bot (products, balances, payments, orders — everywhere).

🌐 <b>Supported examples:</b>

₹ India        •  $ USA          •  € Europe
£ UK           •  ¥ Japan        •  ₨ Pakistan
৳ Bangladesh   •  ฿ Thailand     •  ₺ Turkey
₽ Russia       •  ﷼ Saudi Arabia •  د.إ UAE
₦ Nigeria      •  ₱ Philippines  •  ₩ South Korea
₪ Israel       •  ₫ Vietnam      •  ₴ Ukraine
₸ Kazakhstan   •  R$ Brazil      •  A$ Australia
C$ Canada      •  RM Malaysia    •  S$ Singapore
HK$ Hong Kong  •  CHF Switzerland

✏️ Send any currency symbol:', 'set_currency'],
        'usdt' => ['💵 <b>Send USDT Rate (in your currency)</b>', 'set_usdt'],
        'autoupi' => ['💳 <b>Send Auto UPI ID</b>', 'set_auto_upi'],
        'autokey' => ['🔑 <b>Send Auto API Key</b>', 'set_auto_key'],
        'proof' => ['📄 <b>Send Proof Link</b>', 'set_proof'],
        'howto' => ['📖 <b>Send HowTo Link</b>', 'set_howto'],
        'support' => ['💬 <b>Send Support Username</b>', 'set_support'],
        'maintmsg' => ['🔧 <b>Send Maintenance Message</b>', 'set_maint_msg']
    ];
    if (!isset($map[$key])) return;
    setState($uid, $map[$key][1]);
    smartReply($c, $mid, $map[$key][0], btn([[["❌ Cancel", "settings", "danger"]]]));
}

// ============================================================
// TEXT STATE HANDLER
// ============================================================
function handleTextState($c, $mid, $uid, $text) {
    global $adminID, $settings, $products, $categories, $balances, $users, $orders, $payments, $used_utrs;
    $state = getState($uid);
    $cur = $settings['currency'];

    // ========== ADMIN STATES ==========
    if (isAdmin($uid)) {
        $mapFields = [
            'set_currency' => 'currency', 'set_usdt' => 'usdt_rate', 'set_auto_upi' => 'auto_upi',
            'set_auto_key' => 'auto_api_key',
            'set_proof' => 'proof_link', 'set_howto' => 'howto_link', 'set_support' => 'support_user',
            'set_maint_msg' => 'maintenance_msg'
        ];
        if (isset($mapFields[$state])) {
            $f = $mapFields[$state];
            $v = trim($text);
            if ($f === 'usdt_rate') { $n = (float)$v; if ($n <= 0) { sendMsg($c, "❌ Invalid rate"); return; } $v = $n; }
            $settings[$f] = $v; saveJson("settings", $settings); clearState($uid);
            sendMsg($c, "✅ <b>Updated!</b>\n\n<code>" . htmlspecialchars((string)$v) . "</code>", btn([[["⬅️ Settings", "settings", "primary"]]]));
            return;
        }
        if ($state == "addcat") {
            $cid = "c" . substr(time(), -6) . rand(10, 99);
            $categories[$cid] = ["name" => trim($text)]; saveJson("categories", $categories); clearState($uid);
            sendMsg($c, "✅ <b>Category added:</b>\n\n" . trim($text), btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "addprod_name") { setState($uid, "addprod_price", ["cat" => getExtra($uid, 'cat'), "name" => trim($text)]); sendMsg($c, "💰 <b>Send Price</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addprod_price") { $p = (int)$text; if ($p < 1) { sendMsg($c, "❌ Invalid"); return; } setState($uid, "addprod_desc", ["cat" => getExtra($uid, 'cat'), "name" => getExtra($uid, 'name'), "price" => $p]); sendMsg($c, "📝 <b>Send Description</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addprod_desc") { setState($uid, "addprod_delivery", ["cat" => getExtra($uid, 'cat'), "name" => getExtra($uid, 'name'), "price" => getExtra($uid, 'price'), "desc" => trim($text)]); sendMsg($c, "🎁 <b>Send Delivery Content</b>\n\n• Text / Link\n• Photo / Video / Document / Voice", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addprod_delivery") { setState($uid, "addprod_stock", ["cat" => getExtra($uid, 'cat'), "name" => getExtra($uid, 'name'), "price" => getExtra($uid, 'price'), "desc" => getExtra($uid, 'desc'), "del_type" => "text", "del_content" => trim($text)]); sendMsg($c, "📊 <b>Send Stock</b>\n\nNumber or <code>unlimited</code>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addprod_stock") {
            $stock = (strtolower(trim($text)) == "unlimited") ? -1 : max(0, (int)$text);
            $pid = "p" . substr(time(), -6) . rand(10, 99);
            $products[$pid] = ["name" => getExtra($uid, 'name'), "cat" => getExtra($uid, 'cat'), "price" => getExtra($uid, 'price'), "description" => getExtra($uid, 'desc'), "delivery_type" => getExtra($uid, 'del_type'), "delivery_content" => getExtra($uid, 'del_content'), "stock" => $stock];
            saveJson("products", $products); $nm = getExtra($uid, 'name'); clearState($uid);
            sendMsg($c, "✅ <b>Product Added!</b>\n\n📦 $nm", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "addpm_name") { $pmid = "pm" . substr(time(), -6) . rand(10, 99); $settings['payment_methods'][$pmid] = ["name" => trim($text), "qr_url" => "", "upi_id" => "", "description" => ""]; saveJson("settings", $settings); setState($uid, "addpm_qr", ["pm_id" => $pmid]); sendMsg($c, "📸 <b>Send QR Photo</b>\n\nOr /skip", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addpm_qr") { if (strtolower(trim($text)) == "/skip") { setState($uid, "addpm_upi", ["pm_id" => getExtra($uid, 'pm_id')]); sendMsg($c, "💳 <b>Send UPI ID</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); } else sendMsg($c, "⚠️ Send a photo or /skip"); return; }
        if ($state == "addpm_upi") { $pmid = getExtra($uid, 'pm_id'); $settings['payment_methods'][$pmid]['upi_id'] = trim($text); saveJson("settings", $settings); setState($uid, "addpm_desc", ["pm_id" => $pmid]); sendMsg($c, "📝 <b>Send Description</b>", btn([[["❌ Cancel", "backadmin", "danger"]]])); return; }
        if ($state == "addpm_desc") { $pmid = getExtra($uid, 'pm_id'); $settings['payment_methods'][$pmid]['description'] = trim($text); saveJson("settings", $settings); $n = $settings['payment_methods'][$pmid]['name']; clearState($uid); sendMsg($c, "✅ <b>Payment method added:</b>\n\n💳 $n", btn([[["⬅️ Admin", "backadmin", "danger"]]])); return; }

        if ($state == "adduserbal_id") {
            $tid = trim($text);
            if (!isset($users[$tid])) { sendMsg($c, "❌ User not found"); return; }
            setState($uid, "adduserbal_amt", ["target" => $tid]);
            sendMsg($c, "💰 <b>User:</b> {$users[$tid]['name']}\n\nSend amount", btn([[["❌ Cancel", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "adduserbal_amt") {
            $amt = (int)$text;
            if ($amt <= 0) { sendMsg($c, "❌ Invalid amount"); return; }
            $tid = getExtra($uid, 'target');
            $balances[$tid] = ($balances[$tid] ?? 0) + $amt; saveJson("balances", $balances);
            clearState($uid);
            sendMsg($c, "✅ <b>{$cur}{$amt} added to</b>\n\n🆔 <code>$tid</code>", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            sendMsg($tid, "💰 <b>Balance Added!</b>\n\n{$cur}{$amt}\n━━━━━━━━━━━\n💵 New Balance: {$cur}{$balances[$tid]}", btn([[["⬅️ Menu", "back", "danger"]]]));
            return;
        }
        if ($state == "add_btn_label") { setState($uid, "add_btn_url", ["label" => trim($text)]); sendMsg($c, "🔗 <b>Send Redirect URL</b>\n\nExample: https://t.me/yourchannel", btn([[["❌ Cancel", "bmgmt", "danger"]]])); return; }
        if ($state == "add_btn_url") {
            $url = trim($text);
            if (!isUrl($url)) { sendMsg($c, "❌ Invalid URL. Must start with http:// or https:// or tg://"); return; }
            $label = getExtra($uid, 'label');
            $bid = "cb" . substr(time(), -6) . rand(10, 99);
            $settings['custom_buttons'][] = ["id" => $bid, "label" => $label, "url" => $url, "enabled" => true];
            saveJson("settings", $settings); clearState($uid);
            sendMsg($c, "✅ <b>Custom Button Added!</b>\n\n$label\n$url", btn([[["⬅️ Button Mgmt", "bmgmt", "primary"]]]));
            return;
        }
        if ($state == "pm_user_id") {
            $tid = trim($text);
            if (!isset($users[$tid])) { sendMsg($c, "❌ User not found"); return; }
            setState($uid, "pm_message", ["target" => $tid]);
            sendMsg($c, "📨 <b>Send Message for {$users[$tid]['name']}</b>\n\nText / Photo / Video", btn([[["❌ Cancel", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "pm_message") {
            $tid = getExtra($uid, 'target');
            sendMsg($tid, "📨 <b>Message from Admin</b>\n\n$text");
            clearState($uid);
            sendMsg($c, "✅ <b>Message sent to</b> <code>$tid</code>", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "ban_user_id") {
            $tid = trim($text);
            if (!isset($users[$tid])) { sendMsg($c, "❌ User not found"); return; }
            if (isAdmin($tid)) { sendMsg($c, "❌ Cannot ban admin"); return; }
            if (!in_array((string)$tid, $settings['banned'])) { $settings['banned'][] = (string)$tid; saveJson("settings", $settings); }
            clearState($uid);
            sendMsg($c, "🚫 <b>User banned:</b> <code>$tid</code>", btn([[["⬅️ Back", "banmenu", "danger"]]]));
            sendMsg($tid, "🚫 <b>You have been banned</b> from this bot.");
            return;
        }
        if ($state == "admin_add_id") {
            if (!isOwner($uid)) return;
            $tid = trim($text);
            if (!isset($users[$tid])) { sendMsg($c, "❌ User not found"); return; }
            if (!in_array((string)$tid, $settings['admins'], true)) { $settings['admins'][] = (string)$tid; saveJson("settings", $settings); }
            clearState($uid);
            sendMsg($c, "👑 <b>New admin added:</b> <code>$tid</code>", btn([[["⬅️ Admin Mgmt", "adminmgmt", "primary"]]]));
            sendMsg($tid, "👑 <b>You are now an admin!</b>\n\nUse /admin to access panel.");
            return;
        }
        if ($state == "set_log_channel") {
            $v = trim($text);
            if ($v == "/skip" || $v == "0") { $settings['log_channel'] = ""; saveJson("settings", $settings); clearState($uid); sendMsg($c, "✅ <b>Log channel disabled</b>", btn([[["⬅️ Admin", "backadmin", "danger"]]])); return; }
            $settings['log_channel'] = $v; saveJson("settings", $settings); clearState($uid);
            sendMsg($c, "✅ <b>Log channel set:</b> <code>$v</code>\n\nSending test message...", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            $t = sendMsg($v, "✅ <b>Log Channel Connected!</b>\n\nAll purchases will appear here.");
            if (!$t || stripos($t, '"ok":true') === false) sendMsg($c, "⚠️ Test failed. Make sure the bot is an <b>admin</b> in the channel.");
            return;
        }
        if ($state == "broadcast") {
            set_time_limit(300); $sent = 0; $fail = 0;
            foreach ($users as $tid => $u) {
                if (in_array((string)$tid, $settings['banned'])) continue;
                $r = sendMsg($tid, "📢 <b>Announcement</b>\n\n$text");
                if ($r && stripos($r, '"ok":true') !== false) $sent++; else $fail++;
                usleep(40000);
            }
            clearState($uid);
            sendMsg($c, "✅ <b>Broadcast done</b>\n✅ Sent: $sent\n❌ Failed: $fail", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
    }

    // ========== USER STATES ==========
    if ($state == "auto_amount") { processAutoAmount($c, $uid, (int)$text); return; }
    if ($state == "auto_utr") { processAutoUTR($c, $uid, $text); return; }
    if ($state == "man_amount") { $pmid = getExtra($uid, 'pm_id'); processManualAmount($c, $uid, $pmid, (int)$text); return; }
    if ($state == "man_ss") { sendMsg($c, "📸 <b>Upload photo</b> as screenshot, or /cancel", btn([[["⬅️ Cancel", "addbal", "danger"]]])); return; }

    if ($state == "man_utr") {
        $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($text)));
        if (strlen($utr) < 6) {
            sendMsg($c, "❌ <b>Invalid UTR</b>\n\nMin 6 characters. Try again.", btn([[["❌ Cancel", "addbal", "danger"]]]));
            return;
        }
        $photo_id = getExtra($uid, 'photo');
        $amount   = getExtra($uid, 'amount');
        $pmid     = getExtra($uid, 'pm_id');
        if (!$photo_id || !$amount || !$pmid) { sendMsg($c, "❌ Session expired", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }

        $pm_name = $settings['payment_methods'][$pmid]['name'] ?? "Unknown";
        $oid = "MAN" . substr(time(), -8) . rand(100, 999);
        $payments[$oid] = [
            "user" => $uid, "amount" => $amount, "method" => $pm_name, "method_id" => $pmid,
            "screenshot" => $photo_id, "utr" => $utr, "status" => "pending",
            "type" => "manual", "date" => date("d M Y H:i")
        ];
        saveJson("payments", $payments); clearState($uid);

        sendMsg($c, "✅ <b>PAYMENT SUBMITTED</b>\n━━━━━━━━━━━\n\n🎉 Your payment has been received successfully.\n\n💰 <b>Amount:</b> {$cur}{$amount}\n💳 <b>Method:</b> $pm_name\n🧾 <b>UTR:</b> <code>$utr</code>\n🆔 <b>Order ID:</b> <code>$oid</code>\n\n━━━━━━━━━━━\n⏳ <i>Verification in progress...</i>\n🙏 Please wait for admin approval.", btn([[["⬅️ Menu", "back", "danger"]]]));

        $uname = $users[$uid]['name'] ?? 'User';
        $adminMsg = "🔔 <b>NEW MANUAL PAYMENT</b>\n━━━━━━━━━━━\n\n👤 $uname\n🆔 <code>$uid</code>\n💰 {$cur}{$amount}\n💳 $pm_name\n🧾 UTR: <code>$utr</code>\n🆔 <code>$oid</code>\n📅 " . date("d M Y H:i") . " IST";
        sendPhoto($adminID, $photo_id, $adminMsg, btn([[["✅ Approve", "approve_$oid", "success"], ["❌ Reject", "reject_$oid", "danger"]]]));
        return;
    }
}

// ============================================================
// MEDIA HANDLER
// ============================================================
function handleMediaState($c, $mid, $uid, $msg) {
    global $adminID, $settings, $users, $payments;
    $state = getState($uid);
    if (!$state) return;

    if (isAdmin($uid)) {
        if ($state == "addprod_delivery") {
            $type = null; $content = null;
            if (isset($msg['photo'])) { $type = "photo"; $content = $msg['photo'][count($msg['photo']) - 1]['file_id']; }
            elseif (isset($msg['video'])) { $type = "video"; $content = $msg['video']['file_id']; }
            elseif (isset($msg['document'])) { $type = "document"; $content = $msg['document']['file_id']; }
            elseif (isset($msg['voice'])) { $type = "voice"; $content = $msg['voice']['file_id']; }
            if (!$type) { sendMsg($c, "❌ Unsupported"); return; }
            setState($uid, "addprod_stock", ["cat" => getExtra($uid, 'cat'), "name" => getExtra($uid, 'name'), "price" => getExtra($uid, 'price'), "desc" => getExtra($uid, 'desc'), "del_type" => $type, "del_content" => $content]);
            sendMsg($c, "✅ <b>Saved ($type)</b>\n\n📊 Send stock", btn([[["❌ Cancel", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "addpm_qr" && isset($msg['photo'])) {
            $pmid = getExtra($uid, 'pm_id');
            $settings['payment_methods'][$pmid]['qr_url'] = $msg['photo'][count($msg['photo']) - 1]['file_id']; saveJson("settings", $settings);
            setState($uid, "addpm_upi", ["pm_id" => $pmid]);
            sendMsg($c, "✅ <b>QR saved</b>\n\n💳 Send UPI ID", btn([[["❌ Cancel", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "pm_message") {
            $tid = getExtra($uid, 'target');
            if (isset($msg['photo'])) sendPhoto($tid, $msg['photo'][count($msg['photo']) - 1]['file_id'], "📨 <b>Message from Admin</b>\n\n" . ($msg['caption'] ?? ""));
            elseif (isset($msg['video'])) sendVideo($tid, $msg['video']['file_id'], "📨 <b>Message from Admin</b>\n\n" . ($msg['caption'] ?? ""));
            elseif (isset($msg['document'])) sendDoc($tid, $msg['document']['file_id'], "📨 <b>Message from Admin</b>\n\n" . ($msg['caption'] ?? ""));
            clearState($uid);
            sendMsg($c, "✅ <b>Message sent to</b> <code>$tid</code>", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
        if ($state == "broadcast") {
            set_time_limit(300); $sent = 0; $fail = 0;
            $cap = $msg['caption'] ?? "";
            foreach ($users as $tid => $u) {
                if (in_array((string)$tid, $settings['banned'])) continue;
                $r = false;
                if (isset($msg['photo'])) $r = sendPhoto($tid, $msg['photo'][count($msg['photo']) - 1]['file_id'], "📢 <b>Announcement</b>\n\n$cap");
                elseif (isset($msg['video'])) $r = sendVideo($tid, $msg['video']['file_id'], "📢 <b>Announcement</b>\n\n$cap");
                elseif (isset($msg['document'])) $r = sendDoc($tid, $msg['document']['file_id'], "📢 <b>Announcement</b>\n\n$cap");
                elseif (isset($msg['voice'])) $r = sendVoice($tid, $msg['voice']['file_id'], "📢 <b>Announcement</b>\n\n$cap");
                if ($r && stripos($r, '"ok":true') !== false) $sent++; else $fail++;
                usleep(40000);
            }
            clearState($uid);
            sendMsg($c, "✅ <b>Done</b>\n✅ Sent: $sent\n❌ Failed: $fail", btn([[["⬅️ Admin", "backadmin", "danger"]]]));
            return;
        }
    }
    if ($state == "man_ss") {
        if (isset($msg['photo'])) submitManualPayment($c, $uid, $msg['photo'][count($msg['photo']) - 1]['file_id']);
        else sendMsg($c, "⚠️ Photo required.");
    }
}

// ============================================================
// USER UI
// ============================================================
function showMainMenu($c, $mid, $uid, $name) {
    global $balances, $settings;
    $cur = $settings['currency']; $bal = $balances[$uid] ?? 0;
    $msg = "✨ <b>WELCOME BACK,</b> <b>$name</b> ✨\n━━━━━━━━━━━\n\n🏪 <b>DIGITAL STORE</b>\n<i>Your Trusted Digital Marketplace</i>\n\n━━━━━━━━━━━\n⭐ <b>WHY CHOOSE US</b>\n\n🛍 Premium Digital Products\n⚡ Lightning-Fast Delivery\n🛡 100% Secure Payments\n💎 Best Prices Guaranteed\n💬 24/7 Real Support\n\n━━━━━━━━━━━\n💰 <b>Your Balance:</b> {$cur}{$bal}\n━━━━━━━━━━━";

    $rows = [];
    if (btnOn('shop')) $rows[] = [["🛒 Shop Now", "shop", "success"]];
    $row2 = [];
    if (btnOn('orders')) $row2[] = ["📦 My Orders", "orders", "primary"];
    if (btnOn('profile')) $row2[] = ["👤 Profile", "profile", "primary"];
    if ($row2) $rows[] = $row2;
    $row3 = [];
    if (btnOn('addbal')) $row3[] = ["💰 Add Fund", "addbal", "success"];
    if (btnOn('proof')) $row3[] = ["📄 Payment Proof", "proof", "primary"];
    if ($row3) $rows[] = $row3;
    $row4 = [];
    if (btnOn('howto')) $row4[] = ["📖 How To Use", "howto", "primary"];
    if (btnOn('support')) $row4[] = ["💬 Support", "support", "primary"];
    if ($row4) $rows[] = $row4;

    foreach ($settings['custom_buttons'] as $cb) {
        if (!empty($cb['enabled'])) $rows[] = [[$cb['label'], $cb['url'], 'primary']];
    }
    smartReply($c, $mid, $msg, btn($rows));
}

function showCategories($c, $mid) {
    global $categories;
    if (count($categories) == 0) { smartReply($c, $mid, "🛒 <b>Shop</b>\n━━━━━━━━━━━\n\n❌ No categories available.", btn([[["⬅️ Back", "back", "danger"]]])); return; }
    $msg = "🛒 <b>STORE — SHOP</b>\n━━━━━━━━━━━\n\n📱 Select a category to browse:";
    $kb = [];
    foreach ($categories as $id => $cat) $kb[] = [[$cat['name'], "cat_$id", "primary"]];
    $kb[] = [["⬅️ Back", "back", "danger"]];
    smartReply($c, $mid, $msg, btn($kb));
}

function showProducts($c, $mid, $cid) {
    global $products, $categories, $settings;
    $cur = $settings['currency']; $cn = $categories[$cid]['name'] ?? "Category";
    $list = []; foreach ($products as $id => $p) if (($p['cat'] ?? '') == $cid) $list[$id] = $p;
    if (count($list) == 0) { smartReply($c, $mid, "🛒 <b>$cn</b>\n━━━━━━━━━━━\n\n❌ No products available.", btn([[["⬅️ Back", "backshop", "danger"]]])); return; }
    $msg = "🛒 <b>$cn</b>\n━━━━━━━━━━━\n\n📦 Select a product to view details:";
    $kb = []; foreach ($list as $id => $p) $kb[] = [["{$p['name']} • {$cur}{$p['price']}", "buy_$id", "primary"]];
    $kb[] = [["⬅️ Back", "backshop", "danger"]];
    smartReply($c, $mid, $msg, btn($kb));
}

function showProductDetail($c, $mid, $uid, $pid) {
    global $products, $balances, $settings;
    if (!isset($products[$pid])) { smartReply($c, $mid, "❌ Not found", btn([[["⬅️ Back", "backshop", "danger"]]])); return; }
    $p = $products[$pid]; $cur = $settings['currency']; $bal = $balances[$uid] ?? 0;
    $stock = $p['stock'] ?? -1;
    $st = ($stock == -1) ? "♾ Unlimited" : ($stock > 0 ? "✅ $stock left" : "❌ Out of Stock");
    $msg = "🛍 <b>{$p['name']}</b>\n━━━━━━━━━━━\n\n📝 <b>Description:</b>\n{$p['description']}\n\n━━━━━━━━━━━\n💵 <b>Price:</b> {$cur}{$p['price']}\n📊 <b>Stock:</b> $st\n👛 <b>Your Balance:</b> {$cur}{$bal}\n━━━━━━━━━━━\n⚡ <i>Instant delivery after purchase</i>";
    $kb = [];
    if ($stock != 0) { if ($bal >= $p['price']) $kb[] = [["✅ Buy Now", "confirmbuy_$pid", "success"]]; else $kb[] = [["💰 Add Fund", "addbal", "success"]]; }
    $kb[] = [["⬅️ Back", "backshop", "danger"]];
    smartReply($c, $mid, $msg, btn($kb));
}

function doPurchase($c, $mid, $uid, $pid) {
    global $products, $balances, $orders, $settings, $users, $adminID;
    if (!isset($products[$pid])) { smartReply($c, $mid, "❌ Not found", btn([[["⬅️ Back", "backshop", "danger"]]])); return; }
    $p = $products[$pid]; $cur = $settings['currency']; $bal = $balances[$uid] ?? 0;
    if ($bal < $p['price']) { smartReply($c, $mid, "❌ <b>Insufficient Balance</b>\n━━━━━━━━━━━\nPrice: {$cur}{$p['price']}\nBalance: {$cur}{$bal}", btn([[["💰 Add Fund", "addbal", "success"], ["⬅️ Back", "backshop", "danger"]]])); return; }
    if (($p['stock'] ?? -1) == 0) { smartReply($c, $mid, "❌ <b>Out of Stock!</b>", btn([[["⬅️ Back", "backshop", "danger"]]])); return; }
    $balances[$uid] -= $p['price']; saveJson("balances", $balances);
    if (($p['stock'] ?? -1) > 0) { $products[$pid]['stock'] = $p['stock'] - 1; saveJson("products", $products); }
    $oid = "ORD" . substr(time(), -8) . rand(100, 999);
    $now = date("d M Y H:i");
    $orders[$oid] = ["user" => $uid, "product_id" => $pid, "product_name" => $p['name'], "price" => $p['price'], "status" => "Delivered", "date" => $now];
    saveJson("orders", $orders);
    $uname = $users[$uid]['name'] ?? 'User'; $un = !empty($users[$uid]['username']) ? "@" . $users[$uid]['username'] : "N/A";
    $stockNow = $products[$pid]['stock'] ?? -1;
    sendMsg($adminID, "🔔 <b>NEW PURCHASE</b>\n━━━━━━━━━━━\n👤 <b>Buyer:</b> $uname\n🔗 <b>Username:</b> $un\n🆔 <b>User ID:</b> <code>$uid</code>\n━━━━━━━━━━━\n📦 <b>Product:</b> {$p['name']}\n🔖 <b>Product ID:</b> <code>$pid</code>\n💵 <b>Price:</b> {$cur}{$p['price']}\n📊 <b>Stock Left:</b> " . ($stockNow == -1 ? "♾" : $stockNow) . "\n━━━━━━━━━━━\n🧾 <b>Order ID:</b> <code>$oid</code>\n📅 <b>Date:</b> " . date("d M Y") . "\n⏰ <b>Time:</b> " . date("H:i") . " IST\n💰 <b>Balance:</b> {$cur}{$balances[$uid]}");
    if (!empty($settings['log_channel'])) sendMsg($settings['log_channel'], "💎 <b>NEW ORDER RECEIVED</b> 💎\n━━━━━━━━━━━\n\n📦 <b>Product:</b> {$p['name']}\n💵 <b>Price:</b> {$cur}{$p['price']}\n👤 <b>Buyer ID:</b> <code>$uid</code>\n📅 <b>Date:</b> " . date("d M Y") . "\n⏰ <b>Time:</b> " . date("H:i") . " IST\n\n━━━━━━━━━━━\n✅ Auto Delivered");
    $head = "✅ <b>ORDER DELIVERED SUCCESSFULLY!</b>\n━━━━━━━━━━━\n\n🎉 Thank you for your purchase!\n\n📦 <b>Product:</b> {$p['name']}\n💵 <b>Price:</b> {$cur}{$p['price']}\n🧾 <b>Order ID:</b> <code>$oid</code>\n📅 <b>Date:</b> $now IST\n\n━━━━━━━━━━━\n";
    $kb = btn([[["📦 My Orders", "orders", "primary"], ["⬅️ Menu", "back", "danger"]]]);
    $type = $p['delivery_type']; $content = $p['delivery_content'];
    if ($type == "text") smartReply($c, $mid, $head . "🎁 <b>Your Delivery:</b>\n\n$content", $kb);
    else {
        smartReply($c, $mid, $head . "🎁 <b>Your Delivery:</b> (see below)", btn([[["⬅️ Menu", "back", "danger"]]]));
        if ($type == "photo") sendPhoto($c, $content, "🎁 <b>Order: {$p['name']}</b>", $kb);
        elseif ($type == "video") sendVideo($c, $content, "🎁 <b>Order: {$p['name']}</b>", $kb);
        elseif ($type == "document") sendDoc($c, $content, "🎁 <b>Order: {$p['name']}</b>", $kb);
        elseif ($type == "voice") sendVoice($c, $content, "🎁 <b>Order: {$p['name']}</b>", $kb);
    }
}

function showOrders($c, $mid, $uid) {
    global $orders, $settings;
    $cur = $settings['currency'];
    $mine = []; foreach ($orders as $id => $o) if (($o['user'] ?? 0) == $uid) $mine[$id] = $o;
    if (count($mine) == 0) { smartReply($c, $mid, "📦 <b>My Orders</b>\n━━━━━━━━━━━\n\n❌ You have no orders yet.", btn([[["🛒 Shop", "shop", "success"], ["⬅️ Menu", "back", "danger"]]])); return; }
    $msg = "📦 <b>MY ORDERS (" . count($mine) . ")</b>\n━━━━━━━━━━━\n\n";
    $i = 1;
    foreach (array_reverse($mine) as $id => $o) {
        $pid = $o['product_id'] ?? 'N/A';
        $msg .= "$i. <b>Product ID:</b> <code>$pid</code>\n 💵 Price: {$cur}{$o['price']}\n 📅 Date: {$o['date']} IST\n 🆔 Order: <code>$id</code>\n ✅ Status: {$o['status']}\n\n";
        $i++; if ($i > 10) { $msg .= "<i>...and more</i>"; break; }
    }
    smartReply($c, $mid, $msg, btn([[["⬅️ Menu", "back", "danger"]]]));
}

function showProfile($c, $mid, $uid) {
    global $balances, $users, $orders, $settings;
    $cur = $settings['currency']; $n = $users[$uid]['name'] ?? "User"; $j = $users[$uid]['join'] ?? date("d M Y");
    $b = $balances[$uid] ?? 0; $t = 0;
    foreach ($orders as $o) if (($o['user'] ?? 0) == $uid) $t++;
    $msg = "👤 <b>MY PROFILE</b>\n━━━━━━━━━━━\n\n👹 <b>Name:</b> $n\n🆔 <b>User ID:</b> <code>$uid</code>\n📅 <b>Member Since:</b> $j\n💰 <b>Balance:</b> {$cur}{$b}\n🛒 <b>Total Orders:</b> $t\n━━━━━━━━━━━";
    smartReply($c, $mid, $msg, btn([[["🛒 Shop", "shop", "success"], ["📦 Orders", "orders", "primary"]], [["⬅️ Menu", "back", "danger"]]]));
}

function showProof($c, $mid) { global $settings; smartReply($c, $mid, "📄 <b>Payment Proofs</b>\n━━━━━━━━━━━\n\n🔗 <a href='{$settings['proof_link']}'>Click Here</a>", btn([[["⬅️ Menu", "back", "danger"]]])); }
function showHowTo($c, $mid) { global $settings; smartReply($c, $mid, "📖 <b>How To Use</b>\n━━━━━━━━━━━\n\n1️⃣ Add Fund\n2️⃣ Choose Product\n3️⃣ Instant Delivery\n\n🔗 <a href='{$settings['howto_link']}'>Watch Tutorial</a>", btn([[["⬅️ Menu", "back", "danger"]]])); }
function showSupport($c, $mid) { global $settings; $u = $settings['support_user']; smartReply($c, $mid, "💬 <b>Support</b>\n━━━━━━━━━━━\n\n🔗 <a href='https://t.me/" . ltrim($u, '@') . "'>$u</a>\n\n24/7 Available", btn([[["⬅️ Menu", "back", "danger"]]])); }

// ============================================================
// ADD FUND
// ============================================================
function showAddFund($c, $mid, $uid) {
    global $settings;
    $methods = $settings['payment_methods'];
    $msg = "💰 <b>Add Fund</b>\n━━━━━━━━━━━\n\nChoose payment method:";
    $kb = [];
    foreach ($methods as $id => $pm) $kb[] = [["💳 {$pm['name']}", "pm_$id", "primary"]];
    if ($settings['use_auto']) $kb[] = [["⚡ Automatic UPI", "auto_pay", "success"]];
    $kb[] = [["⬅️ Menu", "back", "danger"]];
    if (count($methods) == 0 && !$settings['use_auto']) $msg = "💰 <b>Add Fund</b>\n━━━━━━━━━━━\n\n❌ No payment methods available.";
    smartReply($c, $mid, $msg, btn($kb));
}
function selectManualPM($c, $mid, $pmid) {
    global $settings;
    $methods = $settings['payment_methods'];
    if (!isset($methods[$pmid])) { smartReply($c, $mid, "❌ Not found", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    $pm = $methods[$pmid];
    $msg = "💳 <b>{$pm['name']}</b>\n━━━━━━━━━━━\n\n" . ($pm['description'] ? "📝 {$pm['description']}\n\n" : "") . "💳 <b>UPI ID:</b>\n<code>{$pm['upi_id']}</code>";
    $kb = btn([[["✅ Continue", "manual_amt_$pmid", "success"]], [["⬅️ Back", "addbal", "danger"]]]);
    if (!empty($pm['qr_url'])) { @tg("deleteMessage", ['chat_id' => $c, 'message_id' => $mid]); $r = sendPhoto($c, $pm['qr_url'], $msg, $kb); if (!$r || stripos($r, '"ok":true') === false) sendMsg($c, $msg, $kb); }
    else smartReply($c, $mid, $msg, $kb);
}
function startManualAmount($c, $mid, $uid, $pmid) {
    global $settings;
    if (!isset($settings['payment_methods'][$pmid])) { smartReply($c, $mid, "❌ Not found", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    setState($uid, "man_amount", ["pm_id" => $pmid]);
    $cur = $settings['currency'];
    smartReply($c, $mid, "💰 <b>Enter Amount</b>\n━━━━━━━━━━━\n\nMin: {$cur}1 • Max: {$cur}100000", btn([[["⬅️ Cancel", "addbal", "danger"]]]));
}
function processManualAmount($c, $uid, $pmid, $amount) {
    global $settings;
    $cur = $settings['currency'];
    if ($amount < 1 || $amount > 100000) { sendMsg($c, "❌ Invalid amount ({$cur}1-{$cur}100000)", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    $pm = $settings['payment_methods'][$pmid] ?? null;
    if (!$pm) { sendMsg($c, "❌ Method not found", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    setState($uid, "man_ss", ["pm_id" => $pmid, "amount" => $amount]);
    sendMsg($c, "💰 <b>Amount: {$cur}{$amount}</b>\n━━━━━━━━━━━\n\n💳 Pay to:\n<code>{$pm['upi_id']}</code>\n\n📸 Upload payment screenshot.\n\n⚠️ Photo only. /cancel to abort.", btn([[["⬅️ Cancel", "addbal", "danger"]]]));
}
function submitManualPayment($c, $uid, $photo_id) {
    $pmid = getExtra($uid, 'pm_id');
    $amount = getExtra($uid, 'amount');
    if (!$pmid || !$amount) { sendMsg($c, "❌ Session expired", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    setState($uid, "man_utr", ["pm_id" => $pmid, "amount" => $amount, "photo" => $photo_id]);
    sendMsg($c, "✅ <b>Screenshot received!</b>\n━━━━━━━━━━━\n\n🔢 <b>Enter UTR / Transaction ID</b>\n\nExample: <code>1234567890123</code>", btn([[["❌ Cancel", "addbal", "danger"]]]));
}

// ============================================================
// AUTO PAYMENT
// ============================================================
function startAutoPayment($c, $mid, $uid) {
    global $settings;
    $cur = $settings['currency'];
    if (!$settings['use_auto']) { smartReply($c, $mid, "❌ Auto payment disabled", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    setState($uid, "auto_amount");
    smartReply($c, $mid, "⚡ <b>Automatic UPI Payment</b>\n━━━━━━━━━━━\n\n💵 Enter Amount\n\nMin: {$cur}1 • Max: {$cur}100000", btn([[["⬅️ Back", "addbal", "danger"]]]));
}
function processAutoAmount($c, $uid, $amount) {
    global $settings;
    $cur = $settings['currency'];
    if ($amount < 1 || $amount > 100000) { sendMsg($c, "❌ Invalid amount ({$cur}1-{$cur}100000)"); return; }
    $upi = $settings['auto_upi']; $name = $settings['auto_name'];
    $upiString = "upi://pay?pa=$upi&pn=$name&am=$amount&cu=INR";
    $msg = "⚡ <b>Automatic Payment</b>\n━━━━━━━━━━━\n\n💵 <b>Amount:</b> {$cur}{$amount}\n💳 <b>UPI ID:</b>\n<code>$upi</code>\n\n📱 <b>Steps:</b>\n1️⃣ Scan QR / Pay to UPI\n2️⃣ Enter UTR after payment\n\n⏰ Complete within 10 minutes";
    $kb = btn([[["✅ I've Paid - Enter UTR", "utr_prompt", "success"]], [["⬅️ Cancel", "addbal", "danger"]]]);
    $ok = sendQrPhoto($c, $upiString, $msg, $kb);
    if (!$ok) sendMsg($c, $msg . "\n\n⚠️ QR couldn't load. Use UPI ID above.", $kb);
    setState($uid, "auto_utr", ["amount" => $amount]);
}

function processAutoUTR($c, $uid, $utrRaw) {
    global $settings, $used_utrs, $balances, $payments, $adminID, $users;
    $cur = $settings['currency'];
    $amount = getExtra($uid, 'amount');
    if (!$amount) { sendMsg($c, "❌ Session expired", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }

    $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($utrRaw)));
    if (strlen($utr) < 6) { sendMsg($c, "❌ <b>Invalid UTR</b>\n\nMin 6 alphanumeric characters.", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }
    if (isset($used_utrs[$utr])) { sendMsg($c, "❌ <b>UTR already used!</b>\n\nThis UTR was used before.", btn([[["⬅️ Back", "addbal", "danger"]]])); return; }

    sendMsg($c, "⏳ <b>Verifying UTR...</b>\n\nPlease wait...");

    $res = verifyUTR($utr, $amount, $settings['auto_api_key'], $settings['auto_api_url']);
    if (($res['type'] ?? '') === 'not_found') { sleep(3); $res = verifyUTR($utr, $amount, $settings['auto_api_key'], $settings['auto_api_url']); }

    if (!empty($res['ok'])) {
        $used_utrs[$utr] = ['user' => $uid, 'amount' => $amount, 'time' => time()]; saveJson("used_utrs", $used_utrs);
        $balances[$uid] = ($balances[$uid] ?? 0) + $amount; saveJson("balances", $balances);
        $oid = "AUT" . substr(time(), -8);
        $payments[$oid] = ['user' => $uid, 'amount' => $amount, 'utr' => $utr, 'status' => 'approved', 'type' => 'auto', 'date' => date("d M Y H:i")];
        saveJson("payments", $payments); clearState($uid);
        sendMsg($c, "✅ <b>PAYMENT SUCCESSFUL</b>\n━━━━━━━━━━━\n\n🎉 Great! Your payment has been verified automatically.\n\n💰 <b>Amount Credited:</b> {$cur}{$amount}\n🧾 <b>UTR:</b> <code>$utr</code>\n💵 <b>New Balance:</b> {$cur}{$balances[$uid]}\n\n━━━━━━━━━━━\n🛒 Ready to shop? Explore our store now!", btn([[["🛒 Shop Now", "shop", "success"], ["⬅️ Menu", "back", "danger"]]]));
        sendMsg($adminID, "⚡ <b>Auto Payment Success</b>\n━━━━━━━━━━━\n👤 <code>$uid</code>\n💰 {$cur}{$amount}\n🆔 <code>$utr</code>");
        if (!empty($settings['log_channel'])) {
            sendMsg($settings['log_channel'], "💎 <b>NEW DEPOSIT RECEIVED</b> 💎\n━━━━━━━━━━━\n\n🆔 <b>UTR:</b> <code>$utr</code>\n💵 <b>Amount:</b> {$cur}{$amount}\n👤 <b>User ID:</b> <code>$uid</code>\n📅 <b>Date:</b> " . date("d M Y H:i") . " IST\n\n━━━━━━━━━━━\n✅ Auto Verified");
        }
        return;
    }

    if (($res['type'] ?? '') === 'api_error') {
        $oid = "AUT" . substr(time(), -8) . rand(100, 999);
        $payments[$oid] = ['user' => $uid, 'amount' => $amount, 'utr' => $utr, 'status' => 'pending', 'type' => 'auto_manual', 'date' => date("d M Y H:i")];
        saveJson("payments", $payments);
        $used_utrs[$utr] = ['user' => $uid, 'amount' => $amount, 'time' => time()]; saveJson("used_utrs", $used_utrs);
        clearState($uid);
        sendMsg($c, "⚠️ <b>Auto verification unavailable</b>\n━━━━━━━━━━━\n\nYour payment is being <b>verified manually</b>.\n\n💰 {$cur}{$amount}\n🆔 UTR: <code>$utr</code>\n🧾 ID: <code>$oid</code>\n\n⏳ Wait for admin approval.", btn([[["⬅️ Menu", "back", "danger"]]]));
        $uname = $users[$uid]['name'] ?? 'User';
        sendMsg($adminID, "🔔 <b>MANUAL VERIFY NEEDED</b>\n━━━━━━━━━━━\n⚠️ Auto API failed\n👤 $uname\n🆔 <code>$uid</code>\n💰 {$cur}{$amount}\n🧾 UTR: <code>$utr</code>\n🆔 ID: <code>$oid</code>\n📅 " . date("d M Y H:i") . " IST", btn([[["✅ Approve", "approve_$oid", "success"], ["❌ Reject", "reject_$oid", "danger"]]]));
        return;
    }

    sendMsg($c, "❌ <b>Transaction Not Found</b>\n━━━━━━━━━━━\n\n" . ($res['msg'] ?? 'Please verify UTR') . "\n\n💡 Make sure UTR is correct and payment completed.", btn([[["🔄 Try Again", "utr_prompt", "primary"], ["⬅️ Back", "addbal", "danger"]]]));
}

function verifyUTR($utr, $amount, $api_key, $api_url) {
    if (empty($api_key) || $api_key === 'YOUR_API_KEY') return ['ok' => false, 'type' => 'api_error', 'msg' => 'Auto API key not configured.'];
    if (empty($api_url)) return ['ok' => false, 'type' => 'api_error', 'msg' => 'Auto API URL not configured.'];

    $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($utr)));
    $amount = number_format((float)$amount, 2, '.', '');

    $api_url = rtrim(trim($api_url), '/');
    $sep = strpos($api_url, '?') !== false ? '&' : '?';
    $url = $api_url . $sep . "api_key=" . urlencode($api_key) . "&utr=" . urlencode($utr) . "&amount=" . urlencode($amount);

    @file_put_contents(__DIR__ . "/bot_error.log", date('Y-m-d H:i:s') . " | verifyUTR REQ: $url\n", FILE_APPEND);
    $res = httpGet($url, 30);
    @file_put_contents(__DIR__ . "/bot_error.log", date('Y-m-d H:i:s') . " | verifyUTR RES: " . substr((string)$res, 0, 2000) . "\n", FILE_APPEND);

    if ($res === false || $res === '') return ['ok' => false, 'type' => 'api_error', 'msg' => 'Verification service not reachable.'];

    $j = @json_decode($res, true);
    if (is_array($j)) {
        $flat = $j;
        foreach (['data', 'result', 'response', 'payment', 'transaction'] as $key) {
            if (isset($j[$key]) && is_array($j[$key])) { $flat = array_merge($flat, $j[$key]); }
        }
        $successWords = ['true','1','yes','success','paid','completed','approved','received','ok','200'];
        $failWords    = ['false','0','no','failed','failure','error','invalid','pending','not found','unpaid','rejected','cancelled','not received'];
        foreach (['success','paid','completed','verified','utr_status','payment_status','transaction_status'] as $k) {
            if (isset($flat[$k])) {
                $v = $flat[$k];
                if ($v === true || $v === 1) return ['ok' => true];
                if (is_string($v) && in_array(strtolower(trim($v)), $successWords)) return ['ok' => true];
            }
        }
        foreach (['status','state','result','code','response_code'] as $k) {
            if (isset($flat[$k])) {
                $v = strtolower(trim((string)$flat[$k]));
                if (in_array($v, $successWords)) return ['ok' => true];
            }
        }
        $m = (string)($flat['message'] ?? $flat['msg'] ?? $flat['response'] ?? $flat['remark'] ?? $flat['description'] ?? '');
        if ($m !== '') {
            $ml = strtolower($m);
            $hasPos = false; $hasNeg = false;
            foreach (['success','received','paid','completed','approved'] as $w) { if (strpos($ml,$w)!==false){$hasPos=true;break;} }
            foreach ($failWords as $w) { if (strpos($ml,$w)!==false){$hasNeg=true;break;} }
            if ($hasPos && !$hasNeg) return ['ok' => true];
        }
        return ['ok' => false, 'type' => 'not_found', 'msg' => $m ?: 'Payment not found'];
    }

    $low = strtolower(trim((string)$res)); $low = trim($low, "\"'");
    if (in_array($low, ['success','ok','1','true','paid','completed','received','yes'])) return ['ok' => true];
    $hasPos = false; $hasNeg = false;
    foreach (['success','received','paid','completed','approved'] as $w) { if (strpos($low,$w)!==false){$hasPos=true;break;} }
    foreach ($failWords as $w) { if (strpos($low,$w)!==false){$hasNeg=true;break;} }
    if ($hasPos && !$hasNeg) return ['ok' => true];
    return ['ok' => false, 'type' => 'not_found', 'msg' => 'Payment not found. Please verify UTR.'];
}

// ============================================================
// ADMIN PANEL
// ============================================================
function showAdmin($c, $mid = 0) {
    global $settings, $users, $payments, $products, $categories, $adminID;
    $cur = $settings['currency'];
    $pending = 0; foreach ($payments as $p) if (($p['status'] ?? '') == 'pending') $pending++;
    $banned = count($settings['banned'] ?? []);
    $extraAdmins = count(array_filter($settings['admins'] ?? [], fn($x) => (string)$x !== (string)$adminID));
    $msg = "👑 <b>ADMIN CONTROL PANEL</b>\n━━━━━━━━━━━\n\n📊 <b>OVERVIEW</b>\n👥 Users: " . count($users) . " (🚫 Banned: $banned)\n📁 Categories: " . count($categories) . "\n📦 Products: " . count($products) . "\n⏳ Pending: $pending\n👑 Admins: " . ($extraAdmins + 1) . " (incl. owner)\n\n━━━━━━━━━━━\n⚙️ <b>CONFIG</b>\n🌍 Currency: {$cur}\n💵 USDT Rate: {$cur}{$settings['usdt_rate']}\n⚡ Auto: " . ($settings['use_auto'] ? "✅ ON" : "❌ OFF") . "\n🔧 Maint: " . ($settings['maintenance'] ? "🔴 ON" : "🟢 OFF") . "\n📢 Log: <code>" . ($settings['log_channel'] ?: "❌ not set") . "</code>";
    $rows = [
        [["📁 Add Category", "addcat", "primary"], ["🗑 Del Category", "delcat", "danger"]],
        [["📦 Add Product", "addprod", "primary"], ["🗑 Del Product", "delprod", "danger"]],
        [["💳 Payment Methods", "pm_menu", "primary"], ["💰 Approvals (" . $pending . ")", "approvals", "success"]],
        [["👥 User List", "userlist", "primary"], ["📊 Statistics", "stats", "primary"]],
        [["🚫 Ban System", "banmenu", "danger"], ["📨 Send PM", "pmuser", "primary"]],
        [["📢 Broadcast", "broadcast", "primary"], ["🔧 Maintenance", "maint", "primary"]],
        [["⚙️ Settings", "settings", "primary"], ["🎨 Button Mgmt", "bmgmt", "primary"]],
        [["💰 Add User Balance", "userbal", "success"], ["📢 Log Channel", "setlogchannel", "primary"]]
    ];
    if (isOwner($GLOBALS['uid'] ?? 0)) $rows[] = [["👑 Admin Management", "adminmgmt", "success"]];
    $rows[] = [["⬅️ Back to Menu", "back", "danger"]];
    smartReply($c, $mid, $msg, btn($rows));
}

function showCatDeleteList($c, $mid) {
    global $categories;
    if (count($categories) == 0) { smartReply($c, $mid, "❌ No categories", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
    $kb = []; foreach ($categories as $id => $cat) $kb[] = [["❌ {$cat['name']}", "delcat_$id", "danger"]];
    $kb[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, "🗑 <b>Delete category:</b>", btn($kb));
}

function showSelectCatForProduct($c, $mid) {
    global $categories;
    if (count($categories) == 0) { smartReply($c, $mid, "❌ No categories. Add one first.", btn([[["📁 Add Category", "addcat", "primary"], ["⬅️ Back", "backadmin", "danger"]]])); return; }
    $kb = []; foreach ($categories as $id => $cat) $kb[] = [[$cat['name'], "addprod_cat_$id", "primary"]];
    $kb[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, "📁 <b>Select Category</b>", btn($kb));
}

function showProductDeleteList($c, $mid) {
    global $products, $settings;
    if (count($products) == 0) { smartReply($c, $mid, "❌ No products", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
    $cur = $settings['currency']; $kb = [];
    foreach ($products as $id => $p) $kb[] = [["❌ {$p['name']} • {$cur}{$p['price']}", "delprod_$id", "danger"]];
    $kb[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, "🗑 <b>Select product to delete:</b>", btn($kb));
}

function showPMManager($c, $mid) {
    global $settings;
    $methods = $settings['payment_methods'];
    $msg = "💳 <b>Payment Methods</b>\n━━━━━━━━━━━\n\n" . count($methods) . " configured";
    $kb = []; foreach ($methods as $id => $pm) $kb[] = [["❌ {$pm['name']}", "delpm_$id", "danger"]];
    $kb[] = [["➕ Add Payment Method", "addpm", "success"]];
    $kb[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, $msg, btn($kb));
}

function showApprovals($c, $mid) {
    global $payments, $settings;
    $cur = $settings['currency'];
    $pending = []; foreach ($payments as $id => $p) if (($p['status'] ?? '') == 'pending') $pending[$id] = $p;
    if (count($pending) == 0) { smartReply($c, $mid, "✅ No pending payments.", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
    smartReply($c, $mid, "💰 <b>Pending: " . count($pending) . "</b>\n━━━━━━━━━━━\n\nReview below:", btn([[["⬅️ Back", "backadmin", "danger"]]]));
    foreach ($pending as $id => $p) {
        $cap = "🆔 <code>$id</code>\n👤 <code>{$p['user']}</code>\n💰 {$cur}{$p['amount']}\n" . (isset($p['method']) ? "💳 {$p['method']}\n" : "⚡ Auto\n") . (isset($p['utr']) ? "🧾 UTR: <code>{$p['utr']}</code>\n" : "") . "📅 {$p['date']} IST";
        $k = btn([[["✅ Approve", "approve_$id", "success"], ["❌ Reject", "reject_$id", "danger"]]]);
        if (!empty($p['screenshot'])) sendPhoto($c, $p['screenshot'], $cap, $k); else sendMsg($c, $cap, $k);
    }
}

function approvePayment($c, $mid, $oid) {
    global $payments, $balances, $settings;
    $cur = $settings['currency'];
    if (!isset($payments[$oid]) || $payments[$oid]['status'] != "pending") { smartReply($c, $mid, "❌ Invalid or processed", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
    $p = $payments[$oid];
    $payments[$oid]['status'] = "approved"; saveJson("payments", $payments);
    $balances[$p['user']] = ($balances[$p['user']] ?? 0) + $p['amount']; saveJson("balances", $balances);
    smartReply($c, $mid, "✅ <b>Approved!</b>\nUser: <code>{$p['user']}</code>\nAmount: {$cur}{$p['amount']}", btn([[["⬅️ Back", "backadmin", "danger"]]]));
    sendMsg($p['user'], "✅ <b>PAYMENT APPROVED</b>\n━━━━━━━━━━━\n\n🎉 Great news! Your payment has been approved.\n\n💰 <b>Amount Credited:</b> {$cur}{$p['amount']}\n💵 <b>New Balance:</b> {$cur}{$balances[$p['user']]}\n\n━━━━━━━━━━━\n🛒 Start shopping now!", btn([[["🛒 Shop Now", "shop", "success"], ["⬅️ Menu", "back", "danger"]]]));
    if (!empty($settings['log_channel'])) {
        $utr = $p['utr'] ?? 'N/A';
        sendMsg($settings['log_channel'], "💎 <b>NEW DEPOSIT RECEIVED</b> 💎\n━━━━━━━━━━━\n\n🆔 <b>UTR:</b> <code>$utr</code>\n💵 <b>Amount:</b> {$cur}{$p['amount']}\n👤 <b>User ID:</b> <code>{$p['user']}</code>\n📅 <b>Date:</b> " . date("d M Y H:i") . " IST\n\n━━━━━━━━━━━\n✅ Manually Verified");
    }
}
function rejectPayment($c, $mid, $oid) {
    global $payments, $settings;
    $cur = $settings['currency'];
    if (!isset($payments[$oid]) || $payments[$oid]['status'] != "pending") { smartReply($c, $mid, "❌ Invalid or processed", btn([[["⬅️ Back", "backadmin", "danger"]]])); return; }
    $p = $payments[$oid];
    $payments[$oid]['status'] = "rejected"; saveJson("payments", $payments);
    smartReply($c, $mid, "❌ <b>Rejected</b>\nUser: <code>{$p['user']}</code>", btn([[["⬅️ Back", "backadmin", "danger"]]]));
    sendMsg($p['user'], "❌ <b>Payment Rejected</b>\n\n{$cur}{$p['amount']} - Please contact support.", btn([[["⬅️ Menu", "back", "danger"]]]));
}

// ============================================================
// STATISTICS
// ============================================================
function showStats($c, $mid) {
    global $users, $payments, $products, $categories, $orders, $settings, $adminID;
    $cur = $settings['currency'];
    $totalUsers = count($users);
    $banned = count($settings['banned'] ?? []);
    $active = $totalUsers - $banned;
    $totalDep = 0; $pendingCount = 0; $pendingAmt = 0; $failCount = 0;
    foreach ($payments as $p) {
        $st = $p['status'] ?? '';
        if ($st == 'approved') $totalDep += (int)($p['amount'] ?? 0);
        elseif ($st == 'rejected') $failCount++;
        elseif ($st == 'pending') { $pendingCount++; $pendingAmt += (int)($p['amount'] ?? 0); }
    }
    $totalStock = 0; $unl = 0;
    foreach ($products as $p) { $s = (int)($p['stock'] ?? 0); if ($s == -1) $unl++; else $totalStock += $s; }
    $totalOrders = count($orders);
    $extraAdmins = count(array_filter($settings['admins'] ?? [], fn($x) => (string)$x !== (string)$adminID));
    $msg = "📊 <b>BOT STATISTICS</b>\n━━━━━━━━━━━\n\n" .
        "👥 <b>USERS</b>\n• Total: $totalUsers\n• Active: $active\n• Banned: $banned\n\n" .
        "💰 <b>FINANCES</b>\n• Total Deposits: {$cur}" . number_format($totalDep) . "\n• Pending: $pendingCount ({$cur}" . number_format($pendingAmt) . ")\n• Failed: $failCount\n\n" .
        "📦 <b>INVENTORY</b>\n• Categories: " . count($categories) . "\n• Products: " . count($products) . "\n• Total Stock: $totalStock (♾ $unl unlimited)\n• Total Orders: $totalOrders\n\n" .
        "👑 <b>ADMINS</b>\n• Owner: 1\n• Admins: $extraAdmins\n\n" .
        "🔧 <b>STATUS</b>\n• Maintenance: " . ($settings['maintenance'] ? "🔴 ON" : "🟢 OFF") . "\n• Auto Payment: " . ($settings['use_auto'] ? "✅ ON" : "❌ OFF");
    smartReply($c, $mid, $msg, btn([[["🔄 Refresh", "stats", "primary"], ["⬅️ Back", "backadmin", "danger"]]]));
}

// ============================================================
// BAN MANAGEMENT
// ============================================================
function showBanMenu($c, $mid) {
    global $settings;
    $count = count($settings['banned'] ?? []);
    $msg = "🚫 <b>BAN SYSTEM</b>\n━━━━━━━━━━━\n\nBanned users: $count";
    smartReply($c, $mid, $msg, btn([
        [["🚫 Ban User", "ban_user", "danger"], ["✅ Unban User", "unban_list", "success"]],
        [["⬅️ Back", "backadmin", "danger"]]
    ]));
}
function showUnbanList($c, $mid) {
    global $settings, $users;
    $banned = $settings['banned'] ?? [];
    if (count($banned) == 0) { smartReply($c, $mid, "✅ No banned users.", btn([[["⬅️ Back", "banmenu", "danger"]]])); return; }
    $kb = [];
    foreach ($banned as $bid) {
        $name = $users[$bid]['name'] ?? "User";
        $kb[] = [["✅ Unban: $name ($bid)", "unban_$bid", "success"]];
    }
    $kb[] = [["⬅️ Back", "banmenu", "danger"]];
    smartReply($c, $mid, "✅ <b>Unban user:</b>", btn($kb));
}

// ============================================================
// BUTTON MANAGEMENT
// ============================================================
function showButtonMgmt($c, $mid) {
    global $settings;
    $defs = ['shop' => '🛒 Shop', 'orders' => '📦 Orders', 'profile' => '👤 Profile', 'addbal' => '💰 Add Fund', 'proof' => '📄 Proof', 'howto' => '📖 HowTo', 'support' => '💬 Support'];
    $msg = "🎨 <b>BUTTON MANAGEMENT</b>\n━━━━━━━━━━━\n\n🎨 Colors: " . (!empty($settings['use_button_colors']) ? "✅ ON" : "❌ OFF") . "\n\n<i>Click button to toggle ON/OFF</i>\n\n";
    $rows = [];
    $cur = [];
    foreach ($defs as $k => $label) {
        $on = btnOn($k);
        $cur[] = [($on ? "✅ " : "❌ ") . $label, "btgl_$k", $on ? "success" : "danger"];
        if (count($cur) == 2) { $rows[] = $cur; $cur = []; }
    }
    if ($cur) $rows[] = $cur;
    $rows[] = [["🎨 Toggle Colors", "togglecolors", "primary"]];

    if (!empty($settings['custom_buttons'])) {
        $msg .= "━━━━━━━━━━━\n\n<b>CUSTOM BUTTONS:</b>\n\n";
        foreach ($settings['custom_buttons'] as $cb) {
            $on = !empty($cb['enabled']);
            $rows[] = [[($on ? "✅ " : "❌ ") . $cb['label'], "bcust_tgl_" . $cb['id'], $on ? "success" : "danger"], ["🗑", "bcust_del_" . $cb['id'], "danger"]];
        }
    }
    $rows[] = [["➕ Add Custom Button", "bcust_add", "success"]];
    $rows[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, $msg, btn($rows));
}

// ============================================================
// ADMIN MANAGEMENT (OWNER ONLY)
// ============================================================
function showAdminMgmt($c, $mid) {
    global $settings, $adminID, $users;
    $msg = "👑 <b>ADMIN MANAGEMENT</b>\n━━━━━━━━━━━\n\n<b>OWNER:</b>\n<code>$adminID</code> (permanent)\n\n<b>ADMINS:</b>\n";
    $kb = [];
    $extra = array_filter($settings['admins'] ?? [], fn($x) => (string)$x !== (string)$adminID);
    if (count($extra) == 0) {
        $msg .= "❌ No additional admins\n";
    } else {
        $i = 1;
        foreach ($extra as $aid) {
            $name = $users[$aid]['name'] ?? "Unknown";
            $msg .= "$i. $name\n   🆔 <code>$aid</code>\n\n";
            $kb[] = [["🗑 Remove: $name", "admin_del_$aid", "danger"]];
            $i++;
        }
    }
    $kb[] = [["➕ Add Admin", "admin_add", "success"]];
    $kb[] = [["⬅️ Back", "backadmin", "danger"]];
    smartReply($c, $mid, $msg, btn($kb));
}

// ============================================================
// MAINTENANCE
// ============================================================
function showMaintPanel($c, $mid) {
    global $settings;
    $st = $settings['maintenance'] ? "🔴 <b>ON</b>" : "🟢 <b>OFF</b>";
    $msg = "🔧 <b>MAINTENANCE MODE</b>\n━━━━━━━━━━━\n\n<b>Status:</b> $st\n\n<b>Message shown to users:</b>\n<i>{$settings['maintenance_msg']}</i>\n\n" . ($settings['maintenance'] ? "⚠️ Regular users cannot use bot.\nAdmins still have full access." : "✅ Bot is fully operational.");
    smartReply($c, $mid, $msg, btn([
        [["🔄 Toggle: " . ($settings['maintenance'] ? "Turn OFF" : "Turn ON"), "maint_toggle", $settings['maintenance'] ? "success" : "danger"]],
        [["✏️ Edit Message", "sets_maintmsg", "primary"]],
        [["⬅️ Back", "backadmin", "danger"]]
    ]));
}

function showUserList($c, $mid) {
    global $users;
    $msg = "👥 <b>Users (" . count($users) . ")</b>\n━━━━━━━━━━━\n\n";
    $i = 1;
    foreach ($users as $id => $u) {
        $msg .= "$i. {$u['name']}";
        if (!empty($u['username'])) $msg .= " (@{$u['username']})";
        $msg .= "\n   🆔 <code>$id</code>\n\n";
        $i++;
        if ($i > 30) { $msg .= "...and " . (count($users) - 30) . " more"; break; }
    }
    smartReply($c, $mid, $msg, btn([[["⬅️ Back", "backadmin", "danger"]]]));
}