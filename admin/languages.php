<?php
require_once '../includes/config.php';
requireAuth();

$pdo = getDB();

// ── Tüm desteklenen diller (flag kodu → isim) ────────────────────────────────
$languageNames = [
    'tr' => 'Türkçe',
    'us' => 'English (İngilizce – US)',
    'gb' => 'English (İngilizce – UK)',
    'ru' => 'Русский (Rusça)',
    'de' => 'Deutsch (Almanca)',
    'at' => 'Deutsch (Almanca – AT)',
    'fr' => 'Français (Fransızca)',
    'it' => 'Italiano (İtalyanca)',
    'es' => 'Español (İspanyolca)',
    'mx' => 'Español (Meksika)',
    'co' => 'Español (Kolombiya)',
    'cl' => 'Español (Şili)',
    'pt' => 'Português (Portekizce)',
    'br' => 'Português (Brezilya)',
    'nl' => 'Nederlands (Hollandaca)',
    'sa' => 'العربية (Arapça)',
    'ae' => 'العربية (Arapça – BAE)',
    'eg' => 'العربية (Arapça – Mısır)',
    'ma' => 'العربية (Arapça – Fas)',
    'dz' => 'العربية (Arapça – Cezayir)',
    'ir' => 'فارسی (Farsça)',
    'pk' => 'اردو (Urduca)',
    'in' => 'हिन्दी (Hintçe)',
    'bg' => 'Български (Bulgarca)',
    'ro' => 'Română (Romence)',
    'pl' => 'Polski (Lehçe)',
    'hu' => 'Magyar (Macarca)',
    'sk' => 'Slovenčina (Slovakça)',
    'si' => 'Slovenščina (Slovence)',
    'hr' => 'Hrvatski (Hırvatça)',
    'rs' => 'Српски (Sırpça)',
    'ba' => 'Bosanski (Boşnakça)',
    'mk' => 'Македонски (Makedonca)',
    'al' => 'Shqip (Arnavutça)',
    'gr' => 'Ελληνικά (Rumca)',
    'ua' => 'Українська (Ukraynaca)',
    'ge' => 'ქართული (Gürcüce)',
    'am' => 'Հայերեն (Ermenice)',
    'az' => 'Azərbaycan (Azerbaycanca)',
    'kz' => 'Қазақша (Kazakça)',
    'kg' => 'Кыргызча (Kırgızca)',
    'uz' => "Oʻzbekcha (Özbekçe)",
    'tm' => 'Türkmen (Türkmence)',
    'tj' => 'Тоҷикӣ (Tacikçe)',
    'cn' => '简体中文 (Çince)',
    'jp' => '日本語 (Japonca)',
    'kr' => '한국어 (Korece)',
    'th' => 'ภาษาไทย (Tayca)',
    'vn' => 'Tiếng Việt (Vietnamca)',
    'id' => 'Bahasa Indonesia (Endonezce)',
    'ee' => 'Eesti (Estonca)',
    'lv' => 'Latviešu (Letonca)',
    'lt' => 'Lietuvių (Litvanca)',
    'fi' => 'Suomi (Fince)',
    'se' => 'Svenska (İsveççe)',
    'no' => 'Norsk (Norveççe)',
    'dk' => 'Dansk (Danimarkaca)',
];

// Flag kodu → Google Translate kodu
$googleCodeMap = [
    'us'=>'en','gb'=>'en','ir'=>'fa','ge'=>'ka','cn'=>'zh',
    'kz'=>'kk','kg'=>'ky','rs'=>'sr','ba'=>'bs','kr'=>'ko','jp'=>'ja',
    'sa'=>'ar','ae'=>'ar','eg'=>'ar','ma'=>'ar','dz'=>'ar',
    'br'=>'pt','at'=>'de','mx'=>'es','co'=>'es','cl'=>'es',
    'pk'=>'ur','in'=>'hi','al'=>'sq','am'=>'hy','az'=>'az',
    'hr'=>'hr','mk'=>'mk','si'=>'sl','sk'=>'sk','hu'=>'hu',
    'pl'=>'pl','gr'=>'el','ua'=>'uk','tm'=>'tk','tj'=>'tg',
    'ee'=>'et','lv'=>'lv','lt'=>'lt','fi'=>'fi','se'=>'sv',
    'no'=>'no','dk'=>'da','th'=>'th','vn'=>'vi','id'=>'id',
    'bg'=>'bg','ro'=>'ro','nl'=>'nl','it'=>'it','es'=>'es',
    'fr'=>'fr','de'=>'de','ru'=>'ru','tr'=>'tr','pt'=>'pt',
];

