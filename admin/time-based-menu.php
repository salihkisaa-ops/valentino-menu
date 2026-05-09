<?php
require_once '../includes/config.php';
requireAuth(); // Admin paneli koruması

$pdo = getDB();
$success = '';
$error = '';

// Tüm kategorileri al (sıralama için)
try {
    $categoriesStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $allCategories = $categoriesStmt->fetchAll();
} catch (PDOException $e) {
    // sort_order kolonu yoksa ismle sırala
    $categoriesStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $allCategories = $categoriesStmt->fetchAll();
}

// Silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM time_based_menu WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Zaman dilimi silindi.";
    } else {
        $error = "Silme işlemi başarısız.";
    }
    header("Location: time-based-menu.php?success=" . urlencode($success));
    exit;
}

// Aktif/Pasif değiştirme
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE time_based_menu SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: time-based-menu.php");
    exit;
}

// Yeni ekleme / Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    // category_order artık formdan direkt dizi olarak geliyor
    $category_order = $_POST['category_order'] ?? [];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $priority = (int)($_POST['priority'] ?? 10);
    // Varsayılan olarak tüm günler aktif (arka planda)
    $days_of_week = '0,1,2,3,4,5,6';

    if (empty($name) || empty($start_time) || empty($end_time)) {
        $error = "Lütfen tüm zorunlu alanları doldurun.";
    } else {
        // category_order formdan dizi olarak geliyor
        $category_order = $_POST['category_order'] ?? [];
        $categoryOrderJson = json_encode(array_values(array_map('intval', $category_order)));
        
        if ($id > 0) {
            // menu_name kolonunu kullan (veritabanındaki gerçek kolon adı)
            $stmt = $pdo->prepare("UPDATE time_based_menu SET menu_name = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
            $result = $stmt->execute([$name, $start_time, $end_time, $is_active, $id]);
            $success = $result ? "Zaman dilimi güncellendi." : "Güncelleme başarısız.";
        } else {
            // menu_name kolonunu kullan, category_order, days_of_week ve priority kolonları yoksa onları kaldır
            $stmt = $pdo->prepare("INSERT INTO time_based_menu (menu_name, start_time, end_time, is_active) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$name, $start_time, $end_time, $is_active]);
            $success = $result ? "Yeni zaman dilimi eklendi." : "Ekleme başarısız.";
        }
    }
}

// Düzenleme için veri çek
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM time_based_menu WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
    if ($editData) {
        // category_order kolonu varsa kullan, yoksa boş dizi
        $categoryOrderValue = $editData['category_order'] ?? '';
        $editData['category_order_array'] = !empty($categoryOrderValue) ? (json_decode($categoryOrderValue, true) ?: []) : [];
    }
}

// Tüm zaman dilimlerini listele
// Priority kolonu yoksa hata vermemesi için ORDER BY düzeltildi
$stmt = $pdo->query("SELECT * FROM time_based_menu ORDER BY start_time ASC, id DESC");
$timeSlots = $stmt->fetchAll();

$pageTitle = 'Saate Göre Menü Düzeni';
include 'header.php';
?>

