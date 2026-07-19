<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/templates.php';

$path = trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'login') {
            $identity = trim((string) ($_POST['identity'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $_SESSION['login_tries'] = array_values(array_filter($_SESSION['login_tries'] ?? [], fn($t) => (int)$t > time() - 900));
            if (count($_SESSION['login_tries']) >= 8) throw new RuntimeException('Çok fazla giriş denemesi yapıldı. 15 dakika bekleyin.');
            $stmt = db()->prepare('SELECT * FROM viohy_users WHERE email = ? OR username = ? LIMIT 1');
            $stmt->execute([$identity, strtolower($identity)]);
            $record = $stmt->fetch();
            if (!$record || !password_verify($password, (string)$record['password_hash'])) {
                $_SESSION['login_tries'][] = time();
                throw new RuntimeException('Giriş bilgileri hatalı.');
            }
            if ((int)$record['is_blocked']) throw new RuntimeException('Hesabınız erişime kapatılmıştır.');
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$record['id'];
            unset($_SESSION['login_tries']);
            flash('success','Hoş geldin, ' . $record['name'] . '.');
            redirect('/dashboard');
        }

        if ($action === 'register') {
            $name = trim((string)($_POST['name'] ?? ''));
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $email = strtolower(trim((string)($_POST['identity'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $errors = [];
            if (mb_strlen($name) < 2) $errors[]='Ad soyad en az 2 karakter olmalıdır.';
            if (!preg_match('/^[a-z0-9._-]{3,30}$/',$username)) $errors[]='Kullanıcı adı 3-30 karakter olmalı; yalnız harf, rakam, nokta, tire ve alt çizgi içermelidir.';
            if (in_array($username,reserved_routes(),true)) $errors[]='Bu kullanıcı adı kullanılamaz.';
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Geçerli bir e-posta adresi girin.';
            if (strlen($password)<8) $errors[]='Şifre en az 8 karakter olmalıdır.';
            $stmt=db()->prepare('SELECT COUNT(*) FROM viohy_users WHERE username=? OR email=?');$stmt->execute([$username,$email]);
            if ((int)$stmt->fetchColumn()>0) $errors[]='Kullanıcı adı veya e-posta zaten kullanımda.';
            if ($errors) {
                render_auth('register',['name'=>$name,'username'=>$username,'identity'=>$email],$errors);exit;
            }
            $stmt=db()->prepare('INSERT INTO viohy_users(name,username,email,password_hash,bio,sector,theme,plan,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$name,$username,$email,password_hash($password,PASSWORD_DEFAULT),'Dijital dünyam tek bağlantıda.','creator','viohy-flow','free']);
            session_regenerate_id(true);$_SESSION['user_id']=(int)db()->lastInsertId();
            flash('success','VIOHY profilin oluşturuldu.');redirect('/dashboard/profile');
        }

        if ($action === 'contact') {
            $profileId=(int)($_POST['profile_id']??0);$name=trim((string)($_POST['contact_name']??''));$email=trim((string)($_POST['contact_email']??''));$phone=trim((string)($_POST['contact_phone']??''));$message=trim((string)($_POST['contact_message']??''));
            if (!$profileId || mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($message)<4) throw new RuntimeException('İletişim formundaki zorunlu alanları kontrol edin.');
            $stmt=db()->prepare('INSERT INTO viohy_contacts(user_id,name,email,phone,message,created_at) VALUES(?,?,?,?,?,NOW())');$stmt->execute([$profileId,$name,$email,$phone,$message]);
            $stmt=db()->prepare('SELECT username FROM viohy_users WHERE id=?');$stmt->execute([$profileId]);$username=(string)$stmt->fetchColumn();
            flash('success','Mesajın başarıyla gönderildi.');redirect('/'.$username);
        }

        $u=require_auth();
        if ($action === 'profile_save') {
            $name=trim((string)($_POST['name']??''));$username=strtolower(trim((string)($_POST['username']??'')));$bio=trim((string)($_POST['bio']??''));$sector=(string)($_POST['sector']??'creator');
            if (mb_strlen($name)<2) throw new RuntimeException('Ad alanını kontrol edin.');
            if (!preg_match('/^[a-z0-9._-]{3,30}$/',$username) || in_array($username,reserved_routes(),true)) throw new RuntimeException('Kullanıcı adı uygun değil.');
            $stmt=db()->prepare('SELECT COUNT(*) FROM viohy_users WHERE username=? AND id<>?');$stmt->execute([$username,$u['id']]);if((int)$stmt->fetchColumn())throw new RuntimeException('Bu kullanıcı adı kullanılıyor.');
            if (!array_key_exists($sector,sectors())) $sector='creator';
            $avatar=upload_image('avatar',(int)$u['id'],(string)$u['avatar']);$cover=upload_image('cover',(int)$u['id'],(string)$u['cover']);
            $stmt=db()->prepare('UPDATE viohy_users SET name=?,username=?,bio=?,sector=?,avatar=?,cover=?,updated_at=NOW() WHERE id=?');$stmt->execute([$name,$username,$bio,$sector,$avatar,$cover,$u['id']]);
            flash('success','Profil bilgilerin güncellendi.');redirect('/dashboard/profile');
        }

        if ($action === 'link_save') {
            $id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));$platform=(string)($_POST['platform']??'website');$url=normalize_url((string)($_POST['url']??''),$platform);$active=isset($_POST['is_active'])?1:0;
            if (mb_strlen($title)<2 || !$url) throw new RuntimeException('Bağlantı başlığı ve adresi zorunludur.');
            if (!array_key_exists($platform,platform_options()))$platform='website';
            if (!$id && $u['plan']==='free'){$stmt=db()->prepare('SELECT COUNT(*) FROM viohy_links WHERE user_id=?');$stmt->execute([$u['id']]);if((int)$stmt->fetchColumn()>=8)throw new RuntimeException('Free pakette en fazla 8 bağlantı ekleyebilirsiniz.');}
            if($id){$stmt=db()->prepare('UPDATE viohy_links SET title=?,url=?,platform=?,is_active=? WHERE id=? AND user_id=?');$stmt->execute([$title,$url,$platform,$active,$id,$u['id']]);}
            else{$stmt=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM viohy_links WHERE user_id=?');$stmt->execute([$u['id']]);$sort=(int)$stmt->fetchColumn();$stmt=db()->prepare('INSERT INTO viohy_links(user_id,title,url,platform,is_active,sort_order,clicks,created_at) VALUES(?,?,?,?,?,?,0,NOW())');$stmt->execute([$u['id'],$title,$url,$platform,$active,$sort]);}
            flash('success','Bağlantı kaydedildi.');redirect('/dashboard/links');
        }

        if ($action === 'link_delete') {
            $stmt=db()->prepare('DELETE FROM viohy_links WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$u['id']]);flash('success','Bağlantı silindi.');redirect('/dashboard/links');
        }
        if ($action === 'link_sort') {
            $ids=$_POST['sort_ids']??[];if(is_array($ids)){foreach($ids as $order=>$id){$stmt=db()->prepare('UPDATE viohy_links SET sort_order=? WHERE id=? AND user_id=?');$stmt->execute([$order+1,(int)$id,$u['id']]);}}flash('success','Bağlantı sırası güncellendi.');redirect('/dashboard/links');
        }

        if ($action === 'card_save') {
            $id=(int)($_POST['id']??0);$type=trim((string)($_POST['type']??'product'));$title=trim((string)($_POST['title']??''));$subtitle=trim((string)($_POST['subtitle']??''));$price=trim((string)($_POST['price']??''));$badge=trim((string)($_POST['badge']??''));$url=normalize_url((string)($_POST['url']??''));$active=isset($_POST['is_active'])?1:0;
            if(mb_strlen($title)<2)throw new RuntimeException('İçerik başlığı zorunludur.');
            $old='';if($id){$stmt=db()->prepare('SELECT image FROM viohy_cards WHERE id=? AND user_id=?');$stmt->execute([$id,$u['id']]);$old=(string)$stmt->fetchColumn();}
            $image=upload_image('image',(int)$u['id'],$old);
            if($id){$stmt=db()->prepare('UPDATE viohy_cards SET type=?,title=?,subtitle=?,price=?,badge=?,image=?,url=?,is_active=? WHERE id=? AND user_id=?');$stmt->execute([$type,$title,$subtitle,$price,$badge,$image,$url,$active,$id,$u['id']]);}
            else{$stmt=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM viohy_cards WHERE user_id=?');$stmt->execute([$u['id']]);$sort=(int)$stmt->fetchColumn();$stmt=db()->prepare('INSERT INTO viohy_cards(user_id,type,title,subtitle,price,badge,image,url,is_active,sort_order,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');$stmt->execute([$u['id'],$type,$title,$subtitle,$price,$badge,$image,$url,$active,$sort]);}
            flash('success','İçerik kartı kaydedildi.');redirect('/dashboard/content');
        }
        if ($action === 'card_delete') {$stmt=db()->prepare('DELETE FROM viohy_cards WHERE id=? AND user_id=?');$stmt->execute([(int)($_POST['id']??0),$u['id']]);flash('success','İçerik kartı silindi.');redirect('/dashboard/content');}

        if ($action === 'theme_save') {
            $key=(string)($_POST['theme']??'viohy-flow');if(!array_key_exists($key,theme_catalog()))throw new RuntimeException('Tema bulunamadı.');$stmt=db()->prepare('UPDATE viohy_users SET theme=?,updated_at=NOW() WHERE id=?');$stmt->execute([$key,$u['id']]);flash('success','Yeni teman profilinde yayında.');redirect('/dashboard/design');
        }

        if ($action === 'admin_user') {
            require_admin();$target=(int)($_POST['user_id']??0);$plan=(string)($_POST['plan']??'free');$blocked=isset($_POST['is_blocked'])?1:0;if(!in_array($plan,['free','pro','business'],true))$plan='free';$stmt=db()->prepare('UPDATE viohy_users SET plan=?,is_blocked=? WHERE id=? AND is_admin=0');$stmt->execute([$plan,$blocked,$target]);flash('success','Kullanıcı ayarları güncellendi.');redirect('/admin');
        }
    } catch (Throwable $ex) {
        flash('error',$ex->getMessage());
        $fallback=$_SERVER['HTTP_REFERER']??'/dashboard';header('Location: '.$fallback);exit;
    }
}

if ($path === 'health') {header('Content-Type: application/json');echo json_encode(['ok'=>true,'brand'=>'VIOHY','version'=>VIOHY_VERSION,'php'=>PHP_VERSION],JSON_UNESCAPED_UNICODE);exit;}
if ($path === '') {render_landing();exit;}
if ($path === 'login') {if(user())redirect('/dashboard');render_auth('login');exit;}
if ($path === 'register') {if(user())redirect('/dashboard');render_auth('register');exit;}
if ($path === 'logout') {unset($_SESSION['user_id']);session_regenerate_id(true);flash('success','Güvenli şekilde çıkış yaptınız.');redirect('/login');}

if (preg_match('~^go/(\d+)$~',$path,$m)) {
    $stmt=db()->prepare('SELECT l.*,u.id AS profile_id FROM viohy_links l JOIN viohy_users u ON u.id=l.user_id WHERE l.id=? AND l.is_active=1 AND u.is_blocked=0');$stmt->execute([(int)$m[1]]);$link=$stmt->fetch();if(!$link){http_response_code(404);exit('Bağlantı bulunamadı.');}
    db()->prepare('UPDATE viohy_links SET clicks=clicks+1 WHERE id=?')->execute([$link['id']]);db()->prepare('INSERT INTO viohy_events(user_id,event_type,link_id,visitor_hash,referrer,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$link['profile_id'],'click',$link['id'],client_hash(),substr((string)($_SERVER['HTTP_REFERER']??''),0,500)]);
    header('Location: '.normalize_url((string)$link['url'],(string)$link['platform']));exit;
}

if (str_starts_with($path,'dashboard') || $path==='admin') {
    $u=require_auth();
    if ($path === 'dashboard') {
        $stmt=db()->prepare("SELECT COUNT(*) FROM viohy_events WHERE user_id=? AND event_type='view'");$stmt->execute([$u['id']]);$views=(int)$stmt->fetchColumn();$stmt=db()->prepare("SELECT COUNT(*) FROM viohy_events WHERE user_id=? AND event_type='click'");$stmt->execute([$u['id']]);$clicks=(int)$stmt->fetchColumn();$stmt=db()->prepare('SELECT COUNT(*) FROM viohy_links WHERE user_id=?');$stmt->execute([$u['id']]);$linkCount=(int)$stmt->fetchColumn();$stmt=db()->prepare('SELECT COUNT(*) FROM viohy_contacts WHERE user_id=?');$stmt->execute([$u['id']]);$messages=(int)$stmt->fetchColumn();
        $stmt=db()->prepare('SELECT * FROM viohy_links WHERE user_id=? ORDER BY clicks DESC,sort_order ASC LIMIT 5');$stmt->execute([$u['id']]);$topLinks=$stmt->fetchAll();
        dashboard_start('overview','Genel bakış','Profilinin son durumu ve hızlı işlemler',$u);
        echo '<section class="hero-card"><span class="badge" style="background:rgba(255,255,255,.12);color:#fff">VIOHY Stüdyo</span><h2>Profilin yayına hazır, ' . e(explode(' ',$u['name'])[0]) . '.</h2><p>Bağlantılarını ve içeriklerini güncel tut; ziyaretçilerin seni tek bir modern sayfada keşfetsin.</p><div class="hero-card-actions"><a class="btn btn-primary" href="/dashboard/links">' . icon('plus','icon-sm') . ' Bağlantı ekle</a><a class="btn btn-secondary" href="/dashboard/design">' . icon('palette','icon-sm') . ' Temayı değiştir</a></div></section><div class="stats-grid">';
        foreach([['eye','Görüntülenme',$views],['link','Tıklama',$clicks],['content','Bağlantı',$linkCount],['inbox','Mesaj',$messages]] as [$ico,$label,$value])echo '<div class="stat-card"><div class="stat-top"><span class="stat-icon">'.icon($ico).'</span><span class="badge success">Canlı</span></div><div class="stat-value">'.number_format($value,0,',','.').'</div><div class="stat-label">'.$label.'</div></div>';
        echo '</div><div class="grid grid-2"><section class="card"><div class="card-header"><div><h2 class="card-title">En çok tıklananlar</h2><div class="small muted">Ziyaretçilerin en çok ilgi gösterdiği bağlantılar</div></div><a class="btn btn-secondary btn-sm" href="/dashboard/analytics">Tüm analiz</a></div><div class="data-list">';
        if(!$topLinks)echo '<div class="empty">'.icon('link').'<div>Henüz bağlantı verisi yok.</div></div>';foreach($topLinks as $link)echo '<div class="data-row"><span class="data-icon '.($link['platform']==='whatsapp'?'whatsapp':'').'">'.platform_icon($link['platform']).'</span><span class="data-main"><strong>'.e($link['title']).'</strong><span>'.e(platform_options()[$link['platform']]??'Bağlantı').'</span></span><span class="badge">'.(int)$link['clicks'].' tıklama</span></div>';
        echo '</div></section><section class="card"><div class="card-header"><div><h2 class="card-title">Profil adresin</h2><div class="small muted">Biyografinde ve paylaşımlarında kullan</div></div>'.icon('globe').'</div><div class="profile-url"><span class="muted">viohy.com/</span><strong>'.e($u['username']).'</strong></div><div style="display:flex;gap:10px;margin-top:14px"><button class="btn btn-secondary" data-copy="'.e(site_url('/'.$u['username'])).'">'.icon('link','icon-sm').' Kopyala</button><a class="btn btn-primary" target="_blank" href="/'.e($u['username']).'">'.icon('eye','icon-sm').' Aç</a></div></section></div>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/profile') {
        dashboard_start('profile','Profil','Kimliğini, kapak görselini ve sektörünü düzenle',$u);
        echo '<div class="preview-grid"><section class="card"><div class="card-header"><div><h2 class="card-title">Profil bilgileri</h2><div class="small muted">Ziyaretçilerinin ilk gördüğü alan</div></div>'.icon('user').'</div><form method="post" enctype="multipart/form-data" class="form-grid">'.csrf_field().'<input type="hidden" name="action" value="profile_save"><label class="field"><span class="label">Ad / marka adı</span><input class="input" name="name" required maxlength="100" value="'.e($u['name']).'"></label><label class="field"><span class="label">Kullanıcı adı</span><input class="input" name="username" required value="'.e($u['username']).'"><span class="help">viohy.com/'.e($u['username']).'</span></label><label class="field full"><span class="label">Biyografi</span><textarea class="textarea" name="bio" maxlength="350">'.e($u['bio']).'</textarea></label><label class="field"><span class="label">Sektör</span><select class="select" name="sector">';foreach(sectors() as $key=>$label)echo '<option value="'.e($key).'" '.($u['sector']===$key?'selected':'').'>'.e($label).'</option>';echo '</select></label><div></div><label class="field"><span class="label">Profil fotoğrafı</span><input class="input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" data-file-preview="#avatar-preview"></label><label class="field"><span class="label">Kapak görseli</span><input class="input" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" data-file-preview="#cover-preview"></label><button class="btn btn-primary full" type="submit">'.icon('check','icon-sm').' Değişiklikleri kaydet</button></form></section><aside class="card"><div class="card-header"><h2 class="card-title">Görsel önizleme</h2><span class="badge">Maks. 5 MB</span></div><img id="cover-preview" class="image-preview" src="'.e($u['cover']?:'/assets/favicon.svg').'" alt="Kapak"><img id="avatar-preview" class="account-avatar" style="width:90px;height:90px;border-radius:28px;margin:-42px auto 12px;border:5px solid #fff" src="'.e($u['avatar']?:'/assets/favicon.svg').'" alt="Profil"><div style="text-align:center"><strong>'.e($u['name']).'</strong><div class="small muted">@'.e($u['username']).'</div></div></aside></div>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/links') {
        $stmt=db()->prepare('SELECT * FROM viohy_links WHERE user_id=? ORDER BY sort_order,id');$stmt->execute([$u['id']]);$links=$stmt->fetchAll();$editId=(int)($_GET['edit']??0);$edit=null;foreach($links as $l)if((int)$l['id']===$editId)$edit=$l;
        dashboard_start('links','Bağlantılar','Sosyal hesaplarını ve yönlendirmelerini yönet',$u);
        echo '<div class="preview-grid"><section class="card"><div class="toolbar"><div><h2 class="card-title">Bağlantıların</h2><div class="small muted">Kartları sürükleyerek sırala</div></div><span class="badge">'.count($links).($u['plan']==='free'?'/8':'').' bağlantı</span></div><form method="post" data-sort-form>'.csrf_field().'<input type="hidden" name="action" value="link_sort"><div class="data-list">';if(!$links)echo '<div class="empty">'.icon('link').'<div>İlk bağlantını ekleyerek başla.</div></div>';foreach($links as $l)echo '<div class="data-row" draggable="true" data-id="'.(int)$l['id'].'"><input type="hidden" name="sort_ids[]" value="'.(int)$l['id'].'"><span class="data-icon '.($l['platform']==='whatsapp'?'whatsapp':'').'">'.platform_icon($l['platform']).'</span><span class="data-main"><strong>'.e($l['title']).'</strong><span>'.e($l['url']).'</span></span><span class="badge '.((int)$l['is_active']?'success':'warning').'">'.((int)$l['is_active']?'Yayında':'Gizli').'</span><span class="data-actions"><a class="btn btn-secondary btn-icon btn-sm" href="?edit='.(int)$l['id'].'">'.icon('edit','icon-sm').'</a><button class="btn btn-danger btn-icon btn-sm" type="submit" name="action" value="link_delete" formaction="/dashboard/links" data-confirm="Bu bağlantı silinsin mi?">'.icon('trash','icon-sm').'</button><input type="hidden" name="id" value="'.(int)$l['id'].'"></span></div>';echo '</div>'.($links?'<button class="btn btn-secondary" style="margin-top:14px" type="submit">'.icon('check','icon-sm').' Sıralamayı kaydet</button>':'').'</form></section><aside class="card"><div class="card-header"><div><h2 class="card-title">'.($edit?'Bağlantıyı düzenle':'Yeni bağlantı').'</h2><div class="small muted">Markalı ikon otomatik eklenir</div></div>'.icon('plus').'</div><form method="post" class="form-grid">'.csrf_field().'<input type="hidden" name="action" value="link_save"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'"><label class="field full"><span class="label">Platform</span><select class="select" name="platform">';foreach(platform_options() as $key=>$label)echo '<option value="'.e($key).'" '.(($edit['platform']??'website')===$key?'selected':'').'>'.e($label).'</option>';echo '</select></label><label class="field full"><span class="label">Başlık</span><input class="input" name="title" required maxlength="90" value="'.e($edit['title']??''). '" placeholder="WhatsApp ile iletişime geç"></label><label class="field full"><span class="label">Adres / telefon</span><input class="input" name="url" required value="'.e($edit['url']??'').'" placeholder="https:// veya 905..."></label><label class="switch full"><input type="checkbox" name="is_active" '.(!$edit||(int)$edit['is_active']?'checked':'').'><span>Profilimde yayınla</span></label><button class="btn btn-primary full" type="submit">'.icon('check','icon-sm').' Kaydet</button></form></aside></div>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/content') {
        $stmt=db()->prepare('SELECT * FROM viohy_cards WHERE user_id=? ORDER BY sort_order,id');$stmt->execute([$u['id']]);$cards=$stmt->fetchAll();$editId=(int)($_GET['edit']??0);$edit=null;foreach($cards as $c)if((int)$c['id']===$editId)$edit=$c;
        dashboard_start('content','İçerik Stüdyosu','Ürün, hizmet, menü, etkinlik ve medya kartları oluştur',$u);
        echo '<div class="preview-grid"><section class="card"><div class="toolbar"><div><h2 class="card-title">İçerik vitrini</h2><div class="small muted">Profilinde görsel kartlar olarak yayınlanır</div></div><span class="badge">'.count($cards).' kart</span></div><div class="data-list">';if(!$cards)echo '<div class="empty">'.icon('content').'<div>İlk içerik kartını oluştur.</div></div>';foreach($cards as $c)echo '<div class="data-row"><span class="data-icon">'.icon('content').'</span><span class="data-main"><strong>'.e($c['title']).'</strong><span>'.e($c['type']).' · '.e($c['price']).'</span></span><span class="badge '.((int)$c['is_active']?'success':'warning').'">'.((int)$c['is_active']?'Yayında':'Gizli').'</span><span class="data-actions"><a class="btn btn-secondary btn-icon btn-sm" href="?edit='.(int)$c['id'].'">'.icon('edit','icon-sm').'</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="card_delete"><input type="hidden" name="id" value="'.(int)$c['id'].'"><button class="btn btn-danger btn-icon btn-sm" data-confirm="Bu kart silinsin mi?">'.icon('trash','icon-sm').'</button></form></span></div>';echo '</div></section><aside class="card"><div class="card-header"><div><h2 class="card-title">'.($edit?'Kartı düzenle':'Yeni kart').'</h2><div class="small muted">Görsel ve aksiyon bağlantısı ekle</div></div>'.icon('plus').'</div><form method="post" enctype="multipart/form-data" class="form-grid">'.csrf_field().'<input type="hidden" name="action" value="card_save"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'"><label class="field"><span class="label">Tür</span><select class="select" name="type">';foreach(['product'=>'Ürün','service'=>'Hizmet','menu'=>'Menü','event'=>'Etkinlik','media'=>'Medya','appointment'=>'Randevu','program'=>'Program'] as $key=>$label)echo '<option value="'.$key.'" '.(($edit['type']??'product')===$key?'selected':'').'>'.$label.'</option>';echo '</select></label><label class="field"><span class="label">Rozet</span><input class="input" name="badge" maxlength="30" value="'.e($edit['badge']??'').'" placeholder="Yeni"></label><label class="field full"><span class="label">Başlık</span><input class="input" name="title" required maxlength="100" value="'.e($edit['title']??'').'"></label><label class="field full"><span class="label">Açıklama</span><textarea class="textarea" name="subtitle" maxlength="300">'.e($edit['subtitle']??'').'</textarea></label><label class="field"><span class="label">Fiyat / bilgi</span><input class="input" name="price" maxlength="40" value="'.e($edit['price']??'').'" placeholder="₺1.290"></label><label class="field"><span class="label">Görsel</span><input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></label><label class="field full"><span class="label">İnceleme / sipariş adresi</span><input class="input" name="url" value="'.e($edit['url']??'').'" placeholder="https://"></label><label class="switch full"><input type="checkbox" name="is_active" '.(!$edit||(int)$edit['is_active']?'checked':'').'><span>Profilimde yayınla</span></label><button class="btn btn-primary full">'.icon('check','icon-sm').' Kartı kaydet</button></form></aside></div>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/design') {
        dashboard_start('design','Tasarım','31 temadan markana en uygun görünümü seç',$u);$themes=theme_catalog();
        echo '<section class="card"><div class="toolbar"><div><h2 class="card-title">Tema galerisi</h2><div class="small muted">Renkler, kart biçimi, arka plan ve görsel dil birlikte değişir</div></div><div class="toolbar-actions"><button class="btn btn-primary btn-sm" data-sector-filter="all">Tümü</button>';foreach(['general'=>'Genel','gaming'=>'Oyun','boutique'=>'Butik','beauty'=>'Beauty','cafe'=>'Cafe','fitness'=>'Fitness','music'=>'Müzik'] as $key=>$label)echo '<button class="btn btn-secondary btn-sm" data-sector-filter="'.$key.'">'.$label.'</button>';echo '</div></div><form method="post">'.csrf_field().'<input type="hidden" name="action" value="theme_save"><input type="hidden" id="theme-input" name="theme" value="'.e($u['theme']).'"><div class="theme-grid">';foreach($themes as $key=>$t)echo '<article class="theme-card '.($u['theme']===$key?'selected':'').'" data-theme="'.e($key).'" data-sector="'.e($t['sector']).'"><div class="theme-preview" style="--t-bg:'.e($t['bg']).';--t-surface:'.e($t['surface']).';--t-text:'.e($t['text']).';--t-accent:'.e($t['accent']).';--t-accent2:'.e($t['accent2']).'"><div class="mini-accent"></div><div class="mini-card"></div><div class="mini-card"></div><div class="mini-card" style="width:72%"></div></div><div class="theme-meta"><strong>'.e($t['name']).'</strong><span>'.e(ucfirst($t['sector'])).' · '.e(ucfirst($t['style'])).'</span></div></article>';echo '</div><button class="btn btn-primary" style="margin-top:20px" type="submit">'.icon('palette','icon-sm').' Seçili temayı uygula</button></form></section>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/analytics') {
        $stmt=db()->prepare("SELECT DATE(created_at) day,COUNT(*) total FROM viohy_events WHERE user_id=? AND created_at>=DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(created_at)");$stmt->execute([$u['id']]);$raw=$stmt->fetchAll();$map=[];foreach($raw as $r)$map[$r['day']]=(int)$r['total'];$days=[];$max=1;for($i=13;$i>=0;$i--){$d=date('Y-m-d',strtotime('-'.$i.' days'));$v=$map[$d]??0;$days[]=[$d,$v];$max=max($max,$v);} $stmt=db()->prepare('SELECT platform,SUM(clicks) clicks FROM viohy_links WHERE user_id=? GROUP BY platform ORDER BY clicks DESC');$stmt->execute([$u['id']]);$breakdown=$stmt->fetchAll();$total=array_sum(array_column($breakdown,'clicks'))?:1;
        dashboard_start('analytics','Analiz','Profilinin ve bağlantılarının performansını ölç',$u);echo '<div class="grid grid-2"><section class="card"><div class="card-header"><div><h2 class="card-title">Son 14 gün</h2><div class="small muted">Görüntülenme ve tıklama hareketleri</div></div>'.icon('chart').'</div><div class="chart-bars">';foreach($days as [$d,$v])echo '<div class="chart-bar" title="'.e($d).' · '.$v.'" style="height:'.max(5,round($v/$max*100)).'%"><span>'.date('d',strtotime($d)).'</span></div>';echo '</div></section><section class="card"><div class="card-header"><div><h2 class="card-title">Platform dağılımı</h2><div class="small muted">Bağlantı tıklamalarının oranı</div></div>'.icon('link').'</div><div class="platform-breakdown">';if(!$breakdown)echo '<div class="empty">Henüz analiz verisi yok.</div>';foreach($breakdown as $r){$pct=round((int)$r['clicks']/$total*100);echo '<div class="platform-line"><span style="display:flex;gap:7px;align-items:center">'.platform_icon($r['platform'],'icon-sm').e(platform_options()[$r['platform']]??$r['platform']).'</span><span class="progress"><span style="width:'.$pct.'%"></span></span><strong>'.$pct.'%</strong></div>';}echo '</div></section></div>';
        dashboard_end();exit;
    }

    if ($path === 'dashboard/inbox') {
        $stmt=db()->prepare('SELECT * FROM viohy_contacts WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$stmt->execute([$u['id']]);$messages=$stmt->fetchAll();dashboard_start('inbox','Müşteri kutusu','Profilinden gelen iletişim talepleri',$u);echo '<section class="card"><div class="card-header"><div><h2 class="card-title">Gelen mesajlar</h2><div class="small muted">En yeni talepler üstte gösterilir</div></div><span class="badge">'.count($messages).' mesaj</span></div><div class="data-list">';if(!$messages)echo '<div class="empty">'.icon('inbox').'<div>Henüz mesaj gelmedi.</div></div>';foreach($messages as $msg)echo '<article class="data-row" style="align-items:flex-start"><span class="data-icon">'.icon('mail').'</span><div class="data-main"><strong>'.e($msg['name']).' · <a href="mailto:'.e($msg['email']).'">'.e($msg['email']).'</a></strong><span>'.e($msg['phone']).' · '.e(date('d.m.Y H:i',strtotime($msg['created_at']))).'</span><p style="white-space:normal;margin:10px 0 0">'.nl2br(e($msg['message'])).'</p></div></article>';echo '</div></section>';dashboard_end();exit;
    }

    if ($path === 'admin') {
        require_admin();$users=db()->query('SELECT * FROM viohy_users ORDER BY created_at DESC LIMIT 300')->fetchAll();$count=(int)db()->query('SELECT COUNT(*) FROM viohy_users')->fetchColumn();dashboard_start('admin','Yönetim','Kullanıcıları, paketleri ve erişimleri yönet',$u);echo '<div class="stats-grid"><div class="stat-card"><div class="stat-top"><span class="stat-icon">'.icon('user').'</span></div><div class="stat-value">'.$count.'</div><div class="stat-label">Toplam kullanıcı</div></div><div class="stat-card"><div class="stat-top"><span class="stat-icon">'.icon('check').'</span></div><div class="stat-value">'.count(array_filter($users,fn($x)=>$x['plan']==='pro')).'</div><div class="stat-label">Pro kullanıcı</div></div><div class="stat-card"><div class="stat-top"><span class="stat-icon">'.icon('content').'</span></div><div class="stat-value">'.count(array_filter($users,fn($x)=>$x['plan']==='business')).'</div><div class="stat-label">Business</div></div><div class="stat-card"><div class="stat-top"><span class="stat-icon">'.icon('close').'</span></div><div class="stat-value">'.count(array_filter($users,fn($x)=>(int)$x['is_blocked'])).'</div><div class="stat-label">Engelli hesap</div></div></div><section class="card" style="overflow:auto"><table class="admin-table"><thead><tr><th>Kullanıcı</th><th>Profil</th><th>Kayıt</th><th>Paket / erişim</th></tr></thead><tbody>';foreach($users as $row)echo '<tr><td><strong>'.e($row['name']).'</strong><br><span class="muted">'.e($row['email']).'</span></td><td><a target="_blank" href="/'.e($row['username']).'">@'.e($row['username']).'</a></td><td>'.e(date('d.m.Y',strtotime($row['created_at']))).'</td><td>'.((int)$row['is_admin']?'<span class="badge">Yönetici</span>':'<form method="post" style="display:flex;gap:8px;align-items:center">'.csrf_field().'<input type="hidden" name="action" value="admin_user"><input type="hidden" name="user_id" value="'.(int)$row['id'].'"><select class="select" style="width:130px;padding:8px" name="plan">'.implode('',array_map(fn($p)=>'<option '.($row['plan']===$p?'selected':'').'>'.$p.'</option>',['free','pro','business'])).'</select><label class="switch"><input type="checkbox" name="is_blocked" '.((int)$row['is_blocked']?'checked':'').'><span class="tiny">Engelle</span></label><button class="btn btn-primary btn-sm">Kaydet</button></form>').'</td></tr>';echo '</tbody></table></section>';dashboard_end();exit;
    }
}

if (in_array($path,reserved_routes(),true)) {http_response_code(404);exit('Sayfa bulunamadı.');}
$stmt=db()->prepare('SELECT * FROM viohy_users WHERE username=? AND is_blocked=0 LIMIT 1');$stmt->execute([strtolower($path)]);$profile=$stmt->fetch();if(!$profile){http_response_code(404);html_head('Profil bulunamadı');echo '<main style="min-height:100vh;display:grid;place-items:center"><section class="card" style="text-align:center;max-width:480px"><img src="/assets/logo.svg" alt="VIOHY" style="height:46px;margin:0 auto 24px"><h1>Profil bulunamadı</h1><p class="muted">Bu kullanıcı adı henüz alınmamış veya profil erişime kapalı.</p><a class="btn btn-primary" href="/register">Bu adı sen al</a></section></main>';html_end();exit;}
$viewKey='viewed_'.$profile['id'];if(empty($_SESSION[$viewKey])||$_SESSION[$viewKey]<time()-1800){db()->prepare('INSERT INTO viohy_events(user_id,event_type,visitor_hash,referrer,created_at) VALUES(?,?,?,?,NOW())')->execute([$profile['id'],'view',client_hash(),substr((string)($_SERVER['HTTP_REFERER']??''),0,500)]);$_SESSION[$viewKey]=time();}
$stmt=db()->prepare('SELECT * FROM viohy_links WHERE user_id=? AND is_active=1 ORDER BY sort_order,id');$stmt->execute([$profile['id']]);$links=$stmt->fetchAll();$stmt=db()->prepare('SELECT * FROM viohy_cards WHERE user_id=? AND is_active=1 ORDER BY sort_order,id');$stmt->execute([$profile['id']]);$cards=$stmt->fetchAll();render_public_profile($profile,$links,$cards);
