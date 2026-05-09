<?php
require_once '../includes/config.php';
requireAuth();

$flash = null;
$errors = [];

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bölüm aktiflik durumu
    if (isset($_POST['toggle_section'])) {
        updateSetting('featured_section_active', $_POST['is_active'] == '1' ? '1' : '0');
        setFlash('success', 'Öne çıkanlar bölümü durumu güncellendi.');
        header('Location: home-recommended.php');
        exit;
    }
    
    // Başlık güncelleme
    if (isset($_POST['update_titles'])) {
        updateSetting('featured_section_title', trim($_POST['featured_title'] ?? 'Bunları Beğenebilirsiniz'));
        setFlash('success', 'Başlık başarıyla güncellendi.');
        header('Location: home-recommended.php');
        exit;
    }

    $productData = isset($_POST['product_data']) ? $_POST['product_data'] : '[]';
    
    // JSON doğrulama
    $decoded = json_decode($productData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Geçersiz veri formatı: ' . json_last_error_msg();
    } else {
        try {
            // Veriyi temizle ve normalize et
            $cleanedData = [];
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['id']) && is_numeric($item['id'])) {
                        $cleanedData[] = [
                            'id' => (int)$item['id'],
                            'active' => isset($item['active']) ? ((int)$item['active'] ? 1 : 0) : 1,
                            'order' => isset($item['order']) ? (int)$item['order'] : 999
                        ];
                    }
                }
            }
            $productData = json_encode($cleanedData);
            updateSetting('home_recommended_products', $productData);
            setFlash('success', 'Öne çıkan ürünler, sıralama ve aktif/pasif durumları başarıyla kaydedildi.');
            header('Location: home-recommended.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Mevcut ayarları getir (JSON formatında: [{"id":1,"active":1},{"id":2,"active":0}])
$recommendedProductsJson = getSetting('home_recommended_products', '[]');
$recommendedProducts = json_decode($recommendedProductsJson, true);
if (!is_array($recommendedProducts)) {
    $recommendedProducts = [];
}

// Eski format desteği (virgülle ayrılmış ID'ler)
if (empty($recommendedProducts) && !empty($recommendedProductsJson) && strpos($recommendedProductsJson, '[') === false) {
    $oldIds = explode(',', $recommendedProductsJson);
    $recommendedProducts = [];
    foreach ($oldIds as $id) {
        $id = trim($id);
        if (!empty($id)) {
            $recommendedProducts[] = ['id' => (int)$id, 'active' => 1];
        }
    }
}

$selectedProductIds = array_column($recommendedProducts, 'id');

// Tüm ürünleri getir
$products = getProducts(null, true);

// Ürünleri kolay erişim için ID'ye göre indeksle
$productsById = [];
foreach ($products as $product) {
    $productsById[$product['id']] = $product;
}

// Seçili ürünleri sıralı olarak ayır
$selectedProductsOrdered = [];
foreach ($selectedProductIds as $id) {
    $id = trim($id);
    if (!empty($id) && isset($productsById[$id])) {
        $selectedProductsOrdered[] = $productsById[$id];
        unset($productsById[$id]); // Listeden çıkar ki aşağıda tekrar görünmesin
    }
}

// Kalan ürünleri kategorilere göre grupla
$groupedProducts = [];
foreach ($productsById as $product) {
    $catName = $product['category_name'] ?? 'Diğer';
    if (!isset($groupedProducts[$catName])) {
        $groupedProducts[$catName] = [];
    }
    $groupedProducts[$catName][] = $product;
}
ksort($groupedProducts);

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
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-10 gap-4">
        <div>
            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight">Ana Sayfa Öne Çıkanlar</h3>
            <p class="text-slate-400 font-medium text-xs sm:text-sm mt-1">"Bunları Beğenebilirsiniz" alanındaki ürünleri seçin, sıralayın ve aktif/pasif yapın.</p>
        </div>
        
        <!-- Başlık Düzenleme Modalı -->
        <button onclick="openTitleModal()" class="w-full sm:w-auto bg-indigo-500 text-white px-6 py-3 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest shadow-lg shadow-indigo-900/20 flex items-center justify-center transition-transform active:scale-95">
            <i class="fas fa-edit mr-2 opacity-60"></i> Başlıkları Düzenle
        </button>
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <!-- Bölüm Aktif/Pasif Toggle -->
            <?php $sectionActive = getSetting('featured_section_active', '1') == '1'; ?>
            <form method="POST" class="bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between gap-4">
                <input type="hidden" name="toggle_section" value="1">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bölümü Göster</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="hidden" onchange="this.form.submit()" <?= $sectionActive ? 'checked' : '' ?>>
                    <div class="toggle-switch <?= $sectionActive ? 'bg-emerald-500' : 'bg-slate-300' ?>" style="width: 36px; height: 18px;">
                        <div class="absolute w-3.5 h-3.5 bg-white rounded-full transition-all duration-300 shadow-sm" style="top: 2.2px; left: <?= $sectionActive ? '20px' : '2.5px' ?>;"></div>
                    </div>
                </label>
            </form>
            
            <button type="button" onclick="submitRecommendedForm()" class="bg-[var(--primary)] text-white px-8 py-3 sm:py-4 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/10 flex items-center justify-center transition-transform active:scale-95">
                <i class="fas fa-save mr-2 sm:mr-3 opacity-60"></i> Değişiklikleri Kaydet
            </button>
        </div>
    </div>

    <form id="homeRecommendedForm" method="POST" action="home-recommended">
        <input type="hidden" name="product_data" id="productDataInput" value="<?= e(json_encode($recommendedProducts)) ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Active/Selected List (Sortable) -->
            <div class="lg:col-span-1">
                <div class="glass-card p-6 min-h-[400px]">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-sort-amount-down"></i>
                            </div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Aktif Sıralama</h4>
                        </div>
                        <?php 
                        $activeCount = 0;
                        foreach ($selectedProductsOrdered as $product) {
                            $productActive = 1;
                            foreach ($recommendedProducts as $rp) {
                                if ($rp['id'] == $product['id']) {
                                    $productActive = isset($rp['active']) ? (int)$rp['active'] : 1;
                                    break;
                                }
                            }
                            if ($productActive == 1) $activeCount++;
                        }
                        ?>
                        <span id="selectedCount" class="text-[10px] font-black bg-emerald-50 text-emerald-600 px-2.5 py-1 rounded-lg"><?= count($selectedProductsOrdered) ?> Ürün (<?= $activeCount ?> Aktif)</span>
                    </div>

                    <div id="sortableList" class="space-y-3">
                        <?php 
                        foreach ($selectedProductsOrdered as $product): 
                            $productActive = 1;
                            foreach ($recommendedProducts as $rp) {
                                if ($rp['id'] == $product['id']) {
                                    $productActive = isset($rp['active']) ? (int)$rp['active'] : 1;
                                    break;
                                }
                            }
                        ?>
                        <div class="sortable-item bg-white p-3 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3 cursor-move group hover:border-[var(--primary)] transition-all" data-id="<?= $product['id'] ?>" data-active="<?= $productActive ?>">
                            <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-slate-50 border border-slate-50">
                                <img src="../assets/img/products/<?= e($product['image']) ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-bold text-slate-700 truncate uppercase"><?= e($product['name']) ?></p>
                                <p class="text-[9px] font-bold text-emerald-600 mt-0.5"><?= formatPrice($product['price']) ?></p>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="product-active-toggle toggle-switch" <?= $productActive ? 'checked' : '' ?>>
                                <span class="text-[8px] font-bold text-slate-400 uppercase">Aktif</span>
                            </label>
                            <button type="button" class="remove-btn text-slate-300 hover:text-rose-500 p-2 transition-colors">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($selectedProductsOrdered)): ?>
                        <div id="emptyState" class="py-12 text-center">
                            <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300 text-xl">
                                <i class="fas fa-plus"></i>
                            </div>
                            <p class="text-xs font-bold text-slate-400">Henüz ürün seçilmedi.<br>Yandan ürün ekleyin.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Product Selection Grid -->
            <div class="lg:col-span-2">
                <div class="glass-card p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b border-slate-50 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Tüm Ürünler</h4>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="productSearch" placeholder="Ürünlerde ara..." 
                                class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-100 focus:border-[var(--primary)] outline-none transition-all text-xs font-medium bg-slate-50">
                        </div>
                    </div>

                    <div class="max-h-[600px] overflow-y-auto pr-2 space-y-8" id="productSelectionArea">
                        <?php foreach ($groupedProducts as $categoryName => $catProducts): ?>
                        <div class="category-section" data-cat="<?= e($categoryName) ?>">
                            <div class="flex items-center gap-2 mb-4 sticky top-0 bg-white/95 backdrop-blur-md py-2 z-10">
                                <span class="w-1 h-4 bg-[var(--primary)] rounded-full"></span>
                                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= e($categoryName) ?></h5>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($catProducts as $product): ?>
                                <div class="product-selection-item bg-slate-50 hover:bg-emerald-50 border border-transparent hover:border-emerald-100 p-3 rounded-2xl flex items-center gap-3 transition-all cursor-pointer group" 
                                     data-id="<?= $product['id'] ?>"
                                     data-name="<?= strtolower(e($product['name'])) ?>"
                                     data-image="<?= e($product['image']) ?>"
                                     data-price="<?= formatPrice($product['price']) ?>">
                                    <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-white border border-slate-100 shadow-sm">
                                        <img src="../assets/img/products/<?= e($product['image']) ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[11px] font-bold text-slate-700 truncate uppercase"><?= e($product['name']) ?></p>
                                        <p class="text-[9px] font-bold text-emerald-600 mt-0.5"><?= formatPrice($product['price']) ?></p>
                                    </div>
                                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-[var(--primary)] shadow-sm group-hover:bg-[var(--primary)] group-hover:text-white transition-all">
                                        <i class="fas fa-plus text-[10px]"></i>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<!-- Başlık Düzenleme Modalı -->
