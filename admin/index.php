<?php
require_once '../includes/config.php';
requireAuth();

$pdo = getDB();

// İstatistikleri hesapla
try {
    // Toplam aktif ürün sayısı
    $totalProductsStmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $totalProducts = $totalProductsStmt->fetch()['total'] ?? 0;
    
    // Son 7 gün içinde eklenen ürün sayısı
    $newProductsStmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newProducts = $newProductsStmt->fetch()['total'] ?? 0;
    
    // Toplam aktif kategori sayısı
    $totalCategoriesStmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1");
    $totalCategories = $totalCategoriesStmt->fetch()['total'] ?? 0;
    
    // Toplam aktif dil sayısı
    $totalLangsStmt = $pdo->query("SELECT COUNT(*) as total FROM languages WHERE is_active = 1");
    $totalLangs = $totalLangsStmt->fetch()['total'] ?? 0;
    
    // Son eklenen 10 ürünü getir (Görselde daha fazla ürün var)
    $recentProductsStmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    $recentProducts = $recentProductsStmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Dashboard Stats Error: ' . $e->getMessage());
    $totalProducts = 0;
    $newProducts = 0;
    $totalCategories = 0;
    $recentProducts = [];
}

// Admin adını al
$adminName = getSetting('admin_name', 'Admin');
if (empty($adminName) || $adminName === 'Admin') {
    $adminName = $_SESSION['admin_username'] ?? 'Yönetici';
}
$displayName = ucfirst(explode(' ', $adminName)[0]) . ' Bey';

include 'header.php';
?>

<style>
    .stat-card {
        background: white;
        padding: 2rem;
        border-radius: 2rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        border-color: var(--primary);
    }
    .recent-product-item {
        background: white;
        padding: 1.25rem;
        border-radius: 1.5rem;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }
    .recent-product-item:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
    }
</style>

<main class="p-4 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">
        <!-- Welcome Header -->
    <div class="mb-8 sm:mb-12">
        <h3 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Hoş Geldiniz, <?= e($displayName) ?> 👋</h3>
        <p class="text-slate-400 font-bold text-xs sm:text-sm mt-1 uppercase tracking-widest opacity-70">İşletmenizin güncel durumu ve hızlı özetleri aşağıdadır.</p>
        </div>

        <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <!-- Toplam Ürün -->
        <div class="stat-card group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center transition-transform group-hover:rotate-12">
                    <i class="fas fa-utensils text-2xl"></i>
                    </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Canlı</span>
                </div>
            </div>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Toplam Ürün</p>
            <div class="flex items-end gap-3 mt-1">
                <p class="text-4xl font-black text-slate-800 tracking-tighter"><?= number_format($totalProducts) ?></p>
                    <?php if ($newProducts > 0): ?>
                <div class="mb-2 px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-lg text-[10px] font-black tracking-wide">+<?= $newProducts ?> Yeni</div>
                    <?php endif; ?>
            </div>
        </div>

        <!-- Kategori -->
        <div class="stat-card group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center transition-transform group-hover:-rotate-12">
                    <i class="fas fa-tags text-2xl"></i>
                </div>
            </div>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Kategori</p>
            <p class="text-4xl font-black text-slate-800 mt-1 tracking-tighter"><?= number_format($totalCategories) ?></p>
                    </div>

        <!-- Diller -->
        <div class="stat-card group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-14 h-14 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center transition-transform group-hover:rotate-12">
                    <i class="fas fa-language text-2xl"></i>
                </div>
            </div>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Aktif Diller</p>
            <p class="text-4xl font-black text-slate-800 mt-1 tracking-tighter"><?= number_format($totalLangs) ?></p>
                    </div>

        <!-- Aktif Ürünler -->
        <div class="stat-card group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
                <span class="text-[9px] font-black text-indigo-500 bg-indigo-50 px-3 py-1.5 rounded-xl uppercase tracking-widest">Aktif</span>
            </div>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Aktif Ürünler</p>
            <p class="text-4xl font-black text-slate-800 mt-1 tracking-tighter"><?= number_format($totalProducts) ?></p>
            </div>
        </div>

            <!-- Recent Activity -->
    <div class="glass-card overflow-hidden">
        <div class="p-6 sm:p-10 border-b border-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h3 class="font-black text-slate-800 text-xl tracking-tight">Son Eklenen Ürünler</h3>
            <a href="products" class="text-[10px] font-black text-slate-400 hover:text-[var(--primary)] transition-all uppercase tracking-[0.2em] flex items-center group">
                Tümünü Yönet 
                <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
        
        <div class="p-6 sm:p-10">
                <div class="space-y-4">
                    <?php if (empty($recentProducts)): ?>
                <div class="text-center py-20 bg-slate-50/50 rounded-[40px] border border-dashed border-slate-200">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                        <i class="fas fa-box-open text-slate-200 text-3xl"></i>
                        </div>
                    <p class="text-slate-400 font-black text-sm uppercase tracking-widest">Henüz ürün eklenmemiş</p>
                    <a href="add-product" class="mt-4 inline-block text-[10px] font-black text-[var(--primary)] uppercase tracking-[0.2em] hover:underline">İlk ürününüzü ekleyin →</a>
                    </div>
                    <?php else: ?>
                        <?php foreach ($recentProducts as $product): 
                            $imagePath = null;
                            $hasImage = false;
                            if ($product['image']) {
                                $imagePath = '../assets/img/products/' . $product['image'];
                                $hasImage = file_exists($imagePath);
                            }
                            $categoryName = $product['category_name'] ?? 'Kategori Yok';
                        ?>
                    <div class="recent-product-item flex items-center justify-between group">
                        <div class="flex items-center gap-5 flex-1 min-w-0">
                            <!-- Image Thumb -->
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-[20px] overflow-hidden shadow-sm border border-slate-100 bg-slate-50 flex-shrink-0 group-hover:scale-105 transition-transform">
                                    <?php if ($hasImage): ?>
                                    <img src="<?= $imagePath ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-image text-slate-200 text-xl"></i>
                                    </div>
                                    <?php endif; ?>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="flex-1 min-w-0">
                                <h4 class="font-black text-slate-800 text-sm sm:text-base tracking-tight truncate mb-1"><?= e($product['name']) ?></h4>
                                <div class="flex items-center flex-wrap gap-2 sm:gap-3">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?= e($categoryName) ?></span>
                                    <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                    <span class="text-[10px] font-black text-emerald-600 tracking-wider"><?= formatPrice($product['price']) ?></span>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <?php if ($product['is_active']): ?>
                                    <span class="text-[8px] font-black bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded uppercase tracking-widest">Aktif</span>
                                    <?php else: ?>
                                    <span class="text-[8px] font-black bg-slate-50 text-slate-400 px-2 py-0.5 rounded uppercase tracking-widest">Pasif</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['is_popular']): ?>
                                    <span class="text-[8px] font-black bg-amber-50 text-amber-600 px-2 py-0.5 rounded uppercase tracking-widest">Popüler</span>
                                    <?php endif; ?>
                                </div>
                </div>
            </div>

                        <!-- Action -->
                        <div class="flex items-center gap-2 ml-4">
                            <a href="edit-product?id=<?= $product['id'] ?>" class="w-10 h-10 sm:w-12 sm:h-12 bg-white hover:bg-emerald-50 hover:text-emerald-600 text-slate-400 rounded-2xl flex items-center justify-center transition-all border border-slate-100 shadow-sm hover:border-emerald-100">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