// Türkçe arama terimleri
$searchTerms = [
    'tr'=>'türkçe','us'=>'ingilizce english','gb'=>'ingilizce english',
    'ru'=>'rusça rus','de'=>'almanca deutsch','at'=>'almanca avusturya',
    'fr'=>'fransızca','it'=>'italyanca','es'=>'ispanyolca',
    'mx'=>'ispanyolca meksika','co'=>'ispanyolca kolombiya','cl'=>'ispanyolca şili',
    'pt'=>'portekizce','br'=>'portekizce brezilya','nl'=>'hollandaca flemenkçe',
    'sa'=>'arapça arabça','ae'=>'arapça bae dubai','eg'=>'arapça mısır',
    'ma'=>'arapça fas morocco','dz'=>'arapça cezayir algeria',
    'ir'=>'farsça iran','pk'=>'urduca pakistan','in'=>'hintçe hindi',
    'bg'=>'bulgarca','ro'=>'romence','pl'=>'lehçe polonya',
    'hu'=>'macarca macaristan','sk'=>'slovakça','si'=>'slovence',
    'hr'=>'hırvatça','rs'=>'sırpça sırbistan','ba'=>'boşnakça bosna',
    'mk'=>'makedonca','al'=>'arnavutça','gr'=>'rumca yunanca',
    'ua'=>'ukraynaca','ge'=>'gürcüce gürcistan','am'=>'ermenice',
    'az'=>'azerbaycanca','kz'=>'kazakça','kg'=>'kırgızca',
    'uz'=>'özbekçe','tm'=>'türkmence','tj'=>'tacikçe',
    'cn'=>'çince mandarin','jp'=>'japonca','kr'=>'korece',
    'th'=>'tayca','vn'=>'vietnamca','id'=>'endonezce',
    'ee'=>'estonca','lv'=>'letonca','lt'=>'litvanca',
    'fi'=>'fince','se'=>'isveççe','no'=>'norveççe','dk'=>'danimarkaca',
];

// ── AJAX: Sıralama güncelle ───────────────────────────────────────────────────
if (isset($_POST['update_order'])) {
    header('Content-Type: application/json');
    foreach (($_POST['order'] ?? []) as $i => $id) {
        $pdo->prepare("UPDATE languages SET sort_order=? WHERE id=?")->execute([$i+1, $id]);
    }
    echo json_encode(['success'=>true]);
    exit;
}

// ── AJAX: Hızlı dil ekle (grid'den tıklama) ──────────────────────────────────
if (isset($_POST['quick_add'])) {
    header('Content-Type: application/json');
    $flagCode = trim($_POST['flag_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    if ($flagCode && $name && $code) {
        $check = $pdo->prepare("SELECT id FROM languages WHERE flag_name=?");
        $check->execute([$flagCode.'.svg']);
        if (!$check->fetch()) {
            $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM languages")->fetchColumn();
            $pdo->prepare("INSERT INTO languages (name,code,flag_name,sort_order,is_active) VALUES(?,?,?,?,1)")
                ->execute([$name, $code, $flagCode.'.svg', ($maxOrder ?: 0)+1]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['success'=>true,'added'=>true,'id'=>$newId]);
        } else {
            echo json_encode(['success'=>true,'added'=>false,'msg'=>'Zaten mevcut']);
        }
    } else {
        echo json_encode(['success'=>false,'msg'=>'Eksik veri']);
    }
    exit;
}

// ── Toplu Kaydetme ────────────────────────────────────────────────────────────
if (isset($_POST['save_languages'])) {
    try {
        $pdo->beginTransaction();
        foreach ($_POST['languages'] ?? [] as $i => $d) {
            $pdo->prepare("UPDATE languages SET sort_order=?,is_active=? WHERE id=?")
                ->execute([(int)$d['sort_order'], isset($d['is_active'])?1:0, (int)$d['id']]);
        }
        $pdo->commit();
        setFlash('success','Dil ayarları kaydedildi.');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error','Hata: '.$e->getMessage());
    }
    header('Location: languages.php'); exit;
}

// ── Dil Sil ──────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM languages WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','Dil silindi.');
    header('Location: languages.php'); exit;
}

