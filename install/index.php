<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$error = '';
$success = false;
$existing = [];
if (is_file($root . '/config.php')) {
    try {
        $loaded = require $root . '/config.php';
        if (is_array($loaded)) $existing = $loaded;
    } catch (Throwable) {}
}

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old(array $existing, string $key, string $default=''): string {
    $v=$existing;foreach(explode('.',$key) as $p){if(!is_array($v)||!array_key_exists($p,$v))return $default;$v=$v[$p];}return is_scalar($v)?(string)$v:$default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $siteName=trim((string)($_POST['site_name']??'VIOHY')) ?: 'VIOHY';
        $siteUrl=rtrim(trim((string)($_POST['site_url']??'')),'/');
        $host=trim((string)($_POST['db_host']??'localhost')) ?: 'localhost';
        $port=(int)($_POST['db_port']??3306) ?: 3306;
        $dbName=trim((string)($_POST['db_name']??''));
        $dbUser=trim((string)($_POST['db_user']??''));
        $dbPass=(string)($_POST['db_pass']??'');
        $adminName=trim((string)($_POST['admin_name']??''));
        $adminUsername=strtolower(trim((string)($_POST['admin_username']??'')));
        $adminEmail=strtolower(trim((string)($_POST['admin_email']??'')));
        $adminPassword=(string)($_POST['admin_password']??'');
        if(!$siteUrl||!filter_var($siteUrl,FILTER_VALIDATE_URL))throw new RuntimeException('Geçerli site adresi girin. Örnek: https://viohy.com');
        if(!$dbName||!$dbUser)throw new RuntimeException('Veritabanı adı ve kullanıcısı zorunludur.');
        if(mb_strlen($adminName)<2)throw new RuntimeException('Yönetici adı en az 2 karakter olmalıdır.');
        if(!preg_match('/^[a-z0-9._-]{3,30}$/',$adminUsername))throw new RuntimeException('Yönetici kullanıcı adı uygun değil.');
        if(!filter_var($adminEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Geçerli yönetici e-postası girin.');
        if(strlen($adminPassword)<8 && !is_file($root.'/config.php'))throw new RuntimeException('Yönetici şifresi en az 8 karakter olmalıdır.');

        $dsn="mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $pdo=new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $queries=[
"CREATE TABLE IF NOT EXISTS viohy_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 username VARCHAR(30) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 bio VARCHAR(350) NOT NULL DEFAULT '',
 avatar VARCHAR(255) NOT NULL DEFAULT '',
 cover VARCHAR(255) NOT NULL DEFAULT '',
 sector VARCHAR(40) NOT NULL DEFAULT 'creator',
 theme VARCHAR(60) NOT NULL DEFAULT 'viohy-flow',
 plan ENUM('free','pro','business') NOT NULL DEFAULT 'free',
 is_admin TINYINT(1) NOT NULL DEFAULT 0,
 is_blocked TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL,
 INDEX idx_username(username), INDEX idx_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS viohy_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(90) NOT NULL,
 url VARCHAR(1000) NOT NULL,
 platform VARCHAR(40) NOT NULL DEFAULT 'website',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_user_sort(user_id,sort_order),
 CONSTRAINT fk_viohy_links_user FOREIGN KEY(user_id) REFERENCES viohy_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS viohy_cards (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(40) NOT NULL DEFAULT 'product',
 title VARCHAR(100) NOT NULL,
 subtitle VARCHAR(300) NOT NULL DEFAULT '',
 price VARCHAR(40) NOT NULL DEFAULT '',
 badge VARCHAR(30) NOT NULL DEFAULT '',
 image VARCHAR(255) NOT NULL DEFAULT '',
 url VARCHAR(1000) NOT NULL DEFAULT '',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_cards_user_sort(user_id,sort_order),
 CONSTRAINT fk_viohy_cards_user FOREIGN KEY(user_id) REFERENCES viohy_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS viohy_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('view','click') NOT NULL,
 link_id BIGINT UNSIGNED NULL,
 visitor_hash CHAR(64) NOT NULL DEFAULT '',
 referrer VARCHAR(500) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_events_user_date(user_id,created_at), INDEX idx_events_link(link_id),
 CONSTRAINT fk_viohy_events_user FOREIGN KEY(user_id) REFERENCES viohy_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_viohy_events_link FOREIGN KEY(link_id) REFERENCES viohy_links(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS viohy_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(30) NOT NULL DEFAULT '',
 message TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_contacts_user_date(user_id,created_at),
 CONSTRAINT fk_viohy_contacts_user FOREIGN KEY(user_id) REFERENCES viohy_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS viohy_settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value TEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        foreach($queries as $sql)$pdo->exec($sql);

        $stmt=$pdo->prepare('SELECT id,password_hash FROM viohy_users WHERE email=? OR username=? LIMIT 1');$stmt->execute([$adminEmail,$adminUsername]);$admin=$stmt->fetch();
        if($admin){
            if(strlen($adminPassword)>=8){$stmt=$pdo->prepare('UPDATE viohy_users SET name=?,username=?,email=?,password_hash=?,is_admin=1,is_blocked=0,plan="business",updated_at=NOW() WHERE id=?');$stmt->execute([$adminName,$adminUsername,$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT),$admin['id']]);}
            else{$stmt=$pdo->prepare('UPDATE viohy_users SET name=?,username=?,email=?,is_admin=1,is_blocked=0,plan="business",updated_at=NOW() WHERE id=?');$stmt->execute([$adminName,$adminUsername,$adminEmail,$admin['id']]);}
        }else{
            if(strlen($adminPassword)<8)throw new RuntimeException('Yeni yönetici için en az 8 karakterli şifre girin.');
            $stmt=$pdo->prepare('INSERT INTO viohy_users(name,username,email,password_hash,bio,sector,theme,plan,is_admin,created_at) VALUES(?,?,?,?,?,?,?,?,1,NOW())');$stmt->execute([$adminName,$adminUsername,$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT),'VIOHY yönetici profili.','business','viohy-flow','business']);
        }

        $key=bin2hex(random_bytes(32));
        $config="<?php\ndeclare(strict_types=1);\nreturn ".var_export(['app'=>['name'=>$siteName,'url'=>$siteUrl,'key'=>$key],'db'=>['host'=>$host,'port'=>$port,'name'=>$dbName,'user'=>$dbUser,'pass'=>$dbPass]],true).";\n";
        if(file_put_contents($root.'/config.php',$config,LOCK_EX)===false)throw new RuntimeException('config.php yazılamadı. public_html yazma izinlerini kontrol edin.');
        if(!is_dir($root.'/uploads')&&!mkdir($root.'/uploads',0755,true)&&!is_dir($root.'/uploads'))throw new RuntimeException('uploads klasörü oluşturulamadı.');
        file_put_contents(__DIR__.'/install.lock',date(DATE_ATOM));
        $success=true;
    }catch(Throwable $e){$error=$e->getMessage();}
}

$checks=[
 ['PHP 8.1 veya üzeri',version_compare(PHP_VERSION,'8.1.0','>=')],
 ['PDO MySQL',extension_loaded('pdo_mysql')],
 ['Fileinfo',extension_loaded('fileinfo')],
 ['Mbstring',extension_loaded('mbstring')],
 ['Ana klasör yazılabilir',is_writable($root)],
 ['Uploads yazılabilir',is_dir($root.'/uploads')?is_writable($root.'/uploads'):is_writable($root)],
];
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VIOHY Kurulum</title><link rel="icon" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/app.css?v=9"><style>.install-shell{min-height:100vh;background:radial-gradient(circle at 10% 10%,#c6fff6,transparent 30%),radial-gradient(circle at 90% 15%,#e2d8ff,transparent 35%),#f5f7fb;padding:40px 16px}.install-wrap{width:min(1040px,100%);margin:auto}.install-brand{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}.install-grid{display:grid;grid-template-columns:320px 1fr;gap:18px}.check-list-install{display:grid;gap:10px}.check-line{display:flex;justify-content:space-between;gap:15px;padding:12px 0;border-bottom:1px solid #eceef3;font-size:13px}.ok{color:#087f5b;font-weight:800}.bad{color:#b42318;font-weight:800}@media(max-width:780px){.install-grid{grid-template-columns:1fr}}</style></head><body><main class="install-shell"><div class="install-wrap"><div class="install-brand"><img src="/assets/logo.svg" alt="VIOHY" style="height:54px"><span class="badge">Paket 9 · cPanel Edition</span></div><?php if($success): ?><section class="card" style="max-width:650px;margin:auto;text-align:center;padding:48px"><div class="feature-icon" style="margin:0 auto 18px"><?php echo '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>'; ?></div><h1>VIOHY hazır</h1><p class="muted">Veritabanı tabloları ve yönetici hesabı başarıyla hazırlandı. Güvenlik için kurulum klasörünü daha sonra silebilirsiniz.</p><a class="btn btn-primary" href="/login">Panele giriş yap →</a></section><?php else: ?><div class="install-grid"><aside class="card"><h2 class="card-title">Sunucu kontrolü</h2><p class="small muted">Dehost / cPanel uyumluluk durumu</p><div class="check-list-install"><?php foreach($checks as [$label,$state]): ?><div class="check-line"><span><?php echo h($label); ?></span><span class="<?php echo $state?'ok':'bad'; ?>"><?php echo $state?'Uygun':'Eksik'; ?></span></div><?php endforeach; ?></div><div class="alert info" style="position:static;margin-top:18px"><div><strong>Mevcut kurulum güvenli</strong><br><span class="small">Tablolar varsa silinmez; yalnız eksik tablolar oluşturulur ve yönetici hesabı güncellenir.</span></div></div></aside><section class="card"><span class="tiny text-gradient" style="font-weight:900;letter-spacing:.1em">VIOHY KURULUM SİHİRBAZI</span><h1 style="font-size:36px;letter-spacing:-.04em;margin:8px 0">Tek hosting, tek kurulum.</h1><p class="muted">MySQL bilgilerini ve yönetici hesabını gir. Eski VIOHY tabloların varsa korunur.</p><?php if($error): ?><div class="alert error" style="position:static;margin:18px 0"><div><?php echo h($error); ?></div></div><?php endif; ?><form method="post" class="form-grid"><label class="field"><span class="label">Site adı</span><input class="input" name="site_name" value="<?php echo h($_POST['site_name']??old($existing,'app.name','VIOHY')); ?>" required></label><label class="field"><span class="label">Site adresi</span><input class="input" name="site_url" value="<?php echo h($_POST['site_url']??old($existing,'app.url','https://viohy.com')); ?>" required></label><label class="field"><span class="label">MySQL sunucusu</span><input class="input" name="db_host" value="<?php echo h($_POST['db_host']??old($existing,'db.host','localhost')); ?>" required></label><label class="field"><span class="label">MySQL portu</span><input class="input" type="number" name="db_port" value="<?php echo h($_POST['db_port']??old($existing,'db.port','3306')); ?>" required></label><label class="field"><span class="label">Veritabanı adı</span><input class="input" name="db_name" value="<?php echo h($_POST['db_name']??old($existing,'db.name','')); ?>" required></label><label class="field"><span class="label">Veritabanı kullanıcısı</span><input class="input" name="db_user" value="<?php echo h($_POST['db_user']??old($existing,'db.user','')); ?>" required></label><label class="field full"><span class="label">Veritabanı şifresi</span><input class="input" type="password" name="db_pass" value="<?php echo h($_POST['db_pass']??old($existing,'db.pass','')); ?>" required></label><label class="field"><span class="label">Yönetici adı</span><input class="input" name="admin_name" value="<?php echo h($_POST['admin_name']??'Yakup Düzgün'); ?>" required></label><label class="field"><span class="label">Yönetici kullanıcı adı</span><input class="input" name="admin_username" value="<?php echo h($_POST['admin_username']??''); ?>" required></label><label class="field"><span class="label">Yönetici e-postası</span><input class="input" type="email" name="admin_email" value="<?php echo h($_POST['admin_email']??''); ?>" required></label><label class="field"><span class="label">Yönetici şifresi</span><input class="input" type="password" name="admin_password" placeholder="Mevcut hesapta boş bırakılabilir"></label><button class="btn btn-primary full" type="submit">Kurulumu / yükseltmeyi tamamla →</button></form></section></div><?php endif; ?></div></main></body></html>