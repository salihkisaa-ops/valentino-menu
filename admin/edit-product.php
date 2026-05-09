<?php
require_once '../includes/config.php';
// requireAuth(); // Canlıda bu satırı aktif edin

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = getProduct($id);

if (!$product) {
    setFlash('error', 'Ürün bulunamadı.');
    header('Location: products.php');
    exit;
}

$categories = getCategories(false);
$errors = [];

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verileri al
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $gramaj = trim($_POST['gramaj'] ?? '');
    $kalori = trim($_POST['kalori'] ?? '');
    $icindekiler = trim($_POST['icindekiler'] ?? '');
    $alerjen = trim($_POST['alerjen'] ?? '');
    $bilgilendirme = trim($_POST['bilgilendirme'] ?? '');
    // is_popular artık kullanılmıyor - öne çıkan ürünler admin/home-recommended.php'den yönetiliyor
    $is_popular = 0;
    $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $delete_image = isset($_POST['delete_image']) && $_POST['delete_image'] == '1';
    
    // Ekstraları al ve JSON'a çevir
    $extras = [];
    if (isset($_POST['extra_names']) && isset($_POST['extra_prices'])) {
        foreach ($_POST['extra_names'] as $index => $extra_name) {
            $extra_name = trim($extra_name);
            $extra_price = floatval($_POST['extra_prices'][$index] ?? 0);
            if (!empty($extra_name)) {
                $extras[] = ['name' => $extra_name, 'price' => $extra_price];
            }
        }
    }
    $extras_json = !empty($extras) ? json_encode($extras, JSON_UNESCAPED_UNICODE) : null;
    
    // Validasyon
    if (empty($name)) {
        $errors[] = 'Ürün adı zorunludur.';
    }
    if ($category_id <= 0) {
        $errors[] = 'Lütfen bir kategori seçin.';
    }
    if ($price <= 0) {
        $errors[] = 'Geçerli bir fiyat girin.';
    }
    
    // Resim yükleme
    $imageName = $product['image']; // Mevcut resmi koru

    if ($delete_image) {
        if ($product['image'] && file_exists(UPLOAD_PATH . $product['image'])) {
            @unlink(UPLOAD_PATH . $product['image']);
        }
        $imageName = null;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $fileType = $_FILES['image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Sadece JPG, PNG veya WEBP formatında resim yükleyebilirsiniz.';
        } else {
            // Upload klasörünü oluştur
            if (!is_dir(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0755, true);
            }
            
            // Eski resmi sil
            if ($product['image'] && file_exists(UPLOAD_PATH . $product['image'])) {
                unlink(UPLOAD_PATH . $product['image']);
            }
            
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid('product_') . '.' . $extension;
            $uploadPath = UPLOAD_PATH . $imageName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $errors[] = 'Resim yüklenirken bir hata oluştu.';
                $imageName = $product['image'];
            }
        }
    }
    
    // Hata yoksa güncelle
    if (empty($errors)) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image = ?, is_popular = ?, is_daily_special = ?, is_active = ?, extras = ?, keywords = ?, gramaj = ?, kalori = ?, icindekiler = ?, alerjen = ?, bilgilendirme = ? WHERE id = ?");
            $stmt->execute([$category_id, $name, $description, $price, $imageName, $is_popular, $is_daily_special, $is_active, $extras_json, $keywords, $gramaj ?: null, $kalori ?: null, $icindekiler ?: null, $alerjen ?: null, $bilgilendirme ?: null, $id]);
            
            setFlash('success', 'Ürün başarıyla güncellendi.');
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            error_log('Product update error: ' . $e->getMessage());
            $errors[] = 'Veritabanı hatası oluştu. Lütfen tekrar deneyin.';
            if (isset($imageName) && $imageName !== $product['image'] && file_exists(UPLOAD_PATH . $imageName)) {
                @unlink(UPLOAD_PATH . $imageName);
            }
        } catch (Exception $e) {
            error_log('Product update error: ' . $e->getMessage());
            $errors[] = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

include 'header.php';
?>

    <main class="p-4 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-10 gap-4">
            <div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight">Ürün Düzenle</h3>
                <p class="text-slate-400 font-medium text-xs sm:text-sm mt-1"><?= e($product['name']) ?> ürününü düzenliyorsunuz.</p>
            </div>
            <div class="flex items-center space-x-2 sm:space-x-3 w-full sm:w-auto">
                <a href="products.php" class="flex-1 sm:flex-none text-center bg-white text-slate-500 px-4 sm:px-6 py-3 sm:py-4 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest border border-slate-100 hover:bg-slate-50 transition-all">Vazgeç</a>
                <button type="submit" form="productForm" class="flex-[2] sm:flex-none bg-[var(--primary)] text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/10 flex items-center justify-center transition-transform active:scale-95">
                    <i class="fas fa-save mr-2 sm:mr-3 opacity-60"></i> Kaydet
                </button>
            </div>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-2xl">
            <ul class="text-red-600 text-xs sm:text-sm font-medium space-y-1">
                <?php foreach ($errors as $error): ?>
                <li><i class="fas fa-exclamation-circle mr-2"></i><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form id="productForm" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
            <!-- Left Column: Main Info -->
            <div class="lg:col-span-2 space-y-6 sm:space-y-8">
                <!-- Core Info Card -->
                <div class="glass-card p-5 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Temel Bilgiler</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Ürün Adı *</label>
                            <input type="text" name="name" value="<?= e($_POST['name'] ?? $product['name']) ?>" placeholder="Örn: Gold Caramel Latte" required class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700">
                        </div>
                        
                        <div>
                            <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Kategori *</label>
                            <select name="category_id" required class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 appearance-none">
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? $product['category_id']) == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Fiyat (₺) *</label>
                            <div class="relative">
                                <input type="number" name="price" step="0.01" min="0" value="<?= e($_POST['price'] ?? $product['price']) ?>" placeholder="0.00" required class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700">
                                <span class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">₺</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Description Card -->
                <div class="glass-card p-5 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-align-left"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Ürün Detayları</h4>
                    </div>
                    
                    <div class="space-y-5 sm:space-y-6">
                        <div>
                            <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Chatbot Anahtar Kelimeleri</label>
                            <input type="text" name="keywords" value="<?= e($_POST['keywords'] ?? $product['keywords'] ?? '') ?>" placeholder="Virgülle ayrılmış anahtar kelimeler (örn: espresso, sıcak kahve, italyan kahvesi)" class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600">
                            <p class="text-[8px] text-slate-400 mt-1 ml-1">Chatbot'un bu ürünü tanıması için kullanılacak kelimeler</p>
                        </div>
                        
                        <div>
                            <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Uzun Açıklama (Detay Sayfası İçin)</label>
                            <textarea name="description" rows="5" placeholder="Ürün içeriği, hazırlanış şekli veya hikayesi..." class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
                        </div>

                        <!-- Besin & Alerjen -->
                        <div class="pt-4 border-t border-slate-50">
                            <p class="text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 ml-1">Besin & Alerjen Bilgileri</p>
                            <p class="text-[8px] text-slate-400 mb-4 ml-1">Boş bırakılan alanlar menüdeki ürün penceresinde gösterilmez.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                                <div>
                                    <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 ml-1">Gramaj</label>
                                    <input type="text" name="gramaj" value="<?= e($_POST['gramaj'] ?? $product['gramaj'] ?? '') ?>" placeholder="örn: 250 gram" class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600">
                                </div>
                                <div>
                                    <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 ml-1">Kalori</label>
                                    <input type="text" name="kalori" value="<?= e($_POST['kalori'] ?? $product['kalori'] ?? '') ?>" placeholder="örn: 320 kcal" class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 ml-1">İçindekiler</label>
                                    <textarea name="icindekiler" rows="3" placeholder="örn: süt, kahve, şeker..." class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600"><?= e($_POST['icindekiler'] ?? $product['icindekiler'] ?? '') ?></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 ml-1">Alerjenler</label>
                                    <textarea name="alerjen" rows="2" placeholder="örn: glüten, süt, yumurta..." class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600"><?= e($_POST['alerjen'] ?? $product['alerjen'] ?? '') ?></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 ml-1">Bilgilendirme</label>
                                    <textarea name="bilgilendirme" rows="2" placeholder="örn: alkol ve domuz ürünü içermez..." class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-medium text-slate-600"><?= e($_POST['bilgilendirme'] ?? $product['bilgilendirme'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ekstra Seçenekler Card -->
                <div class="glass-card p-5 sm:p-8">
                    <div class="flex items-center justify-between mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Ekstra Seçenekler</h4>
                        </div>
                        <button type="button" onclick="addExtraRow()" class="text-[9px] sm:text-[10px] font-extrabold text-[var(--primary)] uppercase tracking-widest hover:opacity-70 transition-all">
                            <i class="fas fa-plus mr-1"></i> Yeni Ekle
                        </button>
                    </div>
                    
                    <div id="extrasContainer" class="space-y-4">
                        <!-- Ekstralar JS ile buraya gelecek -->
                    </div>
                    
                    <div id="noExtrasMsg" class="text-center py-8 hidden">
                        <p class="text-slate-400 text-xs font-medium italic">Henüz ekstra seçenek eklenmemiş.</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Media & Features -->
            <div class="space-y-6 sm:space-y-8">
                <!-- Image Upload Card -->
                <div class="glass-card p-5 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Ürün Görseli</h4>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <input type="hidden" name="delete_image" id="deleteImageInput" value="0">
                        <label class="w-full max-w-[200px] sm:max-w-none aspect-square bg-slate-50 rounded-[24px] sm:rounded-[32px] border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden mb-4 group relative cursor-pointer hover:border-[var(--primary)] transition-all">
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="hidden" id="imageInput">
                            <div id="uploadPlaceholder" class="flex flex-col items-center group-hover:opacity-40 transition-opacity <?= $product['image'] ? 'hidden' : '' ?>">
                                <i class="fas fa-cloud-upload-alt text-slate-300 text-2xl sm:text-3xl mb-1 sm:mb-2"></i>
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-widest">Resim Seç</span>
                            </div>
                            <img id="imagePreview" src="<?= $product['image'] ? getProductImage($product['image']) : '' ?>" alt="" class="absolute inset-0 w-full h-full object-cover <?= $product['image'] ? '' : 'hidden' ?>">
                            
                            <!-- Delete Button Overlay -->
                            <button type="button" id="deleteImageBtn" class="absolute top-3 right-3 w-10 h-10 bg-rose-500 text-white rounded-xl items-center justify-center shadow-lg hover:bg-rose-600 transition-all z-20 <?= $product['image'] ? 'flex' : 'hidden' ?>" onclick="event.preventDefault(); removeImage();">
                                <i class="fas fa-trash-alt"></i>
                            </button>

                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                <i class="fas fa-camera text-white text-xl"></i>
                            </div>
                        </label>
                        <p class="text-[8px] sm:text-[9px] font-bold text-slate-400 uppercase tracking-tighter text-center">Değiştirmek veya Silmek için tıklayın</p>
                    </div>
                </div>

                <!-- Features Card -->
                <div class="glass-card p-5 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                        <div class="w-8 h-8 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center text-sm">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Özellikler</h4>
                    </div>
                    
                    <div class="space-y-3 sm:space-y-4">
                        <label class="flex items-center justify-between p-3 sm:p-4 bg-slate-50 rounded-2xl border border-slate-100 cursor-pointer group">
                            <div>
                                <h5 class="font-bold text-slate-800 text-xs sm:text-sm">Stokta Var</h5>
                                <p class="text-[8px] sm:text-[9px] text-slate-400 font-bold uppercase tracking-tight">Menüde aktif</p>
                            </div>
                            <input type="checkbox" name="is_active" class="toggle-switch" <?= $product['is_active'] ? 'checked' : '' ?>>
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </main>
    </main>

    <style>
    .toggle-switch {
        appearance: none;
        width: 40px;
        height: 20px;
        background: #e2e8f0;
        border-radius: 9999px;
        position: relative;
        cursor: pointer;
        transition: all 0.3s;
    }
    .toggle-switch::before {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        background: white;
        border-radius: 9999px;
        top: 2px;
        left: 2px;
        transition: all 0.3s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .toggle-switch:checked {
        background: var(--primary);
    }
    .toggle-switch:checked::before {
        left: 22px;
    }
    </style>

    <script>
    // Image Preview
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const deleteImageBtn = document.getElementById('deleteImageBtn');
    const deleteImageInput = document.getElementById('deleteImageInput');

    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.classList.remove('hidden');
                uploadPlaceholder.classList.add('hidden');
                deleteImageBtn.classList.remove('hidden');
                deleteImageBtn.classList.add('flex');
                deleteImageInput.value = '0';
            };
            reader.readAsDataURL(file);
        }
    });

    function removeImage() {
        if (confirm('Bu görseli silmek istediğinize emin misiniz?')) {
            imageInput.value = '';
            imagePreview.src = '';
            imagePreview.classList.add('hidden');
            uploadPlaceholder.classList.remove('hidden');
            deleteImageBtn.classList.add('hidden');
            deleteImageBtn.classList.remove('flex');
            deleteImageInput.value = '1';
        }
    }

    // Ekstra Seçenek Yönetimi
    function addExtraRow(name = '', price = '') {
        const container = document.getElementById('extrasContainer');
        const noMsg = document.getElementById('noExtrasMsg');
        noMsg.classList.add('hidden');

        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 sm:gap-3 bg-slate-50 p-2 sm:p-3 rounded-2xl border border-slate-100 animate-fade-in';
        row.innerHTML = `
            <div class="flex-1">
                <input type="text" name="extra_names[]" value="${name}" placeholder="Seçenek Adı (örn: Ekstra Karamel)" class="w-full px-3 py-2 sm:py-2.5 bg-white border border-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-200 transition-all text-xs font-bold text-slate-600">
            </div>
            <div class="w-24 sm:w-32">
                <div class="relative">
                    <input type="number" name="extra_prices[]" value="${price}" step="0.01" placeholder="0.00" class="w-full pl-3 pr-8 py-2 sm:py-2.5 bg-white border border-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-200 transition-all text-xs font-black text-[#c5a059]">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 pointer-events-none">₺</span>
                </div>
            </div>
            <button type="button" onclick="this.parentElement.remove(); checkNoExtras();" class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 bg-red-50 text-red-400 rounded-xl hover:bg-red-500 hover:text-white transition-all text-xs flex items-center justify-center">
                <i class="fas fa-trash-alt"></i>
            </button>
        `;
        container.appendChild(row);
    }

    function checkNoExtras() {
        const container = document.getElementById('extrasContainer');
        const noMsg = document.getElementById('noExtrasMsg');
        if (container.children.length === 0) {
            noMsg.classList.remove('hidden');
        }
    }

    // Mevcut ekstraları yükle
    document.addEventListener('DOMContentLoaded', () => {
        const existingExtras = <?= $product['extras'] ?: '[]' ?>;
        if (existingExtras && existingExtras.length > 0) {
            existingExtras.forEach(extra => {
                addExtraRow(extra.name, extra.price);
            });
        } else {
            checkNoExtras();
        }
    });
    </script>

    <style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.3s ease-out forwards;
    }
    </style>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
