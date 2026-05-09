<?php
ob_start(); // Output buffering başlat
    require_once '../includes/config.php';
requireAuth(); // Admin paneli koruması
    
    $pdo = getDB();

// Kategori Silme
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Ürün sayısını kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $productCount = $stmt->fetchColumn();
    
    if ($productCount > 0) {
        setFlash('error', 'Bu kategoride ' . $productCount . ' ürün bulunuyor. Önce ürünleri başka kategoriye taşıyın veya silin.');
    } else {
        // Silmeden önce görsel ve video dosyalarını al
        $stmt = $pdo->prepare("SELECT image, video FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$id])) {
            // Görsel dosyasını sil
            if ($category && !empty($category['image'])) {
                $imagePath = __DIR__ . '/../assets/img/categories/' . $category['image'];
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }
            
            // Video dosyasını sil
            if ($category && !empty($category['video'])) {
                $videoPath = __DIR__ . '/../assets/img/categories/' . $category['video'];
                if (file_exists($videoPath)) {
                    @unlink($videoPath);
                }
            }
            
            setFlash('success', 'Kategori başarıyla silindi.');
        }
    }
    header('Location: categories.php');
    exit;
}

// Kategori Ekleme/Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $synonyms = trim($_POST['synonyms'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fas fa-utensils');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        setFlash('error', 'Kategori adı zorunludur.');
    } else {
        // Kategori görseli yükleme
        $imageName = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $mimeNorm = normalize_uploaded_image_mime($_FILES['image']['tmp_name']);
            if ($mimeNorm) {
                $extension = extension_for_normalized_image($mimeNorm);
                $imageName = 'category-' . time() . '-' . uniqid() . '.' . $extension;
                $uploadPath = __DIR__ . '/../assets/img/categories/' . $imageName;

                if (!file_exists(__DIR__ . '/../assets/img/categories/')) {
                    mkdir(__DIR__ . '/../assets/img/categories/', 0755, true);
                }

                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    if ($action === 'edit' && isset($_POST['id']) && isset($_POST['old_image']) && !empty($_POST['old_image'])) {
                        $oldImagePath = __DIR__ . '/../assets/img/categories/' . basename((string)$_POST['old_image']);
                        if (file_exists($oldImagePath)) {
                            @unlink($oldImagePath);
                        }
                    }
                } else {
                    $imageName = isset($_POST['old_image']) ? $_POST['old_image'] : null;
                }
            } else {
                setFlash('error', 'Kategori görseli yüklenemedi: JPG, PNG, WEBP veya GIF kullanın.');
                $imageName = isset($_POST['old_image']) ? $_POST['old_image'] : null;
            }
        } else {
            // Görsel yüklenmediyse eski görseli koru (güncelleme durumunda)
            // Ama eğer görsel silme işaretlenmişse null yap
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                // Eski görseli sil
                if (isset($_POST['old_image']) && !empty($_POST['old_image'])) {
                    $oldImagePath = __DIR__ . '/../assets/img/categories/' . $_POST['old_image'];
                    if (file_exists($oldImagePath)) {
                        @unlink($oldImagePath);
                    }
                }
                $imageName = null;
            } else {
            $imageName = isset($_POST['old_image']) ? $_POST['old_image'] : null;
            }
        }
        
        // Kategori video yükleme
        $videoName = null;
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $videoMimeNorm = normalize_uploaded_video_mime($_FILES['video']['tmp_name']);
            if ($videoMimeNorm) {
                $extension = extension_for_normalized_video($videoMimeNorm);
                $videoName = 'category-video-' . time() . '-' . uniqid() . '.' . $extension;
                $uploadPath = __DIR__ . '/../assets/img/categories/' . $videoName;

                if (!file_exists(__DIR__ . '/../assets/img/categories/')) {
                    mkdir(__DIR__ . '/../assets/img/categories/', 0755, true);
                }

                if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadPath)) {
                    if ($action === 'edit' && isset($_POST['id']) && isset($_POST['old_video']) && !empty($_POST['old_video'])) {
                        $oldVideoPath = __DIR__ . '/../assets/img/categories/' . basename((string)$_POST['old_video']);
                        if (file_exists($oldVideoPath)) {
                            @unlink($oldVideoPath);
                        }
                    }
                } else {
                    $videoName = isset($_POST['old_video']) ? $_POST['old_video'] : null;
                }
            } else {
                setFlash('error', 'Kategori videosu yüklenemedi: MP4, WEBM veya OGG kullanın.');
                $videoName = isset($_POST['old_video']) ? $_POST['old_video'] : null;
            }
        } else {
            // Video yüklenmediyse eski videoyu koru (güncelleme durumunda)
            // Ama eğer video silme işaretlenmişse null yap
            if (isset($_POST['delete_video']) && $_POST['delete_video'] == '1') {
                // Eski videoyu sil
                if (isset($_POST['old_video']) && !empty($_POST['old_video'])) {
                    $oldVideoPath = __DIR__ . '/../assets/img/categories/' . $_POST['old_video'];
                    if (file_exists($oldVideoPath)) {
                        @unlink($oldVideoPath);
                    }
                }
                $videoName = null;
            } else {
                $videoName = isset($_POST['old_video']) ? $_POST['old_video'] : null;
            }
        }
        
        try {
            // Önce video ve synonyms kolonlarının var olup olmadığını kontrol et
            $checkVideo = $pdo->query("SHOW COLUMNS FROM categories LIKE 'video'");
            $videoColumnExists = $checkVideo->rowCount() > 0;
            
            $checkSynonyms = $pdo->query("SHOW COLUMNS FROM categories LIKE 'synonyms'");
            $synonymsColumnExists = $checkSynonyms->rowCount() > 0;
            
            if ($action === 'add') {
                $sql = "INSERT INTO categories (name, " . ($synonymsColumnExists ? "synonyms, " : "") . "icon, image, " . ($videoColumnExists ? "video, " : "") . "sort_order, is_active) VALUES (?, " . ($synonymsColumnExists ? "?, " : "") . "?, ?, " . ($videoColumnExists ? "?, " : "") . "?, ?)";
                $params = [$name];
                if ($synonymsColumnExists) $params[] = $synonyms;
                $params[] = $icon;
                $params[] = $imageName;
                if ($videoColumnExists) $params[] = $videoName;
                $params[] = $sort_order;
                $params[] = $is_active;
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    setFlash('success', 'Kategori başarıyla eklendi.');
                } else {
                    setFlash('error', 'Kategori eklenirken bir hata oluştu.');
                }
            } elseif ($action === 'edit' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $sql = "UPDATE categories SET name = ?, " . ($synonymsColumnExists ? "synonyms = ?, " : "") . "icon = ?, image = ?, " . ($videoColumnExists ? "video = ?, " : "") . "sort_order = ?, is_active = ? WHERE id = ?";
                $params = [$name];
                if ($synonymsColumnExists) $params[] = $synonyms;
                $params[] = $icon;
                $params[] = $imageName;
                if ($videoColumnExists) $params[] = $videoName;
                $params[] = $sort_order;
                $params[] = $is_active;
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    setFlash('success', 'Kategori başarıyla güncellendi.');
                } else {
                    setFlash('error', 'Kategori güncellenirken bir hata oluştu.');
                }
            }
        } catch (PDOException $e) {
            error_log('Category save error: ' . $e->getMessage());
            setFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }
    }
    header('Location: categories.php');
    exit;
}

