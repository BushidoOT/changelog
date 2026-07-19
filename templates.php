<?php
declare(strict_types=1);

function html_head(string $title, string $bodyClass = ''): void
{
    $description = 'VIOHY ile linklerini, ürünlerini, hizmetlerini ve dijital vitrini tek sayfada yönet.';
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#6d5dfc"><title>' . e($title) . ' · VIOHY</title><meta name="description" content="' . e($description) . '"><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/app.css?v=' . e(VIOHY_VERSION) . '"><script defer src="/assets/app.js?v=' . e(VIOHY_VERSION) . '"></script></head><body class="' . e($bodyClass) . '">';
    $items = flashes();
    if ($items) {
        echo '<div class="alert-stack">';
        foreach ($items as $item) {
            $type = in_array($item['type'], ['success','error','info'], true) ? $item['type'] : 'info';
            $ico = $type === 'success' ? 'check' : ($type === 'error' ? 'close' : 'home');
            echo '<div class="alert ' . $type . '">' . icon($ico) . '<div>' . e($item['message']) . '</div></div>';
        }
        echo '</div>';
    }
}

function html_end(): void
{
    echo '</body></html>';
}

function marketing_header(): void
{
    echo '<header class="site-header"><div class="container site-header-inner"><a href="/" aria-label="VIOHY ana sayfa"><img class="logo" src="/assets/logo.svg" alt="VIOHY"></a><nav class="site-nav"><a href="#ozellikler">Özellikler</a><a href="#sektorler">Sektörler</a><a href="#fiyatlar">Paketler</a></nav><div class="site-actions"><a class="btn btn-secondary" href="/login">Giriş yap</a><a class="btn btn-primary" href="/register">Ücretsiz başla ' . icon('arrow','icon-sm') . '</a></div></div></header>';
}

function marketing_footer(): void
{
    echo '<footer class="site-footer"><div class="container footer-inner"><div><img src="/assets/logo.svg" alt="VIOHY" style="height:34px;filter:brightness(0) invert(1);margin-bottom:10px"><div class="small">Dijital vitrinin, tek adreste.</div></div><div class="footer-links"><a href="/login">Giriş</a><a href="/register">Kayıt</a><a href="mailto:destek@viohy.com">Destek</a><span>© ' . date('Y') . ' VIOHY</span></div></div></footer>';
}

