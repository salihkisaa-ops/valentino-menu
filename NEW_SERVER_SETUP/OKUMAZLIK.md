# 📦 YENİ SUNUCU KURULUM PAKETİ

## İçindekiler

1. **COMPLETE_NEW_SERVER_INSTALL.sql** (125KB)
   - Tüm tablolar
   - Tüm ürünler
   - Tüm kategoriler
   - Tüm chat cevapları
   - Temel ayarlar

2. **QUICK_INSTALL.sql** (5KB)
   - Sadece tablo yapıları
   - Veri olmadan

3. **KURULUM_REHBERI.md**
   - Detaylı kurulum talimatları
   - Sorun giderme

4. **config.php.example**
   - Config dosyası şablonu

## ⚡ HIZLI BAŞLANGIÇ

### 1. phpMyAdmin'e Giriş Yapın

### 2. Yeni Veritabanı Oluşturun
```sql
CREATE DATABASE kurulumtsl_galantcoffe 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### 3. SQL Dosyasını Import Edin
- `COMPLETE_NEW_SERVER_INSTALL.sql` dosyasını seçin
- Import edin

### 4. Config Dosyasını Düzenleyin
- `config.php.example` dosyasını `config.php` olarak kaydedin
- Veritabanı bilgilerini güncelleyin

### 5. Dosyaları Sunucuya Yükleyin
- Tüm proje dosyalarını FTP ile yükleyin

### 6. İzinleri Ayarlayın
```bash
chmod 755 assets/img/products/
chmod 755 assets/img/categories/
```

### 7. Admin'e Giriş Yapın
- URL: `https://siteniz.com/admin/`
- Kullanıcı: `admin`
- Şifre: `admin123`

## ✅ BAŞARILI!

Artık siteniz hazır!

Detaylı talimatlar için `KURULUM_REHBERI.md` dosyasını okuyun.
