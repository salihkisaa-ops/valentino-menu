<?php
require_once '../includes/config.php';
requireAuth();

$flash = null;
$errors = [];

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recommendedProducts = isset($_POST['recommended_products']) && is_array($_POST['recommended_products']) 
        ? implode(',', array_map('intval', array_filter($_POST['recommended_products']))) 
        : '';
    $assistantName = trim($_POST['assistant_name'] ?? 'Valéntino Patisserié Akıllı Menü Asistanı');
    $welcomeMessage = trim($_POST['welcome_message'] ?? '');
    $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
    
    try {
        $pdo = getDB();
        
        // Tablonun varlığını kontrol et
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'chat_assistant_settings'");
        if ($tableCheck->rowCount() == 0) {
            throw new Exception('chat_assistant_settings tablosu bulunamadı. Lütfen veritabanı SQL dosyasını çalıştırın.');
        }
        
        // Mevcut ayarları kontrol et
        $stmt = $pdo->query("SELECT id FROM chat_assistant_settings LIMIT 1");
        $existing = $stmt->fetch();
        
        if ($existing && isset($existing['id'])) {
            // Güncelle
            $stmt = $pdo->prepare("UPDATE chat_assistant_settings SET 
                recommended_products = ?, 
                assistant_name = ?, 
                welcome_message = ?, 
                is_active = ?,
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$recommendedProducts, $assistantName, $welcomeMessage, $isActive, $existing['id']]);
        } else {
            // Yeni ekle (şemada created_at yok; updated_at DEFAULT ile dolar)
            $stmt = $pdo->prepare(
                "INSERT INTO chat_assistant_settings 
                (recommended_products, assistant_name, welcome_message, is_active) 
                VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$recommendedProducts, $assistantName, $welcomeMessage, $isActive]);
        }
        
        setFlash('success', 'Akıllı Menü Asistanı ayarları başarıyla kaydedildi.');
        header('Location: chat-assistant.php');
        exit;
    } catch (PDOException $e) {
        $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        error_log('Chat Assistant Save Error (PDO): ' . $e->getMessage());
    } catch (Exception $e) {
        $errors[] = 'Bir hata oluştu: ' . $e->getMessage();
        error_log('Chat Assistant Save Error: ' . $e->getMessage());
    }
}

// Mevcut ayarları getir
$pdo = getDB();
$settings = null;
try {
    // Tablonun varlığını kontrol et
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'chat_assistant_settings'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM chat_assistant_settings LIMIT 1");
        $settings = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log('Chat Assistant Load Error: ' . $e->getMessage());
    $settings = null;
}

if (!$settings) {
    // Varsayılan ayarlar
    $settings = [
        'recommended_products' => '',
        'recommended_categories' => '',
        'assistant_name' => 'Valéntino Patisserié Akıllı Menü Asistanı',
        'welcome_message' => 'Merhaba! Ben Valéntino Patisserié Akıllı Menü Asistanı. Size menümüzden ürünler önerebilirim, kategoriler hakkında bilgi verebilirim veya size özel öneriler sunabilirim. 😊',
        'is_active' => 1
    ];
}

// Tüm ürünleri getir
$products = getProducts(null, true);
$selectedProducts = !empty($settings['recommended_products']) ? explode(',', $settings['recommended_products']) : [];

