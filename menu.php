<?php
// Output buffering başlat (header sorunlarını önlemek için)
ob_start();

// Production ortamı için hata raporlamayı kapat
error_reporting(0);
ini_set('display_errors', 0);

// Config dosyasını yükle
try {
    require_once __DIR__ . '/includes/config.php';
} catch (Exception $e) {
    http_response_code(500);
    die("Sistem hatası. Lütfen daha sonra tekrar deneyin.");
}

// AJAX: Kategori ürünlerini getir
if (isset($_GET['ajax_products']) && isset($_GET['category_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $catId = (int)$_GET['category_id'];
    $products = getProducts($catId, true);
    
    // Ürün resim yollarını tam URL'e çevir
    foreach ($products as &$p) {
        $p['image_url'] = getProductImage($p['image']);
        $p['formatted_price'] = formatPrice($p['price']);
    }
    
    echo json_encode($products);
    exit;
}

// AJAX: Ürün detayını getir
if (isset($_GET['ajax_product_detail']) && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $productId = (int)$_GET['id'];
    $product = getProduct($productId);
    
    if ($product) {
        $product['image_url'] = getProductImage($product['image']);
        $product['formatted_price'] = formatPrice($product['price']);
        echo json_encode($product);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ürün bulunamadı']);
    }
    exit;
}

// Verileri çek
$categories = getCategories(true);
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : null;

    $products = getPopularProducts(12);
    $sectionTitle = 'Popüler Lezzetler';

// Site ayarları
$cafeName        = getDisplayCafeName();
$menuWelcomeLine = trim(getSetting('welcome_line_1', '')) ?: 'Hoş geldiniz';
$footerAddress1  = getSetting('footer_address_1', 'Mimar Sinan, İmam Nasır Sk. No:18');
$footerAddress2  = getSetting('footer_address_2', '34674 Üsküdar/İstanbul');
$footerHours     = getSetting('footer_hours', 'Her Gün: 08:00 - 02:00');
$footerPhone     = getSetting('phone', '0538 647 36 72');
$phoneDigitsOnly = preg_replace('/\D/', '', $footerPhone);
if ($phoneDigitsOnly === '') {
    $phoneDigitsOnly = preg_replace('/\D/', '', '0538 647 36 72');
}
if (str_starts_with($phoneDigitsOnly, '90')) {
    $menuTelHref = '+' . $phoneDigitsOnly;
} elseif (str_starts_with($phoneDigitsOnly, '0')) {
    $menuTelHref = '+90' . substr($phoneDigitsOnly, 1);
} elseif (strlen($phoneDigitsOnly) === 10 && str_starts_with($phoneDigitsOnly, '5')) {
    $menuTelHref = '+90' . $phoneDigitsOnly;
} else {
    $menuTelHref = '+' . $phoneDigitsOnly;
}

$siteLogoFile = get_site_logo_filename();
$siteLogoSrc  = SITE_URL . '/assets/img/' . rawurlencode($siteLogoFile);
$siteLogoPath = __DIR__ . '/assets/img/' . $siteLogoFile;
if (is_readable($siteLogoPath)) {
    $siteLogoSrc .= '?v=' . filemtime($siteLogoPath);
}

// Arka plan gradyan renkleri (üst: ayarlardan; alt: bu sayfada Pantone 476 C — yalnızca menu.php)
$gradientTop = getSetting('gradient_top', '#C1AE65');
$gradientBottom = '#4E3629'; // Pantone 476 C

// Chat asistanı ayarlarını kontrol et
$chatAssistantActive = false;
$chatAssistantName = 'Valéntino Patisserié Akıllı Menü Asistanı';
$chatWelcomeMessage = 'Merhaba! Ben Valéntino Patisserié Akıllı Menü Asistanı. Size menümüzden ürünler önerebilirim, kategoriler hakkında bilgi verebilirim veya size özel öneriler sunabilirim. Nasıl yardımcı olabilirim? 😊';

try {
    $pdo = getDB();
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'chat_assistant_settings'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM chat_assistant_settings LIMIT 1");
        $chatSettings = $stmt->fetch();
        if ($chatSettings && isset($chatSettings['is_active']) && $chatSettings['is_active'] == 1) {
            $chatAssistantActive = true;
            if (!empty($chatSettings['assistant_name'])) {
                $chatAssistantName = $chatSettings['assistant_name'];
            }
            if (!empty($chatSettings['welcome_message'])) {
                $chatWelcomeMessage = $chatSettings['welcome_message'];
            }
        }
    }
} catch (Exception $e) {
    error_log('Chat Assistant Settings Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include __DIR__ . '/includes/google-analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= e($cafeName) ?> | Menü</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Pantone 476 C (#4E3629) — style.css içindeki footer !important ve Tailwind sırasından bağımsız */
        body.menu-page-p476 .main-wrapper > header.menu-brand-header {
            background: #4E3629 !important;
            background-color: #4E3629 !important;
        }
        body.menu-page-p476 .main-wrapper > footer.menu-brand-footer {
            background: #4E3629 !important;
            background-color: #4E3629 !important;
        }
        /* style.css "footer i { color: #c5a059 !important }" hover'da altın zemin + altın ikon = görünmez; yalnızca ikon kutusundaki ikonları beyaz yap */
        body.menu-page-p476 footer.menu-brand-footer .group:hover > div.mb-6 i {
            color: #ffffff !important;
        }

        /* Sol alt sticky chat FAB — Pantone 476 C (menü sayfası; Tailwind CDN yeşil bırakırsa bunu kullanır) */
        body.menu-page-p476 #chat-fab button {
            background-color: #4E3629 !important;
            background-image: none !important;
            border-color: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
        }
        body.menu-page-p476 #chat-fab button:hover {
            background-color: #5c4335 !important;
        }

        .category-scroll::-webkit-scrollbar { display: none; }

        /* Menü başlık: altın tek satır (görseldeki gibi) */
        .menu-welcome-sub {
            text-shadow: 0 1px 4px rgba(0,0,0,0.35);
        }

        /* ── Önerilen Ürünler Carousel ── */
        #recommendedScroll {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 20px;
            padding-top: 8px;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-snap-type: x mandatory;
        }
        #recommendedScroll::-webkit-scrollbar { display: none; }

        .rec-item {
            flex-shrink: 0;
            width: 112px;
            cursor: pointer;
            scroll-snap-align: start;
        }
        @media (min-width: 640px)  { .rec-item { width: 128px; } }
        @media (min-width: 1024px) { .rec-item { width: 144px; } }

        .rec-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .rec-card-img {
            height: 96px;
            background: #f8fafc;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        @media (min-width: 640px)  { .rec-card-img { height: 112px; } }
        @media (min-width: 1024px) { .rec-card-img { height: 128px; } }

        .rec-card-img img {
            width: 100%; height: 100%; object-fit: cover;
            display: block;
        }
        .rec-card-img .rec-placeholder {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            color: #cbd5e1; background: #f8fafc;
            font-size: 1.75rem;
        }

        .rec-card-body {
            padding: 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            background: #ffffff;
        }

        .rec-card-name {
            font-weight: 900;
            color: #2d4433;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        @media (min-width: 640px)  { .rec-card-name { font-size: 10px; } }
        @media (min-width: 1024px) { .rec-card-name { font-size: 11px; } }

        .rec-card-desc {
            color: #94a3b8;
            font-size: 7px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            font-style: italic;
            margin-bottom: 6px;
        }
        @media (min-width: 640px)  { .rec-card-desc { font-size: 8px; } }

        .rec-card-price {
            color: #c5a059;
            font-weight: 900;
            font-size: 10px;
            font-style: italic;
        }
        @media (min-width: 640px)  { .rec-card-price { font-size: 12px; } }
        @media (min-width: 1024px) { .rec-card-price { font-size: 14px; } }
        
        /* Dinamik Gradyan Renkleri */
        .main-wrapper {
            --gradient-top: <?= e($gradientTop) ?>;
            --gradient-bottom: <?= e($gradientBottom) ?>;
        }

        /* Menü sayfası — masaüstü çerçeve dışı arka plan (style.css yeşilini ezer, yalnızca bu gövde sınıfıyla) */
        @media (min-width: 1025px) {
            body.menu-page-p476 {
                background: linear-gradient(135deg, #4E3629 0%, #c5a059 100%) !important;
            }
            body.menu-page-p476 .main-wrapper {
                border-color: #2f211a;
                box-shadow:
                    0 50px 100px -20px rgba(0,0,0,0.5),
                    0 30px 60px -30px rgba(0,0,0,0.3),
                    inset 0 0 0 10px #2f211a;
            }
        }
        
        /* Tutarlı Ölçeklendirme Sistemi */
        :root {
            /* Font Boyutları - Mobil, Tablet, Desktop */
            --text-xs: 0.625rem;    /* 10px - Mobil */
            --text-sm: 0.75rem;     /* 12px - Mobil */
            --text-base: 0.875rem;  /* 14px - Mobil */
            --text-lg: 1rem;        /* 16px - Tablet */
            --text-xl: 1.25rem;     /* 20px - Tablet */
            --text-2xl: 1.5rem;     /* 24px - Desktop */
            --text-3xl: 1.875rem;   /* 30px - Desktop */
            --text-4xl: 2.25rem;    /* 36px - Desktop */
            
            /* Spacing - Tutarlı boşluklar */
            --space-1: 0.25rem;     /* 4px */
            --space-2: 0.5rem;      /* 8px */
            --space-3: 0.75rem;     /* 12px */
            --space-4: 1rem;        /* 16px */
            --space-5: 1.25rem;     /* 20px */
            --space-6: 1.5rem;      /* 24px */
            --space-8: 2rem;        /* 32px */
            
            /* Border Radius */
            --radius-sm: 0.5rem;    /* 8px */
            --radius-md: 1rem;      /* 16px */
            --radius-lg: 1.5rem;    /* 24px */
            --radius-xl: 2rem;      /* 32px */
        }
        
        /* Responsive Font Scaling */
        @media (min-width: 640px) {
            :root {
                --text-xs: 0.6875rem;  /* 11px */
                --text-sm: 0.8125rem;  /* 13px */
                --text-base: 1rem;     /* 16px */
                --text-lg: 1.125rem;   /* 18px */
                --text-xl: 1.375rem;   /* 22px */
                --text-2xl: 1.75rem;   /* 28px */
                --text-3xl: 2.25rem;   /* 36px */
                --text-4xl: 2.75rem;   /* 44px */
            }
        }
        
        @media (min-width: 1024px) {
            :root {
                --text-xs: 0.75rem;    /* 12px */
                --text-sm: 0.875rem;   /* 14px */
                --text-base: 1.125rem; /* 18px */
                --text-lg: 1.25rem;    /* 20px */
                --text-xl: 1.5rem;     /* 24px */
                --text-2xl: 2rem;      /* 32px */
                --text-3xl: 2.5rem;    /* 40px */
                --text-4xl: 3rem;      /* 48px */
            }
        }
    </style>
</head>
<body class="text-[#1a241d] pb-0 menu-page-p476">
    <div class="main-wrapper">
    <!-- Top Header & Logo -->
    <header class="menu-brand-header pt-6 pb-4 sm:pt-8 sm:pb-6 lg:pt-10 lg:pb-8 px-4 sm:px-6 lg:px-10 xl:px-12 2xl:px-14 relative overflow-hidden bg-[#4E3629] rounded-b-[30px] sm:rounded-b-[40px] lg:rounded-b-[56px] xl:rounded-b-[60px] shadow-lg">
        <div class="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <div class="relative z-10 flex flex-col items-center">
            <!-- Icons and Logo Row -->
            <div class="w-full flex items-center justify-between mb-4">
                <!-- Left Icons -->
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="tel:<?= e($menuTelHref) ?>" class="w-11 h-11 sm:w-12 sm:h-12 lg:w-14 lg:h-14 flex items-center justify-center rounded-full bg-emerald-600 text-white hover:bg-emerald-700 transition-all duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-phone text-sm sm:text-base lg:text-lg"></i>
                </a>
                    <?php 
                    $googleMapsUrl = getSetting('google_maps_url', '');
                    // Geriye dönük uyumluluk için eski google_url'yi kontrol et
                    if (empty($googleMapsUrl)) {
                        $googleMapsUrl = getSetting('google_url', 'https://www.google.com/maps/search/Valentino+Patisserie+%C3%9Csk%C3%BCdar');
                    }
                    if (!empty($googleMapsUrl)): 
                    ?>
                    <a href="<?= e($googleMapsUrl) ?>" target="_blank" class="w-11 h-11 sm:w-12 sm:h-12 lg:w-14 lg:h-14 flex items-center justify-center rounded-full bg-white text-[#1a73e8] shadow-md">
                        <i class="fas fa-map-marker-alt text-sm sm:text-base lg:text-lg"></i>
                    </a>
                    <?php endif; ?>
            </div>

                <!-- Center Logo -->
                <div class="w-20 h-20 sm:w-24 sm:h-24 lg:w-28 lg:h-28 xl:w-32 xl:h-32 p-2 sm:p-2.5 lg:p-3 bg-white rounded-xl sm:rounded-2xl lg:rounded-3xl shadow-md logo-container overflow-hidden flex items-center justify-center mx-3 sm:mx-4 lg:mx-5">
                    <img src="<?= e($siteLogoSrc) ?>" alt="<?= e($cafeName) ?> Logo" class="max-w-full max-h-full object-contain">
                </div>

                <!-- Right Icons -->
            <div class="flex items-center gap-2 sm:gap-3">
                    <?php 
                    $instagramUrl = getSetting('instagram_url', 'https://www.instagram.com/valentinopatisserie/');
                    $googleReviewsUrl = getSetting('google_reviews_url', '');
                    // Geriye dönük uyumluluk için eski google_url'yi kontrol et
                    if (empty($googleReviewsUrl)) {
                        $googleReviewsUrl = getSetting('google_url', '');
                    }
                    if ($googleReviewsUrl): ?>
                    <a href="<?= e($googleReviewsUrl) ?>" target="_blank" class="w-11 h-11 sm:w-12 sm:h-12 lg:w-14 lg:h-14 flex items-center justify-center rounded-full bg-white text-[#4285F4] shadow-lg">
                        <i class="fab fa-google text-sm sm:text-base lg:text-lg"></i>
                </a>
                    <?php endif; ?>
                    <?php if ($instagramUrl): 
                    ?>
                    <a href="<?= e($instagramUrl) ?>" target="_blank" class="w-11 h-11 sm:w-12 sm:h-12 lg:w-14 lg:h-14 flex items-center justify-center rounded-full bg-gradient-to-tr from-[#f58529] via-[#dd2a7b] to-[#515bd4] text-white shadow-lg ring-2 ring-white/40">
                        <i class="fab fa-instagram text-sm sm:text-base lg:text-lg"></i>
                </a>
                    <?php endif; ?>
            </div>
        </div>
        
            <!-- İşletme adı + tek satır hoş geldiniz (ayar: Slogan / welcome_line_1) -->
            <h1 class="text-white text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-[0.12em] mb-0"><?= eu($cafeName) ?></h1>
            <p class="menu-welcome-sub text-[#c5a059] text-[11px] sm:text-xs font-semibold tracking-[0.22em] mt-2 text-center"><?= eu($menuWelcomeLine) ?></p>
        </div>
    </header>

    <main class="px-5 sm:px-7 lg:px-12 xl:px-14 mt-3 sm:mt-4 lg:mt-6 max-w-6xl mx-auto">
        <!-- Popüler/Önerilen Ürünler -->
        <?php 
        $sectionActive = getSetting('featured_section_active', '1') == '1';
        $recommendedProducts = $sectionActive ? getPopularProducts(12) : [];
        if (!empty($recommendedProducts)): 
        ?>
        <div class="mb-3 sm:mb-4 relative group/carousel">
            <div class="mb-2 sm:mb-3 px-1">
                <h2 class="text-lg sm:text-xl lg:text-2xl font-black text-white uppercase tracking-wider drop-shadow-lg"><?= e(getSetting('featured_section_title', 'Bunları Beğenebilirsiniz')) ?></h2>
            </div>

            <!-- Navigation Arrows (Desktop & UX) -->
            <button onclick="scrollRecommended('left')" class="absolute left-0 top-[55%] -translate-y-1/2 -ml-2 z-20 w-9 h-9 sm:w-10 sm:h-10 lg:w-11 lg:h-11 bg-white/95 backdrop-blur-md rounded-full shadow-xl flex items-center justify-center text-[#4E3629] border border-gray-100 opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 hover:bg-[#4E3629] hover:text-white pointer-events-auto">
                <i class="fas fa-chevron-left text-xs sm:text-sm"></i>
            </button>
            <button onclick="scrollRecommended('right')" class="absolute right-0 top-[55%] -translate-y-1/2 -mr-2 z-20 w-9 h-9 sm:w-10 sm:h-10 lg:w-11 lg:h-11 bg-white/95 backdrop-blur-md rounded-full shadow-xl flex items-center justify-center text-[#4E3629] border border-gray-100 opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 hover:bg-[#4E3629] hover:text-white pointer-events-auto">
                <i class="fas fa-chevron-right text-xs sm:text-sm"></i>
            </button>

            <div id="recommendedScroll">
                <?php foreach ($recommendedProducts as $product): ?>
                <div class="rec-item" onclick="openProductModal(<?= $product['id'] ?>)">
                    <div class="rec-card">
                        <div class="rec-card-img">
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?= getProductImage($product['image']) ?>"
                                 alt="<?= e($product['name']) ?>"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="rec-placeholder" style="display:none;">
                                <i class="fas fa-mug-hot"></i>
                            </div>
                            <?php else: ?>
                            <div class="rec-placeholder">
                                <i class="fas fa-mug-hot"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="rec-card-body">
                            <div>
                                <h3 class="rec-card-name"><?= e($product['name']) ?></h3>
                                <p class="rec-card-desc"><?= e(($product['short_description'] ?? '') ?: ($product['description'] ?? '')) ?></p>
                            </div>
                            <p class="rec-card-price"><?= formatPrice($product['price']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        function scrollRecommended(direction) {
            const container = document.getElementById('recommendedScroll');
            const scrollAmount = 300;
            if (direction === 'left') {
                container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            } else {
                container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            }
        }
        </script>
        <?php endif; ?>
            
        <!-- Kategori Kartları -->
        <div class="mb-20">
            <div class="flex flex-col mb-4 sm:mb-5">
                <div class="flex items-center gap-3 mb-1 sm:mb-1.5">
                    <span class="w-8 sm:w-10 h-[2px] bg-white/80 rounded-full"></span>
                    <h2 class="text-lg sm:text-xl lg:text-2xl font-black text-white uppercase tracking-wider drop-shadow-lg">Menü İçeriğimiz</h2>
                </div>
                <p class="text-[9px] sm:text-[10px] lg:text-[11px] text-white/70 font-bold uppercase tracking-[0.3em] ml-11 sm:ml-13 drop-shadow-md">Tüm Lezzet Gruplarımızı Keşfedin</p>
            </div>
            <div class="flex flex-col gap-4">
                <?php foreach ($categories as $cat): 
                    $hasVideo = !empty($cat['video']);
                    $hasImage = !empty($cat['image']);
                ?>
                <div class="category-accordion group" data-category-id="<?= $cat['id'] ?>">
                    <!-- Kategori Başlık Kartı -->
                    <div class="category-header relative rounded-[32px] overflow-hidden aspect-[21/9] sm:aspect-[21/7] md:aspect-[21/6] bg-slate-100 shadow-sm hover:shadow-2xl hover:shadow-emerald-900/10 transition-all duration-500 cursor-pointer group/cat">
                        <?php if ($hasVideo): ?>
                        <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover/cat:scale-105">
                            <source src="<?= SITE_URL ?>/assets/img/categories/<?= e($cat['video']) ?>" type="video/mp4">
                        </video>
                        <?php elseif ($hasImage): ?>
                        <img src="<?= SITE_URL ?>/assets/img/categories/<?= e($cat['image']) ?>" alt="<?= e($cat['name']) ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover/cat:scale-105" onerror="this.style.display='none'">
                                <?php else: ?>
                        <div class="absolute inset-0 bg-gradient-to-br from-[#4E3629] to-[#2a1f18] flex items-center justify-center">
                            <i class="<?= e($cat['icon']) ?> text-white/10 text-6xl transition-transform duration-500 group-hover/cat:scale-110"></i>
                                </div>
                                <?php endif; ?>
                                
                        <!-- Premium Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/20 transition-opacity duration-500"></div>
                        <div class="absolute inset-0 bg-[#4E3629]/10 opacity-0 group-hover/cat:opacity-100 transition-opacity duration-500"></div>

                        <div class="absolute inset-0 flex items-center justify-start p-4 sm:p-5 lg:p-6 pl-8 sm:pl-12 lg:pl-16">
                                    <div class="flex flex-col items-start">
                                <h3 class="text-white font-black text-2xl sm:text-3xl lg:text-4xl transition-all duration-500 uppercase tracking-tight" style="text-shadow: 0 3px 12px rgba(0,0,0,0.7), 0 6px 24px rgba(0,0,0,0.6), 0 0 40px rgba(0,0,0,0.5), 0 0 60px rgba(0,0,0,0.3);"><?= e($cat['name']) ?></h3>
                                <div class="w-0 h-[2px] bg-[#c5a059] rounded-full mt-1.5 sm:mt-2 transition-all duration-500 group-hover/cat:w-14 sm:group-hover/cat:w-16"></div>
                                    </div>
                            <div class="absolute right-4 sm:right-5 lg:right-6 w-10 h-10 sm:w-11 sm:h-11 lg:w-12 lg:h-12 rounded-xl sm:rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 flex items-center justify-center text-white transition-all duration-500 accordion-icon group-hover/cat:bg-[#c5a059] group-hover/cat:border-transparent group-hover/cat:shadow-xl group-hover/cat:shadow-[#c5a059]/30">
                                <i class="fas fa-chevron-down text-sm sm:text-base lg:text-lg"></i>
                                    </div>
                                </div>
                            </div>

                    <div class="category-content overflow-hidden transition-all duration-500 ease-in-out max-h-0 opacity-0">
                        <div class="pt-6 sm:pt-7 lg:pt-8 pb-4 sm:pb-5">
                            <div class="products-list flex flex-col gap-4 sm:gap-5 lg:gap-6 px-1">
                                <!-- Ürünler buraya AJAX ile gelecek -->
                                <div class="col-span-full py-10 flex justify-center items-center loader">
                                    <div class="w-8 h-8 border-4 border-[#c5a059] border-t-transparent rounded-full animate-spin"></div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const accordions = document.querySelectorAll('.category-accordion');
        const urlParams = new URLSearchParams(window.location.search);
        const categoryIdParam = urlParams.get('category');

        // Otomatik kategori açma fonksiyonu
        async function openAccordion(accordion) {
            const list = accordion.querySelector('.products-list');
            const categoryId = accordion.getAttribute('data-category-id');

            accordion.classList.add('active');
            
            if (list.querySelector('.loader')) {
                try {
                    const response = await fetch(`menu?ajax_products=1&category_id=${categoryId}`);
                    const products = await response.json();
                    
                    if (products.length === 0) {
                        list.innerHTML = '<p class="text-center text-gray-400 py-10 font-bold">Bu kategoride henüz ürün yok.</p>';
                    } else {
                        list.innerHTML = '';
                        products.forEach((product, index) => {
                            const card = document.createElement('div');
                            card.onclick = () => openProductModal(product.id);
                            card.className = 'product-list-item group bg-white rounded-2xl sm:rounded-3xl overflow-hidden shadow-md border border-gray-100/80 flex items-center p-4 sm:p-5 lg:p-6 cursor-pointer transition-all duration-300 hover:shadow-2xl hover:shadow-[#c5a059]/10 hover:border-[#c5a059]/40 hover:-translate-y-0.5 active:scale-[0.99] backdrop-blur-sm';
                            card.style.animationDelay = `${index * 0.05}s`;
                            
                            // Ekstra bilgisi var mı kontrol et ve parse et
                            let extrasHtml = '';
                            try {
                                if (product.extras) {
                                    const extras = JSON.parse(product.extras);
                                    if (Array.isArray(extras) && extras.length > 0) {
                                        extrasHtml = '<div class="flex flex-wrap gap-1.5 mt-1.5">';
                                        extras.forEach(extra => {
                                            extrasHtml += `<span class="inline-flex items-center gap-1 text-[7px] sm:text-[8px] font-bold text-amber-800 bg-gradient-to-r from-amber-50/90 via-amber-50/70 to-amber-50/50 px-2 py-0.5 rounded-md uppercase tracking-wide border border-amber-200/60 shadow-sm backdrop-blur-sm"><i class="fas fa-plus text-[9px] sm:text-[10px] text-amber-700"></i><span class="text-amber-900">${extra.name}</span><span class="text-[9px] sm:text-[10px] text-amber-700 font-black">+${extra.price}₺</span></span>`;
                                        });
                                        extrasHtml += '</div>';
                                    }
                                }
                            } catch(e) {}

                            card.innerHTML = `
                                <div class="w-24 h-24 sm:w-28 sm:h-28 lg:w-32 lg:h-32 flex-shrink-0 relative rounded-2xl sm:rounded-3xl overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100 shadow-lg group-hover:shadow-xl transition-all duration-300 ring-2 ring-gray-50/50 group-hover:ring-[#c5a059]/20">
                                    <img src="${product.image_url}" alt="${product.name}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>
                                <div class="flex-1 ml-4 sm:ml-5 lg:ml-6 pr-3 sm:pr-4 text-left min-w-0 flex flex-col justify-between">
                                    <div>
                                        <h4 class="font-black text-[#c5a059] text-base sm:text-lg lg:text-xl line-clamp-3 group-hover:text-[#d4b16a] transition-colors duration-300 leading-tight mb-2 tracking-tight" style="word-break: normal; overflow-wrap: break-word; hyphens: none; white-space: normal;">${product.name}</h4>
                                        ${extrasHtml}
                                    </div>
                                    <div class="mt-3 pt-2.5 border-t border-gray-100/60">
                                        <div class="h-[1px] bg-gradient-to-r from-transparent via-[#c5a059]/20 to-transparent"></div>
                                    </div>
            </div>
                                <div class="flex flex-col items-end justify-between gap-3 px-3 sm:px-4 min-w-[100px] sm:min-w-[120px]">
                                    <div class="text-right w-full">
                                        <span class="text-[#c5a059] font-black text-lg sm:text-xl lg:text-2xl whitespace-nowrap block leading-none mb-1">${product.formatted_price}</span>
                                        <span class="text-[9px] sm:text-[10px] lg:text-[11px] text-gray-400 font-semibold uppercase tracking-widest">FİYAT</span>
            </div>
                                    <div class="w-10 h-10 sm:w-11 sm:h-11 lg:w-12 lg:h-12 rounded-xl sm:rounded-2xl bg-gradient-to-br from-amber-50/80 via-amber-50/60 to-amber-50/40 flex items-center justify-center text-[#c5a059] group-hover:from-[#c5a059]/20 group-hover:via-[#c5a059]/15 group-hover:to-[#c5a059]/10 group-hover:text-[#d4b16a] transition-all duration-300 shadow-md group-hover:shadow-lg group-hover:scale-105 border border-amber-100/50 group-hover:border-[#c5a059]/30 backdrop-blur-sm">
                                        <i class="fas fa-chevron-right text-sm sm:text-base"></i>
        </div>
    </div>
                            `;
                            list.appendChild(card);
                        });
                    }
                } catch (error) {
                    console.error('Ürünler yüklenirken hata:', error);
                    list.innerHTML = '<p class="text-center text-red-500 py-10">Ürünler yüklenirken bir hata oluştu.</p>';
                }
            }

            // Scroll devre dışı bırakıldı - Kullanıcı deneyimi için
        }

        // Eğer URL'de kategori ID varsa otomatik aç
        if (categoryIdParam) {
            const targetAccordion = document.querySelector(`.category-accordion[data-category-id="${categoryIdParam}"]`);
            if (targetAccordion) {
                setTimeout(() => openAccordion(targetAccordion), 500);
            }
        }

        accordions.forEach(accordion => {
            const header = accordion.querySelector('.category-header');
            header.addEventListener('click', async function() {
                const isActive = accordion.classList.contains('active');
                
                if (!isActive) {
                    // Yeni kategoriyi aç (diğerleri açık kalacak)
                    await openAccordion(accordion);
                } else {
                    // Sadece tıklanan kategoriyi kapat
                    accordion.classList.remove('active');
                }
            });
        });
    });
    </script>

    <style>
        .category-accordion.active .category-content {
            max-height: 5000px;
            opacity: 1;
            margin-bottom: 20px;
        }
        .category-content {
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .category-accordion.active .accordion-icon {
            transform: rotate(180deg);
            background-color: #c5a059;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }
        .category-header {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .category-header:active {
            transform: scale(0.97);
        }
        .category-accordion.active .category-header {
            box-shadow: 0 8px 25px rgba(45, 68, 51, 0.15);
        }
        .product-list-item {
            animation: slideInList 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(15px) scale(0.96);
            position: relative;
        }
        @keyframes slideInList {
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        /* Ürün kartı premium border efekti */
        .product-list-item::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1.5px;
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.3), rgba(197, 160, 89, 0.1), rgba(197, 160, 89, 0));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
        }
        .product-list-item:hover::before {
            opacity: 1;
        }
        
        /* Ürün kartı arka plan glow efekti */
        .product-list-item::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.1), rgba(197, 160, 89, 0.05));
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: -1;
            filter: blur(8px);
        }
        .product-list-item:hover::after {
            opacity: 1;
        }
        
        /* Ürün görsel hover efekti */
        .product-list-item img {
            filter: brightness(1) saturate(1);
            transition: filter 0.4s ease, transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-list-item:hover img {
            filter: brightness(1.08) saturate(1.1);
        }
        
        /* Ürün adı hover efekti */
        .product-list-item h4 {
            text-shadow: 0 1px 2px rgba(197, 160, 89, 0.1);
            transition: text-shadow 0.3s ease;
        }
        .product-list-item:hover h4 {
            text-shadow: 0 2px 4px rgba(197, 160, 89, 0.2);
        }
        
        /* Fiyat hover efekti */
        .product-list-item:hover [class*="text-[#c5a059]"] {
            transform: scale(1.02);
            transition: transform 0.3s ease;
        }
        
        /* Navigasyon butonu premium efekt */
        .product-list-item .fa-chevron-right {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-list-item:hover .fa-chevron-right {
            transform: translateX(2px);
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .animate-pulse-soft {
            animation: pulse-soft 2s infinite ease-in-out;
        }
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Dil Dropdown Scrollbar Stilleri */
        #langDropdown::-webkit-scrollbar {
            width: 6px;
        }
        #langDropdown::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 10px;
        }
        #langDropdown::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        #langDropdown::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Mobil için smooth scroll */
        #langDropdown {
            -webkit-overflow-scrolling: touch;
        }
    </style>

    <!-- Dil Değiştirici -->
    <div id="languageLoader" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center text-white">
        <div class="flex flex-col items-center gap-4">
            <div class="w-12 h-12 border-4 border-t-[#c5a059] border-white/20 rounded-full animate-spin"></div>
            <p class="font-bold">Çeviriliyor...</p>
        </div>
    </div>

    <div class="fixed right-0 bottom-32 sm:bottom-36 lg:bottom-40 z-50">
        <button id="langBtn" class="w-14 h-16 sm:w-15 sm:h-17 lg:w-16 lg:h-18 bg-white rounded-l-xl sm:rounded-l-2xl shadow-lg flex items-center justify-center border border-gray-100 hover:shadow-xl transition-shadow">
            <img id="currentLangFlag" src="<?= SITE_URL ?>/assets/img/flags/tr.svg" alt="TR" class="w-7 h-7 sm:w-8 sm:h-8 lg:w-9 lg:h-9 object-contain">
            </button>
        <div id="langDropdown" class="hidden absolute right-[56px] sm:right-[60px] lg:right-[64px] bottom-0 bg-white rounded-xl sm:rounded-2xl shadow-2xl p-2.5 sm:p-3 border border-gray-50 w-[200px] sm:w-[220px] lg:w-[240px] max-h-[200px] overflow-y-auto z-50">
            <?php 
            $activeLanguages = getLanguages();
            foreach($activeLanguages as $lang): ?>
            <button onclick="changeLanguage('<?= e($lang['code']) ?>')" class="w-full flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 hover:bg-gray-50 rounded-lg sm:rounded-xl transition-all text-left">
                <img src="<?= SITE_URL ?>/assets/img/flags/<?= e($lang['flag_name']) ?>" class="w-5 h-5 sm:w-6 sm:h-6 lg:w-7 lg:h-7 object-contain flex-shrink-0">
                <span class="text-[11px] sm:text-xs lg:text-sm font-bold text-[#2d4433] flex-1 truncate"><?= e(preg_replace('/\s*\([^)]*\)\s*$/', '', $lang['name'])) ?></span>
                    </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="google_translate_element" style="display:none"></div>
    <script type="text/javascript">
        let currentLang = 'tr';
        const activeLangs = <?= json_encode($activeLanguages) ?>;

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }

        function getSavedLanguage() {
            // Önce localStorage'dan bayrak kodunu kontrol et
            let savedLangCode = localStorage.getItem('selectedLangCode');
            
            // Cookie'yi kontrol et ve senkronize et
            const googtrans = getCookie('googtrans');
            if (googtrans) {
                const parts = googtrans.split('/');
                if (parts.length >= 3) {
                    const translateCode = parts[2];
                    // Google Translate kodunu bayrak koduna çevir
                    const translateToFlagMap = {
                        'en': 'us', // İngilizce için US bayrağı
                        'ar': 'sa', // Arapça için SA bayrağı
                        'fa': 'ir',
                        'ka': 'ge',
                        'zh': 'cn',
                        'kk': 'kz',
                        'ky': 'kg',
                        'sr': 'rs',
                        'ko': 'kr',
                        'ja': 'jp'
                    };
                    const flagCodeFromCookie = translateToFlagMap[translateCode] || translateCode;
                    
                    // Eğer localStorage'da farklı bir dil varsa, cookie'yi öncelikli yap
                    if (!savedLangCode || savedLangCode !== flagCodeFromCookie) {
                        const lang = activeLangs.find(l => l.code === flagCodeFromCookie);
                        if (lang) {
                            savedLangCode = flagCodeFromCookie;
                            localStorage.setItem('selectedLangCode', flagCodeFromCookie);
                        }
                    }
                }
            }
            
            // localStorage'dan gelen değeri kontrol et
            if (savedLangCode) {
                const lang = activeLangs.find(l => l.code === savedLangCode);
                if (lang) {
                    return savedLangCode;
                }
            }
            
            // Varsayılan olarak Türkçe
            localStorage.setItem('selectedLangCode', 'tr');
            return 'tr';
        }

        const siteUrl = <?= json_encode(SITE_URL) ?>;

        function getFlagForLang(langCode) {
            const lang = activeLangs.find(l => l.code === langCode);
            return lang ? `${siteUrl}/assets/img/flags/${lang.flag_name}` : `${siteUrl}/assets/img/flags/tr.svg`;
        }

        // Google Translate dil kodlarını dönüştür (bazı bayrak kodları farklı olabilir)
        function getGoogleTranslateCode(langCode) {
            const codeMap = {
                'us': 'en',
                'gb': 'en',
                'sa': 'ar',
                'ir': 'fa',
                'ge': 'ka',
                'cn': 'zh',
                'kz': 'kk',
                'kg': 'ky',
                'rs': 'sr',
                'kr': 'ko',
                'jp': 'ja'
            };
            return codeMap[langCode] || langCode;
        }

        function applySavedLanguage() {
            // Google Translate yüklenmemişse çık
            if (typeof google === 'undefined' || !google.translate) {
                return;
            }
            
            const savedLang = getSavedLanguage();
            if (savedLang && savedLang !== 'tr') {
                let attempts = 0;
                const checkSelect = setInterval(() => {
                    attempts++;
                    const select = document.querySelector('.goog-te-combo');
                    if (select && select.options.length > 0) {
                        const translateCode = getGoogleTranslateCode(savedLang);
                        // Select'te bu değer var mı kontrol et
                        const optionExists = Array.from(select.options).some(opt => opt.value === translateCode);
                        if (optionExists) {
                            select.value = translateCode;
                            // Change event'i tetikle
                            const event = new Event('change', { bubbles: true });
                            select.dispatchEvent(event);
                        }
                        clearInterval(checkSelect);
                    } else if (attempts > 50) { // 5 saniye sonra vazgeç
                        clearInterval(checkSelect);
                    }
                }, 100);
            }
        }

        function googleTranslateElementInit() {
            try {
                // Önce bayrağı güncelle
                currentLang = getSavedLanguage();
                const flagImg = document.getElementById('currentLangFlag');
                if (flagImg) {
                    flagImg.src = getFlagForLang(currentLang);
                }

                // Google Translate API'sinin yüklenip yüklenmediğini kontrol et
                if (typeof google === 'undefined' || !google.translate) {
                    console.warn('Google Translate API yüklenemedi');
                    return;
                }

                // Google Translate için dil kodlarını dönüştür
                const includedLanguages = activeLangs.map(l => getGoogleTranslateCode(l.code)).filter((v, i, a) => a.indexOf(v) === i).join(',');

                new google.translate.TranslateElement({
                    pageLanguage: 'tr',
                    includedLanguages: includedLanguages, 
                    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                    autoDisplay: false
                }, 'google_translate_element');
                
                // Google Translate elementinin hazır olmasını bekle
                setTimeout(() => {
                    applySavedLanguage();
                }, 800);
                
                // Ekstra kontrol - MutationObserver ile Google Translate elementinin oluşmasını bekle
                const observer = new MutationObserver((mutations, obs) => {
                    const select = document.querySelector('.goog-te-combo');
                    if (select && select.options.length > 0) {
                        applySavedLanguage();
                        obs.disconnect();
                    }
                });
                
                // Google Translate elementinin ekleneceği yeri gözle
                const translateElement = document.getElementById('google_translate_element');
                if (translateElement) {
                    observer.observe(translateElement, { childList: true, subtree: true });
                }
            } catch (error) {
                console.error('Google Translate başlatma hatası:', error);
                // Hata durumunda en azından bayrağı göster
                const savedLang = getSavedLanguage();
                const flagImg = document.getElementById('currentLangFlag');
                if (flagImg && savedLang) {
                    flagImg.src = getFlagForLang(savedLang);
                }
            }
        }
        
        // Google Translate script'ini güvenli şekilde yükle
        function loadGoogleTranslateScript(retryCount = 0) {
            const maxRetries = 3;
            const retryDelay = 2000; // 2 saniye
            
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            
            script.onerror = function() {
                console.warn('Google Translate script yüklenemedi, retry:', retryCount);
                if (retryCount < maxRetries) {
                    setTimeout(() => {
                        loadGoogleTranslateScript(retryCount + 1);
                    }, retryDelay * (retryCount + 1)); // Exponential backoff
                } else {
                    console.error('Google Translate script yüklenemedi. Lütfen daha sonra tekrar deneyin.');
                    // Fallback: Sadece bayrağı göster, çeviri olmadan
                    const savedLang = getSavedLanguage();
                    const flagImg = document.getElementById('currentLangFlag');
                    if (flagImg && savedLang) {
                        flagImg.src = getFlagForLang(savedLang);
                    }
                }
            };
            
            script.onload = function() {
                console.log('Google Translate script başarıyla yüklendi');
            };
            
            document.head.appendChild(script);
        }
    </script>
    <script type="text/javascript">
        // Sayfa yüklendiğinde script'i yükle
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                loadGoogleTranslateScript();
            });
        } else {
            loadGoogleTranslateScript();
        }
    </script>

    <script>
        const langBtn = document.getElementById('langBtn');
        const langDropdown = document.getElementById('langDropdown');
        langBtn.addEventListener('click', (e) => { e.stopPropagation(); langDropdown.classList.toggle('hidden'); });
        document.addEventListener('click', () => { langDropdown.classList.add('hidden'); });

        function changeLanguage(langCode) {
            // Dil değişikliği flag'ini set et
            sessionStorage.setItem('languageChanging', 'true');
            
            // Loader'ı göster
            const loader = document.getElementById('languageLoader');
            if (loader) {
                loader.classList.remove('hidden');
            }
            
            // Bayrağı hemen güncelle
            const flagImg = document.getElementById('currentLangFlag');
            if (flagImg) {
                flagImg.src = getFlagForLang(langCode);
            }
            
            // Dropdown'u kapat
            langDropdown.classList.add('hidden');
            
            // Google Translate kodunu al
            const translateCode = getGoogleTranslateCode(langCode);
            
            // Önce localStorage'ı güncelle (sayfa yenilendiğinde doğru bayrağı göstermek için)
            localStorage.setItem('selectedLangCode', langCode);
            
            // Cookie'yi güncelle (Google Translate için)
            document.cookie = `googtrans=/tr/${translateCode}; path=/; max-age=31536000`;
            
            // Mevcut dili güncelle
            currentLang = langCode;
            
            // Google Translate elementini kontrol et ve çevir
            // Önce Google Translate'in yüklenip yüklenmediğini kontrol et
            if (typeof google === 'undefined' || !google.translate) {
                // Google Translate yüklenmemiş, sadece sayfayı yenile
                setTimeout(() => {
                    window.location.reload();
                }, 300);
                return;
            }
            
            let attempts = 0;
            const maxAttempts = 50; // 5 saniye
            const checkTranslate = setInterval(() => {
                attempts++;
                const select = document.querySelector('.goog-te-combo');
                if (select && select.options.length > 0) {
                    // Select'te bu değer var mı kontrol et
                    const optionExists = Array.from(select.options).some(opt => opt.value === translateCode);
                    if (optionExists) {
                        // Select değerini güncelle
                        select.value = translateCode;
                        
                        // Change event'i tetikle
                        const event = new Event('change', { bubbles: true });
                        select.dispatchEvent(event);
                        
                        // Kısa bir bekleme sonrası sayfayı yenile (çevirinin uygulanması için)
                        clearInterval(checkTranslate);
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else if (attempts >= maxAttempts) {
                        // Seçenek bulunamadı, yine de sayfayı yenile
                        clearInterval(checkTranslate);
                        window.location.reload();
                    }
                } else if (attempts >= maxAttempts) {
                    // Select bulunamadı, yine de sayfayı yenile
                    clearInterval(checkTranslate);
                    window.location.reload();
                }
            }, 100);
        }
        
        // Sayfa yüklendiğinde bayrağı güncelle ve loader'ı kontrol et
        document.addEventListener('DOMContentLoaded', function() {
            const savedLang = getSavedLanguage();
            const flagImg = document.getElementById('currentLangFlag');
            if (flagImg && savedLang) {
                flagImg.src = getFlagForLang(savedLang);
            }
            
            // Eğer dil değişikliği yapılıyorsa loader'ı göster
            const isLanguageChanging = sessionStorage.getItem('languageChanging');
            const loader = document.getElementById('languageLoader');
            if (isLanguageChanging && loader) {
                loader.classList.remove('hidden');
            } else if (loader) {
                loader.classList.add('hidden');
            }
        });
        
        // Sayfa tamamen yüklendiğinde de kontrol et ve loader'ı gizle
        window.addEventListener('load', function() {
            const savedLang = getSavedLanguage();
            const flagImg = document.getElementById('currentLangFlag');
            if (flagImg && savedLang) {
                flagImg.src = getFlagForLang(savedLang);
            }
            
            // Google Translate'in çeviriyi uygulaması için biraz bekle, sonra loader'ı gizle
            setTimeout(function() {
                const loader = document.getElementById('languageLoader');
                if (loader) {
                    loader.classList.add('hidden');
                    sessionStorage.removeItem('languageChanging');
                }
            }, 800); // Çevirinin uygulanması için 800ms bekle
        });
    </script>

    <!-- Premium Footer -->
    <footer class="menu-brand-footer bg-[#4E3629] text-white pt-20 pb-10 px-6 mt-20 relative overflow-hidden">
        <!-- Decorative Background -->
        <div class="absolute top-0 left-0 w-full h-full opacity-[0.03] pointer-events-none">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="footer-grid-premium" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#footer-grid-premium)" />
            </svg>
        </div>
        
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/20 pointer-events-none"></div>
        
        <div class="max-w-5xl mx-auto relative z-10">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-12 mb-16">
                <!-- Adres -->
                <div class="flex flex-col items-center group">
                    <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mb-6 transition-all duration-500 group-hover:bg-[#c5a059] group-hover:scale-110 group-hover:shadow-2xl group-hover:shadow-[#c5a059]/20">
                        <i class="fas fa-map-marker-alt text-[#c5a059] text-2xl group-hover:text-white transition-colors duration-500"></i>
                    </div>
                    <h4 class="font-black uppercase tracking-[0.3em] mb-3 text-[11px] text-[#c5a059]">Adres</h4>
                    <p class="text-xs font-medium opacity-70 leading-loose text-center max-w-[260px]">
                        <?= e($footerAddress1) ?><br>
                        <span class="text-white/90"><?= e($footerAddress2) ?></span>
                    </p>
            </div>

                <!-- İletişim -->
                <div class="flex flex-col items-center group">
                    <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mb-6 transition-all duration-500 group-hover:bg-[#c5a059] group-hover:scale-110 group-hover:shadow-2xl group-hover:shadow-[#c5a059]/20">
                        <i class="fas fa-phone-alt text-[#c5a059] text-2xl group-hover:text-white transition-colors duration-500"></i>
                    </div>
                    <h4 class="font-black uppercase tracking-[0.3em] mb-3 text-[11px] text-[#c5a059]">İletişim</h4>
                    <a href="tel:<?= e(preg_replace('/\D/', '', $footerPhone)) ?>" class="text-base font-black hover:text-[#c5a059] transition-all duration-300 tracking-wider">
                        <?= e($footerPhone) ?>
                    </a>
                    <div class="flex gap-4 mt-4">
                        <?php if ($instagram = getSetting('instagram')): ?>
                        <a href="https://instagram.com/<?= ltrim($instagram, '@') ?>" target="_blank" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-white/50 hover:bg-white/10 hover:text-white transition-all">
                            <i class="fab fa-instagram text-sm"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Açılış -->
                <div class="flex flex-col items-center group">
                    <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mb-6 transition-all duration-500 group-hover:bg-[#c5a059] group-hover:scale-110 group-hover:shadow-2xl group-hover:shadow-[#c5a059]/20">
                        <i class="fas fa-clock text-[#c5a059] text-2xl group-hover:text-white transition-colors duration-500"></i>
                    </div>
                    <h4 class="font-black uppercase tracking-[0.3em] mb-3 text-[11px] text-[#c5a059]">Açılış</h4>
                    <p class="text-xs font-bold bg-white/5 px-4 py-2 rounded-full border border-white/10 group-hover:border-[#c5a059]/30 transition-all duration-500 text-center">
                        <?= e($footerHours) ?>
                    </p>
                </div>
            </div>

            <!-- Bottom Copyright Area -->
            <div class="pt-10 border-t border-white/5 flex flex-col items-center gap-3">
                <a href="https://knot.software" target="_blank">
                    <img src="<?= SITE_URL ?>/assets/img/knot-logo.png" alt="Knot Software" class="h-10 sm:h-12 object-contain opacity-40 grayscale hover:grayscale-0 hover:opacity-70 transition-all duration-500" onerror="this.parentElement.style.display='none'">
                </a>
                <p class="text-[9px] sm:text-[10px] font-bold tracking-[0.25em] uppercase text-white/25">
                    Knot Software · Tüm Hakları Saklıdır
                </p>
            </div>
        </div>
    </footer>

    <!-- Product Detail Modal -->
    <div id="productModal" class="fixed inset-0 z-[10000] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeProductModal()"></div>
        <div class="absolute bottom-0 left-0 right-0 sm:inset-0 sm:flex sm:items-center sm:justify-center p-0 sm:p-4">
            <div class="bg-white w-full max-w-lg sm:rounded-[32px] rounded-t-[32px] overflow-hidden shadow-2xl transform transition-all duration-300 translate-y-full sm:translate-y-0 sm:scale-95 opacity-0" id="modalContent">
                <!-- Close Button (Mobile) -->
                <div class="sm:hidden absolute top-4 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-gray-200 rounded-full z-20"></div>
                
                <div class="relative max-h-[90vh] overflow-y-auto" id="modalScrollableContent">
                    <!-- Image Area -->
                    <div class="relative aspect-square sm:aspect-video bg-gray-100">
                        <img id="modalProductImage" src="" alt="" class="w-full h-full object-cover">
                        <button onclick="closeProductModal()" class="absolute top-6 right-6 w-10 h-10 bg-white/90 backdrop-blur-md rounded-full flex items-center justify-center text-[#2d4433] shadow-lg transition-transform active:scale-90 z-10">
                <i class="fas fa-times"></i>
            </button>
        </div>

                    <!-- Content Area -->
                    <div class="p-8 sm:p-10">
                        <div class="mb-6">
                            <span id="modalCategoryName" class="text-[#c5a059] text-[10px] sm:text-xs font-black uppercase tracking-[0.2em] mb-2 block">Kategori</span>
                            <h2 id="modalProductName" class="text-2xl sm:text-3xl font-black text-[#2d4433] mb-4">Ürün Adı</h2>
                            <div class="w-12 h-1 bg-[#c5a059] rounded-full"></div>
        </div>

                        <div class="flex items-center justify-between mb-8 pb-8 border-b border-gray-100">
                            <span class="text-xs font-bold text-[#6b7a6e] uppercase tracking-widest">Fiyat</span>
                            <span id="modalProductPrice" class="text-3xl font-black text-[#2d4433]">0.00 ₺</span>
    </div>

                        <!-- Ekstra Seçenekler Alanı -->
                        <div id="modalExtrasArea" class="space-y-4 mb-8 hidden">
                            <h3 class="text-[10px] font-black text-[#2d4433] uppercase tracking-widest">Ekstra Seçenekler</h3>
                            <div id="modalExtrasList" class="grid grid-cols-1 gap-2">
                                <!-- Ekstralar buraya gelecek -->
                                </div>
                    </div>

                        <div id="modalDescriptionArea" class="space-y-4 mb-6">
                            <h3 class="text-[10px] font-black text-[#2d4433] uppercase tracking-widest">Ürün Hakkında</h3>
                            <p id="modalProductDescription" class="text-[#6b7a6e] text-sm sm:text-base leading-relaxed font-medium">
                                Ürün açıklaması buraya gelecek.
                            </p>
                        </div>

                        <!-- Gramaj & Kalori -->
                        <div id="modalNutritionArea" class="hidden mb-6">
                            <div class="grid grid-cols-2 gap-3">
                                <div id="modalGramajBox" class="hidden bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Gramaj</p>
                                    <p id="modalGramaj" class="text-sm font-black text-[#2d4433]">—</p>
                                </div>
                                <div id="modalKaloriBox" class="hidden bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Kalori</p>
                                    <p id="modalKalori" class="text-sm font-black text-[#2d4433]">—</p>
                                </div>
                            </div>
                        </div>

                        <!-- İçindekiler -->
                        <div id="modalIcindekilerArea" class="hidden mb-5">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">İçindekiler</p>
                            <p id="modalIcindekiler" class="text-[#6b7a6e] text-xs sm:text-sm leading-relaxed font-medium"></p>
                        </div>

                        <!-- Alerjenler -->
                        <div id="modalAlerjenArea" class="hidden mb-5">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Alerjenler</p>
                            <p id="modalAlerjen" class="text-[#6b7a6e] text-xs sm:text-sm leading-relaxed font-medium"></p>
                        </div>

                        <!-- Bilgilendirme -->
                        <div id="modalBilgilendirmeArea" class="hidden mb-2">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Bilgilendirme</p>
                            <p id="modalBilgilendirme" class="text-[#6b7a6e] text-xs sm:text-sm leading-relaxed font-medium"></p>
                        </div>
                    </div>
                    </div>
                        </div>
                    </div>
                        </div>

    <script>
    async function openProductModal(id) {
        const modal = document.getElementById('productModal');
        const modalContent = document.getElementById('modalContent');
        
        // Reset modal state
        modalContent.classList.add('translate-y-full', 'sm:scale-95', 'opacity-0');
                
                try {
            const response = await fetch(`menu?ajax_product_detail=1&id=${id}`);
            const product = await response.json();
                    
            if (product.error) throw new Error(product.error);

            // Verileri yerleştir
            document.getElementById('modalProductImage').src = product.image_url;
            document.getElementById('modalProductName').textContent = product.name;
            document.getElementById('modalCategoryName').textContent = product.category_name || 'Menü';
            document.getElementById('modalProductPrice').textContent = product.formatted_price;
            
            const desc = document.getElementById('modalProductDescription');
            if (product.description && product.description.trim() !== '') {
                desc.innerHTML = product.description.replace(/\n/g, '<br>');
                document.getElementById('modalDescriptionArea').style.display = 'block';
                    } else {
                document.getElementById('modalDescriptionArea').style.display = 'none';
            }

            // Ekstraları işle
            const extrasArea = document.getElementById('modalExtrasArea');
            const extrasList = document.getElementById('modalExtrasList');
            extrasList.innerHTML = '';
            
            try {
                if (product.extras) {
                    const extras = JSON.parse(product.extras);
                    if (Array.isArray(extras) && extras.length > 0) {
                        extrasArea.classList.remove('hidden');
                        extras.forEach(extra => {
                            const extraItem = document.createElement('div');
                            extraItem.className = 'flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100';
                            extraItem.innerHTML = `
                                <span class="text-xs font-bold text-[#2d4433]">${extra.name}</span>
                                <span class="text-xs font-black text-[#c5a059]">+${extra.price} ₺</span>
                            `;
                            extrasList.appendChild(extraItem);
                        });
                    } else {
                        extrasArea.classList.add('hidden');
                    }
                } else {
                    extrasArea.classList.add('hidden');
                }
            } catch(e) {
                console.error('Extras parsing error:', e);
                extrasArea.classList.add('hidden');
            }

            // Gramaj & Kalori
            const hasGramaj = product.gramaj && product.gramaj.trim() !== '';
            const hasKalori = product.kalori && product.kalori.trim() !== '';
            const gramajBox = document.getElementById('modalGramajBox');
            const kaloriBox = document.getElementById('modalKaloriBox');
            const nutritionArea = document.getElementById('modalNutritionArea');
            if (hasGramaj) {
                document.getElementById('modalGramaj').textContent = product.gramaj;
                gramajBox.classList.remove('hidden');
            } else { gramajBox.classList.add('hidden'); }
            if (hasKalori) {
                document.getElementById('modalKalori').textContent = product.kalori;
                kaloriBox.classList.remove('hidden');
            } else { kaloriBox.classList.add('hidden'); }
            nutritionArea.classList.toggle('hidden', !hasGramaj && !hasKalori);

            // İçindekiler
            const icindekilerArea = document.getElementById('modalIcindekilerArea');
            if (product.icindekiler && product.icindekiler.trim() !== '') {
                document.getElementById('modalIcindekiler').textContent = product.icindekiler;
                icindekilerArea.classList.remove('hidden');
            } else { icindekilerArea.classList.add('hidden'); }

            // Alerjenler
            const alerjenArea = document.getElementById('modalAlerjenArea');
            if (product.alerjen && product.alerjen.trim() !== '') {
                document.getElementById('modalAlerjen').textContent = product.alerjen;
                alerjenArea.classList.remove('hidden');
            } else { alerjenArea.classList.add('hidden'); }

            // Bilgilendirme
            const bilgilendirmeArea = document.getElementById('modalBilgilendirmeArea');
            if (product.bilgilendirme && product.bilgilendirme.trim() !== '') {
                document.getElementById('modalBilgilendirme').textContent = product.bilgilendirme;
                bilgilendirmeArea.classList.remove('hidden');
            } else { bilgilendirmeArea.classList.add('hidden'); }

            // Modal'ı göster
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Animasyonlar
                    setTimeout(() => {
                modalContent.classList.remove('translate-y-full', 'sm:scale-95', 'opacity-0');
            }, 50);
            
            // Aşağı kaydırınca kapatma özelliği (Mobile)
            let touchStartY = 0;
            let touchStartScrollTop = 0;
            let isDragging = false;
            const scrollableContent = document.getElementById('modalScrollableContent');

            if (scrollableContent) {
                scrollableContent.addEventListener('touchstart', (e) => {
                    touchStartY = e.touches[0].clientY;
                    touchStartScrollTop = scrollableContent.scrollTop;
                    isDragging = false;
                }, { passive: true });
                
                scrollableContent.addEventListener('touchmove', (e) => {
                    // Sadece scroll en üstteyse ve aşağı kaydırıyorsa
                    if (touchStartScrollTop === 0 && scrollableContent.scrollTop === 0) {
                        const touchY = e.touches[0].clientY;
                        const deltaY = touchY - touchStartY;
                        
                        // Aşağı kaydırma
                        if (deltaY > 0) {
                            isDragging = true;
                            e.preventDefault(); // Scroll'u engelle
                            // Modal'ı aşağı kaydır
                            const dragAmount = Math.min(deltaY, 200);
                            modalContent.style.transform = `translateY(${dragAmount}px)`;
                            modalContent.style.transition = 'none';
                        }
                    }
                }, { passive: false });
                
                scrollableContent.addEventListener('touchend', (e) => {
                    if (isDragging) {
                        const touchY = e.changedTouches[0].clientY;
                        const deltaY = touchY - touchStartY;
                        
                        modalContent.style.transition = '';
                        
                        if (deltaY > 100) {
                            // Yeterince aşağı kaydırıldıysa kapat
                            closeProductModal();
                } else {
                            // Geri dön
                            modalContent.style.transform = '';
                }
            }
                    isDragging = false;
                }, { passive: true });
            }

                } catch (error) {
            console.error('Ürün detay hatası:', error);
            alert('Ürün detayları yüklenirken bir hata oluştu.');
                    }
            }

    function closeProductModal() {
        const modal = document.getElementById('productModal');
        const modalContent = document.getElementById('modalContent');

        modalContent.classList.add('translate-y-full', 'sm:scale-95', 'opacity-0');
        modalContent.style.transform = ''; // Touch event'ten kalan transform'u temizle
        
                    setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            modalContent.style.transform = ''; // Tekrar temizle
        }, 300);
    }
    </script>

    <?php if ($chatAssistantActive): ?>
    <?php include 'includes/chat-assistant-modal.php'; ?>
    <?php endif; ?>

    </div> <!-- End .main-wrapper -->
</body>
</html>
<?php
// Output buffer'ı temizle ve gönder
ob_end_flush();
?>
