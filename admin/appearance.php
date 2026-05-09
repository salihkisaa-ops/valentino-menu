<?php
require_once '../includes/config.php';
requireAuth();

$flash = null;
$errors = [];

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gradyan Renkleri
    if (isset($_POST['update_gradient'])) {
        updateSetting('gradient_top', trim($_POST['gradient_top_text'] ?? $_POST['gradient_top'] ?? '#C1AE65'));
        updateSetting('gradient_bottom', trim($_POST['gradient_bottom_text'] ?? $_POST['gradient_bottom'] ?? '#2D4434'));
        setFlash('success', 'Arka plan renkleri başarıyla güncellendi.');
    }

    // Logo yükleme (MIME sunucuda güvenilir olmayabilir — finfo ile doğrula)
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $mimeNorm = normalize_uploaded_image_mime($_FILES['logo']['tmp_name']);
        if ($mimeNorm) {
            $extension = extension_for_normalized_image($mimeNorm);
            $logoName = 'gallant-new-logo.' . $extension;
            $uploadPath = __DIR__ . '/../assets/img/' . $logoName;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                updateSetting('logo', $logoName);
                setFlash('success', 'Logo başarıyla güncellendi.');
            } else {
                setFlash('error', 'Logo kaydedilemedi (klasör izni veya disk).');
            }
        } else {
            setFlash('error', 'Logo yüklenemedi: JPG, PNG, WEBP veya GIF seçin.');
        }
    }

    // Video yükleme
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $videoMime = normalize_uploaded_video_mime($_FILES['video']['tmp_name']);
        if ($videoMime) {
            $extension = extension_for_normalized_video($videoMime);
            $videoName = 'gallant-video.' . $extension;
            $uploadPath = __DIR__ . '/../assets/img/' . $videoName;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadPath)) {
                updateSetting('hero_video', $videoName);
                setFlash('success', 'Arka plan videosu başarıyla güncellendi.');
            } else {
                setFlash('error', 'Video kaydedilemedi.');
            }
        } else {
            setFlash('error', 'Video yüklenemedi: MP4 veya WEBM seçin.');
        }
    }
    
    header('Location: appearance.php');
    exit;
}

// Mevcut ayarları getir
$logo = get_site_logo_filename();
$footerPartnerLogo = get_footer_partner_logo_filename();
$heroVideo = getSetting('hero_video', 'gallant-video.webm');
$gradientTop = getSetting('gradient_top', '#C1AE65');
$gradientBottom = getSetting('gradient_bottom', '#2D4434');

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
        <div class="mb-8 sm:mb-10">
            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight">Görünüm Ayarları</h3>
            <p class="text-slate-400 font-medium text-xs sm:text-sm mt-1">Sitenin görsel kimliğini, renklerini ve medya dosyalarını buradan yönetin.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Logo & Video Management -->
            <div class="space-y-8">
                <form method="POST" enctype="multipart/form-data" class="glass-card p-6 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-photo-video"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Medya Dosyaları</h4>
                    </div>

                    <div class="space-y-8">
                        <!-- Logo -->
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div class="w-24 h-24 sm:w-32 sm:h-32 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden flex-shrink-0 relative group">
                                <img src="../assets/img/<?= e($logo) ?>" class="w-full h-full object-contain p-2" id="logoPreview" alt="Logo önizleme">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <i class="fas fa-camera text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 text-center sm:text-left">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Kurumsal Logo</label>
                                <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-xl text-[10px] font-bold uppercase tracking-widest hover:bg-black transition-all">
                                    <i class="fas fa-upload mr-2"></i> Yeni Logo Seç
                                    <input type="file" name="logo" accept="image/*" class="hidden" onchange="previewImage(this, 'logoPreview')">
                                </label>
                                <p class="text-[8px] text-slate-400 mt-2 italic">Önerilen: 512x512px, PNG veya WEBP</p>
                            </div>
                        </div>

                        <!-- Video -->
                        <div class="pt-6 border-t border-slate-50">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Giriş Ekranı Videosu</label>
                            <div class="relative aspect-video bg-slate-900 rounded-2xl overflow-hidden mb-4 shadow-inner">
                                <video muted loop autoplay playsinline class="w-full h-full object-cover opacity-60">
                                    <source src="../assets/img/<?= e($heroVideo) ?>" type="video/webm">
                                </video>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <label class="cursor-pointer flex flex-col items-center">
                                        <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-full flex items-center justify-center text-white mb-2 hover:bg-white/20 transition-all">
                                            <i class="fas fa-play"></i>
                                        </div>
                                        <span class="text-[9px] text-white font-bold uppercase tracking-widest">Videoyu Değiştir</span>
                                        <input type="file" name="video" accept="video/mp4,video/webm" class="hidden" onchange="this.form.submit()">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full mt-6 bg-[var(--primary)] text-white py-4 rounded-xl font-bold text-[10px] uppercase tracking-widest shadow-lg shadow-emerald-900/10 hover:scale-[1.02] active:scale-95 transition-all">
                        Değişiklikleri Uygula
                    </button>
                </form>
            </div>

            <!-- Gradient Management -->
            <div class="space-y-8">
                <form method="POST" class="glass-card p-6 sm:p-8">
                    <input type="hidden" name="update_gradient" value="1">
                    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-palette"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Arka Plan Renkleri</h4>
                    </div>

                    <div class="space-y-6">
                        <!-- Preview -->
                        <div class="w-full h-32 rounded-2xl shadow-inner mb-6 relative overflow-hidden" id="gradientPreview" style="background: linear-gradient(180deg, <?= e($gradientTop) ?> 0%, <?= e($gradientBottom) ?> 100%);">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[10px] font-black text-white/50 uppercase tracking-[0.3em]">Canlı Önizleme</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Üst Renk (Başlangıç)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="gradient_top" value="<?= e($gradientTop) ?>" class="w-12 h-12 rounded-xl cursor-pointer border-none" oninput="updatePreview()">
                                    <input type="text" name="gradient_top_text" id="topText" value="<?= e($gradientTop) ?>" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]" oninput="updatePreviewFromText()">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Alt Renk (Bitiş)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="gradient_bottom" value="<?= e($gradientBottom) ?>" class="w-12 h-12 rounded-xl cursor-pointer border-none" oninput="updatePreview()">
                                    <input type="text" name="gradient_bottom_text" id="bottomText" value="<?= e($gradientBottom) ?>" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]" oninput="updatePreviewFromText()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-8 bg-slate-800 text-white py-4 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-black transition-all">
                        Renkleri Kaydet
                    </button>
                </form>
            </div>

        </div>
    </main>

    <script>
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function updatePreview() {
        const top = document.querySelector('input[name="gradient_top"]').value;
        const bottom = document.querySelector('input[name="gradient_bottom"]').value;
        document.getElementById('topText').value = top;
        document.getElementById('bottomText').value = bottom;
        document.getElementById('gradientPreview').style.background = `linear-gradient(180deg, ${top} 0%, ${bottom} 100%)`;
    }

    function updatePreviewFromText() {
        const top = document.getElementById('topText').value;
        const bottom = document.getElementById('bottomText').value;
        if(/^#[0-9A-F]{6}$/i.test(top)) document.querySelector('input[name="gradient_top"]').value = top;
        if(/^#[0-9A-F]{6}$/i.test(bottom)) document.querySelector('input[name="gradient_bottom"]').value = bottom;
        document.getElementById('gradientPreview').style.background = `linear-gradient(180deg, ${top} 0%, ${bottom} 100%)`;
    }
    </script>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