// Ürünleri kategorilere göre grupla
$groupedProducts = [];
foreach ($products as $product) {
    $catName = $product['category_name'] ?? 'Diğer';
    if (!isset($groupedProducts[$catName])) {
        $groupedProducts[$catName] = [];
    }
    $groupedProducts[$catName][] = $product;
}
ksort($groupedProducts); // Kategorileri isme göre sırala

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

        <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100">
            <div class="flex items-start gap-2">
                <i class="fas fa-exclamation-circle mt-0.5"></i>
                <div class="font-bold text-xs sm:text-sm space-y-1">
                    <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-10 gap-4">
            <div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight">Akıllı Menü Asistanı</h3>
                <p class="text-slate-400 font-medium text-xs sm:text-sm mt-1">Asistan ayarlarını ve önerilen ürünleri buradan yönetin.</p>
            </div>
            <button type="submit" form="chatAssistantForm" class="w-full sm:w-auto bg-[var(--primary)] text-white px-8 py-3 sm:py-4 rounded-xl font-bold text-[10px] sm:text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/10 flex items-center justify-center transition-transform active:scale-95">
                <i class="fas fa-save mr-2 sm:mr-3 opacity-60"></i> Ayarları Kaydet
            </button>
        </div>

        <form id="chatAssistantForm" method="POST">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- General Settings -->
                <div class="lg:col-span-1 space-y-6 sm:space-y-8">
                    <!-- Assistant Info -->
                    <div class="glass-card p-5 sm:p-8">
                        <div class="flex items-center space-x-3 mb-6 sm:mb-8 pb-4 border-b border-slate-50">
                            <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-robot"></i>
                            </div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Asistan Bilgileri</h4>
                        </div>

                        <div class="space-y-5 sm:space-y-6">
                            <div>
                                <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Asistan Adı</label>
                                <input type="text" name="assistant_name" value="<?= e($settings['assistant_name']) ?>" 
                                    class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700">
                            </div>

                            <div>
                                <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 sm:mb-2.5 ml-1">Hoş Geldin Mesajı</label>
                                <textarea name="welcome_message" rows="4" 
                                    class="w-full px-4 sm:px-5 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:bg-white transition-all text-xs sm:text-sm font-bold text-slate-700 resize-none"><?= e($settings['welcome_message']) ?></textarea>
                            </div>

                            <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-2xl border border-slate-100 cursor-pointer">
                                <input type="checkbox" name="is_active" id="is_active" value="1" 
                                    <?= $settings['is_active'] ? 'checked' : '' ?> 
                                    class="w-5 h-5 rounded border-slate-300 text-[var(--primary)] focus:ring-[var(--primary)]">
                                <label for="is_active" class="text-xs sm:text-sm font-bold text-slate-700 cursor-pointer">
                                    Asistanı Aktif Et
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Selection -->
                <div class="lg:col-span-2 space-y-6 sm:space-y-8">
                    <!-- Recommended Products -->
                    <div class="glass-card p-5 sm:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b border-slate-100 gap-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fas fa-coffee"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[10px] sm:text-xs">Önerilecek Ürünler</h4>
                                    <p class="text-[9px] sm:text-[10px] text-slate-400 mt-0.5">Chat asistanı bu ürünleri önerecek</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg">
                                    <?= count($selectedProducts) ?> seçili
                                </span>
                            </div>
                        </div>

                        <!-- Search and Actions -->
                        <div class="mb-4 flex flex-col sm:flex-row gap-2">
                            <div class="flex-1 relative">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" id="productSearch" placeholder="Ürün ara..." 
                                    class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20 outline-none transition-all text-xs sm:text-sm font-medium">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" id="selectAllProducts" class="flex-1 sm:flex-none px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[10px] sm:text-xs font-bold transition-all">
                                Tümünü Seç
                            </button>
                                <button type="button" id="deselectAllProducts" class="flex-1 sm:flex-none px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[10px] sm:text-xs font-bold transition-all">
                                Temizle
                            </button>
                            </div>
                        </div>

                        <!-- Selected Products Preview -->
                        <div id="selectedProductsContainer" class="mb-4 p-3 bg-emerald-50 border border-emerald-100 rounded-xl" style="display: <?= !empty($selectedProducts) ? 'block' : 'none' ?>;">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-emerald-700">Seçili Ürünler:</span>
                                <button type="button" id="clearSelectedProducts" class="text-xs text-emerald-600 hover:text-emerald-700 font-bold">
                                    Temizle
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2" id="selectedProductsPreview">
                                <?php 
                                $selectedProductDetails = array_filter($products, function($p) use ($selectedProducts) {
                                    return in_array($p['id'], $selectedProducts);
                                });
                                foreach (array_slice($selectedProductDetails, 0, 5) as $sp): ?>
                                <span class="text-xs bg-white px-2 py-1 rounded-lg text-slate-700 font-medium border border-emerald-200">
                                    <?= e($sp['name']) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($selectedProductDetails) > 5): ?>
                                <span class="text-xs bg-white px-2 py-1 rounded-lg text-slate-500 font-medium border border-emerald-200">
                                    +<?= count($selectedProductDetails) - 5 ?> daha
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="max-h-[600px] overflow-y-auto space-y-6 pr-2" id="productList">
                            <?php foreach ($groupedProducts as $categoryName => $catProducts): ?>
                            <div class="category-group" data-category="<?= e($categoryName) ?>">
                                <div class="flex items-center gap-2 mb-3 sticky top-0 bg-white/95 backdrop-blur-sm py-2 z-10 border-b border-slate-50">
                                    <span class="w-1 h-4 bg-[var(--primary)] rounded-full"></span>
                                    <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]"><?= e($categoryName) ?></h5>
                                    <span class="text-[9px] font-bold text-slate-300 ml-auto"><?= count($catProducts) ?> Ürün</span>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($catProducts as $product): 
                                $isSelected = in_array($product['id'], $selectedProducts);
                            ?>
                                    <label class="flex items-center space-x-3 p-3 rounded-xl border <?= $isSelected ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100 hover:bg-slate-50' ?> cursor-pointer transition-all group product-item">
                                <input type="checkbox" name="recommended_products[]" value="<?= $product['id'] ?>" 
                                    <?= $isSelected ? 'checked' : '' ?>
                                    class="w-5 h-5 rounded border-slate-300 text-[var(--primary)] focus:ring-[var(--primary)] product-checkbox">
                                        <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-3">
                                        <?php if ($product['image'] && file_exists(__DIR__ . '/../assets/img/products/' . $product['image'])): ?>
                                        <img src="../assets/img/products/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" 
                                                    class="w-12 h-12 rounded-lg object-cover border border-slate-100 shadow-sm">
                                        <?php else: ?>
                                                <div class="w-12 h-12 rounded-lg bg-slate-50 flex items-center justify-center border border-slate-100">
                                                    <i class="fas fa-image text-slate-300 text-xs"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                        <p class="font-bold text-slate-800 text-[13px] truncate"><?= e($product['name']) ?></p>
                                                <?php if ($product['is_popular']): ?>
                                                        <span class="text-[8px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-black tracking-tighter">POPÜLER</span>
                                                <?php endif; ?>
                                            </div>
                                                    <p class="text-[11px] font-bold text-emerald-600 mt-0.5"><?= formatPrice($product['price']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($isSelected): ?>
                                        <i class="fas fa-check-circle text-emerald-600 text-lg check-icon"></i>
                                <?php endif; ?>
                            </label>
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

    <script>
        // Product Search
        const productSearch = document.getElementById('productSearch');
        const productList = document.getElementById('productList');
        const categoryGroups = productList.querySelectorAll('.category-group');
        const productCheckboxes = productList.querySelectorAll('.product-checkbox');

        productSearch.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            categoryGroups.forEach(group => {
                const items = group.querySelectorAll('.product-item');
                let groupHasMatch = false;
            
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                        item.style.display = 'flex';
                        groupHasMatch = true;
                } else {
                        item.style.display = 'none';
                }
        });

                // Kategori başlığını ve grubunu göster/gizle
                group.style.display = groupHasMatch ? 'block' : 'none';
            });
        });

        // Select All Products
        document.getElementById('selectAllProducts')?.addEventListener('click', () => {
            const visibleItems = Array.from(productList.querySelectorAll('.product-item')).filter(item => {
                const group = item.closest('.category-group');
                return group.style.display !== 'none' && item.style.display !== 'none';
            });
            visibleItems.forEach(item => {
                const cb = item.querySelector('.product-checkbox');
                if (cb) cb.checked = true;
            });
            updateCounts();
            updatePreviews();
        });

        // Deselect All Products
        document.getElementById('deselectAllProducts')?.addEventListener('click', () => {
            productCheckboxes.forEach(cb => cb.checked = false);
            updateCounts();
            updatePreviews();
        });

        // Clear Selected Products
        document.getElementById('clearSelectedProducts')?.addEventListener('click', () => {
            productCheckboxes.forEach(cb => cb.checked = false);
            updateCounts();
            updatePreviews();
        });

        // Update selected counts
        function updateCounts() {
            const selectedProducts = document.querySelectorAll('.product-checkbox:checked').length;
            const productCountSpan = document.querySelector('.glass-card:nth-of-type(2) .text-emerald-600');
            if (productCountSpan) {
                productCountSpan.textContent = selectedProducts + ' seçili';
            }
        }

        // Update previews
        function updatePreviews() {
            // Update product preview
            const selectedCheckboxes = Array.from(document.querySelectorAll('.product-checkbox:checked'));
            const selectedProductIds = selectedCheckboxes.map(cb => cb.value);
            const container = document.getElementById('selectedProductsContainer');
            const preview = document.getElementById('selectedProductsPreview');
            
            if (container && preview) {
                if (selectedProductIds.length > 0) {
                    container.style.display = 'block';
                    
                    // Rebuild preview content
                    preview.innerHTML = '';
                    const maxVisible = 5;
                    
                    selectedCheckboxes.slice(0, maxVisible).forEach(cb => {
                        const label = cb.closest('label');
                        const name = label.querySelector('p.font-bold').textContent;
                        
                        const span = document.createElement('span');
                        span.className = 'text-xs bg-white px-2 py-1 rounded-lg text-slate-700 font-medium border border-emerald-200';
                        span.textContent = name;
                        preview.appendChild(span);
                    });
                    
                    if (selectedProductIds.length > maxVisible) {
                        const span = document.createElement('span');
                        span.className = 'text-xs bg-white px-2 py-1 rounded-lg text-slate-500 font-medium border border-emerald-200';
                        span.textContent = `+${selectedProductIds.length - maxVisible} daha`;
                        preview.appendChild(span);
                    }
                } else {
                    container.style.display = 'none';
                }
            }

            // Update visual states of labels
            document.querySelectorAll('.product-checkbox').forEach(cb => {
                const label = cb.closest('label');
                if (cb.checked) {
                    label.classList.add('border-emerald-200', 'bg-emerald-50');
                    label.classList.remove('border-slate-100');
                    if (!label.querySelector('.fa-check-circle')) {
                        const checkIcon = document.createElement('i');
                        checkIcon.className = 'fas fa-check-circle text-emerald-600 text-lg';
                        label.appendChild(checkIcon);
                    }
                } else {
                    label.classList.remove('border-emerald-200', 'bg-emerald-50');
                    label.classList.add('border-slate-100');
                    const checkIcon = label.querySelector('.fa-check-circle');
                    if (checkIcon) checkIcon.remove();
                }
            });
        }

        productCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                updateCounts();
                updatePreviews();
            });
        });

        // Initial update
        updatePreviews();
    </script>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
