<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('viohy_session');
    session_start();
}

define('VIOHY_ROOT', __DIR__);
define('VIOHY_VERSION', '9.0.0');

$configFile = VIOHY_ROOT . '/config.php';
if (!is_file($configFile)) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!str_starts_with($path, '/install')) {
        header('Location: /install/');
        exit;
    }
    return;
}

$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('VIOHY yapılandırması okunamadı.');
}

function cfg(string $key, mixed $default = null): mixed
{
    global $config;
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db.host', 'localhost'),
        (int) cfg('db.port', 3306),
        cfg('db.name', '')
    );
    $pdo = new PDO($dsn, (string) cfg('db.user', ''), (string) cfg('db.pass', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string
{
    return rtrim((string) cfg('app.url', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $target = preg_match('~^https?://~i', $path) ? $path : site_url($path);
    header('Location: ' . $target);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!$sent || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function user(): ?array
{
    static $cached = false;
    static $record = null;
    if ($cached) {
        return $record;
    }
    $cached = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM viohy_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $record = $stmt->fetch() ?: null;
    if ($record && (int) $record['is_blocked'] === 1) {
        unset($_SESSION['user_id']);
        flash('error', 'Hesabınız geçici olarak erişime kapatılmıştır.');
        return null;
    }
    return $record;
}

function require_auth(): array
{
    $u = user();
    if (!$u) {
        flash('info', 'Devam etmek için giriş yapın.');
        redirect('/login');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_auth();
    if (!(int) $u['is_admin']) {
        http_response_code(403);
        exit('Bu alana erişim yetkiniz yok.');
    }
    return $u;
}

function client_hash(): string
{
    $raw = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return hash_hmac('sha256', $raw, (string) cfg('app.key', 'viohy'));
}

function normalize_url(string $url, string $platform = 'website'): string
{
    $url = trim($url);
    if ($platform === 'whatsapp') {
        $digits = preg_replace('/\D+/', '', $url) ?: '';
        if ($digits && str_starts_with($digits, '0')) {
            $digits = '90' . substr($digits, 1);
        }
        if ($digits && !str_starts_with($digits, '90') && strlen($digits) === 10) {
            $digits = '90' . $digits;
        }
        return $digits ? 'https://wa.me/' . $digits : $url;
    }
    if ($url && !preg_match('~^(https?://|mailto:|tel:)~i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function upload_image(string $field, int $userId, string $old = ''): string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int) $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $old;
    }
    $file = $_FILES[$field];
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Görsel yüklenemedi.');
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Görsel en fazla 5 MB olabilir.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Yalnız JPG, PNG, WEBP veya GIF yükleyebilirsiniz.');
    }
    $dir = VIOHY_ROOT . '/uploads/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Görsel sunucuya kaydedilemedi.');
    }
    if ($old && str_starts_with($old, '/uploads/' . $userId . '/')) {
        $oldPath = VIOHY_ROOT . $old;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    return '/uploads/' . $userId . '/' . $name;
}

function platform_options(): array
{
    return [
        'website' => 'Web sitesi',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'spotify' => 'Spotify',
        'x' => 'X / Twitter',
        'facebook' => 'Facebook',
        'telegram' => 'Telegram',
        'discord' => 'Discord',
        'email' => 'E-posta',
        'phone' => 'Telefon',
        'location' => 'Konum',
    ];
}

function sectors(): array
{
    return [
        'creator' => 'İçerik üreticisi',
        'gaming' => 'Oyun & Espor',
        'boutique' => 'Butik & Moda',
        'beauty' => 'Beauty & Kozmetik',
        'cafe' => 'Cafe & Restoran',
        'fitness' => 'Fitness & Koç',
        'music' => 'Müzik & Sahne',
        'business' => 'İşletme',
    ];
}

function theme_catalog(): array
{
    return [
        'viohy-flow' => ['name'=>'VIOHY Flow','sector'=>'general','style'=>'flow','bg'=>'#f6f8ff','surface'=>'#ffffff','text'=>'#101828','accent'=>'#6d5dfc','accent2'=>'#21d4c2'],
        'viohy-night' => ['name'=>'VIOHY Night','sector'=>'general','style'=>'neon','bg'=>'#07111f','surface'=>'#111d31','text'=>'#f8fbff','accent'=>'#2ce6d3','accent2'=>'#7c5cff'],
        'aqua-glass' => ['name'=>'Aqua Glass','sector'=>'general','style'=>'glass','bg'=>'#e8fbfb','surface'=>'#ffffffcc','text'=>'#073b4c','accent'=>'#00b8a9','accent2'=>'#4361ee'],
        'mono-studio' => ['name'=>'Mono Studio','sector'=>'general','style'=>'editorial','bg'=>'#f5f5f2','surface'=>'#ffffff','text'=>'#111111','accent'=>'#111111','accent2'=>'#8c8c8c'],
        'sunset-pop' => ['name'=>'Sunset Pop','sector'=>'general','style'=>'soft','bg'=>'#fff3ee','surface'=>'#ffffff','text'=>'#352126','accent'=>'#ff5e7d','accent2'=>'#ffb15e'],
        'aurora' => ['name'=>'Aurora','sector'=>'general','style'=>'aurora','bg'=>'#0d1024','surface'=>'#181b3c','text'=>'#ffffff','accent'=>'#54e6c1','accent2'=>'#b56cff'],
        'paper' => ['name'=>'Paper','sector'=>'general','style'=>'paper','bg'=>'#f3efe6','surface'=>'#fffdf8','text'=>'#2c2924','accent'=>'#8b5e3c','accent2'=>'#c89b6d'],
        'cyber-arena' => ['name'=>'Cyber Arena','sector'=>'gaming','style'=>'neon','bg'=>'#050711','surface'=>'#101529','text'=>'#f5f7ff','accent'=>'#00e5ff','accent2'=>'#8b5cf6'],
        'neon-stream' => ['name'=>'Neon Stream','sector'=>'gaming','style'=>'grid','bg'=>'#09051a','surface'=>'#17102d','text'=>'#ffffff','accent'=>'#ff3cf7','accent2'=>'#00d9ff'],
        'retro-pixel' => ['name'=>'Retro Pixel','sector'=>'gaming','style'=>'pixel','bg'=>'#171022','surface'=>'#2a1d3a','text'=>'#fff7d6','accent'=>'#ffd166','accent2'=>'#ef476f'],
        'tactical-ops' => ['name'=>'Tactical Ops','sector'=>'gaming','style'=>'grid','bg'=>'#0b110e','surface'=>'#17211b','text'=>'#ecf7ef','accent'=>'#75ff8a','accent2'=>'#a7b4a9'],
        'lunera-editorial' => ['name'=>'Lunera Editorial','sector'=>'boutique','style'=>'editorial','bg'=>'#f6f0e7','surface'=>'#fffdf9','text'=>'#261f1b','accent'=>'#a97855','accent2'=>'#d9bda5'],
        'noir-atelier' => ['name'=>'Noir Atelier','sector'=>'boutique','style'=>'editorial','bg'=>'#0d0d0d','surface'=>'#191919','text'=>'#fafafa','accent'=>'#d5b06c','accent2'=>'#777777'],
        'rose-editorial' => ['name'=>'Rose Editorial','sector'=>'boutique','style'=>'soft','bg'=>'#fff3f5','surface'=>'#ffffff','text'=>'#40252e','accent'=>'#d45d79','accent2'=>'#efb7c2'],
        'urban-chic' => ['name'=>'Urban Chic','sector'=>'boutique','style'=>'grid','bg'=>'#f0f1f3','surface'=>'#ffffff','text'=>'#15171a','accent'=>'#111827','accent2'=>'#a3a3a3'],
        'glow-rose' => ['name'=>'Glow Rose','sector'=>'beauty','style'=>'soft','bg'=>'#fff3f7','surface'=>'#ffffff','text'=>'#442634','accent'=>'#ec6a9b','accent2'=>'#f6c2d4'],
        'pearl-spa' => ['name'=>'Pearl Spa','sector'=>'beauty','style'=>'glass','bg'=>'#edf9f7','surface'=>'#ffffffd9','text'=>'#173b36','accent'=>'#59b6a8','accent2'=>'#b8ded7'],
        'lavender-skin' => ['name'=>'Lavender Skin','sector'=>'beauty','style'=>'soft','bg'=>'#f4f0ff','surface'=>'#ffffff','text'=>'#322b48','accent'=>'#9a72e8','accent2'=>'#d8c8fb'],
        'champagne-beauty' => ['name'=>'Champagne Beauty','sector'=>'beauty','style'=>'editorial','bg'=>'#fbf7ef','surface'=>'#ffffff','text'=>'#352d22','accent'=>'#c49a55','accent2'=>'#ead9b7'],
        'brewhouse' => ['name'=>'BrewHouse','sector'=>'cafe','style'=>'paper','bg'=>'#efe5d7','surface'=>'#fffaf3','text'=>'#362418','accent'=>'#9b5d35','accent2'=>'#55704c'],
        'rustic-kitchen' => ['name'=>'Rustic Kitchen','sector'=>'cafe','style'=>'paper','bg'=>'#f4eadc','surface'=>'#fffbf5','text'=>'#37291e','accent'=>'#b45b3c','accent2'=>'#80704e'],
        'modern-bistro' => ['name'=>'Modern Bistro','sector'=>'cafe','style'=>'editorial','bg'=>'#f6f4ef','surface'=>'#ffffff','text'=>'#171717','accent'=>'#b3261e','accent2'=>'#d4af37'],
        'olive-brunch' => ['name'=>'Olive Brunch','sector'=>'cafe','style'=>'soft','bg'=>'#f2f4e8','surface'=>'#ffffff','text'=>'#2f3826','accent'=>'#718a45','accent2'=>'#d5a253'],
        'volt-coach' => ['name'=>'Volt Coach','sector'=>'fitness','style'=>'neon','bg'=>'#090d0a','surface'=>'#151b16','text'=>'#f5fff7','accent'=>'#b6ff35','accent2'=>'#00d7ff'],
        'iron-pro' => ['name'=>'Iron Pro','sector'=>'fitness','style'=>'grid','bg'=>'#111315','surface'=>'#202326','text'=>'#ffffff','accent'=>'#f2b705','accent2'=>'#7f8c8d'],
        'aqua-motion' => ['name'=>'Aqua Motion','sector'=>'fitness','style'=>'flow','bg'=>'#e9faff','surface'=>'#ffffff','text'=>'#0d3140','accent'=>'#00a8d6','accent2'=>'#3ee0b4'],
        'boxing-red' => ['name'=>'Boxing Red','sector'=>'fitness','style'=>'grid','bg'=>'#170a0a','surface'=>'#281111','text'=>'#fff5f5','accent'=>'#ff3b30','accent2'=>'#ff9f0a'],
        'neon-dj' => ['name'=>'Neon DJ','sector'=>'music','style'=>'neon','bg'=>'#080616','surface'=>'#171129','text'=>'#ffffff','accent'=>'#b943ff','accent2'=>'#00e5ff'],
        'midnight-artist' => ['name'=>'Midnight Artist','sector'=>'music','style'=>'aurora','bg'=>'#070c1c','surface'=>'#121b35','text'=>'#f7f8ff','accent'=>'#5b8cff','accent2'=>'#f154ff'],
        'festival-pulse' => ['name'=>'Festival Pulse','sector'=>'music','style'=>'grid','bg'=>'#150819','surface'=>'#2b102f','text'=>'#ffffff','accent'=>'#ff3d8d','accent2'=>'#ffd43b'],
        'acoustic-gold' => ['name'=>'Acoustic Gold','sector'=>'music','style'=>'paper','bg'=>'#f2e8dc','surface'=>'#fffaf3','text'=>'#33261f','accent'=>'#b17a42','accent2'=>'#6e513b'],
    ];
}

function theme(string $key): array
{
    $themes = theme_catalog();
    return $themes[$key] ?? $themes['viohy-flow'];
}

function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'home'=>'<path d="M3 10.8 12 3l9 7.8V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'link'=>'<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/>',
        'palette'=>'<path d="M12 3a9 9 0 0 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a2 2 0 0 1 0-4h2a7 7 0 0 0-2-11Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="9.5" cy="6.5" r="1"/><circle cx="14.5" cy="6.5" r="1"/><circle cx="16.5" cy="10.5" r="1"/>',
        'chart'=>'<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
        'content'=>'<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h7M7 17h4"/>',
        'inbox'=>'<path d="M4 4h16l2 10v5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5z"/><path d="M2 14h5l2 3h6l2-3h5"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'logout'=>'<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'edit'=>'<path d="m4 16-1 5 5-1L19 9l-4-4zM13.5 6.5l4 4"/>',
        'trash'=>'<path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6"/>',
        'eye'=>'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'whatsapp'=>'<path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.4-4.7A8.5 8.5 0 1 1 20.5 11.7Z"/><path d="M8.3 7.8c.2-.4.4-.4.7-.4h.5c.2 0 .4.1.5.5l.7 1.7c.1.3.1.5-.1.7l-.5.7c-.2.2-.2.4 0 .7.6 1.1 1.5 2 2.7 2.6.3.2.5.1.7-.1l.7-.9c.2-.3.5-.3.8-.2l1.6.8c.3.2.5.3.5.5 0 .2-.1 1.2-.8 1.8-.6.6-1.5.9-2.4.7-1-.2-2.3-.7-3.9-2.1-2-1.8-3.2-4-3.3-4.2-.1-.2-.8-1.8 0-3.3Z"/>',
        'instagram'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>',
        'tiktok'=>'<path d="M14 3v11.5a4.5 4.5 0 1 1-4-4.47"/><path d="M14 3c.7 2.5 2.2 4 5 4.5"/>',
        'youtube'=>'<path d="M21 8.2a2.7 2.7 0 0 0-1.9-1.9C17.4 6 12 6 12 6s-5.4 0-7.1.3A2.7 2.7 0 0 0 3 8.2C2.7 9.9 2.7 12 2.7 12s0 2.1.3 3.8a2.7 2.7 0 0 0 1.9 1.9C6.6 18 12 18 12 18s5.4 0 7.1-.3a2.7 2.7 0 0 0 1.9-1.9c.3-1.7.3-3.8.3-3.8s0-2.1-.3-3.8Z"/><path d="m10 9 5 3-5 3z"/>',
        'spotify'=>'<circle cx="12" cy="12" r="10"/><path d="M7 9.5c3.6-1 7.4-.7 10.5.8M7.8 13c3-.8 6.2-.5 8.8.7M8.7 16c2.4-.6 4.8-.4 6.8.5"/>',
        'x'=>'<path d="M5 4l14 16M19 4 5 20"/>',
        'facebook'=>'<path d="M14 8h4V3h-4a5 5 0 0 0-5 5v3H6v5h3v5h5v-5h4l1-5h-5z"/>',
        'telegram'=>'<path d="m22 3-8 18-4-7-7-4zM10 14l4-4"/>',
        'discord'=>'<path d="M7 6c3-1.5 7-1.5 10 0 1.5 2 2.5 4.5 2.5 7.5-2 1.8-4 2.6-5.5 2.9l-.8-1.1c1-.3 1.8-.7 2.5-1.1-2.4 1.1-5 1.1-7.4 0 .7.4 1.5.8 2.5 1.1l-.8 1.1c-1.5-.3-3.5-1.1-5.5-2.9C4.5 10.5 5.5 8 7 6Z"/><circle cx="9" cy="11.5" r="1"/><circle cx="15" cy="11.5" r="1"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'=>'<path d="M6.6 3h3l1.4 5-2 1.3a15 15 0 0 0 5.7 5.7l1.3-2 5 1.4v3c0 2-1.7 3.6-3.7 3.4A17 17 0 0 1 3.2 6.7C3 4.7 4.6 3 6.6 3Z"/>',
        'location'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'globe'=>'<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close'=>'<path d="m5 5 14 14M19 5 5 19"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
    ];
    $path = $paths[$name] ?? $paths['globe'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

function platform_icon(string $platform, string $class = 'icon'): string
{
    $map = ['website'=>'globe','email'=>'mail','phone'=>'phone'];
    return icon($map[$platform] ?? $platform, $class);
}

function reserved_routes(): array
{
    return ['login','register','logout','dashboard','admin','install','go','api','assets','uploads','health','forgot-password'];
}