// AJAX Kategori Aktif/Pasif Değiştirme
if (isset($_POST['toggle_active']) && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        $newStatus = $pdo->prepare("SELECT is_active FROM categories WHERE id = ?");
        $newStatus->execute([$id]);
        $status = $newStatus->fetchColumn();
        
        echo json_encode([
            'success' => true, 
            'is_active' => $status,
            'status_text' => $status ? 'Aktif' : 'Pasif'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Kategorileri çek - Admin panelde saate göre menü kullanma
$sql = "SELECT * FROM categories ORDER BY sort_order ASC, id ASC";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

$flash = getFlash();

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
                <h3 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Kategori Yönetimi</h3>
                <p class="text-slate-400 font-bold text-xs sm:text-sm mt-1 uppercase tracking-wider opacity-70">Menünüzdeki <?= count($categories) ?> kategoriyi yönetin.</p>
            </div>
            <button onclick="openAddModal()" class="w-full sm:w-auto bg-[var(--primary)] text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-emerald-900/20 flex items-center justify-center transition-all hover:scale-105 active:scale-95">
                <i class="fas fa-plus mr-3 opacity-60 text-sm"></i> Yeni Kategori
            </button>
        </div>

        <!-- Categories Container -->
        <div id="categoriesContainer">
            <?php if (empty($categories)): ?>
            <div class="py-20 text-center bg-white rounded-[40px] border border-slate-100 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-folder-open text-slate-200 text-5xl"></i>
                </div>
                <p class="text-slate-400 font-black text-xl tracking-tight">Henüz kategori eklenmemiş.</p>
                <button onclick="openAddModal()" class="mt-6 text-[var(--primary)] font-black text-sm uppercase tracking-widest hover:underline">İlk kategorinizi ekleyin →</button>
            </div>
            <?php else: ?>
            
            <!-- Desktop List View (Hidden on Mobile) -->
            <div class="hidden lg:block glass-card overflow-hidden border border-slate-100/50 shadow-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Kategori</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Ürün Sayısı</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Sıralama</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Durum</th>
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($categories as $cat): 
                            $hasImage = !empty($cat['image']) && file_exists(__DIR__ . '/../assets/img/categories/' . $cat['image']);
                            $imageUrl = $hasImage ? '../assets/img/categories/' . $cat['image'] : null;
                            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                            $countStmt->execute([$cat['id']]);
                            $productCount = $countStmt->fetchColumn();
                        ?>
                        <tr class="category-item hover:bg-slate-50/50 transition-colors group" data-id="<?= $cat['id'] ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[var(--primary)] to-emerald-600 overflow-hidden flex-shrink-0 shadow-sm relative group-hover:scale-110 transition-transform flex items-center justify-center">
                                        <?php if ($hasImage && $imageUrl): ?>
                                        <img src="<?= e($imageUrl) ?>" alt="<?= e($cat['name']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <i class="<?= e($cat['icon'] ?: 'fas fa-utensils') ?> text-white text-base"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="font-black text-slate-700 text-sm"><?= e($cat['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest"><i class="fas fa-box mr-2 text-emerald-500/50"></i><?= $productCount ?> Ürün</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-slate-400 tracking-widest">#<?= $cat['sort_order'] ?></td>
                            <td class="px-6 py-4">
                                <label class="inline-flex items-center cursor-pointer group category-active-toggle-wrapper" data-id="<?= $cat['id'] ?>">
                                    <input type="checkbox" class="category-active-toggle toggle-switch" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                    <span class="ml-2 status-text text-[9px] font-black uppercase tracking-widest <?= $cat['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?>">
                                        <?= $cat['is_active'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </label>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openEditModal(<?= json_encode($cat) ?>)' class="w-8 h-8 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 text-slate-400 rounded-lg flex items-center justify-center transition-all border border-transparent hover:border-emerald-100" title="Düzenle">
                                        <i class="fas fa-pen text-[10px]"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $cat['id'] ?>, '<?= e($cat['name']) ?>', <?= $productCount ?>)" class="w-8 h-8 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 rounded-lg flex items-center justify-center transition-all border border-transparent hover:border-rose-100" title="Sil">
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
            <div class="space-y-3 lg:hidden">
            <?php foreach ($categories as $index => $cat):
                $hasImage = !empty($cat['image']);
                $imageUrl = $hasImage ? '../assets/img/categories/' . $cat['image'] : null;
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $countStmt->execute([$cat['id']]);
                $productCount = $countStmt->fetchColumn();
            ?>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm category-item" data-id="<?= $cat['id'] ?>">
                    <!-- Top row: image + info + buttons -->
                    <div class="flex items-center gap-3 p-3">
                        <!-- Görsel -->
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-[var(--primary)] to-emerald-600 overflow-hidden flex-shrink-0 flex items-center justify-center">
                            <?php if ($hasImage && $imageUrl): ?>
                            <img src="<?= e($imageUrl) ?>" alt="<?= e($cat['name']) ?>" class="w-full h-full object-cover" onerror="this.style.display='none'">
                            <?php else: ?>
                            <i class="<?= e($cat['icon'] ?: 'fas fa-utensils') ?> text-white text-xl"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Bilgi -->
                        <div class="flex-1 min-w-0">
                            <h4 class="font-black text-slate-800 text-base leading-tight mb-1 truncate"><?= e($cat['name']) ?></h4>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <i class="fas fa-box mr-1 text-emerald-400"></i><?= $productCount ?> Ürün
                            </p>
                            <label class="inline-flex items-center cursor-pointer mt-1 category-active-toggle-wrapper" data-id="<?= $cat['id'] ?>">
                                <input type="checkbox" class="category-active-toggle toggle-switch" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                <span class="ml-2 status-text text-[9px] font-black uppercase tracking-widest <?= $cat['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?>">
                                    <?= $cat['is_active'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </label>
                        </div>

                        <!-- Butonlar -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button onclick='openEditModal(<?= json_encode($cat) ?>)'
                                class="w-9 h-9 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                                <i class="fas fa-pen text-[11px]"></i>
                            </button>
                            <button onclick="confirmDelete(<?= $cat['id'] ?>, '<?= e($cat['name']) ?>', <?= $productCount ?>)"
                                class="w-9 h-9 bg-slate-50 hover:bg-rose-50 hover:text-rose-500 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                                <i class="fas fa-trash text-[11px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[40px] p-6 sm:p-8 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto animate-scaleUp">
            <h3 id="modalTitle" class="text-2xl font-black text-slate-800 mb-6 tracking-tight">Yeni Kategori Ekle</h3>
            
            <form id="categoryForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                <input type="hidden" name="old_image" id="formOldImage" value="">
                <input type="hidden" name="old_video" id="formOldVideo" value="">
                <input type="hidden" name="delete_image" id="formDeleteImage" value="0">
                <input type="hidden" name="delete_video" id="formDeleteVideo" value="0">
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Kategori Görseli</label>
                        <div class="relative group">
                            <div id="imagePreview" class="hidden w-full h-40 rounded-3xl overflow-hidden bg-slate-50 mb-3 border border-slate-100 relative group/image">
                                <img id="previewImg" src="" alt="Önizleme" class="w-full h-full object-cover">
                                <button type="button" onclick="deleteImage()" class="absolute top-3 right-3 bg-rose-500 text-white rounded-xl w-10 h-10 flex items-center justify-center shadow-lg hover:bg-rose-600 transition-all">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="imageDeleteInfo" class="hidden mb-3 p-4 bg-rose-50 border border-rose-100 rounded-2xl">
                                <p class="text-rose-600 text-[10px] font-black uppercase tracking-widest flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Mevcut Görsel Silinecek
                                </p>
                            </div>
                            <label for="formImage" class="cursor-pointer block">
                                <div class="w-full px-5 py-8 bg-slate-50 border-2 border-dashed border-slate-200 rounded-[30px] hover:border-emerald-500 hover:bg-emerald-50/30 transition-all text-center group">
                                    <i class="fas fa-image text-slate-300 text-3xl mb-3 group-hover:text-emerald-500 transition-colors"></i>
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Görsel Seç</p>
                                    <p class="text-[9px] text-slate-400 mt-1 font-bold">JPG, PNG, WEBP (İdeal: 800×920px)</p>
                                </div>
                                <input type="file" name="image" id="formImage" accept="image/jpeg,image/png,image/webp,image/jpg" class="hidden" onchange="previewImage(this)">
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Video Arka Plan (Opsiyonel)</label>
                        <div class="relative group">
                            <div id="videoPreview" class="hidden w-full h-40 rounded-3xl overflow-hidden bg-slate-50 mb-3 border border-slate-100 relative group/video">
                                <video id="previewVideo" src="" class="w-full h-full object-cover" muted loop></video>
                                <button type="button" onclick="deleteVideo()" class="absolute top-3 right-3 bg-rose-500 text-white rounded-xl w-10 h-10 flex items-center justify-center shadow-lg hover:bg-rose-600 transition-all">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="videoDeleteInfo" class="hidden mb-3 p-4 bg-rose-50 border border-rose-100 rounded-2xl">
                                <p class="text-rose-600 text-[10px] font-black uppercase tracking-widest flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Mevcut Video Silinecek
                                </p>
                            </div>
                            <label for="formVideo" class="cursor-pointer block">
                                <div class="w-full px-5 py-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-[30px] hover:border-emerald-500 hover:bg-emerald-50/30 transition-all text-center group">
                                    <i class="fas fa-video text-slate-300 text-2xl mb-2 group-hover:text-emerald-500 transition-colors"></i>
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Video Seç</p>
                                    <p class="text-[9px] text-slate-400 mt-1 font-bold">MP4, WEBM (Maks 10MB)</p>
                                </div>
                                <input type="file" name="video" id="formVideo" accept="video/mp4,video/webm,video/ogg" class="hidden" onchange="previewVideo(this)">
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Kategori Adı *</label>
                        <input type="text" name="name" id="formName" required placeholder="Örn: Kahveler" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-black text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Eş Anlamlı Kelimeler (Chatbot İçin)</label>
                        <textarea name="synonyms" id="formSynonyms" rows="2" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-medium text-slate-600" placeholder="Örn: kahve, coffee, espresso, americano (Virgülle ayırın)"></textarea>
                        <p class="mt-2 text-[9px] font-bold text-slate-400 uppercase tracking-wider ml-1 italic">* Kullanıcı bu kelimeleri yazdığında bu kategori önerilir.</p>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Hızlı İkon</label>
                        <div class="grid grid-cols-6 gap-2 mb-4">
                            <?php 
                            $icons = ['fas fa-coffee', 'fas fa-glass-whiskey', 'fas fa-birthday-cake', 'fas fa-hamburger', 'fas fa-ice-cream', 'fas fa-pizza-slice', 'fas fa-wine-glass-alt', 'fas fa-cookie-bite', 'fas fa-apple-alt', 'fas fa-leaf', 'fas fa-mug-hot', 'fas fa-utensils'];
                            foreach($icons as $icon): ?>
                            <button type="button" onclick="selectIcon('<?= $icon ?>')" class="icon-btn aspect-square bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 hover:bg-emerald-500 hover:text-white transition-all text-base border border-slate-100">
                                <i class="<?= $icon ?>"></i>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="icon" id="formIcon" value="fas fa-utensils" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-400">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 opacity-70">Sıralama</label>
                            <input type="number" name="sort_order" id="formSortOrder" value="0" min="0" class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:bg-white focus:border-emerald-500 transition-all text-sm font-black text-slate-700">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center justify-between px-6 py-4 bg-slate-50 rounded-2xl border border-slate-100 cursor-pointer w-full group">
                                <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Aktif</span>
                                <input type="checkbox" name="is_active" id="formIsActive" class="toggle-switch" checked>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-10">
                    <button type="button" onclick="closeModal()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all">Vazgeç</button>
                    <button type="submit" class="flex-1 py-4 bg-emerald-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-emerald-600 shadow-lg shadow-emerald-900/20 transition-all">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[40px] p-8 max-w-md w-full shadow-2xl animate-scaleUp">
            <div class="w-20 h-20 bg-rose-50 rounded-[30px] flex items-center justify-center mx-auto mb-6 border border-rose-100">
                <i class="fas fa-trash text-rose-500 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800 text-center mb-2 tracking-tight">Kategoriyi Sil</h3>
            <p class="text-slate-400 font-bold text-sm text-center mb-8 px-4">
                <span id="deleteCategoryName" class="text-slate-700"></span> kategorisini silmek istediğinize emin misiniz?
            </p>
            <p id="deleteWarning" class="hidden mb-8 p-4 bg-rose-50 border border-rose-100 rounded-2xl text-rose-600 text-[10px] font-black uppercase tracking-widest text-center animate-pulse"></p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all">Vazgeç</button>
                <a id="deleteLink" href="#" class="flex-1 py-4 bg-rose-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest text-center hover:bg-rose-600 shadow-lg shadow-rose-900/20 transition-all">Evet, Sil</a>
            </div>
        </div>
    </div>

    <style>
    @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .animate-scaleUp { animation: scaleUp 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    
    .icon-btn.selected { background: #10b981 !important; color: white !important; border-color: #10b981 !important; }
    </style>

    <script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Yeni Kategori Ekle';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formSynonyms').value = '';
        document.getElementById('formIcon').value = 'fas fa-utensils';
        document.getElementById('formSortOrder').value = '0';
        document.getElementById('formIsActive').checked = true;
        document.getElementById('formOldImage').value = '';
        document.getElementById('formOldVideo').value = '';
        document.getElementById('formDeleteImage').value = '0';
        document.getElementById('formDeleteVideo').value = '0';
        document.getElementById('imagePreview').classList.add('hidden');
        document.getElementById('imageDeleteInfo').classList.add('hidden');
        document.getElementById('videoPreview').classList.add('hidden');
        document.getElementById('videoDeleteInfo').classList.add('hidden');
        document.getElementById('formImage').value = '';
        document.getElementById('formVideo').value = '';
        updateIconSelection('fas fa-utensils');
        document.getElementById('categoryModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openEditModal(cat) {
        document.getElementById('modalTitle').textContent = 'Kategori Düzenle';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = cat.id;
        document.getElementById('formName').value = cat.name;
        document.getElementById('formSynonyms').value = cat.synonyms || '';
        document.getElementById('formIcon').value = cat.icon || 'fas fa-utensils';
        document.getElementById('formSortOrder').value = cat.sort_order || 0;
        document.getElementById('formIsActive').checked = cat.is_active == 1;
        document.getElementById('formOldImage').value = cat.image || '';
        document.getElementById('formOldVideo').value = cat.video || '';
        document.getElementById('formImage').value = '';
        document.getElementById('formVideo').value = '';
        
        if (cat.image) {
            document.getElementById('previewImg').src = '../assets/img/categories/' + cat.image;
            document.getElementById('imagePreview').classList.remove('hidden');
        } else {
            document.getElementById('imagePreview').classList.add('hidden');
        }
        
        if (cat.video) {
            document.getElementById('previewVideo').src = '../assets/img/categories/' + cat.video;
            document.getElementById('videoPreview').classList.remove('hidden');
            document.getElementById('previewVideo').load();
        } else {
            document.getElementById('videoPreview').classList.add('hidden');
        }
        
        document.getElementById('formDeleteImage').value = '0';
        document.getElementById('imageDeleteInfo').classList.add('hidden');
        document.getElementById('formDeleteVideo').value = '0';
        document.getElementById('videoDeleteInfo').classList.add('hidden');
        
        updateIconSelection(cat.icon || 'fas fa-utensils');
        document.getElementById('categoryModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        if (input.files && input.files[0]) {
            document.getElementById('formDeleteImage').value = '0';
            document.getElementById('imageDeleteInfo').classList.add('hidden');
            const reader = new FileReader();
            reader.onload = e => { previewImg.src = e.target.result; preview.classList.remove('hidden'); };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function deleteImage() {
        if (confirm('Görseli silmek istediğinize emin misiniz?')) {
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('formImage').value = '';
            document.getElementById('formDeleteImage').value = '1';
            document.getElementById('imageDeleteInfo').classList.remove('hidden');
        }
    }
    
    function previewVideo(input) {
        const preview = document.getElementById('videoPreview');
        const previewVideo = document.getElementById('previewVideo');
        if (input.files && input.files[0]) {
            if (input.files[0].size > 10 * 1024 * 1024) { alert('Video dosyası maksimum 10MB olabilir!'); input.value = ''; return; }
            document.getElementById('formDeleteVideo').value = '0';
            document.getElementById('videoDeleteInfo').classList.add('hidden');
            const reader = new FileReader();
            reader.onload = e => { previewVideo.src = e.target.result; preview.classList.remove('hidden'); previewVideo.load(); };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function deleteVideo() {
        if (confirm('Videoyu silmek istediğinize emin misiniz?')) {
            document.getElementById('videoPreview').classList.add('hidden');
            document.getElementById('formVideo').value = '';
            document.getElementById('formDeleteVideo').value = '1';
            document.getElementById('videoDeleteInfo').classList.remove('hidden');
        }
    }

    function closeModal() {
        document.getElementById('categoryModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function selectIcon(icon) {
        document.getElementById('formIcon').value = icon;
        updateIconSelection(icon);
    }

    function updateIconSelection(selectedIcon) {
        document.querySelectorAll('.icon-btn').forEach(btn => {
            if (btn.querySelector('i').className === selectedIcon) btn.classList.add('selected');
            else btn.classList.remove('selected');
        });
    }

    function confirmDelete(id, name, productCount) {
        document.getElementById('deleteCategoryName').textContent = name;
        document.getElementById('deleteLink').href = 'categories.php?delete=' + id;
        const warning = document.getElementById('deleteWarning');
        if (productCount > 0) {
            warning.textContent = 'Bu kategoride ' + productCount + ' ürün var. Önce ürünleri taşıyın.';
            warning.classList.remove('hidden');
            document.getElementById('deleteLink').classList.add('opacity-50', 'pointer-events-none');
        } else {
            warning.classList.add('hidden');
            document.getElementById('deleteLink').classList.remove('opacity-50', 'pointer-events-none');
        }
        document.getElementById('deleteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.getElementById('categoryModal').addEventListener('click', e => { if (e.target === document.getElementById('categoryModal')) closeModal(); });
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === document.getElementById('deleteModal')) closeDeleteModal(); });
    
    // Kategori Aktif/Pasif Toggle
    document.querySelectorAll('.category-active-toggle-wrapper').forEach(wrapper => {
        const checkbox = wrapper.querySelector('.category-active-toggle');
        const statusText = wrapper.querySelector('.status-text');
        const categoryId = wrapper.dataset.id;

        checkbox.addEventListener('change', async function() {
            const originalChecked = this.checked;
            this.disabled = true;

            try {
        const formData = new FormData();
                formData.append('toggle_active', '1');
                formData.append('id', categoryId);
        
                const response = await fetch('categories', {
            method: 'POST',
            body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();

                if (data.success) {
                    statusText.textContent = data.status_text;
                    statusText.className = 'ml-2 status-text text-[9px] font-black uppercase tracking-widest ' + 
                        (data.is_active ? 'text-emerald-600' : 'text-slate-400');
                } else {
                    this.checked = !originalChecked;
                    alert('Hata: ' + (data.error || 'Durum güncellenemedi'));
                }
            } catch (error) {
                this.checked = !originalChecked;
                console.error('Toggle error:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            } finally {
                this.disabled = false;
            }
        });
    });
    </script>

    </div><!-- Close Main Content Div from Header -->
</body>
</html>
