<?php
// Tüm aktif kategorileri al
$pdo = getDB();
try {
    $stmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
    $allActiveCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    // sort_order kolonu yoksa varsayılan sıralama
    $stmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $allActiveCategories = $stmt->fetchAll();
}

// Mevcut sıralamayı al (category_order kolonu opsiyonel)
$categoryOrderValue = ($editData && isset($editData['category_order'])) ? $editData['category_order'] : '';
$currentOrder = !empty($categoryOrderValue) ? (json_decode($categoryOrderValue, true) ?: []) : [];

// Varsa, category_order_array'dan da al (zaten çözülmüşse)
if (empty($currentOrder) && $editData && isset($editData['category_order_array']) && is_array($editData['category_order_array'])) {
    $currentOrder = $editData['category_order_array'];
}

// Kategorileri sıralı hazırla
$orderedCategories = [];
// 1. Önce kaydedilmiş sıraya göre diz
foreach ($currentOrder as $catId) {
    foreach ($allActiveCategories as $cat) {
        if ($cat['id'] == $catId) {
            $orderedCategories[] = $cat;
            break;
        }
    }
}
// 2. Yeni eklenen (sıralamada olmayan) kategorileri sona ekle
foreach ($allActiveCategories as $cat) {
    $found = false;
    foreach ($orderedCategories as $ordered) {
        if ($ordered['id'] == $cat['id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $orderedCategories[] = $cat;
    }
}
?>

<div class="space-y-5">
    <!-- Döngü İsmi -->
    <div>
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">Döngü İsmi <span class="text-rose-500">*</span></label>
        <div class="relative">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400">
                <i class="fas fa-tag text-xs"></i>
            </div>
            <input type="text" name="name" class="w-full pl-11 pr-4 py-3.5 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-bold text-slate-700 shadow-sm" value="<?= $editData ? e($editData['menu_name'] ?? $editData['name'] ?? '') : '' ?>" placeholder="Örn: Akşam Menüsü" required>
        </div>
    </div>

    <!-- Saatler -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">Başlangıç <span class="text-rose-500">*</span></label>
            <div class="relative">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400">
                    <i class="fas fa-clock text-[10px]"></i>
                </div>
                <input type="time" name="start_time" class="w-full pl-10 pr-3 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-bold text-slate-700 shadow-sm" value="<?= $editData ? e($editData['start_time']) : '08:00' ?>" required>
            </div>
        </div>
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">Bitiş <span class="text-rose-500">*</span></label>
            <div class="relative">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400">
                    <i class="fas fa-clock text-[10px]"></i>
                </div>
                <input type="time" name="end_time" class="w-full pl-10 pr-3 py-3 sm:py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-bold text-slate-700 shadow-sm" value="<?= $editData ? e($editData['end_time']) : '16:00' ?>" required>
            </div>
        </div>
    </div>

    <!-- Sıralama -->
    <div>
        <div class="flex items-center justify-between mb-3 px-1">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-0.5">Kategori Sıralaması</label>
                <p class="text-[9px] font-bold text-slate-400 opacity-70">Sürükleyerek sırayı değiştirin.</p>
            </div>
            <div class="w-7 h-7 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-600">
                <i class="fas fa-sort-amount-down text-[10px]"></i>
            </div>
        </div>
        
        <div class="bg-slate-50/50 rounded-[28px] p-2 sm:p-4 border border-slate-100 shadow-inner max-h-[300px] sm:max-h-[350px] overflow-y-auto">
            <ul class="category-sort-list sortable-category-list space-y-2 sm:space-y-3" id="sortList_<?= $editData ? $editData['id'] : 'new' ?>">
                <?php foreach ($orderedCategories as $cat): ?>
                <li class="bg-white p-3 sm:p-4 border border-slate-100 rounded-[20px] flex items-center gap-3 sm:gap-4 shadow-sm active:scale-[0.98] transition-transform group cursor-move hover:border-emerald-500/30" data-id="<?= $cat['id'] ?>">
                    <div class="w-8 h-8 flex items-center justify-center text-slate-300 group-hover:text-emerald-500 transition-colors">
                        <i class="fas fa-grip-vertical text-xs cursor-move"></i>
                    </div>
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center text-sm sm:text-base border border-slate-100 group-hover:bg-emerald-50 group-hover:text-emerald-500 group-hover:border-emerald-100 transition-all flex-shrink-0">
                        <i class="<?= e($cat['icon']) ?>"></i>
                    </div>
                    <span class="text-[12px] sm:text-sm font-black text-slate-700 flex-grow truncate tracking-tight"><?= e($cat['name']) ?></span>
                    <input type="hidden" name="category_order[]" value="<?= $cat['id'] ?>">
                    
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-sort text-[10px] text-slate-300"></i>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Ayarlar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center pt-2">
        <label class="flex items-center space-x-3 p-4 bg-slate-50/80 rounded-2xl border border-slate-100 cursor-pointer hover:bg-white hover:border-emerald-200 transition-all group">
            <div class="form-check form-switch m-0">
                <input type="checkbox" name="is_active" id="active_<?= $editData ? $editData['id'] : 'new' ?>" class="form-check-input scale-110 cursor-pointer" <?= (!$editData || $editData['is_active']) ? 'checked' : '' ?>>
            </div>
            <span class="text-[10px] sm:text-xs font-black text-slate-600 uppercase tracking-widest cursor-pointer group-hover:text-emerald-600 transition-colors">Döngüyü Aktifleştir</span>
        </label>
        
        <div class="flex items-center justify-between gap-4 p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
            <label class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-[0.1em]">Sistem Önceliği:</label>
            <input type="number" name="priority" class="w-14 px-2 py-1.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-xs sm:text-sm font-black text-slate-700 text-center shadow-sm" value="<?= $editData ? (int)($editData['priority'] ?? 10) : 10 ?>">
        </div>
    </div>
</div>