function render_landing(): void
{
    html_head('Dijital vitrinin tek adreste');
    marketing_header();
    echo '<main><section class="hero"><div class="container hero-grid"><div><span class="chip">' . icon('check','icon-sm') . ' Kurulumu kolay, kullanımı hızlı</span><h1>İnternetteki bütün dünyan <span class="text-gradient">tek bağlantıda.</span></h1><p>Linklerini, ürünlerini, menünü, randevularını ve içeriklerini sektörüne özel modern bir sayfada buluştur. Kod bilmeden düzenle, paylaş ve sonuçları ölç.</p><div class="hero-actions"><a class="btn btn-primary" href="/register">Ücretsiz profilini oluştur ' . icon('arrow','icon-sm') . '</a><a class="btn btn-secondary" href="#sektorler">Temaları keşfet ' . icon('eye','icon-sm') . '</a></div><div class="hero-proof"><div class="avatar-stack"><span></span><span></span><span></span><span></span></div><span>İçerik üreticileri ve işletmeler için tasarlandı</span></div></div><div class="phone-mock"><div class="phone-screen"><div class="mock-profile"><div class="mock-avatar"></div><strong style="font-size:22px">Lunera Studio</strong><div class="muted small">Yeni koleksiyon ve özel tasarımlar</div></div><div class="mock-link"><span class="icon-wrap" style="background:#e8f9ee;color:#1fa855">' . icon('whatsapp') . '</span><span>WhatsApp ile sipariş ver</span></div><div class="mock-link"><span class="icon-wrap">' . icon('instagram') . '</span><span>Instagram koleksiyonu</span></div><div class="mock-products"><div class="mock-product"><div class="mock-product-image"></div><div class="mock-product-body"><strong>Yeni sezon</strong><div class="small muted">₺1.290</div></div></div><div class="mock-product"><div class="mock-product-image" style="background:linear-gradient(135deg,#bdeee8,#bfc7ff)"></div><div class="mock-product-body"><strong>Özel seri</strong><div class="small muted">₺1.790</div></div></div></div></div></div></div></section>';
    echo '<section id="ozellikler" class="section section-soft"><div class="container"><div class="section-head"><span class="chip">VIOHY Stüdyo</span><h2>Sıradan bağlantı listesinden fazlası</h2><p>Markanı güçlü göstermek ve ziyaretçiyi aksiyona yönlendirmek için ihtiyaç duyduğun bütün araçlar.</p></div><div class="grid grid-3">';
    $features = [
        ['palette','31 modern tema','Oyun, butik, beauty, cafe, fitness, müzik ve genel kullanım için özgün görünümler.'],
        ['content','Sektörel içerik kartları','Ürün, hizmet, menü, etkinlik, müzik, randevu ve medya kartları oluştur.'],
        ['whatsapp','Gerçek sosyal ikonlar','WhatsApp, Instagram, TikTok, YouTube, Spotify ve diğer platformları markalı ikonlarla göster.'],
        ['chart','Detaylı analiz','Profil görüntülenmelerini, bağlantı tıklamalarını ve en iyi performans gösteren içerikleri izle.'],
        ['inbox','Müşteri kutusu','Ziyaretçilerin iletişim taleplerini panelinden düzenli şekilde yönet.'],
        ['settings','Tek panelden yönetim','Profil, tema, içerik, bağlantı ve ayarları mobil uyumlu modern panelden düzenle.'],
    ];
    foreach ($features as [$ico,$title,$text]) {
        echo '<article class="feature-card"><div class="feature-icon">' . icon($ico) . '</div><h3>' . e($title) . '</h3><p>' . e($text) . '</p></article>';
    }
    echo '</div></div></section>';
    echo '<section id="sektorler" class="section"><div class="container"><div class="section-head"><span class="chip">Her sektöre özel</span><h2>Sayfan, işinin karakterini taşısın</h2><p>Sadece renk değil; kart biçimleri, vitrin düzeni, ikonlar ve görsel dil sektörüne göre değişir.</p></div><div class="grid grid-3">';
    $sectors = [
        ['gaming','Oyun & Espor','Canlı yayın, Discord, klip ve takım bağlantıları.'],
        ['boutique','Butik & Moda','Ürün vitrini, fiyat, kampanya ve WhatsApp sipariş.'],
        ['beauty','Beauty & Kozmetik','Randevu, hizmet, ürün ve öncesi/sonrası içerikleri.'],
        ['cafe','Cafe & Restoran','Menü, rezervasyon, konum ve çalışma saatleri.'],
        ['fitness','Fitness & Koç','Program, dönüşüm, danışmanlık ve video içerikleri.'],
        ['music','Müzik & Sahne','Parçalar, konser tarihleri, bilet ve müzik platformları.'],
    ];
    foreach ($sectors as [$key,$title,$text]) {
        echo '<article class="sector-card sector-' . e($key) . '"><span class="badge" style="width:max-content;background:rgba(255,255,255,.15);color:white">4 premium tema</span><h3>' . e($title) . '</h3><p>' . e($text) . '</p></article>';
    }
    echo '</div></div></section>';
    echo '<section id="fiyatlar" class="section section-soft"><div class="container"><div class="section-head"><span class="chip">Şeffaf paketler</span><h2>Büyüdükçe güçlenen yapı</h2><p>İlk profilini ücretsiz oluştur, daha fazla özellik gerektiğinde paketini yükselt.</p></div><div class="grid grid-3">';
    $plans = [
        ['Free','₺0','Başlangıç için temel profil',['8 bağlantı','1 sektör teması','Temel analiz','VIOHY imzası'],false],
        ['Pro','₺149','İçerik üreticileri için',['Sınırsız bağlantı','31 tema','İçerik stüdyosu','Gelişmiş analiz','VIOHY imzasını kaldır'],true],
        ['Business','₺299','İşletmeler ve markalar için',['Pro özelliklerinin tamamı','Müşteri iletişim kutusu','Ürün ve hizmet vitrinleri','Öncelikli destek','Daha yüksek görsel kotası'],false],
    ];
    foreach ($plans as [$name,$price,$desc,$list,$featured]) {
        echo '<article class="card pricing-card' . ($featured ? ' featured' : '') . '">' . ($featured ? '<span class="badge">En popüler</span>' : '') . '<h3>' . e($name) . '</h3><p class="muted">' . e($desc) . '</p><div class="price">' . e($price) . '<small>/ay</small></div><div class="check-list">';
        foreach ($list as $item) echo '<div class="check-item">' . icon('check','icon-sm') . '<span>' . e($item) . '</span></div>';
        echo '</div><a class="btn ' . ($featured ? 'btn-primary' : 'btn-secondary') . '" style="width:100%" href="/register">Hemen başla</a></article>';
    }
    echo '</div></div></section></main>';
    marketing_footer();
    html_end();
}

