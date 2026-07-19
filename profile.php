<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

$username = strtolower(trim((string) ($_GET['u'] ?? '')));
if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username) || in_array($username, reserved_routes(), true)) {
    http_response_code(404);
    $username = '';
}

$profile = null;
if ($username !== '') {
    $stmt = db()->prepare('SELECT * FROM viohy_users WHERE username = ? AND is_blocked = 0 LIMIT 1');
    $stmt->execute([$username]);
    $profile = $stmt->fetch() ?: null;
}

if (!$profile) {
    http_response_code(404);
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profil bulunamadı · VIOHY</title><link rel="icon" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/themes-v2.css?v=9.2.0"></head><body class="viohy-profile theme-viohy-flow"><main class="not-found"><img src="/assets/logo.svg" alt="VIOHY"><h1>Profil bulunamadı</h1><p>Bu kullanıcı adı henüz alınmamış veya profil erişime kapalı.</p><a class="theme-button" href="/register">Bu adı sen al</a></main></body></html><?php
    exit;
}

$viewKey = 'viewed_' . (int) $profile['id'];
if (empty($_SESSION[$viewKey]) || (int) $_SESSION[$viewKey] < time() - 1800) {
    db()->prepare('INSERT INTO viohy_events(user_id,event_type,visitor_hash,referrer,created_at) VALUES(?,?,?,?,NOW())')
        ->execute([(int) $profile['id'], 'view', client_hash(), substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500)]);
    $_SESSION[$viewKey] = time();
}

$stmt = db()->prepare('SELECT * FROM viohy_links WHERE user_id = ? AND is_active = 1 ORDER BY sort_order,id');
$stmt->execute([(int) $profile['id']]);
$links = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM viohy_cards WHERE user_id = ? AND is_active = 1 ORDER BY sort_order,id');
$stmt->execute([(int) $profile['id']]);
$cards = $stmt->fetchAll();

$themeKey = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $profile['theme'])) ?: 'viohy-flow';
$t = theme($themeKey);
$styleKey = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($t['style'] ?? 'flow'))) ?: 'flow';
$sectorKey = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($profile['sector'] ?? 'creator'))) ?: 'creator';
$sectorLabel = sectors()[$profile['sector']] ?? 'Dijital profil';
$owner = user();
$isOwner = $owner && (int) $owner['id'] === (int) $profile['id'];

$whatsapp = '';
$socialPlatforms = ['instagram','tiktok','youtube','spotify','x','facebook','telegram','discord','whatsapp'];
$socialLinks = [];
foreach ($links as $link) {
    if ($link['platform'] === 'whatsapp') {
        $whatsapp = '/go/' . (int) $link['id'];
    }
    if (in_array($link['platform'], $socialPlatforms, true) && count($socialLinks) < 8) {
        $socialLinks[] = $link;
    }
}

$typeLabels = [
    'product' => 'Ürün',
    'service' => 'Hizmet',
    'menu' => 'Menü',
    'event' => 'Etkinlik',
    'media' => 'Medya',
    'appointment' => 'Randevu',
    'program' => 'Program',
];

$cssVars = '--p-bg:' . ($t['bg'] ?? '#f6f8ff') . ';--p-surface:' . ($t['surface'] ?? '#ffffff') . ';--p-text:' . ($t['text'] ?? '#101828') . ';--p-accent:' . ($t['accent'] ?? '#6d5dfc') . ';--p-accent2:' . ($t['accent2'] ?? '#21d4c2') . ';';
$pageTitle = e((string) $profile['name']) . ' · VIOHY';
$description = trim((string) ($profile['bio'] ?: $sectorLabel . ' profili'));
?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="<?= e((string) ($t['bg'] ?? '#0b1020')) ?>">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= e(mb_substr($description, 0, 160)) ?>">
    <meta property="og:title" content="<?= e((string) $profile['name']) ?>">
    <meta property="og:description" content="<?= e(mb_substr($description, 0, 160)) ?>">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="<?= e(site_url('/' . $profile['username'])) ?>">
    <?php if ($profile['avatar']): ?><meta property="og:image" content="<?= e(site_url((string) $profile['avatar'])) ?>"><?php endif; ?>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/themes-v2.css?v=9.2.0">
    <script defer src="/assets/profile-theme.js?v=9.2.0"></script>
</head>
<body class="viohy-profile theme-<?= e($themeKey) ?> style-<?= e($styleKey) ?> sector-<?= e($sectorKey) ?>" style="<?= e($cssVars) ?>">
<div class="theme-backdrop" aria-hidden="true"><span></span><span></span><span></span></div>

<header class="profile-nav">
    <a class="viohy-mini" href="/" aria-label="VIOHY ana sayfa"><img src="/assets/viohy-mark.svg" alt=""><span>VIOHY</span></a>
    <div class="profile-nav-actions">
        <?php if ($isOwner): ?><a class="nav-pill" href="/dashboard/design"><?= icon('palette','icon-sm') ?><span>Düzenle</span></a><?php endif; ?>
        <button class="nav-pill" type="button" data-share data-url="<?= e(site_url('/' . $profile['username'])) ?>" data-title="<?= e((string) $profile['name']) ?>"><?= icon('share','icon-sm') ?><span>Paylaş</span></button>
    </div>
</header>

