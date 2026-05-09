# QR Menü Sistemi

Modern, sade ve kullanıcı dostu QR menü sistemi. PHP ve MySQL ile geliştirilmiştir.

## Özellikler

- Tam mobil uyumlu tasarım
- Çoklu dil desteği (Türkçe, İngilizce, Arapça)
- Ürün, kategori ve görsel yönetimi
- Saate göre değişen menü (time-based menu)
- AI destekli chat asistanı
- Popüler ürün ve günün lezzeti bölümleri
- Video karşılama ekranı
- Kapsamlı yönetim paneli (admin)
- SEO dostu yapı

## Kurulum

### 1. Depoyu klonlayın

```bash
git clone https://github.com/kullanici/qr-menu.git
cd qr-menu
```

### 2. Veritabanını oluşturun

cPanel / phpMyAdmin'de yeni bir veritabanı ve kullanıcı oluşturun, ardından şemayı yükleyin:

```bash
# Sadece tablo yapısı + örnek ayarlar
mysql -u kullanici -p veritabani_adi < database/qr_menu.sql
```

ya da phpMyAdmin > İçe Aktar (Import) sekmesinden `database/qr_menu.sql` dosyasını yükleyin.

### 3. Konfigürasyon dosyasını hazırlayın

```bash
cp includes/config.php.example includes/config.php
```

`includes/config.php` dosyasını açıp gerçek veritabanı bilgilerinizi girin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adi');
define('DB_USER', 'kullanici_adi');
define('DB_PASS', 'guclu_bir_sifre');
```

> `includes/config.php` `.gitignore` ile depoya dahil edilmez — kimlik bilgileriniz güvende kalır.

### 4. Admin kullanıcısı oluşturun

```bash
mysql -u kullanici -p veritabani_adi < database/CREATE_ADMIN_USER.sql
```

Dosyanın içinde `YOUR_ADMIN_PASSWORD` değerini güçlü bir şifreyle değiştirin.

### 5. Klasör izinlerini ayarlayın

```bash
chmod 755 assets/img/products
chmod 755 assets/img/categories
```

### 6. Admin paneline girin

```
https://yourdomain.com/admin/
```

Ayarlar > Genel bölümünden admin kullanıcı adı ve şifresini değiştirin.

## Proje Yapısı

```
qr-menu/
├── admin/                  # Yönetim paneli
│   ├── index.php           # Dashboard
│   ├── products.php        # Ürün yönetimi
│   ├── categories.php      # Kategori yönetimi
│   ├── settings.php        # Site ayarları
│   ├── appearance.php      # Görünüm ayarları
│   ├── chat-assistant.php  # Chat asistanı ayarları
│   └── time-based-menu.php # Saate göre menü
├── api/
│   └── chat.php            # Chat API endpoint
├── assets/
│   ├── css/style.css       # Stil dosyaları
│   ├── img/                # Görseller (ürün/kategori/logo)
│   └── js/                 # JavaScript dosyaları
├── database/               # SQL kurulum dosyaları
├── includes/
│   ├── config.php.example  # Konfigürasyon şablonu (config.php'yi buradan oluşturun)
│   ├── chat-assistant-modal.php
│   └── google-analytics.php
├── NEW_SERVER_SETUP/       # Yeni sunucu kurulum rehberi
├── index.php               # Ana sayfa (video karşılama)
├── menu.php                # QR Menü sayfası
└── product-detail.php      # Ürün detay sayfası
```

## Teknik Gereksinimler

| Bileşen | Minimum |
|---------|---------|
| PHP | 8.0+ (8.2 önerilir) |
| MySQL | 5.7+ / MariaDB 10.3+ |
| PHP Eklentileri | PDO, pdo_mysql, fileinfo, mbstring |

Frontend bağımlılıkları CDN üzerinden yüklenir (Tailwind CSS, Font Awesome) — kurulum gerekmez.

## Güvenlik Notları

- `includes/config.php` asla depoya eklemeyin (`.gitignore` ile zaten hariç tutulmuştur).
- Canlıya geçmeden önce admin şifresini mutlaka değiştirin.
- HTTPS kullanın; `.htaccess` zaten güvenlik başlıklarını eklemektedir.
- `assets/img/products/` ve `assets/img/categories/` klasörlerinin PHP yürütmesini engelleyin (`.htaccess` bunu yapar).