function render_auth(string $mode, array $values = [], array $errors = []): void
{
    $isLogin = $mode === 'login';
    html_head($isLogin ? 'Giriş yap' : 'Ücretsiz kayıt ol', 'auth-shell');
    echo '<aside class="auth-brand"><a href="/"><img src="/assets/logo.svg" alt="VIOHY" style="height:44px;filter:brightness(0) invert(1)"></a><div class="auth-copy"><span class="chip">Dijital vitrinin</span><h1>İçeriğin tek akışta <span style="color:#54e6d2">parlasın.</span></h1><p>31 tema, gerçek sosyal medya ikonları, ürün kartları, analizler ve tamamen sana ait bir profil.</p></div><div class="auth-pills"><span class="chip">Oyun</span><span class="chip">Butik</span><span class="chip">Cafe</span><span class="chip">Müzik</span><span class="chip">Fitness</span></div></aside>';
    echo '<main class="auth-main"><section class="auth-card"><img class="brand-mark" src="/assets/logo.svg" alt="VIOHY"><span class="tiny text-gradient" style="font-weight:900;letter-spacing:.12em;text-transform:uppercase">' . ($isLogin ? 'Tekrar hoş geldin' : 'Yeni hesabını oluştur') . '</span><h2>' . ($isLogin ? 'Giriş yap' : 'Ücretsiz başla') . '</h2><p class="muted small">' . ($isLogin ? 'Paneline devam etmek için bilgilerini gir.' : 'Birkaç dakika içinde dijital vitrinin hazır olsun.') . '</p>';
    if ($errors) echo '<div class="alert error" style="position:static;margin:16px 0">' . icon('close') . '<div>' . implode('<br>', array_map('e',$errors)) . '</div></div>';
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="' . ($isLogin ? 'login' : 'register') . '">';
    if (!$isLogin) {
        echo '<label class="field"><span class="label">Ad soyad</span><input class="input" name="name" required maxlength="100" value="' . e($values['name'] ?? '') . '" autocomplete="name"></label><label class="field"><span class="label">Kullanıcı adı</span><input class="input" name="username" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9._-]+" value="' . e($values['username'] ?? '') . '" placeholder="markam" autocomplete="username"><span class="help">viohy.com/kullaniciadi</span></label>';
    }
    echo '<label class="field"><span class="label">' . ($isLogin ? 'E-posta veya kullanıcı adı' : 'E-posta') . '</span><input class="input" name="identity" type="' . ($isLogin ? 'text' : 'email') . '" required value="' . e($values['identity'] ?? '') . '" autocomplete="' . ($isLogin ? 'username' : 'email') . '"></label><label class="field"><span class="label">Şifre</span><input class="input" name="password" type="password" required minlength="8" autocomplete="' . ($isLogin ? 'current-password' : 'new-password') . '"></label><button class="btn btn-primary" type="submit">' . ($isLogin ? 'Giriş yap' : 'Hesabımı oluştur') . ' ' . icon('arrow','icon-sm') . '</button></form><div class="auth-links">' . ($isLogin ? 'Hesabın yok mu? <a href="/register">Ücretsiz kayıt ol</a>' : 'Zaten hesabın var mı? <a href="/login">Giriş yap</a>') . '</div></section></main>';
    html_end();
}

function dashboard_nav(string $active, array $u): void
{
    $items = [
        'overview'=>['/dashboard','home','Genel bakış'],
        'profile'=>['/dashboard/profile','user','Profil'],
        'links'=>['/dashboard/links','link','Bağlantılar'],
        'content'=>['/dashboard/content','content','İçerikler'],
        'design'=>['/dashboard/design','palette','Tasarım'],
        'analytics'=>['/dashboard/analytics','chart','Analiz'],
        'inbox'=>['/dashboard/inbox','inbox','Müşteri kutusu'],
    ];
    if ((int) $u['is_admin']) $items['admin']=['/admin','settings','Yönetim'];
    echo '<aside class="sidebar" id="sidebar"><div class="sidebar-logo"><a href="/dashboard"><img src="/assets/logo.svg" alt="VIOHY"></a></div><nav class="sidebar-nav">';
    foreach ($items as $key=>[$href,$ico,$label]) echo '<a class="' . ($active===$key?'active':'') . '" href="' . e($href) . '">' . icon($ico) . '<span>' . e($label) . '</span></a>';
    echo '</nav><div class="sidebar-bottom"><div class="account-mini"><img class="account-avatar" src="' . e($u['avatar'] ?: '/assets/favicon.svg') . '" alt=""><div><strong>' . e($u['name']) . '</strong><span>@' . e($u['username']) . ' · ' . e(ucfirst($u['plan'])) . '</span></div></div><a class="btn btn-secondary" href="/logout">' . icon('logout','icon-sm') . ' Çıkış yap</a></div></aside>';
}

function dashboard_start(string $active, string $title, string $subtitle, array $u): void
{
    html_head($title, 'dashboard');
    dashboard_nav($active,$u);
    echo '<main class="dash-main"><header class="dash-topbar"><div style="display:flex;align-items:center;gap:12px"><button class="btn btn-secondary btn-icon mobile-menu" data-menu aria-label="Menü">' . icon('menu') . '</button><div class="topbar-title"><h1>' . e($title) . '</h1><p>' . e($subtitle) . '</p></div></div><div class="topbar-actions"><a class="btn btn-secondary" target="_blank" href="/' . e($u['username']) . '">' . icon('eye','icon-sm') . '<span>Profili aç</span></a></div></header><div class="dash-content">';
}

