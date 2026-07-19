<?php
declare(strict_types=1);

/**
 * VIOHY Paket 9 - Modern FTP Guncellemesi
 * Mevcut Paket 8 PHP/cPanel kurulumunu yerinde modernlestirir.
 * Islemden sonra bu dosyayi ve viohy-package klasorunu silin.
 */

session_start();
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$root = __DIR__;
$assetSource = $root . '/viohy-package/assets';
$assetTarget = $root . '/assets/viohy';
$report = [];
$errors = [];
$changedCount = 0;
$scannedCount = 0;
$backupDir = '';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function addReport(array &$report, string $message): void {
    $report[] = $message;
}

function ensureDir(string $path): bool {
    if (is_dir($path)) {
        return true;
    }
    return @mkdir($path, 0755, true) || is_dir($path);
}

function copyFileSafe(string $source, string $target): bool {
    if (!is_file($source)) {
        return false;
    }
    if (!ensureDir(dirname($target))) {
        return false;
    }
    return @copy($source, $target);
}

function backupFile(string $file, string $root, string $backupDir): bool {
    $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
    $target = $backupDir . '/' . $relative;
    if (!ensureDir(dirname($target))) {
        return false;
    }
    return @copy($file, $target);
}

function isExcludedPath(string $path, string $root): bool {
    $relative = '/' . ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    $blocked = [
        '/uploads/', '/vendor/', '/node_modules/', '/.git/', '/cache/', '/logs/',
        '/viohy-package/', '/_viohy_backup_', '/cgi-bin/'
    ];
    foreach ($blocked as $needle) {
        if (stripos($relative, $needle) !== false) {
            return true;
        }
    }
    return basename($path) === basename(__FILE__);
}

function modernizeContent(string $content, string $extension): string {
    $content = str_replace(
        [
            'BioAkış Normal Hosting Edition',
            'BioAkis Normal Hosting Edition',
            'BioAkış ile oluşturuldu',
            'BioAkis ile olusturuldu',
            'BİOAKIŞ',
            'BioAkış',
            'bioakis.com',
            'bioakış.com'
        ],
        [
            'VIOHY',
            'VIOHY',
            'VIOHY ile oluşturuldu',
            'VIOHY ile oluşturuldu',
            'VIOHY',
            'VIOHY',
            'viohy.com',
            'viohy.com'
        ],
        $content
    );

    // BioAkis ifadesini yalnizca gorunen metin/string konumlarinda degistirir.
    $content = (string) preg_replace('/(^|[>\s\"\'\'`])BioAkis(?=[$<\s\"\'\'`])/u', '$1VIOHY', $content);

    if (in_array($extension, ['php', 'html', 'htm'], true)) {
        if (stripos($content, '</head>') !== false && stripos($content, 'viohy-brand.css') === false) {
            $head = "\n<link rel=\"icon\" type=\"image/svg+xml\" href=\"/assets/viohy/viohy-mark.svg\">\n" .
                    "<link rel=\"stylesheet\" href=\"/assets/viohy/viohy-brand.css?v=9.0.0\">\n";
            $content = str_ireplace('</head>', $head . '</head>', $content);
        }
        if (stripos($content, '</body>') !== false && stripos($content, 'viohy-ui.js') === false) {
            $script = "\n<script src=\"/assets/viohy/viohy-ui.js?v=9.0.0\" defer></script>\n";
            $content = str_ireplace('</body>', $script . '</body>', $content);
        }
    }

    return $content;
}

function modernizeFiles(string $root, string $backupDir, array &$report, array &$errors, int &$scannedCount, int &$changedCount): void {
    $allowed = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $file = $item->getPathname();
        if (isExcludedPath($file, $root)) {
            continue;
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            continue;
        }
        if ($item->getSize() > 2_500_000) {
            continue;
        }

        $scannedCount++;
        $original = @file_get_contents($file);
        if ($original === false || strpos($original, "\0") !== false) {
            continue;
        }
        $updated = modernizeContent($original, $extension);
        if ($updated === $original) {
            continue;
        }

        if (!backupFile($file, $root, $backupDir)) {
            $errors[] = 'Yedeklenemedi: ' . $file;
            continue;
        }
        if (@file_put_contents($file, $updated, LOCK_EX) === false) {
            $errors[] = 'Yazilamadi: ' . $file;
            continue;
        }
        $changedCount++;
    }

    addReport($report, $scannedCount . ' metin dosyasi tarandi.');
    addReport($report, $changedCount . ' dosya VIOHY markasi ve modern arayuz kaynaklariyla guncellendi.');
}

function removeLegacyFiles(string $root, string $backupDir, array &$report): void {
    $files = [
        'BioAkis-Paket-8-Dehost-cPanel-PublicHTML.zip',
        'README.md', 'DEGISIKLIKLER.md', 'DEHOST-CPANEL-KURULUMU.md',
        'HIZLI-KURULUM.txt', 'PAKET-8-KONTROL-LISTESI.md', 'PAKET-8-TEST-RAPORU.md'
    ];
    $removed = 0;
    foreach ($files as $name) {
        $path = $root . '/' . $name;
        if (!is_file($path)) {
            continue;
        }
        backupFile($path, $root, $backupDir);
        if (@unlink($path)) {
            $removed++;
        }
    }
    addReport($report, $removed . ' eski paket/dokuman dosyasi temizlendi.');
}