// ── Dil Ekle/Düzenle ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    $name = trim($_POST['name']??'');
    $code = trim($_POST['code']??'');
    $flag_name = trim($_POST['flag_name']??'');
    $is_active = isset($_POST['is_active'])?1:0;
    if ($name && $code && $flag_name) {
        if ($_POST['action']==='add') {
            $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM languages")->fetchColumn();
            $pdo->prepare("INSERT INTO languages (name,code,flag_name,sort_order,is_active) VALUES(?,?,?,?,?)")
                ->execute([$name,$code,$flag_name,($maxOrder?:0)+1,$is_active]);
            setFlash('success','Dil eklendi.');
        } elseif ($_POST['action']==='edit' && isset($_POST['id'])) {
            $pdo->prepare("UPDATE languages SET name=?,code=?,flag_name=?,is_active=? WHERE id=?")
                ->execute([$name,$code,$flag_name,$is_active,(int)$_POST['id']]);
            setFlash('success','Dil güncellendi.');
        }
    }
    header('Location: languages.php'); exit;
}

// ── Verileri çek ─────────────────────────────────────────────────────────────
$langs = $pdo->query("SELECT * FROM languages ORDER BY sort_order ASC")->fetchAll();
$activeFlagNames = array_column($langs, 'flag_name'); // ['tr.svg','us.svg',...]

// Flag dosyaları kontrol
$flagPath = __DIR__.'/../assets/img/flags/';
$availableFlags = [];
foreach (array_keys($languageNames) as $code) {
    if (file_exists($flagPath.$code.'.svg')) {
        $availableFlags[] = $code;
    }
}

include 'header.php';
$flash = getFlash();
?>

<main class="p-4 sm:p-8 lg:p-12 max-w-7xl mx-auto w-full">

<?php if ($flash): ?>
<div id="flashMsg" class="mb-6 p-4 rounded-2xl flex items-center gap-3 <?= $flash['type']==='success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
    <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?> text-lg"></i>
    <span class="font-bold text-xs sm:text-sm flex-1"><?= e($flash['message']) ?></span>
    <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100"><i class="fas fa-times text-xs"></i></button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="mb-8">
    <h3 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Dil Ayarları</h3>
    <p class="text-slate-400 font-bold text-xs sm:text-sm mt-1 uppercase tracking-wider">Menü dillerini yönetin ve yeni diller ekleyin.</p>
</div>

