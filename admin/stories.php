<?php
require_once '../includes/config.php';
requireAuth();

$flash = null;

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
        $stmt->execute([$deleteId]);
        setFlash('success', 'Story silindi.');
    } catch (Exception $e) {
        setFlash('error', 'Silinirken hata: ' . $e->getMessage());
    }
    header('Location: stories.php');
    exit;
}

// Kaydet (Ekle/Güncelle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_story'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $cover_url = trim($_POST['cover_url'] ?? '');
    $media_url = trim($_POST['media_url'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 1);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '' || $cover_url === '' || $media_url === '') {
        setFlash('error', 'Başlık, kapak ve medya alanları zorunludur.');
    } else {
        try {
            $pdo = getDB();
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE stories SET title = ?, cover_url = ?, media_url = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $cover_url, $media_url, $sort_order, $is_active, $id]);
                setFlash('success', 'Story güncellendi.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO stories (title, cover_url, media_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $cover_url, $media_url, $sort_order, $is_active]);
                setFlash('success', 'Story eklendi.');
            }
        } catch (Exception $e) {
            setFlash('error', 'Kayıt sırasında hata: ' . $e->getMessage());
        }
    }
    header('Location: stories.php');
    exit;
}

// Liste
$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM stories ORDER BY sort_order ASC, created_at DESC");
$stories = $stmt->fetchAll();

include 'header.php';
$flash = getFlash();
?>

<main class="p-8 lg:p-12 max-w-6xl mx-auto w-full space-y-8">
    <?php if ($flash): ?>
    <div class="p-4 rounded-2xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
        <div class="flex items-center">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
            <span class="font-bold text-sm"><?= e($flash['message']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="glass-card p-6">
        <h3 class="text-xl font-extrabold text-slate-800 mb-4">Story Ekle / Düzenle</h3>
        <form method="POST" class="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="id" id="storyId" value="">
            <div class="sm:col-span-2">
                <label class="text-xs font-bold text-slate-500">Başlık *</label>
                <input type="text" name="title" id="storyTitle" class="w-full mt-1 px-4 py-3 border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Kapak (görsel URL) *</label>
                <input type="text" name="cover_url" id="storyCover" class="w-full mt-1 px-4 py-3 border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]" required placeholder="https://...">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Medya (video URL) *</label>
                <input type="text" name="media_url" id="storyMedia" class="w-full mt-1 px-4 py-3 border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]" required placeholder="https://...mp4">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Sıra No</label>
                <input type="number" name="sort_order" id="storySort" value="1" min="1" class="w-full mt-1 px-4 py-3 border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
            </div>
            <div class="flex items-center space-x-3 mt-6">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" id="storyActive" class="toggle-switch" checked>
                    <span class="text-sm font-bold text-slate-700">Aktif</span>
                </label>
            </div>
            <div class="sm:col-span-2 flex space-x-3 pt-2">
                <button type="submit" name="save_story" class="flex-1 py-3 bg-[var(--primary)] text-white rounded-xl font-bold text-sm hover:bg-emerald-700 transition">Kaydet</button>
                <button type="button" id="resetStoryForm" class="flex-1 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Sıfırla</button>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div class="glass-card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-800">Story Listesi</h3>
            <span class="text-sm text-slate-500 font-semibold"><?= count($stories) ?> kayıt</span>
        </div>
        <?php if (empty($stories)): ?>
            <p class="text-slate-500">Henüz kayıt yok.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 uppercase text-[11px] tracking-widest">
                        <th class="py-3">Kapak</th>
                        <th class="py-3">Başlık</th>
                        <th class="py-3">Medya</th>
                        <th class="py-3">Sıra</th>
                        <th class="py-3">Durum</th>
                        <th class="py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($stories as $story): ?>
                    <tr class="align-middle">
                        <td class="py-3">
                            <img src="<?= e($story['cover_url']) ?>" alt="<?= e($story['title']) ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-100">
                        </td>
                        <td class="py-3 font-semibold text-slate-800"><?= e($story['title']) ?></td>
                        <td class="py-3 text-slate-500 truncate max-w-[240px]"><?= e($story['media_url']) ?></td>
                        <td class="py-3 font-bold text-slate-700"><?= (int)$story['sort_order'] ?></td>
                        <td class="py-3">
                            <?php if ($story['is_active']): ?>
                                <span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase rounded-md">Aktif</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-slate-100 text-slate-400 text-[10px] font-bold uppercase rounded-md">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-right space-x-2">
                            <button 
                                class="px-3 py-2 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition edit-story-btn"
                                data-id="<?= (int)$story['id'] ?>"
                                data-title="<?= e($story['title']) ?>"
                                data-cover="<?= e($story['cover_url']) ?>"
                                data-media="<?= e($story['media_url']) ?>"
                                data-sort="<?= (int)$story['sort_order'] ?>"
                                data-active="<?= (int)$story['is_active'] ?>"
                            ><i class="fas fa-pen mr-1"></i>Düzenle</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$story['id'] ?>">
                                <button type="submit" class="px-3 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-bold hover:bg-red-100 transition">
                                    <i class="fas fa-trash mr-1"></i>Sil
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Formu edit verisi ile doldur
document.querySelectorAll('.edit-story-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('storyId').value = btn.dataset.id || '';
        document.getElementById('storyTitle').value = btn.dataset.title || '';
        document.getElementById('storyCover').value = btn.dataset.cover || '';
        document.getElementById('storyMedia').value = btn.dataset.media || '';
        document.getElementById('storySort').value = btn.dataset.sort || 1;
        document.getElementById('storyActive').checked = btn.dataset.active == "1";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

// Form sıfırlama
document.getElementById('resetStoryForm')?.addEventListener('click', () => {
    document.getElementById('storyId').value = '';
    document.getElementById('storyTitle').value = '';
    document.getElementById('storyCover').value = '';
    document.getElementById('storyMedia').value = '';
    document.getElementById('storySort').value = 1;
    document.getElementById('storyActive').checked = true;
});
</script>

</div><!-- Close Main Content Div from Header -->
</body>
</html>