<main class="profile-shell">
    <section class="profile-hero">
        <div class="hero-cover <?= $profile['cover'] ? 'has-image' : 'no-image' ?>">
            <?php if ($profile['cover']): ?><img src="<?= e((string) $profile['cover']) ?>" alt="<?= e((string) $profile['name']) ?> kapak görseli"><?php endif; ?>
            <div class="cover-shade"></div>
            <span class="sector-chip"><?= e($sectorLabel) ?></span>
        </div>
        <div class="hero-content">
            <img class="profile-avatar" src="<?= e((string) ($profile['avatar'] ?: '/assets/favicon.svg')) ?>" alt="<?= e((string) $profile['name']) ?>">
            <div class="identity">
                <h1><?= e((string) $profile['name']) ?></h1>
                <div class="handle">@<?= e((string) $profile['username']) ?></div>
                <p class="profile-bio"><?= nl2br(e((string) ($profile['bio'] ?: 'Dijital dünyam tek bağlantıda.'))) ?></p>
            </div>
            <?php if ($socialLinks): ?>
            <nav class="social-dock" aria-label="Sosyal medya bağlantıları">
                <?php foreach ($socialLinks as $link): ?>
                    <a class="social-button" data-platform="<?= e((string) $link['platform']) ?>" href="/go/<?= (int) $link['id'] ?>" target="_blank" rel="noopener" aria-label="<?= e(platform_options()[$link['platform']] ?? (string) $link['title']) ?>"><?= platform_icon((string) $link['platform']) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($links): ?>
    <section class="profile-block links-block">
        <div class="block-heading"><span>Bağlantılar</span><small><?= count($links) ?> bağlantı</small></div>
        <div class="link-stack">
            <?php foreach ($links as $index => $link): ?>
            <a class="modern-link <?= $index === 0 ? 'featured' : '' ?>" data-platform="<?= e((string) $link['platform']) ?>" href="/go/<?= (int) $link['id'] ?>" target="_blank" rel="noopener">
                <span class="link-icon"><?= platform_icon((string) $link['platform']) ?></span>
                <span class="link-copy"><strong><?= e((string) $link['title']) ?></strong><small><?= e(platform_options()[$link['platform']] ?? 'Bağlantı') ?></small></span>
                <span class="link-arrow"><?= icon('arrow') ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($cards): ?>
    <section class="profile-block showcase-block">
        <div class="block-heading"><span>Öne çıkanlar</span><small><?= count($cards) ?> içerik</small></div>
        <div class="showcase-grid">
            <?php foreach ($cards as $card): ?>
            <article class="showcase-card type-<?= e((string) $card['type']) ?>">
                <div class="showcase-media <?= $card['image'] ? 'has-image' : 'no-image' ?>">
                    <?php if ($card['image']): ?><img src="<?= e((string) $card['image']) ?>" alt="<?= e((string) $card['title']) ?>"><?php else: ?><span><?= icon('content') ?></span><?php endif; ?>
                    <span class="type-chip"><?= e($typeLabels[$card['type']] ?? ucfirst((string) $card['type'])) ?></span>
                    <?php if ($card['badge']): ?><span class="card-badge"><?= e((string) $card['badge']) ?></span><?php endif; ?>
                </div>
                <div class="showcase-content">
                    <h2><?= e((string) $card['title']) ?></h2>
                    <?php if ($card['subtitle']): ?><p><?= e((string) $card['subtitle']) ?></p><?php endif; ?>
                    <div class="showcase-bottom">
                        <?php if ($card['price']): ?><strong class="price-tag"><?= e((string) $card['price']) ?></strong><?php endif; ?>
                        <?php if ($card['url']): ?><a class="card-action" href="<?= e((string) $card['url']) ?>" target="_blank" rel="noopener">İncele <?= icon('arrow','icon-sm') ?></a><?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <details class="contact-sheet profile-block">
        <summary>
            <span class="contact-icon"><?= icon('mail') ?></span>
            <span><strong>İletişime geç</strong><small>Mesajını doğrudan <?= e((string) $profile['name']) ?> ile paylaş</small></span>
            <span class="summary-arrow"><?= icon('arrow') ?></span>
        </summary>
        <form action="/" method="post" class="contact-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="contact">
            <input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
            <label><span>Adın</span><input name="contact_name" required maxlength="80" autocomplete="name"></label>
            <label><span>E-posta</span><input type="email" name="contact_email" required maxlength="160" autocomplete="email"></label>
            <label><span>Telefon <em>isteğe bağlı</em></span><input name="contact_phone" maxlength="30" autocomplete="tel"></label>
            <label><span>Mesajın</span><textarea name="contact_message" required maxlength="1200" rows="4"></textarea></label>
            <button class="theme-button" type="submit">Mesajı gönder <?= icon('arrow','icon-sm') ?></button>
        </form>
    </details>

    <footer class="profile-footer"><img src="/assets/viohy-mark.svg" alt=""><span>VIOHY ile oluşturuldu</span></footer>
</main>

<?php if ($whatsapp): ?><a class="whatsapp-float-v2" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="WhatsApp ile iletişime geç"><?= icon('whatsapp') ?><span>WhatsApp</span></a><?php endif; ?>
<div class="share-toast" role="status" aria-live="polite">Bağlantı kopyalandı</div>
</body>
</html>