function dashboard_end(): void
{
    echo '</div></main>';
    html_end();
}

function render_public_profile(array $profile, array $links, array $cards): void
{
    $t = theme((string) $profile['theme']);
    $style = 'style-' . $t['style'];
    $vars = '--p-bg:' . $t['bg'] . ';--p-surface:' . $t['surface'] . ';--p-text:' . $t['text'] . ';--p-accent:' . $t['accent'] . ';--p-accent2:' . $t['accent2'];
    html_head($profile['name'], 'public-body ' . $style);
    echo '<div style="' . e($vars) . '"><main class="public-wrap"><section class="public-hero"><div class="cover"' . ($profile['cover'] ? ' style="background-image:url(' . e($profile['cover']) . ')"' : '') . '></div><div class="public-profile-head"><img class="public-avatar" src="' . e($profile['avatar'] ?: '/assets/favicon.svg') . '" alt="' . e($profile['name']) . '"><h1>' . e($profile['name']) . '</h1><div class="username">@' . e($profile['username']) . '</div><p class="bio">' . nl2br(e($profile['bio'] ?: 'VIOHY üzerinde dijital dünyam.')) . '</p><div class="social-row">';
    $whatsapp = '';
    foreach ($links as $link) {
        if (in_array($link['platform'], ['instagram','tiktok','youtube','spotify','x','facebook','telegram','discord','whatsapp'], true)) {
            echo '<a class="social-circle" href="/go/' . (int) $link['id'] . '" target="_blank" rel="noopener" aria-label="' . e(platform_options()[$link['platform']] ?? $link['title']) . '">' . platform_icon($link['platform']) . '</a>';
        }
        if ($link['platform']==='whatsapp') $whatsapp='/go/' . (int) $link['id'];
    }
    echo '</div></div></section><section class="public-section">';
    foreach ($links as $link) {
        echo '<a class="public-link ' . ($link['platform']==='whatsapp'?'whatsapp':'') . '" href="/go/' . (int) $link['id'] . '" target="_blank" rel="noopener"><span class="public-link-icon">' . platform_icon((string)$link['platform']) . '</span><span class="public-link-text"><strong>' . e($link['title']) . '</strong><span>' . e(platform_options()[$link['platform']] ?? 'Bağlantı') . '</span></span>' . icon('arrow') . '</a>';
    }
    echo '</section>';
    if ($cards) {
        echo '<section class="public-section"><div class="content-grid">';
        foreach ($cards as $card) {
            $imageStyle = $card['image'] ? ' style="background-image:url(' . e($card['image']) . ')"' : '';
            echo '<article class="public-card"><div class="public-card-image"' . $imageStyle . '></div><div class="public-card-body"><div class="public-card-top"><h3>' . e($card['title']) . '</h3>' . ($card['badge'] ? '<span class="badge">' . e($card['badge']) . '</span>' : '') . '</div><p>' . e($card['subtitle']) . '</p>' . ($card['price'] ? '<div class="public-card-price">' . e($card['price']) . '</div>' : '') . ($card['url'] ? '<a class="btn" href="' . e($card['url']) . '" target="_blank" rel="noopener">İncele ' . icon('arrow','icon-sm') . '</a>' : '') . '</div></article>';
        }
        echo '</div></section>';
    }
    echo '<section class="public-section contact-card"><div class="card-header"><div><h2 class="card-title">İletişime geç</h2><div class="small muted">Mesajın doğrudan profil sahibinin paneline gider.</div></div>' . icon('mail') . '</div><form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="contact"><input type="hidden" name="profile_id" value="' . (int)$profile['id'] . '"><label class="field"><span class="label">Adın</span><input class="input" name="contact_name" required maxlength="80"></label><label class="field"><span class="label">E-posta</span><input class="input" type="email" name="contact_email" required maxlength="160"></label><label class="field full"><span class="label">Telefon</span><input class="input" name="contact_phone" maxlength="30"></label><label class="field full"><span class="label">Mesajın</span><textarea class="textarea" name="contact_message" required maxlength="1200"></textarea></label><button class="btn btn-primary full" type="submit">Mesajı gönder ' . icon('arrow','icon-sm') . '</button></form></section><footer class="public-footer"><img src="/assets/logo.svg" alt="VIOHY">VIOHY ile oluşturuldu</footer></main>' . ($whatsapp ? '<a class="whatsapp-float" href="' . e($whatsapp) . '" target="_blank" aria-label="WhatsApp">' . icon('whatsapp') . '</a>' : '') . '</div>';
    html_end();
}