function removeDirectory(string $directory): bool {
    if (!is_dir($directory)) {
        return true;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $ok = $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        if (!$ok) {
            return false;
        }
    }
    return @rmdir($directory);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'apply') {
        $token = (string) ($_POST['token'] ?? '');
        if (!hash_equals((string) ($_SESSION['viohy_token'] ?? ''), $token)) {
            $errors[] = 'Guvenlik dogrulamasi basarisiz. Sayfayi yenileyip tekrar deneyin.';
        } elseif (!is_dir($assetSource)) {
            $errors[] = 'viohy-package/assets klasoru bulunamadi. ZIP icindeki tum dosyalari public_html dizinine yukleyin.';
        } else {
            $backupDir = $root . '/_viohy_backup_' . date('Ymd_His');
            if (!ensureDir($backupDir)) {
                $errors[] = 'Yedek klasoru olusturulamadi.';
            } else {
                $assets = ['viohy-logo.svg', 'viohy-mark.svg', 'viohy-brand.css', 'viohy-ui.js'];
                foreach ($assets as $asset) {
                    if (!copyFileSafe($assetSource . '/' . $asset, $assetTarget . '/' . $asset)) {
                        $errors[] = 'Varlik kopyalanamadi: ' . $asset;
                    }
                }
                copyFileSafe($assetSource . '/viohy-mark.svg', $root . '/favicon.svg');
                addReport($report, 'Ucuncu VIOHY logo konseptine uygun logo, favicon, CSS ve JavaScript kaynaklari kuruldu.');

                modernizeFiles($root, $backupDir, $report, $errors, $scannedCount, $changedCount);
                removeLegacyFiles($root, $backupDir, $report);

                $installPath = $root . '/install';
                if (is_dir($installPath)) {
                    // Kurulum tamamlandigi icin install klasorunu guvenlik amaciyla kaldirir.
                    if (removeDirectory($installPath)) {
                        addReport($report, 'install klasoru guvenli sekilde kaldirildi.');
                    } else {
                        $errors[] = 'install klasoru otomatik silinemedi; cPanel/FTP uzerinden elle silin.';
                    }
                }

                $security = "\n# VIOHY security hardening\nOptions -Indexes\n<IfModule mod_headers.c>\nHeader always set X-Content-Type-Options \"nosniff\"\nHeader always set X-Frame-Options \"SAMEORIGIN\"\nHeader always set Referrer-Policy \"strict-origin-when-cross-origin\"\nHeader always set Permissions-Policy \"camera=(), microphone=(), geolocation=(self)\"\n</IfModule>\n";
                $htaccess = $root . '/.htaccess';
                $existing = is_file($htaccess) ? (string) @file_get_contents($htaccess) : '';
                if (stripos($existing, 'VIOHY security hardening') === false) {
                    backupFile($htaccess, $root, $backupDir);
                    @file_put_contents($htaccess, rtrim($existing) . $security, LOCK_EX);
                    addReport($report, 'Temel guvenlik basliklari ve klasor listeleme korumasi eklendi.');
                }

                @file_put_contents($root . '/VIOHY-GUNCELLEME-TAMAMLANDI.txt',
                    "VIOHY Paket 9 uygulandi: " . date('c') . "\nYedek: " . basename($backupDir) . "\nDegisen dosya: " . $changedCount . "\n",
                    LOCK_EX
                );
            }
        }
    } elseif ($action === 'cleanup') {
        $token = (string) ($_POST['token'] ?? '');
        if (hash_equals((string) ($_SESSION['viohy_token'] ?? ''), $token)) {
            removeDirectory($root . '/viohy-package');
            $self = __FILE__;
            echo '<!doctype html><meta charset="utf-8"><title>VIOHY</title><style>body{font-family:system-ui;background:#071323;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0}.box{max-width:620px;padding:36px;border:1px solid #334155;border-radius:24px;background:#0f1e33}a{color:#67e8f9}</style><div class="box"><h1>Temizlik tamamlandi</h1><p>Kaynak klasoru silindi. Guvenlik icin FTP veya cPanel uzerinden <b>' . h(basename($self)) . '</b> dosyasini da silin.</p><p><a href="/">VIOHY ana sayfasina don</a></p></div>';
            exit;
        }
    }
}