<div id="toast" class="fixed top-6 right-6 z-[9999] hidden">
    <div class="bg-[#4E3629] text-white px-5 py-3 rounded-2xl shadow-xl text-sm font-bold flex items-center gap-2">
        <i class="fas fa-check-circle text-emerald-400"></i>
        <span id="toastMsg">Eklendi</span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    <!-- ── SOL: DİL GRİD ── -->
    <div class="lg:col-span-7">
        <div class="glass-card p-6 sm:p-8">
            <!-- Başlık + Arama -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-star text-sm"></i>
                    </div>
                    <h4 class="font-black text-slate-800 uppercase tracking-wider text-xs">Popüler Diller</h4>
                </div>
                <div class="relative w-full sm:w-60">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" id="flagSearch" placeholder="Dil ara..."
                        class="w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-100 focus:border-[var(--primary)] outline-none transition-all text-xs font-bold bg-slate-50">
                </div>
            </div>
            <p class="text-[9px] text-slate-400 font-bold mb-5">Popüler grid'deki dil sayısı hazır şablonları gösterir. Aktif listenize daha kısa olabilir. Menüye eklediğiniz dilin kutucuğunda <span class="text-emerald-500 font-black">yeşil tik</span> görünür.</p>

            <!-- Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 max-h-[620px] overflow-y-auto pr-1 custom-scrollbar" id="flagGrid">
                <?php foreach ($availableFlags as $flagCode):
                    $fullName = $languageNames[$flagCode] ?? strtoupper($flagCode);
                    $isActive = in_array($flagCode.'.svg', $activeFlagNames);
                    $search = strtolower($fullName.' '.($searchTerms[$flagCode]??''));
                ?>
                <div class="flag-item relative group bg-slate-50 hover:bg-white border <?= $isActive ? 'border-emerald-400/60 bg-white' : 'border-transparent hover:border-slate-200' ?> p-3 rounded-2xl flex flex-col items-center gap-2 transition-all cursor-pointer"
                     data-name="<?= $search ?>"
                     data-flag="<?= $flagCode ?>.svg"
                     data-fullname="<?= addslashes($fullName) ?>"
                     data-code="<?= $googleCodeMap[$flagCode] ?? $flagCode ?>"
                     data-active="<?= $isActive ? '1' : '0' ?>"
                     onclick="handleFlagClick(this)">

                    <?php if ($isActive): ?>
                    <div class="absolute top-2 right-2 w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center shadow-sm z-10">
                        <i class="fas fa-check text-white" style="font-size:8px;"></i>
                    </div>
                    <?php endif; ?>

                    <div class="w-14 h-10 rounded-lg overflow-hidden shadow-sm border border-white group-hover:scale-105 transition-transform">
                        <img src="../assets/img/flags/<?= $flagCode ?>.svg" class="w-full h-full object-cover" loading="lazy">
                    </div>
                    <span class="text-[9px] font-black text-slate-700 uppercase tracking-tight text-center line-clamp-2 leading-tight <?= $isActive ? 'text-emerald-700' : '' ?>"><?= e($fullName) ?></span>
                    <span class="text-[7px] font-bold text-slate-300 uppercase tracking-widest"><?= $flagCode ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── SAĞ: AKTİF DİL LİSTESİ ── -->
    <div class="lg:col-span-5 space-y-4">
        <div class="flex items-center justify-between px-1">
            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Aktif Dil Listesi</h4>
            <span class="text-[10px] font-bold text-slate-300" id="langCount"><?= count($langs) ?> / Kayıt</span>
        </div>

        <!-- Kaydet -->
        <form id="saveLanguagesForm" method="POST">
            <input type="hidden" name="save_languages" value="1">
            <button type="submit" class="w-full py-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-emerald-900/20 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Değişiklikleri Kaydet
            </button>
            <p class="text-[8px] text-slate-400 mt-2 text-center font-bold uppercase tracking-widest">Sıralama ve aktif durumlar anasayfaya yansır</p>
        </form>

        <!-- Liste -->
        <div id="activeLangList" class="space-y-2">
            <?php foreach ($langs as $lang): ?>
            <div class="lang-card bg-white p-3 rounded-[20px] border border-slate-100 shadow-sm flex items-center gap-3 cursor-move hover:border-emerald-400/30 transition-all" data-id="<?= $lang['id'] ?>">
                <div class="text-slate-200 hover:text-slate-400 transition-colors flex-shrink-0">
                    <i class="fas fa-grip-vertical text-xs"></i>
                </div>
                <div class="w-9 h-6 rounded overflow-hidden bg-slate-50 flex-shrink-0 shadow-sm">
                    <img src="../assets/img/flags/<?= e($lang['flag_name']) ?>" class="w-full h-full object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-slate-700 truncate uppercase"><?= e($lang['name']) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= e($lang['code']) ?></p>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" onclick="toggleLanguage(this,<?= $lang['id'] ?>)"
                        class="toggle-active w-8 h-8 rounded-xl flex items-center justify-center transition-all <?= $lang['is_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-50 text-slate-300' ?>"
                        data-active="<?= $lang['is_active']?'1':'0' ?>" title="<?= $lang['is_active']?'Pasif Yap':'Aktif Yap' ?>">
                        <i class="fas fa-power-off text-[10px]"></i>
                    </button>
                    <button onclick='openEditModal(<?= json_encode($lang) ?>)'
                        class="w-8 h-8 bg-slate-50 hover:bg-blue-50 hover:text-blue-600 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                        <i class="fas fa-pen text-[10px]"></i>
                    </button>
                    <button onclick="deleteLang(<?= $lang['id'] ?>, this)"
                        class="w-8 h-8 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                        <i class="fas fa-trash text-[10px]"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($langs)): ?>
            <div class="py-12 text-center bg-white rounded-[24px] border border-dashed border-slate-200">
                <i class="fas fa-language text-slate-200 text-3xl mb-3"></i>
                <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">Henüz dil eklenmedi.</p>
                <p class="text-slate-300 text-[10px] mt-1">Sol taraftan bir dil seçin.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>