<div id="titleModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-[40px] p-6 sm:p-10 max-w-lg w-full shadow-2xl animate-scaleUp">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">
                <i class="fas fa-heading text-xl"></i>
            </div>
            <div>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight">Bölüm Başlıkları</h3>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest">Ana sayfada görünecek metinleri düzenleyin</p>
            </div>
        </div>
        
        <form method="POST" id="titleForm">
            <input type="hidden" name="update_titles" value="1">
            
            <div class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Ana Başlık *</label>
                    <input type="text" name="featured_title" id="featuredTitle" required value="<?= e(getSetting('featured_section_title', 'Bunları Beğenebilirsiniz')) ?>" placeholder="Bunları Beğenebilirsiniz" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:bg-white focus:border-indigo-500 transition-all text-sm font-black text-slate-700">
                </div>
            </div>
            
            <div class="flex gap-3 mt-10">
                <button type="button" onclick="closeTitleModal()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all">Vazgeç</button>
                <button type="submit" class="flex-1 py-4 bg-indigo-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-600 shadow-lg shadow-indigo-900/20 transition-all">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.animate-scaleUp { animation: scaleUp 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
</style>

<script>
function openTitleModal() {
    document.getElementById('titleModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTitleModal() {
    document.getElementById('titleModal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.getElementById('titleModal').addEventListener('click', e => {
    if (e.target === document.getElementById('titleModal')) closeTitleModal();
});
</script>

<!-- Sortable.js -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    const sortableList = document.getElementById('sortableList');
    const input = document.getElementById('productDataInput');
    const selectedCount = document.getElementById('selectedCount');
    const productSelectionArea = document.getElementById('productSelectionArea');
    const emptyState = document.getElementById('emptyState');
    const productSearch = document.getElementById('productSearch');

    // Initialize Sortable
    const sortable = new Sortable(sortableList, {
        animation: 150,
        handle: '.sortable-item',
        ghostClass: 'opacity-50',
        onEnd: updateValue
    });

    // Search functionality
    productSearch.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const sections = document.querySelectorAll('.category-section');
        
        sections.forEach(section => {
            let hasVisible = false;
            const items = section.querySelectorAll('.product-selection-item');
            
            items.forEach(item => {
                const name = item.dataset.name;
                if (name.includes(term)) {
                    item.style.display = 'flex';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            section.style.display = hasVisible ? 'block' : 'none';
        });
    });

    // Add Product
    document.querySelectorAll('.product-selection-item').forEach(item => {
        item.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.querySelector('p.font-bold').textContent;
            const image = this.dataset.image;
            const price = this.dataset.price;

            // Check if already added
            if (document.querySelector(`.sortable-item[data-id="${id}"]`)) {
                return;
            }

            if (emptyState) emptyState.remove();

            const newItem = document.createElement('div');
            newItem.className = 'sortable-item bg-white p-3 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3 cursor-move group hover:border-[var(--primary)] transition-all';
            newItem.dataset.id = id;
            newItem.dataset.active = '1';
            newItem.innerHTML = `
                <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-slate-50 border border-slate-50">
                    <img src="../assets/img/products/${image}" class="w-full h-full object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-bold text-slate-700 truncate uppercase">${name}</p>
                    <p class="text-[9px] font-bold text-emerald-600 mt-0.5">${price}</p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" class="product-active-toggle toggle-switch" checked>
                    <span class="text-[8px] font-bold text-slate-400 uppercase">Aktif</span>
                </label>
                <button type="button" class="remove-btn text-slate-300 hover:text-rose-500 p-2 transition-colors">
                    <i class="fas fa-times-circle"></i>
                </button>
            `;

            sortableList.appendChild(newItem);
            this.style.opacity = '0.5';
            this.style.pointerEvents = 'none';
            
            updateValue();
        });
    });

    // Remove Product
    document.addEventListener('click', (e) => {
        if (e.target.closest('.remove-btn')) {
            const item = e.target.closest('.sortable-item');
            const id = item.dataset.id;
            
            const selectionItem = document.querySelector(`.product-selection-item[data-id="${id}"]`);
            if (selectionItem) {
                selectionItem.style.opacity = '1';
                selectionItem.style.pointerEvents = 'auto';
            }
            
            item.remove();
            
            if (sortableList.children.length === 0) {
                sortableList.innerHTML = `
                    <div id="emptyState" class="py-12 text-center">
                        <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300 text-xl">
                            <i class="fas fa-plus"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-400">Henüz ürün seçilmedi.<br>Yandan ürün ekleyin.</p>
                    </div>
                `;
            }
            
            updateValue();
        }
    });

    // Toggle aktif/pasif durumu
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('product-active-toggle')) {
            const item = e.target.closest('.sortable-item');
            if (item) {
                item.dataset.active = e.target.checked ? '1' : '0';
                updateValue();
            }
        }
    });

    function submitRecommendedForm() {
        updateValue(); // Son durumu al
        document.getElementById('homeRecommendedForm').submit();
    }

    function updateValue() {
        const items = Array.from(sortableList.querySelectorAll('.sortable-item'));
        const productData = items.map((item, index) => {
            const checkbox = item.querySelector('.product-active-toggle');
            const isActive = checkbox ? checkbox.checked : (item.dataset.active === '1');
            return {
                id: parseInt(item.dataset.id),
                active: isActive ? 1 : 0,
                order: index + 1
            };
        });
        input.value = JSON.stringify(productData);
        const activeCount = productData.filter(p => p.active === 1).length;
        selectedCount.textContent = `${items.length} Ürün (${activeCount} Aktif)`;
    }

    // Initial disable already selected
    try {
        const initialData = JSON.parse(input.value);
        if (Array.isArray(initialData)) {
            initialData.forEach(p => {
                const item = document.querySelector(`.product-selection-item[data-id="${p.id}"]`);
                if (item) {
                    item.style.opacity = '0.5';
                    item.style.pointerEvents = 'none';
                }
            });
        }
    } catch(e) {
        // Eski format desteği
        const initialIds = input.value.split(',');
        initialIds.forEach(id => {
            const item = document.querySelector(`.product-selection-item[data-id="${id}"]`);
            if (item) {
                item.style.opacity = '0.5';
                item.style.pointerEvents = 'none';
            }
        });
    }
    
    // Initial update
    updateValue();
</script>

<style>
    /* Sortable item hover */
    .sortable-item:hover {
        border-color: #10b981;
    }
</style>

<?php include 'footer.php'; ?>