if (empty($_SESSION['viohy_token'])) {
    $_SESSION['viohy_token'] = bin2hex(random_bytes(24));
}
$token = (string) $_SESSION['viohy_token'];
$success = !empty($report) && empty($errors);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VIOHY Paket 9 Guncelleme</title>
<style>
:root{--bg:#071323;--panel:#0d1c31;--line:#243754;--text:#f8fafc;--muted:#9fb0c7;--a:#38e6d1;--b:#5b7cff;--c:#b260f5}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--text);background:radial-gradient(circle at 15% 5%,rgba(56,230,209,.18),transparent 30%),radial-gradient(circle at 90% 10%,rgba(178,96,245,.14),transparent 28%),var(--bg);padding:32px 18px}.shell{max-width:930px;margin:auto}.brand{display:flex;align-items:center;gap:14px;margin-bottom:22px}.brand img{width:58px;height:58px}.brand strong{font-size:25px;letter-spacing:.16em}.grid{display:grid;grid-template-columns:1.3fr .7fr;gap:18px}.card{background:linear-gradient(145deg,rgba(20,39,65,.96),rgba(9,24,44,.96));border:1px solid var(--line);border-radius:26px;padding:28px;box-shadow:0 28px 80px rgba(0,0,0,.25)}h1{font-size:clamp(30px,5vw,54px);line-height:1.02;margin:0 0 16px;letter-spacing:-.04em}h2{margin-top:0}.lead{color:var(--muted);font-size:17px;line-height:1.7}.chips{display:flex;flex-wrap:wrap;gap:9px;margin:22px 0}.chip{border:1px solid rgba(103,232,249,.25);background:rgba(15,35,58,.8);border-radius:999px;padding:8px 12px;color:#c8fafe;font-size:13px}.btn{width:100%;border:0;border-radius:15px;padding:15px 18px;font-weight:800;font-size:15px;color:#05111f;cursor:pointer;background:linear-gradient(135deg,var(--a),#65b7ff 52%,#c884ff);box-shadow:0 14px 35px rgba(56,230,209,.22);transition:.2s}.btn:hover{transform:translateY(-2px);filter:brightness(1.05)}.btn.secondary{background:#152941;color:#eaf4ff;border:1px solid #35506f;box-shadow:none}.list{display:grid;gap:10px;padding:0;list-style:none}.list li{display:flex;gap:10px;color:#cad7e8;line-height:1.45}.list li:before{content:'✓';color:var(--a);font-weight:900}.alert{border-radius:16px;padding:14px 16px;margin:16px 0;line-height:1.55}.alert.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35)}.alert.err{background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.35)}code{color:#a5f3fc}.small{color:var(--muted);font-size:13px;line-height:1.6}@media(max-width:760px){.grid{grid-template-columns:1fr}.card{padding:22px}body{padding:18px 12px}}
</style>
</head>
<body>
<div class="shell">
  <div class="brand"><img src="viohy-package/assets/viohy-mark.svg" alt="VIOHY"><strong>VIOHY</strong></div>
  <div class="grid">
    <main class="card">
      <div class="chip" style="display:inline-flex">PAKET 9 · MODERN FTP GUNCELLEMESI</div>
      <h1>BioAkis izlerini kaldir, VIOHY'yi modernlestir.</h1>
      <p class="lead">Bu arac mevcut veritabanini ve kullanici iceriklerini silmeden marka metinlerini yeniler, ucuncu logo konseptini kurar, buton/kart/form tasarimlarini modernlestirir ve yaygin arayuz sorunlarini duzeltir.</p>

      <?php if ($errors): ?>
        <div class="alert err"><b>Islem tamamlanamadi:</b><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if ($report): ?>
        <div class="alert ok"><b>Guncelleme raporu:</b><ul><?php foreach ($report as $item): ?><li><?= h($item) ?></li><?php endforeach; ?></ul></div>
        <p class="small">Yedek klasoru: <code><?= h(basename($backupDir)) ?></code>. Siteyi Ctrl+F5 ile yenileyin ve mobil/masaustu gorunumlerini kontrol edin.</p>
        <form method="post"><input type="hidden" name="action" value="cleanup"><input type="hidden" name="token" value="<?= h($token) ?>"><button class="btn secondary" type="submit">Paket kaynaklarini temizle</button></form>
      <?php else: ?>
        <form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Guncelleniyor…'">
          <input type="hidden" name="action" value="apply"><input type="hidden" name="token" value="<?= h($token) ?>">
          <button class="btn" type="submit">VIOHY guncellemesini baslat</button>
        </form>
      <?php endif; ?>
    </main>
    <aside class="card">
      <h2>Pakette neler var?</h2>
      <ul class="list">
        <li>Ucuncu VIOHY logo konsepti ve favicon</li>
        <li>BioAkis/BioAkis gorunen metin temizligi</li>
        <li>Modern gradient butonlar ve kartlar</li>
        <li>Form, tablo ve mobil ekran iyilestirmeleri</li>
        <li>Cift tik/form tekrar gonderme korumasi</li>
        <li>Aktif menu, sifre gosterme ve tablo kaydirma</li>
        <li>Degisen dosyalar icin otomatik yedek</li>
        <li>Install ve eski paket dosyalarinin temizligi</li>
      </ul>
      <p class="small">Onemli: Bu dosyayi yalnizca yonetici olarak kullanin. Islem bittikten sonra FTP/cPanel uzerinden guncelleyici dosyasini silin.</p>
    </aside>
  </div>
</div>
</body>
</html>