<style>
.time-slot-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid rgba(78, 54, 41, 0.08);
    transition: all 0.2s ease;
}
.time-slot-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.time-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, rgba(78, 54, 41, 0.08), rgba(197, 160, 89, 0.12));
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    color: #4E3629;
}
.category-sort-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.category-sort-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: move;
    transition: all 0.2s ease;
}
.category-sort-item:hover {
    background: #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.category-sort-item.dragging {
    opacity: 0.5;
}
.drag-handle {
    font-size: 18px;
    color: #6c757d;
    cursor: grab;
}
.drag-handle:active {
    cursor: grabbing;
}
.day-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.day-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 13px;
    font-weight: 600;
}
.day-checkbox-label:hover {
    background: #e9ecef;
}
.day-checkbox-label input:checked + span {
    color: #4E3629;
}
.day-checkbox-label input:checked ~ * {
    border-color: #c5a059;
    background: rgba(197, 160, 89, 0.1);
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-fadeIn { animation: fadeIn 0.3s ease-out forwards; }
</style>

    <main class="p-3 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-10 gap-4">
            <div class="w-full sm:w-auto">
                <h3 class="text-xl sm:text-3xl font-black text-slate-800 tracking-tight">Saate Göre Menü</h3>
                <p class="text-slate-400 font-medium text-[10px] sm:text-sm mt-1 uppercase tracking-wider">Kategori akışını otomatiğe bağlayın.</p>
            </div>
            <button class="w-full sm:w-auto bg-[var(--primary)] text-white px-6 py-4 sm:py-4 rounded-2xl font-black text-[11px] sm:text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/20 flex items-center justify-center transition-all hover:scale-[1.02] active:scale-95" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus mr-2 sm:mr-3 opacity-60 text-sm"></i> Yeni Ekle
            </button>
        </div>

        <?php if ($success): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center animate-fadeIn">
            <i class="fas fa-check-circle mr-3"></i>
            <span class="font-bold text-xs sm:text-sm"><?= e($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100 flex items-center animate-fadeIn">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <span class="font-bold text-xs sm:text-sm"><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Mevcut Zaman Dilimleri Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
            <?php if (empty($timeSlots)): ?>
            <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-slate-200 text-4xl"></i>
                </div>
                <p class="text-slate-400 font-bold text-lg">Henüz zaman dilimi eklenmemiş.</p>
                <button class="mt-4 text-[var(--primary)] font-bold text-sm hover:underline" data-bs-toggle="modal" data-bs-target="#addModal">İlk zaman diliminizi ekleyin →</button>
            </div>
            <?php else: ?>
            <?php foreach ($timeSlots as $slot): 
                $categoryOrderValue = $slot['category_order'] ?? '';
                $categoryOrderArray = !empty($categoryOrderValue) ? (json_decode($categoryOrderValue, true) ?: []) : [];
            ?>
            <div class="time-slot-card flex flex-col h-full bg-white p-5 sm:p-8 rounded-[32px] border border-slate-100 shadow-sm hover:shadow-xl hover:border-emerald-100 transition-all duration-300">
                <div class="flex justify-between items-start mb-5">
                    <div class="flex-grow pr-4">
                        <h4 class="font-black text-slate-800 text-lg sm:text-xl mb-2 leading-tight"><?= e($slot['menu_name']) ?></h4>
                        <div class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-full text-[10px] sm:text-xs font-black tracking-wide border border-emerald-100/50">
                            <i class="fas fa-clock mr-2 opacity-70"></i>
                            <?= date('H:i', strtotime($slot['start_time'])) ?> – <?= date('H:i', strtotime($slot['end_time'])) ?>
                        </div>
                    </div>
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input scale-125 cursor-pointer shadow-none" type="checkbox" <?= $slot['is_active'] ? 'checked' : '' ?> 
                               onchange="window.location.href='?toggle=<?= $slot['id'] ?>'">
                    </div>
                </div>

                <div class="mb-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-[9px] sm:text-[10px] font-extrabold text-slate-400 uppercase tracking-[0.2em]">Kategori Akışı</p>
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-300 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-100"><?= count($categoryOrderArray) ?> Öge</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <?php if (empty($categoryOrderArray)): ?>
                            <div class="col-span-full py-6 px-6 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 text-center">
                                <span class="text-[10px] sm:text-xs font-bold text-amber-600/60 uppercase tracking-widest italic">Sıralama belirlenmemiş</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categoryOrderArray as $idx => $catId): 
                                $cat = array_filter($allCategories, fn($c) => $c['id'] == $catId);
                                $cat = reset($cat);
                                if ($cat):
                            ?>
                            <div class="flex items-center p-2 sm:p-3 bg-slate-50/50 hover:bg-white hover:shadow-md border border-slate-100 rounded-2xl transition-all group overflow-hidden">
                                <div class="w-6 h-6 sm:w-7 sm:h-7 bg-white rounded-xl flex items-center justify-center text-[9px] sm:text-[11px] font-black text-slate-400 border border-slate-100 mr-2 sm:mr-3 group-hover:border-emerald-200 group-hover:text-emerald-500 transition-colors flex-shrink-0">
                                    <?= $idx + 1 ?>
                                </div>
                                <span class="text-[10px] sm:text-xs font-bold text-slate-600 truncate"><?= e($cat['name']) ?></span>
                            </div>
                            <?php endif; endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-50 mt-auto">
                    <div class="flex gap-2">
                        <button class="flex-1 bg-slate-50 hover:bg-[var(--primary)] hover:text-white text-slate-600 py-4 rounded-2xl font-black text-[11px] sm:text-xs uppercase tracking-widest flex items-center justify-center transition-all duration-300 shadow-sm hover:shadow-lg hover:shadow-emerald-900/20" data-bs-toggle="modal" data-bs-target="#editModal<?= $slot['id'] ?>">
                            <i class="fas fa-sliders-h mr-2.5 text-sm opacity-70"></i> Yapılandır
                        </button>
                        <button onclick="if(confirm('Bu zaman dilimini silmek istediğinize emin misiniz?')) window.location.href='?delete=<?= $slot['id'] ?>'" class="w-14 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded-2xl flex items-center justify-center transition-all duration-300 border border-rose-100 hover:border-rose-500">
                            <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Edit Modal (Mobil Uyumlu) -->
            <div class="modal fade" id="editModal<?= $slot['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered p-4">
                    <div class="modal-content border-0 rounded-[32px] overflow-hidden shadow-2xl">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                            <div class="modal-header border-0 p-6 sm:p-8 pb-0 flex items-center justify-between">
                                <h5 class="text-xl font-extrabold text-slate-800 tracking-tight">Zaman Dilimini Düzenle</h5>
                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-6 sm:p-8 max-h-[70vh] overflow-y-auto">
                                <?php 
                                $editData = $slot;
                                $editData['category_order_array'] = $categoryOrderArray;
                                include '_time_based_menu_form.php'; 
                                ?>
                            </div>
                            <div class="modal-footer border-0 p-6 sm:p-8 pt-0 flex gap-3">
                                <button type="button" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold text-sm" data-bs-dismiss="modal">İptal</button>
                                <button type="submit" class="flex-1 py-4 bg-[var(--primary)] text-white rounded-2xl font-bold text-sm shadow-xl shadow-emerald-900/10">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Modal (Mobil Uyumlu) -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered p-4">
            <div class="modal-content border-0 rounded-[32px] overflow-hidden shadow-2xl">
                <form method="POST">
                    <div class="modal-header border-0 p-6 sm:p-8 pb-0 flex items-center justify-between">
                        <h5 class="text-xl font-extrabold text-slate-800 tracking-tight">Yeni Zaman Dilimi Ekle</h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-6 sm:p-8 max-h-[70vh] overflow-y-auto">
                        <?php 
                        $editData = null;
                        include '_time_based_menu_form.php'; 
                        ?>
                    </div>
                    <div class="modal-footer border-0 p-6 sm:p-8 pt-0 flex gap-3">
                        <button type="button" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold text-sm" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="flex-1 py-4 bg-[var(--primary)] text-white rounded-2xl font-bold text-sm shadow-xl shadow-emerald-900/10">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sortable başlatma fonksiyonu
    function initTimeBasedSortable() {
        document.querySelectorAll('.sortable-category-list').forEach(el => {
            if (el.dataset.initialized) return;
            
            new Sortable(el, {
                animation: 150,
                handle: '.fas.fa-grip-vertical',
                ghostClass: 'dragging',
                forceFallback: true // Mobil cihazlarda daha stabil çalışır
            });
            
            el.dataset.initialized = "true";
        });
    }

    // İlk yüklemede çalıştır
    initTimeBasedSortable();

    // Modallar açıldığında (hem Ekle hem Düzenle için) tekrar tetikle
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            initTimeBasedSortable();
        });
    });
});
</script>

<?php include 'footer.php'; ?>
