# 🚀 GALLANT GALATA - YENİ SUNUCU KURULUM REHBERİ

## 📋 Gerekli Dosyalar

Bu kurulum paketi aşağıdaki dosyaları içerir:
- `COMPLETE_NEW_SERVER_INSTALL.sql` - Tüm veritabanı kurulum dosyası (125KB)
- `FULL_INSTALL_NEW_SERVER.sql` - Tablo yapıları ve temel veriler
- `qr_menu_complete_fixed.sql` - Ürün verileri
- `chat_assistant.sql` - Chat asistan verileri

## ⚙️ KURULUM ADIMLARI

### 1️⃣ Veritabanı Oluşturma

cPanel veya phpMyAdmin'den yeni bir veritabanı oluşturun:

**cPanel'de:**
- MySQL Databases bölümüne gidin
- Veritabanı adı: `u398002296_menu`
- Kullanıcı adı: `u398002296_menu_user`
- Şifre: `menuuser**1@K`

**veya SQL ile:**
```sql
CREATE DATABASE u398002296_menu 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### 2️⃣ SQL Dosyasını İmport Etme

#### Yöntem A: phpMyAdmin ile (ÖNERİLEN)

1. phpMyAdmin'e giriş yapın
2. Sol menüden `kurulumtsl_galantcoffe` veritabanını seçin
3. Üst menüden **"İçe Aktar" (Import)** sekmesine tıklayın
4. **"Dosya seç"** butonuna tıklayın
5. `COMPLETE_NEW_SERVER_INSTALL.sql` dosyasını seçin
6. Karakter seti: **utf8mb4** olarak ayarlayın
7. **"Git" (Go)** butonuna tıklayın

#### Yöntem B: Terminal/SSH ile

```bash
mysql -u u398002296_menu_user -p u398002296_menu < COMPLETE_NEW_SERVER_INSTALL.sql
# Şifre: menuuser**1@K
```

### 3️⃣ Config Dosyası Güncelleme

`includes/config.php` dosyasını açın ve veritabanı bilgilerini güncelleyin:

```php
// Veritabanı Bağlantı Bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'u398002296_menu');
define('DB_USER', 'u398002296_menu_user');
define('DB_PASS', 'menuuser**1@K');
define('DB_CHARSET', 'utf8mb4');
```

### 4️⃣ Dosya İzinleri

Aşağıdaki klasörlere yazma izni verin:

```bash
chmod 755 assets/img/products/
chmod 755 assets/img/categories/
chmod 755 assets/img/flags/
```

### 5️⃣ Admin Panel Giriş

- **URL:** `https://siteniz.com/admin/login.php`
- **Kullanıcı Adı:** `admin`
- **Şifre:** `admin123`

⚠️ **ÖNEMLİ:** İlk girişten sonra şifreyi mutlaka değiştirin!

## 📊 OLUŞTURULAN TABLOLAR

Kurulum sonrası aşağıdaki tablolar oluşturulacak:

| Tablo | Açıklama | Kayıt Sayısı |
|-------|----------|--------------|
| `categories` | Ürün kategorileri | 10 |
| `products` | Ürün listesi | 108+ |
| `settings` | Site ayarları | 15+ |
| `languages` | Dil listesi | 21 |
| `chat_assistant_settings` | Chat asistan ayarları | 1 |
| `chat_responses` | Chat cevapları | 550+ |
| `stories` | Hikayeler | 0 |
| `time_based_menu` | Zamana göre menü | 0 |

## ✅ KURULUM KONTROLÜ

Kurulumun başarılı olup olmadığını kontrol edin:

### 1. Tablo Kontrolü

```sql
SHOW TABLES FROM u398002296_menu;
```

8 tablo görmelisiniz.

### 2. Kategori Kontrolü

```sql
SELECT COUNT(*) FROM categories WHERE is_active = 1;
```

Sonuç: **10 kategori**

### 3. Ürün Kontrolü

```sql
SELECT COUNT(*) FROM products WHERE is_active = 1;
```

Sonuç: **100+ ürün**

### 4. Dil Kontrolü

```sql
SELECT COUNT(*) FROM languages WHERE is_active = 1;
```

Sonuç: **21 dil**

## 🔧 SORUN GİDERME

### Hata: "Table doesn't exist"

```sql
-- Tabloları tekrar oluşturmak için:
DROP DATABASE u398002296_menu;
CREATE DATABASE u398002296_menu 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
-- Sonra tekrar import edin
```

### Hata: "Türkçe Karakterler Bozuk"

```sql
-- Veritabanı karakter setini kontrol edin:
ALTER DATABASE u398002296_menu 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Tüm tabloları düzeltin:
ALTER TABLE categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE languages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE chat_responses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Hata: "Foreign Key Constraint Fails"

```sql
-- Foreign key kontrollerini kapat, import et, tekrar aç:
SET FOREIGN_KEY_CHECKS = 0;
-- SQL dosyasını import edin
SET FOREIGN_KEY_CHECKS = 1;
```

## 📱 ÖZELLİKLER

Kurulum sonrası çalışan özellikler:

✅ Çok dilli menü sistemi (21 dil)
✅ Chat asistan (550+ önceden tanımlı cevap)
✅ Kategori bazlı ürün listesi
✅ Ürün arama ve filtreleme
✅ Popüler ürünler
✅ Öne çıkan ürünler
✅ Admin panel
✅ Responsive tasarım
✅ Google Translate entegrasyonu

## 🎨 TEMA RENKLERİ

- **Primary:** #2d4433 (Koyu Yeşil)
- **Accent:** #c5a059 (Altın)

## 📞 İLETİŞİM

- **Telefon:** +905386473672
- **Instagram:** @gallantgalata
- **WhatsApp:** +905386473672

## 🔐 GÜVENLİK

**İlk yapılması gerekenler:**

1. Admin şifresini değiştirin:
   - Admin Panel → Ayarlar → Güvenlik

2. `config.php` dosyasını koruyun:
   ```bash
   chmod 600 includes/config.php
   ```

3. `.htaccess` dosyasını kontrol edin

## 📝 BACKUP

Düzenli yedekleme için:

```bash
# Veritabanı yedeği
mysqldump -u KULLANICI -p kurulumtsl_galantcoffe > backup_$(date +%Y%m%d).sql

# Dosya yedeği
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/qr-menu/
```

## 🚀 SONRAKI ADIMLAR

1. ✅ Ürün görselleri yükleyin (`assets/img/products/`)
2. ✅ Logo'yu güncelleyin (`assets/img/`)
3. ✅ Site ayarlarını yapın (Admin Panel → Ayarlar)
4. ✅ Dilleri aktif/pasif yapın (Admin Panel → Diller)
5. ✅ Öne çıkan ürünleri seçin (Admin Panel → Öne Çıkanlar)

---

**Kurulum Tarihi:** 25 Ocak 2026  
**Versiyon:** v62  
**Destek:** Herhangi bir sorun için bize ulaşın!
