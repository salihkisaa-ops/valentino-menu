<?php
// AJAX endpoint'leri için output buffering'i en başta temizle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_price']) || isset($_POST['toggle_active']))) {
    // Tüm output buffer'ları temizle
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

require_once '../includes/config.php';

// AJAX: Fiyat Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    // AJAX için auth kontrolü
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $productId = (int)($_POST['product_id'] ?? 0);
        $newPrice = floatval($_POST['price'] ?? 0);
        
        if ($productId <= 0) throw new Exception('Geçersiz ürün ID');
        if ($newPrice < 0) throw new Exception('Fiyat sıfırdan küçük olamaz');
        
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
        
        if ($stmt->execute([$newPrice, $productId])) {
            echo json_encode([
                'success' => true,
                'message' => 'Fiyat güncellendi',
                'formatted_price' => number_format($newPrice, 2, '.', '') . ' ₺',
                'price' => $newPrice
            ]);
        } else {
            throw new Exception('Fiyat güncellenemedi');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX: Aktif/Pasif Durumu Değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    // AJAX için auth kontrolü
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $productId = (int)($_POST['product_id'] ?? 0);
        
        if ($productId <= 0) throw new Exception('Geçersiz ürün ID');
        
        $pdo = getDB();
        // Mevcut durumu al
        $stmt = $pdo->prepare("SELECT is_active FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) throw new Exception('Ürün bulunamadı');
        
        $newStatus = $current['is_active'] == 1 ? 0 : 1;
        
        // Durumu güncelle
        $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
        
        if ($stmt->execute([$newStatus, $productId])) {
            echo json_encode([
                'success' => true,
                'message' => 'Durum güncellendi',
                'is_active' => $newStatus,
                'status_text' => $newStatus == 1 ? 'Aktif' : 'Pasif'
            ]);
        } else {
            throw new Exception('Durum güncellenemedi');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Normal sayfa için
ob_start();
requireAuth(); // Admin paneli koruması

// Ürün Silme İşlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $product = getProduct($id);
    
    if ($product) {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt->execute([$id])) {
            if ($product['image'] && file_exists(UPLOAD_PATH . $product['image'])) {
                @unlink(UPLOAD_PATH . $product['image']);
            }
            setFlash('success', 'Ürün başarıyla silindi.');
        } else {
            setFlash('error', 'Ürün silinirken bir hata oluştu.');
        }
    }
    header('Location: products.php');
    exit;
}

// Verileri çek
$products = getProducts(null, false);
$categories = getCategories(false);

include 'header.php';
$flash = getFlash();
?>

    <main class="p-4 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">
        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
            <div class="flex items-center">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-lg"></i>
                <span class="font-bold text-xs sm:text-sm"><?= e($flash['message']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 sm:mb-12 gap-5">
            <div>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Ürün Yönetimi</h3>
                <p class="text-slate-400 font-bold text-xs sm:text-sm mt-1 uppercase tracking-wider opacity-70">Toplam <?= count($products) ?> Lezzet Bulunuyor</p>
            </div>
            <a href="add-product.php" class="w-full sm:w-auto bg-[var(--primary)] text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/20 flex items-center justify-center transition-all hover:scale-105 active:scale-95">
                <i class="fas fa-plus mr-3 opacity-60 text-sm"></i> Yeni Ürün Ekle
            </a>
        </div>

        <!-- Filter & Search Card -->
        <div class="glass-card p-4 sm:p-6 mb-8 sm:mb-10 border border-slate-100/50 shadow-sm">
            <div class="flex flex-col lg:row-cols-2 gap-4">
                <div class="relative flex-grow">
                    <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-300">
                        <i class="fas fa-search"></i>
                    </div>
                    <input type="text" id="searchInput" placeholder="İsim veya içerik ile ara..." class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-bold text-slate-700">
                </div>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative flex-grow sm:min-w-[200px]">
                        <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-300">
                            <i class="fas fa-filter text-xs"></i>
            </div>
                        <select id="categoryFilter" class="w-full pl-11 pr-10 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-bold text-slate-600 appearance-none cursor-pointer">
                <option value="">Tüm Kategoriler</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
                        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-300">
                            <i class="fas fa-chevron-down text-[10px]"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Container -->
        <div id="productsContainer">
            <?php if (empty($products)): ?>
            <div class="py-20 text-center bg-white rounded-[40px] border border-slate-100 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-mug-hot text-slate-200 text-5xl"></i>
                </div>
                <p class="text-slate-400 font-black text-xl tracking-tight">Henüz ürün eklenmemiş.</p>
                <a href="add-product.php" class="inline-block mt-6 text-[var(--primary)] font-black text-sm uppercase tracking-widest hover:underline">İlk lezzetinizi oluşturun →</a>
            </div>
            <?php else: ?>
            
            <!-- Desktop List View (Hidden on Mobile) -->
            <div class="hidden lg:block glass-card overflow-hidden border border-slate-100/50 shadow-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Ürün</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Kategori</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Fiyat</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Durum</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($products as $product): ?>
                        <tr class="product-row hover:bg-slate-50/50 transition-colors group" 
                            data-category="<?= $product['category_id'] ?>" 
                            data-name="<?= strtolower(e($product['name'])) ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0 shadow-sm relative group-hover:scale-110 transition-transform">
                                        <?php if ($product['image']): ?>
                                        <img src="<?= getProductImage($product['image']) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-300">
                                            <i class="fas fa-image text-xs"></i>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($product['is_popular']): ?>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-amber-400 text-white rounded-md flex items-center justify-center shadow-sm border border-amber-300" title="Popüler">
                                            <i class="fas fa-star text-[6px]"></i>
                                    </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="font-black text-slate-700 text-sm"><?= e($product['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest rounded-lg"><?= e($product['category_name']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="price-edit-wrapper relative" data-product-id="<?= $product['id'] ?>">
                                    <div class="price-display flex items-baseline gap-1 cursor-pointer group/price">
                                        <span class="text-sm font-black text-[var(--primary)]"><?= number_format($product['price'], 2, '.', '') ?></span>
                                        <span class="text-[10px] font-black text-slate-400">₺</span>
                                        <i class="fas fa-pen text-[8px] text-slate-300 ml-2 opacity-0 group-hover/price:opacity-100 transition-opacity"></i>
                                    </div>
                                    <div class="price-input-container hidden absolute inset-y-0 left-0 flex items-center bg-white z-10 w-full animate-fadeIn">
                                        <div class="flex items-center gap-1.5 w-full">
                                            <input type="number" step="0.01" min="0" value="<?= $product['price'] ?>" 
                                                   class="price-input w-20 px-2 py-1.5 bg-slate-50 border-2 border-emerald-500 rounded-lg text-xs font-black focus:outline-none shadow-sm">
                                            <button class="price-save-btn w-7 h-7 bg-emerald-500 text-white rounded-lg flex items-center justify-center shadow-lg shadow-emerald-900/20 hover:bg-emerald-600 transition-all">
                                                <i class="fas fa-check text-[10px]"></i>
                                            </button>
                                            <button class="price-cancel-btn w-7 h-7 bg-slate-100 text-slate-400 rounded-lg flex items-center justify-center hover:bg-slate-200 transition-all">
                                                <i class="fas fa-times text-[10px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <label class="inline-flex items-center cursor-pointer product-status-toggle" data-product-id="<?= $product['id'] ?>">
                                    <input type="checkbox" class="toggle-switch" <?= $product['is_active'] ? 'checked' : '' ?>>
                                    <span class="ml-2 status-text text-[9px] font-black uppercase tracking-widest <?= $product['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?>">
                                        <?= $product['is_active'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                                </label>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="edit-product.php?id=<?= $product['id'] ?>" class="w-8 h-8 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 text-slate-400 rounded-lg flex items-center justify-center transition-all border border-transparent hover:border-emerald-100" title="Düzenle">
                                        <i class="fas fa-pen text-[10px]"></i>
                                    </a>
                                    <button onclick="confirmDelete(<?= $product['id'] ?>, '<?= e($product['name']) ?>')" class="w-8 h-8 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 rounded-lg flex items-center justify-center transition-all border border-transparent hover:border-rose-100" title="Sil">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (Hidden on Desktop) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 lg:hidden">
                <?php foreach ($products as $product): ?>
                <div class="glass-card p-4 sm:p-5 flex flex-col transition-all duration-300 hover:shadow-xl hover:border-emerald-100 group product-row" 
                     data-category="<?= $product['category_id'] ?>" 
                     data-name="<?= strtolower(e($product['name'])) ?>">
                    
                    <div class="flex gap-4 sm:gap-5 mb-4">
                        <!-- Product Image -->
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-[24px] bg-slate-100 overflow-hidden flex-shrink-0 shadow-lg group-hover:scale-105 transition-transform relative">
                            <?php if ($product['image']): ?>
                            <img src="<?= getProductImage($product['image']) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                                <i class="fas fa-image text-2xl mb-1"></i>
                                <span class="text-[8px] font-black uppercase">Görsel Yok</span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Badges on Image -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                <?php if ($product['is_popular']): ?>
                                <span class="w-6 h-6 bg-amber-400 text-white rounded-lg flex items-center justify-center shadow-md border border-amber-300" title="Popüler">
                                    <i class="fas fa-star text-[10px]"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Main Info -->
                        <div class="flex-grow min-w-0 flex flex-col justify-center">
                            <div class="mb-1">
                                <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest rounded-md mb-1.5"><?= e($product['category_name']) ?></span>
                                <h4 class="font-black text-slate-800 text-base sm:text-lg leading-tight truncate"><?= e($product['name']) ?></h4>
                            </div>
                            
                            <!-- Price Display & Edit -->
                            <div class="mt-2 price-edit-wrapper relative" data-product-id="<?= $product['id'] ?>">
                                <div class="price-display flex items-baseline gap-1 cursor-pointer hover:scale-105 transition-transform origin-left group/price" title="Fiyatı hızlıca güncellemek için tıklayın">
                                    <span class="text-xl font-black text-[var(--primary)]"><?= number_format($product['price'], 2, '.', '') ?></span>
                                    <span class="text-xs font-black text-slate-400">₺</span>
                                    <i class="fas fa-pen text-[10px] text-slate-300 ml-2 opacity-0 group-hover/price:opacity-100 transition-opacity"></i>
                                </div>
                                
                                <div class="price-input-container hidden absolute inset-y-0 left-0 flex items-center bg-white z-10 w-full animate-fadeIn">
                                    <div class="flex items-center gap-2 w-full">
                                        <input type="number" step="0.01" min="0" value="<?= $product['price'] ?>" 
                                               class="price-input w-24 px-3 py-2 bg-slate-50 border-2 border-emerald-500 rounded-xl text-sm font-black focus:outline-none shadow-sm">
                                        <button class="price-save-btn w-9 h-9 bg-emerald-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-emerald-900/20 hover:bg-emerald-600 transition-all">
                                            <i class="fas fa-check text-xs"></i>
                                        </button>
                                        <button class="price-cancel-btn w-9 h-9 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Footer Stats & Status -->
                    <div class="flex items-center justify-between pt-4 mt-auto border-t border-slate-50">
                        <div class="flex items-center gap-3">
                            <?php if ($product['is_active']): ?>
                            <span class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest rounded-xl">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2 animate-pulse"></span> Aktif
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1.5 bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest rounded-xl">
                                <span class="w-1.5 h-1.5 bg-slate-300 rounded-full mr-2"></span> Pasif
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="edit-product.php?id=<?= $product['id'] ?>" class="w-10 h-10 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 text-slate-400 rounded-xl flex items-center justify-center transition-all border border-transparent hover:border-emerald-100" title="Düzenle">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button onclick="confirmDelete(<?= $product['id'] ?>, '<?= e($product['name']) ?>')" class="w-10 h-10 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 rounded-xl flex items-center justify-center transition-all border border-transparent hover:border-rose-100" title="Sil">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[40px] p-8 max-w-md w-full shadow-2xl animate-scaleUp">
            <div class="w-20 h-20 bg-rose-50 rounded-[30px] flex items-center justify-center mx-auto mb-6 border border-rose-100">
                <i class="fas fa-trash text-rose-500 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800 text-center mb-2 tracking-tight">Ürünü Sil</h3>
            <p class="text-slate-400 font-bold text-sm text-center mb-8 px-4">
                <span id="deleteProductName" class="text-slate-700"></span> ürününü kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.
            </p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all">Vazgeç</button>
                <a id="deleteLink" href="#" class="flex-1 py-4 bg-rose-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest text-center hover:bg-rose-600 shadow-lg shadow-rose-900/20 transition-all">Evet, Sil</a>
            </div>
        </div>
    </div>

    <style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .animate-fadeIn { animation: fadeIn 0.2s ease-out forwards; }
    .animate-scaleUp { animation: scaleUp 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    </style>

    <script>
    // Search & Filter Logic
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const productRows = document.querySelectorAll('.product-row');

    function filterProducts() {
        const search = searchInput.value.toLowerCase().trim();
        const category = categoryFilter.value;
        
        productRows.forEach(row => {
            const name = row.dataset.name;
            const cat = row.dataset.category;
            const matchSearch = !search || name.includes(search);
            const matchCategory = !category || cat === category;
            
            if (matchSearch && matchCategory) {
                row.classList.remove('hidden');
                row.classList.add('flex');
            } else {
                row.classList.add('hidden');
                row.classList.remove('flex');
            }
        });
    }

    searchInput.addEventListener('input', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);

    // Delete Modal Logic
    function confirmDelete(id, name) {
        document.getElementById('deleteProductName').textContent = name;
        document.getElementById('deleteLink').href = 'products.php?delete=' + id;
        const modal = document.getElementById('deleteModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    // Toggle Active/Inactive Status
    document.querySelectorAll('.product-status-toggle').forEach(toggle => {
        const checkbox = toggle.querySelector('.toggle-switch');
        const statusText = toggle.querySelector('.status-text');
        const productId = toggle.dataset.productId;
        
        checkbox.addEventListener('change', async function() {
            const isActive = this.checked ? 1 : 0;
            const originalChecked = this.checked;
            
            // Disable checkbox during request
            this.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('toggle_active', '1');
                formData.append('product_id', productId);
                
                const response = await fetch('products', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                // Response'u text olarak al, sonra parse et
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error. Response was:', responseText);
                    throw new Error('Sunucudan geçersiz yanıt geldi. Lütfen sayfayı yenileyin.');
                }
                
                if (data.success) {
                    statusText.textContent = data.status_text;
                    statusText.className = 'ml-2 status-text text-[9px] font-black uppercase tracking-widest ' + 
                        (data.is_active == 1 ? 'text-emerald-600' : 'text-slate-400');
                } else {
                    // Revert on error
                    this.checked = !originalChecked;
                    alert('Hata: ' + (data.error || 'Durum güncellenemedi'));
                }
            } catch (error) {
                // Revert on error
                this.checked = !originalChecked;
                console.error('Toggle error:', error);
                alert('Bir hata oluştu: ' + (error.message || 'Lütfen tekrar deneyin.'));
            } finally {
                this.disabled = false;
            }
        });
    });

    // Inline Price Editing
    document.querySelectorAll('.price-edit-wrapper').forEach(wrapper => {
        const productId = wrapper.dataset.productId;
        const display = wrapper.querySelector('.price-display');
        const inputContainer = wrapper.querySelector('.price-input-container');
        const input = wrapper.querySelector('.price-input');
        const saveBtn = wrapper.querySelector('.price-save-btn');
        const cancelBtn = wrapper.querySelector('.price-cancel-btn');
        let originalValue = input.value;

        display.addEventListener('click', (e) => {
            e.stopPropagation();
            originalValue = input.value;
            inputContainer.classList.remove('hidden');
            input.focus();
            input.select();
        });

        const cancelEdit = () => {
            input.value = originalValue;
            inputContainer.classList.add('hidden');
        };

        cancelBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            cancelEdit();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') saveBtn.click();
            if (e.key === 'Escape') cancelEdit();
        });

        saveBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const newPrice = parseFloat(input.value);
            
            if (isNaN(newPrice) || newPrice < 0) {
                alert('Lütfen geçerli bir fiyat girin');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const formData = new FormData();
                formData.append('update_price', '1');
                formData.append('product_id', productId);
                formData.append('price', newPrice);

                const response = await fetch('products', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('JSON Parse Error. Response was:', responseText);
                    throw new Error('Sunucudan geçersiz yanıt geldi. Konsolu kontrol edin.');
                }

                if (data.success) {
                    display.querySelector('span:first-child').textContent = data.formatted_price.replace(' ₺', '');
                    originalValue = newPrice;
                    inputContainer.classList.add('hidden');
                    
                    // Success animation
                    display.classList.add('scale-110', 'text-emerald-500');
                    setTimeout(() => display.classList.remove('scale-110', 'text-emerald-500'), 500);
                } else {
                    alert('Hata: ' + (data.error || 'Fiyat güncellenemedi'));
                }
            } catch (error) {
                console.error('Price update error:', error);
                alert('Bir hata oluştu. Sunucu yanıtını kontrol edin.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-check text-xs"></i>';
            }
        });
    });

    // Global click listener to close open price inputs
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.price-input-container:not(.hidden)').forEach(container => {
            if (!container.contains(e.target) && !container.previousElementSibling.contains(e.target)) {
                container.classList.add('hidden');
            }
        });
    });
    </script>

    <style>
        .toggle-switch {
            appearance: none;
            width: 40px;
            height: 22px;
            background: #cbd5e1;
            border-radius: 11px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
            flex-shrink: 0;
        }
        
        .toggle-switch::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: left 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .toggle-switch:checked {
            background: #10b981;
        }
        
        .toggle-switch:checked::before {
            left: 20px;
        }
        
        .toggle-switch:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
