VIOHY PAKET 9 — FTP / CPANEL EDITION
====================================

Sürüm: 9.0.0
Gereksinim: PHP 8.1+, PDO MySQL, Fileinfo, Mbstring, Apache/LiteSpeed mod_rewrite

ÖNEMLİ
------
Bu paket tamamen VIOHY markalıdır. BioAkış adı, logosu ve eski kurulum metinleri bulunmaz.
Yeni veritabanı tabloları viohy_ önekiyle oluşturulur; önceki tablolar silinmez.

GÜVENLİ YÜKSELTME
-----------------
1. cPanel Dosya Yöneticisi veya FTP ile public_html klasörünün yedeğini alın.
2. Mevcut config.php ve uploads klasörünü ayrıca indirin.
3. ZIP içindeki viohy klasörünün İÇERİĞİNİ public_html içine yükleyin.
4. Sorulursa dosyaların üzerine yazılmasına izin verin.
5. Mevcut config.php eski formattaysa silmeyin; /install/ ekranı yeni formatta tekrar oluşturacaktır.
6. https://viohy.com/install/ adresini açın.
7. Aynı MySQL bilgilerini girin. Kurulum eksik viohy_ tablolarını oluşturur, mevcut olanları korur.
8. Yönetici e-postası/kullanıcı adı mevcutsa hesap yöneticiye yükseltilir. Yeni şifre yazarsanız şifre yenilenir.
9. Giriş: https://viohy.com/login
10. Her şey çalışınca install klasörünü silebilirsiniz.

TEMİZ KURULUM
-------------
1. public_html içindeki gereksiz varsayılan dosyaları temizleyin; cgi-bin ve php.ini kalabilir.
2. viohy klasörünün içeriğini public_html içine yükleyin.
3. /install/ adresinden MySQL ve yönetici bilgilerini girin.

PAKETTEKİ ANA ÖZELLİKLER
------------------------
- Modern VIOHY ana sayfası ve giriş/kayıt ekranları
- Üçüncü logo konseptine göre turkuaz-mavi-mor VIOHY SVG logosu
- 31 tema: 7 genel + 24 sektör teması
- Oyun, butik, beauty, cafe, fitness ve müzik sektörleri
- WhatsApp, Instagram, TikTok, YouTube, Spotify, X, Facebook, Telegram ve Discord ikonları
- Bağlantı ekleme, düzenleme, silme ve sıralama
- Ürün, hizmet, menü, etkinlik, medya, randevu ve program kartları
- Profil ve kapak görseli yükleme
- Profil görüntülenme ve bağlantı tıklama analizi
- Müşteri iletişim kutusu
- Free / Pro / Business paket yönetimi
- Yönetici engelleme ve paket atama ekranı
- CSRF, prepared statement, password_hash, yükleme MIME kontrolü ve güvenli klasör kuralları

DOSYA İZİNLERİ
--------------
Normalde cPanel izinlerini değiştirmeyin.
Klasörler: 755
Dosyalar: 644
uploads klasörü PHP tarafından yazılabilir olmalıdır.
Paket chmod() fonksiyonuna ihtiyaç duymaz.

SAĞLIK KONTROLÜ
---------------
https://viohy.com/health
JSON içinde ok=true ve version=9.0.0 görünmelidir.

DESTEK NOTU
-----------
config.php ve veritabanı şifresini kimseyle paylaşmayın.
FTP/cPanel ekran görüntülerinde şifreleri kapatın.
