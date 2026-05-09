<?php
// AJAX endpoint kontrolü - En başta kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product_order'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    // Config dosyasını yükle
    require_once '../includes/config.php';
    
    // Session kontrolü
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Oturum açmanız gerekli.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $pdo = getDB();
    
    try {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $productDataJson = $_POST['product_data'] ?? '[]';
        $productData = json_decode($productDataJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Veri formatı hatası');
        }
        
        if ($categoryId <= 0) throw new Exception('Geçersiz kategori');
        
        $pdo->beginTransaction();
        $updatedCount = 0;
        
        foreach ($productData as $item) {
            $productId = (int)($item['id'] ?? 0);
            $sortOrder = (int)($item['sort_order'] ?? 0);
            
            if ($productId > 0) {
                $stmt = $pdo->prepare("UPDATE products SET sort_order = ? WHERE id = ? AND category_id = ?");
                if ($stmt->execute([$sortOrder, $productId, $categoryId])) {
                    $updatedCount++;
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Sıralama kaydedildi', 'updated_count' => $updatedCount]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
        exit;
}

// Normal sayfa yüklemesi için config
require_once '../includes/config.php';
requireAuth();
$pdo = getDB();

// Tüm kategorileri ve ürünlerini çek
$pdo_temp = getDB();
$sql = "SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        GROUP BY c.id 
        ORDER BY c.sort_order ASC";
$stmt_temp = $pdo_temp->query($sql);
$categories = $stmt_temp->fetchAll();

// Her kategori için ürünleri çek
$categoryProducts = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("SELECT id, name, price, is_active, sort_order FROM products WHERE category_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$category['id']]);
    $categoryProducts[$category['id']] = $stmt->fetchAll();
}

$cafeName = getDisplayCafeName();
$flash = getFlash();

include 'header.php';
?>

    <!-- SortableJS Library -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

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
                <h3 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Kategori Ağacı</h3>
                <p class="text-slate-400 font-bold text-xs sm:text-sm mt-1 uppercase tracking-wider opacity-70">Ürünlerin kategori bazlı sıralamasını sürükle-bırak ile yönetin.</p>
            </div>
            <button onclick="saveAllProductSortOrder(event)" class="w-full sm:w-auto bg-[var(--primary)] text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/20 flex items-center justify-center transition-all hover:scale-105 active:scale-95">
                <i class="fas fa-save mr-3 opacity-60 text-sm"></i> Tümünü Kaydet
            </button>
        </div>

        <!-- Bilgi Kutusu -->
        <div class="mb-8 p-4 bg-blue-50 border border-blue-100 rounded-3xl flex items-center gap-4 animate-fadeIn">
            <div class="w-10 h-10 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-500 flex-shrink-0">
                <i class="fas fa-info-circle"></i>
            </div>
            <p class="text-blue-700 text-xs sm:text-sm font-bold">Ürünleri sürükleyerek sıralayabilirsiniz. Sıralama bittiğinde "Tümünü Kaydet" butonuna basmayı unutmayın.</p>
    </div>

    <!-- Kategoriler ve Ürünleri -->
        <div class="space-y-6 sm:space-y-8">
        <?php if (empty($categories)): ?>
            <div class="py-20 text-center bg-white rounded-[40px] border border-slate-100 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-folder-open text-slate-200 text-5xl"></i>
                </div>
                <p class="text-slate-400 font-black text-xl tracking-tight">Henüz kategori eklenmemiş.</p>
                <a href="categories.php" class="inline-block mt-6 text-[var(--primary)] font-black text-sm uppercase tracking-widest hover:underline">Kategorileri ekleyin →</a>
        </div>
        <?php else: ?>
        <?php foreach ($categories as $category): 
            $products = $categoryProducts[$category['id']] ?? [];
        ?>
            <div class="glass-card p-4 sm:p-8">
            <!-- Kategori Başlığı -->
                <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-100/50">
                <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-[var(--primary)] to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-900/20 flex-shrink-0">
                            <i class="<?= e($category['icon']) ?> text-white text-lg sm:text-xl"></i>
                    </div>
                    <div>
                            <h4 class="font-black text-slate-800 text-base sm:text-xl tracking-tight"><?= e($category['name']) ?></h4>
                            <p class="text-slate-400 text-[10px] sm:text-xs font-black uppercase tracking-widest opacity-70"><?= count($products) ?> Ürün Mevcut</p>
                    </div>
                </div>
                <?php if ($category['is_active']): ?>
                    <span class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-600 text-[9px] sm:text-[10px] font-black uppercase tracking-widest rounded-xl">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2 animate-pulse"></span> Aktif
                </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1.5 bg-slate-50 text-slate-400 text-[9px] sm:text-[10px] font-black uppercase tracking-widest rounded-xl">
                    <span class="w-1.5 h-1.5 bg-slate-300 rounded-full mr-2"></span> Pasif
                </span>
                <?php endif; ?>
            </div>

                <!-- Ürün Listesi -->
            <?php if (empty($products)): ?>
                <div class="py-10 text-center bg-slate-50/50 rounded-[30px] border border-dashed border-slate-200">
                    <i class="fas fa-box-open text-slate-200 text-3xl mb-3"></i>
                    <p class="text-slate-400 font-black text-xs uppercase tracking-widest">Bu kategoride ürün bulunmuyor.</p>
            </div>
            <?php else: ?>
                <ul id="products-<?= $category['id'] ?>" class="product-sortable-list space-y-3" data-category-id="<?= $category['id'] ?>">
                <?php foreach ($products as $index => $product): ?>
                    <li class="product-item glass-card p-4 sm:p-5 flex items-center justify-between group cursor-move hover:border-emerald-500/30 transition-all duration-300 active:scale-[0.98] active:shadow-sm" 
                        data-product-id="<?= $product['id'] ?>">
                        
                        <div class="flex items-center gap-4 flex-1 min-w-0">
                            <!-- Drag Handle -->
                            <div class="w-8 h-8 bg-slate-50 rounded-lg flex items-center justify-center text-slate-300 group-hover:text-emerald-500 transition-colors flex-shrink-0">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                        
                        <!-- Ürün Bilgileri -->
                            <div class="flex-1 min-w-0">
                                <h5 class="font-black text-slate-800 text-sm sm:text-base leading-tight truncate mb-1"><?= e($product['name']) ?></h5>
                                <div class="flex items-center gap-2">
                                    <span class="text-emerald-600 text-xs font-black"><?= formatPrice($product['price']) ?></span>
                                    <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Sıra: <span class="sort-order-text text-slate-600"><?= $product['sort_order'] ?: ($index + 1) ?></span></span>
                                </div>
                        </div>
                        
                            <!-- Status Badges -->
                            <div class="flex items-center gap-2">
                        <?php if ($product['is_active']): ?>
                                <span class="hidden sm:inline-flex items-center px-2 py-1 bg-emerald-50 text-emerald-500 text-[8px] font-black uppercase tracking-widest rounded-lg">Aktif</span>
                        <?php else: ?>
                                <span class="hidden sm:inline-flex items-center px-2 py-1 bg-slate-50 text-slate-400 text-[8px] font-black uppercase tracking-widest rounded-lg">Pasif</span>
                        <?php endif; ?>
                            </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<style>
    .sortable-ghost { opacity: 0.4; background: #ecfdf5 !important; border: 2px dashed #10b981 !important; }
    .sortable-drag { background: white !important; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
    
.product-item {
        border: 1px solid rgba(0,0,0,0.03);
        touch-action: none; /* Prevents scrolling while dragging on mobile */
}

.success-message {
    position: fixed;
        bottom: 30px;
        right: 30px;
    background: #10b981;
    color: white;
        padding: 16px 32px;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
    z-index: 1000;
        font-weight: 800;
    font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

    @keyframes slideIn { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<script>
    // Initialize Sortable for each category product list
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.product-sortable-list').forEach(list => {
            new Sortable(list, {
                animation: 250,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                handle: '.product-item', // Whole item is a handle
                onEnd: function() {
                    // Update visual sort order numbers
                    updateOrderTexts(list);
                }
            });
        });
    });
    
    function updateOrderTexts(list) {
        const items = list.querySelectorAll('.product-item');
        items.forEach((item, index) => {
            const textSpan = item.querySelector('.sort-order-text');
            if (textSpan) textSpan.textContent = index + 1;
        });
    }

    async function saveAllProductSortOrder(event) {
        const lists = document.querySelectorAll('.product-sortable-list');
        const saveBtn = event.currentTarget;
    const originalText = saveBtn.innerHTML;
        
    saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Kaydediliyor...';
    
        try {
    const promises = [];
    
            for (const list of lists) {
                const categoryId = list.dataset.categoryId;
                const items = list.querySelectorAll('.product-item');
                const productData = [];
                
                items.forEach((item, index) => {
                    productData.push({
                        id: item.dataset.productId,
                        sort_order: index + 1
                    });
                });
                
                if (productData.length > 0) {
        const formData = new FormData();
        formData.append('update_product_order', '1');
        formData.append('category_id', categoryId);
        formData.append('product_data', JSON.stringify(productData));
        
                    const promise = fetch('category-tree', {
                method: 'POST',
                body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(async r => {
                        const text = await r.text();
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON Parse Error. Response was:', text);
                            return { success: false, error: 'Sunucudan geçersiz yanıt geldi.' };
                }
                    });
                    
                    promises.push(promise);
                }
            }
            
            if (promises.length === 0) {
                alert('Kaydedilecek veri bulunamadı.');
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
                return;
            }

            const results = await Promise.all(promises);
            const allSuccess = results.every(r => r.success);
                
            if (allSuccess) {
                showSuccessMessage('Sıralamalar başarıyla kaydedildi!');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                const error = results.find(r => !r.success)?.error || 'Bir hata oluştu.';
                alert('Hata: ' + error);
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
            
        } catch (error) {
            console.error('Save error:', error);
            alert('Sıralama kaydedilirken bir hata oluştu.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
}

function showSuccessMessage(message) {
    const flash = document.createElement('div');
    flash.className = 'success-message';
        flash.innerHTML = '<i class="fas fa-check-circle"></i>' + message;
    document.body.appendChild(flash);
    
    setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(20px)';
            flash.style.transition = 'all 0.4s ease';
            setTimeout(() => flash.remove(), 400);
    }, 2000);
}
</script>

</div><!-- Close Main Content Div from Header -->
</body>
</html>