<!-- Dil Düzenleme Modalı -->
<div id="languageModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display:none!important;">
    <div class="bg-white rounded-[32px] p-7 sm:p-10 max-w-lg w-full shadow-2xl animate-scaleUp">
        <div class="flex items-center gap-4 mb-7">
            <div id="modalFlagPreview" class="w-16 h-11 rounded-xl shadow border border-slate-100 overflow-hidden flex-shrink-0">
                <img src="" class="w-full h-full object-cover">
            </div>
            <div>
                <h3 id="modalTitle" class="text-xl font-black text-slate-800">Dil Yapılandır</h3>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest" id="modalFlagCode"></p>
            </div>
            <button onclick="closeModal()" class="ml-auto w-8 h-8 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center text-slate-400">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form id="languageForm" method="POST" class="space-y-5">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="flag_name" id="formFlag">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Görünen İsim *</label>
                <input type="text" name="name" id="formName" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/20 focus:border-[var(--primary)] focus:bg-white transition-all text-sm font-bold text-slate-700">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Google Çeviri Kodu *</label>
                <input type="text" name="code" id="formCode" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/20 focus:border-[var(--primary)] focus:bg-white transition-all text-sm font-bold text-slate-700">
                <p class="text-[8px] text-slate-400 mt-1.5 font-bold ml-1">ISO kodu (tr, en, ar vb.)</p>
            </div>
            <label class="flex items-center justify-between px-5 py-3.5 bg-slate-50 rounded-2xl border border-slate-100 cursor-pointer">
                <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Aktif</span>
                <input type="checkbox" name="is_active" id="formIsActive" class="toggle-switch" checked>
            </label>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="flex-1 py-3.5 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all">Vazgeç</button>
                <button type="submit" class="flex-1 py-3.5 bg-[var(--primary)] text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:opacity-90 shadow-lg transition-all">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes scaleUp { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
.animate-scaleUp { animation: scaleUp 0.2s cubic-bezier(0.34,1.56,0.64,1) forwards; }
.toggle-switch { appearance:none; width:36px; height:18px; background:#e2e8f0; border-radius:9999px; position:relative; cursor:pointer; transition:all .3s; }
.toggle-switch::before { content:''; position:absolute; width:14px; height:14px; background:white; border-radius:9999px; top:2px; left:2px; transition:all .3s; box-shadow:0 1px 3px rgba(0,0,0,.1); }
.toggle-switch:checked { background:#10b981; }
.toggle-switch:checked::before { left:20px; }
.custom-scrollbar::-webkit-scrollbar { width:4px; }
.custom-scrollbar::-webkit-scrollbar-track { background:transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:10px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ── Form veri güncelleme ──────────────────────────────────────────────────────
function updateFormData() {
    const form = document.getElementById('saveLanguagesForm');
    form.querySelectorAll('input[name^="languages"]').forEach(i=>i.remove());
    document.querySelectorAll('#activeLangList .lang-card').forEach((card,i) => {
        const id = card.dataset.id;
        const active = card.querySelector('.toggle-active')?.dataset.active ?? '0';
        [['id',id],['sort_order',i+1],['is_active',active]].forEach(([k,v]) => {
            const inp = document.createElement('input');
            inp.type='hidden'; inp.name=`languages[${i}][${k}]`; inp.value=v;
            form.appendChild(inp);
        });
    });
}

// ── Sortable ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    new Sortable(document.getElementById('activeLangList'), {
        animation:150, ghostClass:'opacity-40',
        onEnd: updateFormData
    });
    updateFormData();

    // Arama
    const input = document.getElementById('flagSearch');
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        document.querySelectorAll('.flag-item').forEach(el => {
            el.style.display = el.dataset.name.includes(q) ? '' : 'none';
        });
    });
});

