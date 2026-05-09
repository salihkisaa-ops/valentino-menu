<?php
require_once '../includes/config.php';
requireAuth();

$flash = null;
$errors = [];

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instagram = trim($_POST['instagram'] ?? '');
    // Instagram URL'ini otomatik oluştur
    $instagram_url = '';
    if (!empty($instagram)) {
        // @ işareti varsa kaldır
        $username = ltrim($instagram, '@');
        $instagram_url = 'https://www.instagram.com/' . $username . '/';
    }
    
    $settings = [
        'cafe_name'           => trim($_POST['cafe_name'] ?? ''),
        'cafe_tagline'        => trim($_POST['cafe_tagline'] ?? ''),
        'welcome_line_1'      => trim($_POST['welcome_line_1'] ?? ''),
        'welcome_line_2'      => trim($_POST['welcome_line_2'] ?? ''),
        'phone'               => trim($_POST['phone'] ?? ''),
        'instagram'           => $instagram,
        'instagram_url'       => $instagram_url,
        'google_maps_url'     => trim($_POST['google_maps_url'] ?? ''),
        'google_reviews_url'  => trim($_POST['google_reviews_url'] ?? ''),
        'whatsapp'            => trim($_POST['whatsapp'] ?? ''),
        'footer_address_1'    => trim($_POST['footer_address_1'] ?? ''),
        'footer_address_2'    => trim($_POST['footer_address_2'] ?? ''),
        'footer_hours'        => trim($_POST['footer_hours'] ?? ''),
    ];
    
    // Ayarları kaydet
    foreach ($settings as $key => $value) {
        updateSetting($key, $value);
    }
    
    setFlash('success', 'Site bilgileri başarıyla kaydedildi.');
    header('Location: settings.php');
    exit;
}

// Mevcut ayarları getir
$cafeName = getSetting('cafe_name', 'Valéntino Patisserié');
$cafeTagline = getSetting('cafe_tagline', 'Coffee & More');
$welcomeLine1 = getSetting('welcome_line_1', '');
$welcomeLine2 = getSetting('welcome_line_2', '');
$instagram = getSetting('instagram', '');
// Eğer instagram boşsa ama URL varsa, URL'den kullanıcı adını çıkar
if (empty($instagram)) {
    $instagramUrl = getSetting('instagram_url', '');
    if (!empty($instagramUrl)) {
        preg_match('/instagram\.com\/([^\/\?]+)/', $instagramUrl, $matches);
        if (!empty($matches[1])) {
            $instagram = '@' . $matches[1];
        }
    }
} else {
    $username = ltrim($instagram, '@');
    $instagramUrl = 'https://www.instagram.com/' . $username . '/';
}
$googleMapsUrl    = getSetting('google_maps_url', getSetting('google_url', ''));
$googleReviewsUrl = getSetting('google_reviews_url', getSetting('google_url', ''));
$phone          = getSetting('phone', '');
$whatsapp       = getSetting('whatsapp', '');
$footerAddress1 = getSetting('footer_address_1', 'Mimar Sinan, İmam Nasır Sk. No:18');
$footerAddress2 = getSetting('footer_address_2', '34674 Üsküdar/İstanbul');
$footerHours    = getSetting('footer_hours', 'Her Gün: 08:00 - 02:00');

include 'header.php';
$flash = getFlash();
?>

    <main class="p-4 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">
        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
            <div class="flex items-center">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                <span class="font-bold text-xs sm:text-sm"><?= e($flash['message']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 sm:mb-10 gap-4">
            <div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight">Site Bilgileri</h3>
                <p class="text-slate-400 font-medium text-xs sm:text-sm mt-1">İşletmenizin temel bilgilerini ve iletişim ayarlarını buradan yönetin.</p>
            </div>
            <button type="submit" form="settingsForm" class="w-full sm:w-auto bg-[var(--primary)] text-white px-8 py-4 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/10 flex items-center justify-center transition-transform active:scale-95">
                <i class="fas fa-save mr-3 opacity-60"></i> Bilgileri Kaydet
            </button>
        </div>

        <form id="settingsForm" method="POST">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Configuration -->
                <div class="glass-card p-6 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Temel Bilgiler</h4>
                    </div>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">İşletme Adı</label>
                            <input type="text" name="cafe_name" value="<?= e($cafeName) ?>" required class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm" placeholder="Örn: Valéntino Patisserié">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Slogan (Tagline)</label>
                            <input type="text" name="welcome_line_1" value="<?= e($welcomeLine1) ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm" placeholder="Hoş geldiniz">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Alt Slogan</label>
                            <input type="text" name="welcome_line_2" value="<?= e($welcomeLine2) ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm" placeholder="Mutluluk olsun">
                            <p class="text-[8px] text-slate-400 mt-1.5 ml-1 font-bold uppercase tracking-widest">Ana sayfada sloganın altında gösterilir</p>
                        </div>
                    </div>
                </div>

                <!-- Contact & Social -->
                <div class="glass-card p-6 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">İletişim & Sosyal Medya</h4>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Telefon Numarası</label>
                                <input type="text" name="phone" value="<?= e($phone) ?>" placeholder="+90 5XX XXX XX XX" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">WhatsApp</label>
                                <input type="text" name="whatsapp" value="<?= e($whatsapp) ?>" placeholder="905XXXXXXXXX" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                    </div>
                </div>

                        <div class="pt-4 border-t border-slate-50">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Instagram Kullanıcı Adı</label>
                            <input type="text" name="instagram" value="<?= e($instagram) ?>" placeholder="@gallantcafe veya gallantcafe" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                            <p class="text-[8px] text-slate-400 mt-2 font-bold uppercase tracking-widest ml-1">URL otomatik olarak oluşturulacaktır</p>
                    </div>
                    
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Google Harita URL</label>
                                <input type="url" name="google_maps_url" value="<?= e($googleMapsUrl) ?>" placeholder="https://maps.app.goo.gl/..." class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                                <p class="text-[8px] text-slate-400 mt-1 ml-1 font-bold uppercase tracking-widest">Menüdeki 📍 konum butonuna bağlanır</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Google Yorum URL</label>
                                <input type="url" name="google_reviews_url" value="<?= e($googleReviewsUrl) ?>" placeholder="https://g.page/r/.../review" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                                <p class="text-[8px] text-slate-400 mt-1 ml-1 font-bold uppercase tracking-widest">Menüdeki <span style="font-family:sans-serif;">G</span> Google butonu</p>
                            </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bilgileri -->
            <div class="glass-card p-6 sm:p-8 mt-8 lg:col-span-2">
                <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-50">
                    <div class="w-8 h-8 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Footer — Adres & Çalışma Saati</h4>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Adres 1. Satır</label>
                        <input type="text" name="footer_address_1" value="<?= e($footerAddress1) ?>" placeholder="Mimar Sinan, İmam Nasır Sk. No:18" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Adres 2. Satır</label>
                        <input type="text" name="footer_address_2" value="<?= e($footerAddress2) ?>" placeholder="34674 Üsküdar/İstanbul" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Çalışma Saati</label>
                        <input type="text" name="footer_hours" value="<?= e($footerHours) ?>" placeholder="Her Gün: 08:00 - 02:00" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 shadow-sm">
                        <p class="text-[8px] text-slate-400 mt-1.5 ml-1 font-bold uppercase tracking-widest">Telefon: İletişim &amp; Sosyal alanından güncellenir</p>
                    </div>
                </div>
            </div>
        </form>
    </main>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