// ── Grid tıklama ─────────────────────────────────────────────────────────────
function handleFlagClick(el) {
    const flagCode = el.dataset.flag.replace('.svg','');
    const fullName = el.dataset.fullname;
    const code     = el.dataset.code;
    const isActive = el.dataset.active === '1';

    if (isActive) {
        // Zaten mevcut → düzenleme modali aç
        const existing = document.querySelector('#activeLangList .lang-card[data-flag-name="' + el.dataset.flag + '"]');
        // Flag ismi data attr. yok, modal ile editleyebilir
        openAddModal(el.dataset.flag, fullName, code);
    } else {
        // Hızlı ekle
        quickAdd(flagCode, fullName, code, el);
    }
}

function quickAdd(flagCode, fullName, code, gridEl) {
    fetch('languages.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `quick_add=1&flag_code=${encodeURIComponent(flagCode)}&name=${encodeURIComponent(fullName)}&code=${encodeURIComponent(code)}`
    })
    .then(r=>r.json())
    .then(data=>{
        if (data.success) {
            if (data.added) {
                showToast('✓ ' + fullName + ' eklendi');
                // Grid item'ı işaretle
                markGridActive(gridEl);
                // Listeye ekle
                appendToList(data.id, fullName, code, flagCode+'.svg');
                updateFormData();
            } else {
                openAddModal(flagCode+'.svg', fullName, code);
            }
        }
    })
    .catch(()=>{ openAddModal(flagCode+'.svg', fullName, code); });
}

function markGridActive(el) {
    el.dataset.active = '1';
    el.classList.add('border-emerald-400/60','bg-white');
    el.classList.remove('border-transparent');
    // Checkmark ekle
    if (!el.querySelector('.check-badge')) {
        const badge = document.createElement('div');
        badge.className='check-badge absolute top-2 right-2 w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center shadow-sm z-10';
        badge.innerHTML='<i class="fas fa-check text-white" style="font-size:8px;"></i>';
        el.appendChild(badge);
    }
    const label = el.querySelector('span:first-of-type');
    if (label) label.classList.add('text-emerald-700');
}

function appendToList(id, name, code, flagFile) {
    const list = document.getElementById('activeLangList');
    const empty = list.querySelector('.border-dashed');
    if (empty) empty.remove();

    const div = document.createElement('div');
    div.className = 'lang-card bg-white p-3 rounded-[20px] border border-slate-100 shadow-sm flex items-center gap-3 cursor-move hover:border-emerald-400/30 transition-all animate-fadeIn';
    div.dataset.id = id;
    div.innerHTML = `
        <div class="text-slate-200 flex-shrink-0"><i class="fas fa-grip-vertical text-xs"></i></div>
        <div class="w-9 h-6 rounded overflow-hidden bg-slate-50 flex-shrink-0 shadow-sm">
            <img src="../assets/img/flags/${flagFile}" class="w-full h-full object-cover">
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-black text-slate-700 truncate uppercase">${name}</p>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">${code}</p>
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <button type="button" onclick="toggleLanguage(this,${id})"
                class="toggle-active w-8 h-8 rounded-xl flex items-center justify-center transition-all bg-emerald-50 text-emerald-600"
                data-active="1" title="Pasif Yap">
                <i class="fas fa-power-off text-[10px]"></i>
            </button>
            <button class="w-8 h-8 bg-slate-50 hover:bg-blue-50 hover:text-blue-600 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                <i class="fas fa-pen text-[10px]"></i>
            </button>
            <button onclick="deleteLang(${id}, this)"
                class="w-8 h-8 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 rounded-xl flex items-center justify-center transition-all">
                <i class="fas fa-trash text-[10px]"></i>
            </button>
        </div>`;
    list.appendChild(div);

    const count = list.querySelectorAll('.lang-card').length;
    document.getElementById('langCount').textContent = count + ' / Kayıt';
}

// ── Modal ──────────────────────────────────────────────────────────────────────
function openAddModal(flagFile, fullName, code) {
    document.getElementById('modalTitle').textContent = 'Yeni Dil Ekle';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formFlag').value = flagFile;
    document.getElementById('formName').value = fullName;
    document.getElementById('formCode').value = code;
    document.getElementById('formIsActive').checked = true;
    document.getElementById('modalFlagPreview').querySelector('img').src = '../assets/img/flags/' + flagFile;
    document.getElementById('modalFlagCode').textContent = flagFile;
    showModal();
}

function openEditModal(lang) {
    document.getElementById('modalTitle').textContent = 'Dili Düzenle';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = lang.id;
    document.getElementById('formFlag').value = lang.flag_name;
    document.getElementById('formName').value = lang.name;
    document.getElementById('formCode').value = lang.code;
    document.getElementById('formIsActive').checked = lang.is_active == 1;
    document.getElementById('modalFlagPreview').querySelector('img').src = '../assets/img/flags/' + lang.flag_name;
    document.getElementById('modalFlagCode').textContent = lang.flag_name;
    showModal();
}

function showModal() {
    const m = document.getElementById('languageModal');
    m.style.removeProperty('display');
    m.style.display = 'flex';
    m.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const m = document.getElementById('languageModal');
    m.style.display = 'none !important';
    m.classList.add('hidden');
    document.body.style.overflow = '';
}

document.getElementById('languageModal').addEventListener('click', e => {
    if (e.target === document.getElementById('languageModal')) closeModal();
});

// ── Toggle aktif/pasif ────────────────────────────────────────────────────────
function toggleLanguage(btn, id) {
    const isActive = btn.dataset.active === '1';
    if (isActive) {
        btn.classList.replace('bg-emerald-50','bg-slate-50');
        btn.classList.replace('text-emerald-600','text-slate-300');
        btn.dataset.active = '0'; btn.title='Aktif Yap';
        btn.closest('.lang-card').classList.add('opacity-60');
    } else {
        btn.classList.replace('bg-slate-50','bg-emerald-50');
        btn.classList.replace('text-slate-300','text-emerald-600');
        btn.dataset.active = '1'; btn.title='Pasif Yap';
        btn.closest('.lang-card').classList.remove('opacity-60');
    }
    updateFormData();
}

// ── Sil ──────────────────────────────────────────────────────────────────────
function deleteLang(id, btn) {
    if (!confirm('Bu dili silmek istediğinize emin misiniz?')) return;
    const card = btn.closest('.lang-card');
    card.style.transition = 'all .3s';
    card.style.opacity = '0'; card.style.transform = 'scale(0.9)';
    setTimeout(() => {
        card.remove();
        updateFormData();
        window.location.href = '?delete=' + id;
    }, 300);
}

// ── Toast ──────────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.remove('hidden');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.add('hidden'), 2800);
}

// Arama fonk. grid item display (flex) düzelt
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flag-item').forEach(el => {
        el.style.display = 'flex';
        el.style.flexDirection = 'column';
    });
});
</script>

<style>
@keyframes fadeIn { from{opacity:0;transform:translateY(4px);} to{opacity:1;transform:translateY(0);} }
.animate-fadeIn { animation: fadeIn .3s ease forwards; }
</style>

<?php include 'footer.php'; ?>
