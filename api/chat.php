<?php
// Hata raporlamayı aç (API için)
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSON çıktısı için display_errors kapalı
ini_set('log_errors', 1);

// Hata yakalama için output buffering başlat
ob_start();

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                          strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                          strpos($_SERVER['HTTP_HOST'] ?? '', 'testkurulum') !== false);
        
        echo json_encode([
            'success' => false,
            'error' => $isDevelopment 
                ? 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
                : 'Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.',
            'debug' => $isDevelopment ? $error : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Config dosyasını yükle
$configPath = __DIR__ . '/../includes/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Config dosyası bulunamadı: ' . $configPath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;

// Gerekli fonksiyonların tanımlı olduğunu kontrol et
if (!function_exists('getDB')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı fonksiyonu (getDB) bulunamadı'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('getProductImage')) {
    // Fallback fonksiyon
    function getProductImage($image, $placeholder = true) {
        if ($image && file_exists(__DIR__ . '/../assets/img/products/' . $image)) {
            return 'assets/img/products/' . $image;
        }
        return $placeholder ? 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=400' : '';
    }
}

if (!function_exists('formatPrice')) {
    // Fallback fonksiyon
    function formatPrice($price) {
        return number_format((float)$price, 2, '.', '') . ' ₺';
    }
}

// Chat fonksiyonları - try bloğundan önce tanımlanmalı
if (!function_exists('generateSmartResponse')) {
    function generateSmartResponse($pdo, $userMessage, $preferences) {
        $response = '';
        $categoryIds = $preferences['categories'] ?? [];
        
        if (!empty($categoryIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $stmt = $pdo->prepare("SELECT name FROM categories WHERE id IN ($placeholders)");
                $stmt->execute($categoryIds);
                $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($categories) > 1) {
                    $lastCat = array_pop($categories);
                    $categoryNames = implode(', ', $categories) . ' ve ' . $lastCat;
                    $response = "Harika seçimler! " . e($categoryNames) . " kategorilerimizden size özel hazırladığımız önerileri aşağıda bulabilirsiniz:";
            } else {
                    $categoryName = $categories[0] ?? 'bu kategori';
                    $response = "Harika bir seçim! " . e($categoryName) . " kategorimizde sizin için seçtiğimiz harika seçenekler var. Önerilerimi aşağıda bulabilirsiniz:";
                }
            } catch (Exception $e) {
                $response = "Harika bir seçim! Sizin için seçtiğimiz harika seçenekler var. Önerilerimi aşağıda bulabilirsiniz:";
            }
        } else {
            $response = "Anladım! Size uygun seçenekler önerebilirim. Önerilerimi aşağıda bulabilirsiniz:";
        }
        
        return $response;
    }
}

if (!function_exists('generateConversationalResponse')) {
    function generateConversationalResponse($userMessage) {
        return "Tam olarak anlayamadım, aşağıda bulunan kategorilere tıklayarak önerilerimizi öğrenebilirsiniz. 😊";
    }
}

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Veritabanı bağlantısını kontrol et
    try {
        $pdo = getDB();
        if (!$pdo) {
            throw new Exception('Veritabanı bağlantısı kurulamadı - getDB() null döndü');
        }
        
        // Basit bir test sorgusu çalıştır
        $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        throw new Exception('Veritabanı bağlantı hatası: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
    } catch (Exception $e) {
        throw new Exception('Veritabanı hatası: ' . $e->getMessage());
    }
    
    // Chat tablolarının varlığını kontrol et
    try {
        $tables = ['chat_assistant_settings', 'chat_responses'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                error_log("Chat API Warning: Table '$table' does not exist. Please run the SQL script to create it.");
            }
        }
    } catch (Exception $e) {
        error_log('Chat API Table Check Error: ' . $e->getMessage());
        // Tablo kontrolü başarısız olsa bile devam et
    }
    
    // Kullanıcı mesajını al
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('Mesaj verisi alınamadı');
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON verisi: ' . json_last_error_msg());
    }
    
    $userMessage = isset($input['message']) ? trim(mb_strtolower($input['message'], 'UTF-8')) : '';
    
    // Türkçe karakter düzeltmesi (İ -> i, I -> ı vb.)
    $search = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'i̇'];
    $replace = ['i', 'g', 'u', 's', 'o', 'c', 'i'];
    $userMessageNormalized = str_replace($search, $replace, $userMessage);
    
    // Asistan ayarlarını al
    $settings = null;
    try {
        // Önce tablonun varlığını kontrol et
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'chat_assistant_settings'");
        if ($tableCheck->rowCount() > 0) {
            // Tablo varsa ayarları al - is_active kontrolünü burada yapmıyoruz, aşağıda yapacağız
            $stmt = $pdo->prepare("SELECT * FROM chat_assistant_settings LIMIT 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            error_log('Chat API: chat_assistant_settings table does not exist');
            $settings = null;
        }
    } catch (PDOException $e) {
        // Tablo yoksa veya hata varsa varsayılan ayarları kullan
        error_log('Chat settings error: ' . $e->getMessage());
        $settings = null;
    } catch (Exception $e) {
        // Genel hata durumu
        error_log('Chat settings general error: ' . $e->getMessage());
        $settings = null;
    }
    
    // Eğer ayar yoksa veya asistan pasifse hata döndür
    if (!$settings || !is_array($settings)) {
        ob_clean();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Chat asistanı şu anda aktif değil.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Asistan aktif değilse hata döndür
    if (!isset($settings['is_active']) || $settings['is_active'] != 1) {
        ob_clean();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Chat asistanı şu anda aktif değil.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Varsayılan değerleri ayarla
    if (empty($settings['assistant_name'])) {
        $settings['assistant_name'] = 'Valéntino Patisserié Akıllı Menü Asistanı';
    }
    if (empty($settings['welcome_message'])) {
        $settings['welcome_message'] = 'Merhaba! Ben Valéntino Patisserié Akıllı Menü Asistanı. Size menümüzden ürünler önerebilirim, kategoriler hakkında bilgi verebilirim veya size özel öneriler sunabilirim. Nasıl yardımcı olabilirim? 😊';
    }
    
    // Chatbot yanıtlarını al (öncelik sırasına göre)
    $responses = [];
    try {
        // Önce tablonun varlığını kontrol et
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'chat_responses'");
        if ($tableCheck->rowCount() > 0) {
            // Tablo varsa yanıtları al
            $stmt = $pdo->prepare("SELECT * FROM chat_responses WHERE is_active = 1 ORDER BY priority DESC");
            $stmt->execute();
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            error_log('Chat API: chat_responses table does not exist');
            $responses = [];
        }
    } catch (PDOException $e) {
        // Tablo yoksa veya hata varsa boş array kullan
        error_log('Chat responses error: ' . $e->getMessage());
        $responses = [];
    } catch (Exception $e) {
        // Genel hata durumu
        error_log('Chat responses general error: ' . $e->getMessage());
        $responses = [];
    }
    
    $botResponse = null;
    $responseType = 'text';
    $suggestedProducts = [];
    $suggestedCategories = [];
    
    // Kullanıcı mesajını kelimelere ayır
    $userWords = preg_split('/\s+/', $userMessage);
    $userWords = array_filter($userWords, function($word) {
        return mb_strlen($word) > 2; // 2 karakterden uzun kelimeler
    });
    
    $bestMatch = null;
    $bestScore = 0;
    
    // Gelişmiş anahtar kelime kontrolü
    foreach ($responses as $response) {
        // Response array kontrolü
        if (!is_array($response) || !isset($response['keyword'])) {
            continue;
        }
        
        $keyword = mb_strtolower(trim($response['keyword'] ?? ''), 'UTF-8');
        if (empty($keyword)) {
            continue;
        }
        
        $keywordNormalized = str_replace($search, $replace, $keyword);
        
        $keywordsJson = $response['keywords'] ?? '[]';
        $keywords = [];
        if (!empty($keywordsJson) && is_string($keywordsJson)) {
            $decoded = json_decode($keywordsJson, true);
            $keywords = is_array($decoded) ? $decoded : [];
        }
        
        $score = 0;
        
        // Tam eşleşme kontrolü (en yüksek öncelik)
        if ($userMessage === $keyword || $userMessageNormalized === $keywordNormalized) {
            $score = 100;
        }
        // Ana anahtar kelime kontrolü
        elseif (strpos($userMessage, $keyword) !== false || strpos($userMessageNormalized, $keywordNormalized) !== false) {
            $score = 80;
        }
        // Kelime bazlı eşleşme
        elseif (in_array($keyword, $userWords) || in_array($keywordNormalized, $userWords)) {
            $score = 70;
        }
        
        // Alternatif anahtar kelimeler kontrolü
        if (is_array($keywords)) {
            foreach ($keywords as $altKeyword) {
                if (!is_string($altKeyword)) {
                    continue;
                }
                $altKeyword = mb_strtolower(trim($altKeyword), 'UTF-8');
                if (empty($altKeyword)) {
                    continue;
                }
                $altKeywordNormalized = str_replace($search, $replace, $altKeyword);
                
                if ($userMessage === $altKeyword || $userMessageNormalized === $altKeywordNormalized) {
                    $score = 95;
                    break;
                } elseif (strpos($userMessage, $altKeyword) !== false || strpos($userMessageNormalized, $altKeywordNormalized) !== false) {
                    $score = max($score, 75);
                } elseif (in_array($altKeyword, $userWords) || in_array($altKeywordNormalized, $userWords)) {
                    $score = max($score, 65);
                }
            }
        }
        
        // En iyi eşleşmeyi bul
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $response;
        }
    }
    
    // Kullanıcı tercihlerini analiz et
    $userPreferences = [
        'categories' => [], // Çoklu kategori desteği
        'taste' => null, // tatlı, tuzlu, acı, vs.
        'temperature' => null, // sıcak, soğuk
        'price_range' => null, // ucuz, orta, pahalı
        'time' => null, // sabah, öğle, akşam
        'mood' => null // enerjik, rahatlatıcı, vs.
    ];
    
    // Kategorileri veritabanından çek ve eşleştir
    try {
        $stmt = $pdo->query("SELECT id, name, synonyms FROM categories WHERE is_active = 1");
        $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allCategories as $cat) {
            $catName = mb_strtolower(trim($cat['name']), 'UTF-8');
            $catNameNormalized = str_replace($search, $replace, $catName);
            
            // 1. Kategori adı ile eşleşme (Tam veya Kısmi)
            // a) Tam eşleşme
            if ($userMessage === $catName || $userMessageNormalized === $catNameNormalized) {
                if (!in_array($cat['id'], $userPreferences['categories'])) {
                    $userPreferences['categories'][] = $cat['id'];
                }
            }
            // b) Kullanıcı mesajı kategori adında var mı? (örn: "filtre" kelimesi "Filtre Kahveler" kategorisinde)
            elseif (mb_strlen($userMessage) >= 3 && (strpos($catName, $userMessage) !== false || strpos($catNameNormalized, $userMessageNormalized) !== false)) {
                if (!in_array($cat['id'], $userPreferences['categories'])) {
                    $userPreferences['categories'][] = $cat['id'];
                }
            }
            // c) Kategori adı kullanıcı mesajında var mı? (örn: "filtre kahveler" kelimesi "filtre kahve ne var" mesajında)
            elseif (mb_strlen($catName) >= 3 && (strpos($userMessage, $catName) !== false || strpos($userMessageNormalized, $catNameNormalized) !== false)) {
                if (!in_array($cat['id'], $userPreferences['categories'])) {
                    $userPreferences['categories'][] = $cat['id'];
                }
            }
            
            // 2. Eş anlamlılar (synonyms) ile eşleşme
            if (!empty($cat['synonyms'])) {
                $synonyms = explode(',', mb_strtolower($cat['synonyms'], 'UTF-8'));
                foreach ($synonyms as $syn) {
                    $syn = trim($syn);
                    if (!empty($syn)) {
                        $synNormalized = str_replace($search, $replace, $syn);
                        
                        if (strpos($userMessage, $syn) !== false || strpos($userMessageNormalized, $synNormalized) !== false) {
                            if (!in_array($cat['id'], $userPreferences['categories'])) {
                                $userPreferences['categories'][] = $cat['id'];
                            }
                        }
                    }
                }
            }
            
            // 3. Alternatif isimler (tekil/çoğul vb.)
            $singular = preg_replace('/(lar|ler)$/u', '', $catName);
            $singularNormalized = str_replace($search, $replace, $singular);
            if (mb_strlen($singular) >= 3 && (strpos($userMessage, $singular) !== false || strpos($userMessageNormalized, $singularNormalized) !== false)) {
                if (!in_array($cat['id'], $userPreferences['categories'])) {
                    $userPreferences['categories'][] = $cat['id'];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Category detection error: ' . $e->getMessage());
    }
    
    // Ürün isimlerini ve keywords'lerini kontrol et
    // NOT: Eğer kategori tespit edildiyse, sadece o kategorideki ürünleri eşleştir
    $matchedProductIds = [];
    try {
        $stmt = $pdo->query("SELECT id, name, category_id, keywords FROM products WHERE is_active = 1");
        $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allProducts as $product) {
            // Kategori filtresi: Eğer kategori tespit edildiyse, sadece o kategorideki ürünleri kontrol et
            if (!empty($userPreferences['categories']) && !in_array($product['category_id'], $userPreferences['categories'])) {
                continue; // Bu ürünü atla, farklı kategoriden
            }
            
            $productName = mb_strtolower(trim($product['name']), 'UTF-8');
            $productNameNormalized = str_replace($search, $replace, $productName);
            
            // 1. Ürün adı ile eşleşme
            if (strpos($userMessage, $productName) !== false || strpos($userMessageNormalized, $productNameNormalized) !== false) {
                $matchedProductIds[] = $product['id'];
                if (!in_array($product['category_id'], $userPreferences['categories'])) {
                    $userPreferences['categories'][] = $product['category_id'];
                }
            }
            
            // 2. Ürün keywords ile eşleşme
            if (!empty($product['keywords'])) {
                $keywords = explode(',', mb_strtolower($product['keywords'], 'UTF-8'));
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (!empty($keyword)) {
                        $keywordNormalized = str_replace($search, $replace, $keyword);
                        
                        if (strpos($userMessage, $keyword) !== false || strpos($userMessageNormalized, $keywordNormalized) !== false) {
                            $matchedProductIds[] = $product['id'];
                            if (!in_array($product['category_id'], $userPreferences['categories'])) {
                                $userPreferences['categories'][] = $product['category_id'];
                            }
                            break;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Product detection error: ' . $e->getMessage());
    }
    
    // Manuel anahtar kelime eşleştirmesi (İngilizce ve özel kelimeler için - her zaman kontrol et)
    // NOT: Bu kontrol yukarıdaki kategori tespitinden sonra çalışır, sadece eksik kalan durumlar için
    $manualCategoryKeywords = [
        'coffee' => 'kahve', 'espresso' => 'kahve', 'latte' => 'kahve',
        'drink' => 'içecek', 'cold' => 'soğuk', 'iced' => 'soğuk',
        'dessert' => 'tatlı', 'sweet' => 'tatlı', 'şekerli' => 'tatlı',
        'breakfast' => 'kahvaltı',
        'tea' => 'çay',
        'türk' => 'türk kahve', 'turkish' => 'türk kahve', 'geleneksel' => 'türk kahve',
        'filtre' => 'filtre', 'drip' => 'filtre', 'filter' => 'filtre',
        'avrupa' => 'dünya kahve', 'dünya' => 'dünya kahve',
        'pasta' => 'tatlı', 'kek' => 'tatlı', 'kurabiye' => 'cookie',
        'cookie' => 'cookie', 'cooky' => 'cookie', 'kukie' => 'cookie'
    ];
    
    foreach ($manualCategoryKeywords as $keyword => $categoryKeyword) {
        if (strpos($userMessage, $keyword) !== false) {
            foreach ($allCategories as $cat) {
                $catNameLower = mb_strtolower($cat['name']);
                if (strpos($catNameLower, $categoryKeyword) !== false) {
                    if (!in_array($cat['id'], $userPreferences['categories'])) {
                        $userPreferences['categories'][] = $cat['id'];
                    }
                }
            }
        }
    }
    
    // Tat tercihi
    if (strpos($userMessage, 'tatlı') !== false || strpos($userMessage, 'sweet') !== false) {
        $userPreferences['taste'] = 'sweet';
    } elseif (strpos($userMessage, 'tuzlu') !== false || strpos($userMessage, 'savory') !== false) {
        $userPreferences['taste'] = 'savory';
    }
    
    // Sıcaklık tercihi
    if (strpos($userMessage, 'sıcak') !== false || strpos($userMessage, 'hot') !== false) {
        $userPreferences['temperature'] = 'hot';
    } elseif (strpos($userMessage, 'soğuk') !== false || strpos($userMessage, 'cold') !== false || strpos($userMessage, 'iced') !== false) {
        $userPreferences['temperature'] = 'cold';
    }
    
    // Fiyat aralığı
    if (strpos($userMessage, 'ucuz') !== false || strpos($userMessage, 'cheap') !== false || strpos($userMessage, 'ekonomik') !== false) {
        $userPreferences['price_range'] = 'low';
    } elseif (strpos($userMessage, 'pahalı') !== false || strpos($userMessage, 'expensive') !== false || strpos($userMessage, 'premium') !== false) {
        $userPreferences['price_range'] = 'high';
    }
    
    // Eşleşme bulunduysa kullan
    if ($bestMatch && $bestScore >= 50) {
        $botResponse = $bestMatch['response'];
        $responseType = $bestMatch['response_type'];
        
        // Eğer veritabanındaki yanıtta bir kategori ID'si tanımlıysa, onu tercihlere ekle
        if (!empty($bestMatch['category_id'])) {
            if (!in_array($bestMatch['category_id'], $userPreferences['categories'])) {
                $userPreferences['categories'][] = $bestMatch['category_id'];
            }
        }
        
        // "Mesaj olarak önermesin" kuralı: Eğer mesajda liste varsa veya ürün isimleri geçiyorsa temizle veya daha genel hale getir
        // Özellikle "öner", "tavsiye", "popüler" gibi anahtar kelimelerde mesajı sadeleştir
        $genericKeywords = ['öner', 'tavsiye', 'popüler', 'en iyi', 'en popüler', 'en çok satan', 'ne var', 'ne önerirsin', 'ne tavsiye edersin', 'en güzel ne var', 'en iyi ne var'];
        if (in_array($bestMatch['keyword'], $genericKeywords)) {
            $botResponse = "Sizin için seçtiğimiz özel lezzetleri aşağıda bulabilirsiniz. Hangisini denemek istersiniz? 😊";
        }
    } else {
        // ÇOK KAPSAMLI MENÜ KELİMELERİ LİSTESİ - Menü/içecek ile ilgili TÜM kelimeler
        $hasMenuKeyword = false;
        $menuKeywords = [
            // Genel Menü Terimleri
            'menü', 'menu', 'ürün', 'product', 'kategori', 'category', 'lezzet', 'taste', 
            'fiyat', 'price', 'ne var', 'ne öner', 'tavsiye', 'öner', 'popüler', 'popular',
            'ne kadar', 'kaç para', 'ücret', 'cost', 'satın al', 'buy', 'sipariş', 'order',
            
            // Kahve ve Kahve Çeşitleri
            'kahve', 'coffee', 'espresso', 'latte', 'cappuccino', 'americano', 'macchiato', 
            'mocha', 'filtre kahve', 'filtre', 'drip coffee', 'türk kahvesi', 'turkish coffee',
            'türk kahve', 'caffe', 'cafe', 'barista', 'çekirdek', 'bean', 'demleme', 'brew',
            'double shot', 'single shot', 'sütlü kahve', 'sütsüz kahve', 'sade kahve',
            
            // İçecekler (Sıcak ve Soğuk)
            'içecek', 'drink', 'beverage', 'sıcak', 'hot', 'soğuk', 'cold', 'iced', 'buzlu',
            'cold brew', 'iced americano', 'smoothie', 'limonata', 'lemonade', 'meyve suyu',
            'juice', 'soda', 'gazlı', 'fizzy', 'serinletici', 'refreshing',
            
            // Çaylar
            'çay', 'tea', 'siyah çay', 'black tea', 'yeşil çay', 'green tea', 'bitki çayı',
            'herbal tea', 'chai', 'chai latte', 'papatya çayı', 'chamomile', 'ada çayı',
            'sage tea', 'ıhlamur', 'linden', 'kuşburnu', 'rosehip',
            
            // Sıcak İçecekler
            'sıcak çikolata', 'hot chocolate', 'sıcak içecek', 'hot drink', 'sıcak içecekler',
            
            // Tatlılar
            'tatlı', 'dessert', 'sweet', 'cheesecake', 'san sebastian', 'tiramisu', 'pasta',
            'cake', 'kurabiye', 'cookie', 'biscuit', 'waffle', 'vafli', 'kek', 'brownie',
            'muffin', 'croissant', 'kruvasan', 'çikolata', 'chocolate', 'şeker', 'sugar',
            
            // Kahvaltı ve Yemekler
            'kahvaltı', 'breakfast', 'toast', 'avokado', 'avocado', 'burger', 'gallant burger',
            'menemen', 'omlet', 'omelet', 'yumurta', 'egg', 'simit', 'peynir', 'cheese',
            'zeytin', 'olive', 'bal', 'honey', 'reçel', 'jam', 'tereyağı', 'butter',
            
            // Kruvasan ve Hamur İşleri
            'kruvasan', 'croissant', 'hamur işi', 'pastry', 'börek', 'borek', 'poğaça',
            'poğaca', 'açma', 'simit', 'bagel',
            
            // Atıştırmalıklar
            'atıştırmalık', 'snack', 'cips', 'chips', 'fındık', 'nut', 'badem', 'almond',
            'ceviz', 'walnut', 'fıstık', 'peanut',
            
            // Özel Durumlar ve Tercihler
            'vegan', 'vejetaryen', 'vegetarian', 'gluten free', 'glutensiz', 'şekersiz',
            'sugar free', 'diyabetik', 'diabetic', 'süt alternatifi', 'milk alternative',
            'laktozsuz', 'lactose free', 'organik', 'organic', 'taze', 'fresh', 'günlük',
            'daily', 'özel', 'special', 'premium', 'lüks', 'luxury',
            
            // Boyut ve Miktar
            'büyük', 'large', 'küçük', 'small', 'orta', 'medium', 'ekstra', 'extra',
            'double', 'tek', 'single', 'çift', 'porsiyon', 'portion', 'servis', 'serving',
            
            // Sıcaklık ve Hazırlık
            'sıcak', 'hot', 'soğuk', 'cold', 'ılık', 'warm', 'buzlu', 'iced', 'sıcacık',
            'piping hot', 'serin', 'cool', 'dondurulmuş', 'frozen',
            
            // Lezzet ve Tat
            'tatlı', 'sweet', 'tuzlu', 'savory', 'acı', 'spicy', 'ekşi', 'sour', 'acılı',
            'bitter', 'lezzetli', 'delicious', 'nefis', 'yummy', 'harika', 'great',
            
            // Popüler ve Önerilen
            'popüler', 'popular', 'en çok satan', 'best seller', 'önerilen', 'recommended',
            'günün önerisi', 'daily special', 'özel', 'special', 'yeni', 'new', 'taze',
            'fresh', 'günlük', 'daily',
            
            // Soru Kelimeleri (Menü ile ilgili)
            'ne var', 'what is', 'ne öner', 'what do you recommend', 'hangi', 'which',
            'kaç', 'how much', 'ne kadar', 'how many', 'nasıl', 'how', 'nerede', 'where',
            'ne zaman', 'when', 'hangi', 'which', 'ne tür', 'what kind', 'ne çeşit',
            'what variety', 'ne marka', 'what brand',
            
            // Genel Sohbet Kelimeleri (Menü bağlamında)
            'beğendim', 'i like', 'sevdim', 'i love', 'güzel', 'nice', 'harika', 'great',
            'mükemmel', 'perfect', 'lezzetli', 'delicious', 'öner', 'recommend', 'tavsiye',
            'suggest', 'alayım', 'i will take', 'istiyorum', 'i want', 'istersiniz', 'would you like'
        ];
        
        // Kullanıcı mesajında menü kelimesi var mı kontrol et
        foreach ($menuKeywords as $menuKeyword) {
            if (strpos($userMessage, $menuKeyword) !== false) {
                $hasMenuKeyword = true;
                break;
            }
        }
        
        // Eğer menü kelimesi YOKSA → Kesinlikle menüye yönlendir
        if (!$hasMenuKeyword && empty($userPreferences['categories'])) {
            // Menü dışı konu tespiti - Menü/içecek kelimesi yoksa direkt menüye yönlendir
            $botResponse = generateConversationalResponse($userMessage);
            $responseType = 'category_suggestion';
        } elseif ($hasMenuKeyword || !empty($userPreferences['categories'])) {
            // Kullanıcı bir kategori veya menü öğesi bahsetti
            $botResponse = generateSmartResponse($pdo, $userMessage, $userPreferences);
            $responseType = 'product_suggestion';
        } else {
            // Genel sohbet - kullanıcıya sorular sor ve menüye yönlendir
            $botResponse = generateConversationalResponse($userMessage);
            $responseType = 'category_suggestion';
        }
    }
    
    // Ürün/kategori önerileri - ÖNERİLEN ÜRÜNLER ÖNCELİKLİ
    // Not: Kullanıcı sadece kategori adı yazdığında bestScore 0 olabilir; yine de admin seçimi + kategori filtresi çalışsın.
    $shouldSuggestProducts = ($responseType === 'product_suggestion' || $responseType === 'category_suggestion')
        && (
            $bestScore >= 50
            || !empty($userPreferences['categories'])
            || !empty($matchedProductIds)
        );

    if ($shouldSuggestProducts) {
        // ÖNCELİK 1: Yönetim panelinde seçilen önerilen ürünler
        $recommendedProductIds = [];
        if (!empty($settings['recommended_products'])) {
            $recommendedProductIds = array_filter(array_map('intval', explode(',', $settings['recommended_products'])));
        }
        
        // Kullanıcı tercihlerine göre ürün öner
        try {
            // Products tablosunun varlığını kontrol et
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'products'");
            if ($tableCheck->rowCount() > 0) {
                // İKİ YÖNTEM: 1) Eşleşen ürünler (matchedProductIds), 2) Kategoriye göre admin seçili ürünler
                
                // ÖNCE: Direkt eşleşen ürünleri al (İsim veya keyword'e göre)
                if (!empty($matchedProductIds)) {
                    $uniqueMatched = array_values(array_unique($matchedProductIds));
                    $placeholders = implode(',', array_fill(0, count($uniqueMatched), '?'));
                    $query = "SELECT * FROM products WHERE is_active = 1 AND id IN ($placeholders) ORDER BY is_popular DESC LIMIT 12";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($uniqueMatched);
                    $suggestedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                // EĞER YETERSİZSE: Kategoriye göre admin seçili ürünleri ekle
                if (empty($suggestedProducts) && !empty($recommendedProductIds)) {
                    $query = "SELECT * FROM products WHERE is_active = 1 AND id IN (" . implode(',', array_fill(0, count($recommendedProductIds), '?')) . ")";
                    $params = $recommendedProductIds;
                    
                    // Kategori filtresi (Çoklu destek)
                    if (!empty($userPreferences['categories'])) {
                        $placeholders = implode(',', array_fill(0, count($userPreferences['categories']), '?'));
                        $query .= " AND category_id IN ($placeholders)";
                        foreach ($userPreferences['categories'] as $catId) {
                            $params[] = $catId;
                        }
                    }
                    
                    // Sıralama ve Limit
                    $query .= " ORDER BY FIELD(id, " . implode(',', $recommendedProductIds) . ") LIMIT 12";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $suggestedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                // POPÜLER ÜRÜN VEYA DİĞER FALLBACK'LER KALDIRILDI
                // Sadece adminin seçtiği ürünler veya direkt eşleşenler gösterilecek
            } else {
                error_log('Chat API: products table does not exist');
                $suggestedProducts = [];
            }
        } catch (Exception $e) {
            error_log('Smart product suggestion error: ' . $e->getMessage());
                $suggestedProducts = [];
            }
    }
    
    // KATEGORİ ÖNERİSİ - Her zaman göster (eşleşme olsun ya da olmasın)
    if ($responseType === 'product_suggestion' || $responseType === 'category_suggestion' || $bestScore < 50) {
        // Önerilecek kategoriler (Sabit Akış)
        try {
            // Categories tablosunun varlığını kontrol et
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'categories'");
            if ($tableCheck->rowCount() > 0) {
                // Sadece aktif kategorileri sıralı getir
                $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
                    $suggestedCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                error_log('Chat API: categories table does not exist');
                $suggestedCategories = [];
            }
        } catch (Exception $e) {
            error_log('Recommended categories error: ' . $e->getMessage());
            $suggestedCategories = [];
        }
    }
    
    // Ürün bilgilerini formatla
    foreach ($suggestedProducts as &$product) {
        try {
            $product['image_url'] = getProductImage($product['image'] ?? '', false);
            $product['price_formatted'] = formatPrice($product['price'] ?? 0);
            $product['url'] = 'product-detail?id=' . ($product['id'] ?? '');
        } catch (Exception $e) {
            $product['image_url'] = '';
            $product['price_formatted'] = '0.00 ₺';
            $product['url'] = '';
        }
    }
    
    // Kategori bilgilerini formatla
    foreach ($suggestedCategories as &$category) {
        try {
            $category['url'] = 'menu';
            $categoryImage = $category['image'] ?? '';
            $imagePath = __DIR__ . '/../assets/img/categories/' . $categoryImage;
            $hasImage = !empty($categoryImage) && file_exists($imagePath);
            $category['image_url'] = $hasImage ? 'assets/img/categories/' . $categoryImage : null;
            $category['name'] = $category['name'] ?? '';
        } catch (Exception $e) {
            $category['url'] = '';
            $category['image_url'] = null;
            $category['name'] = '';
        }
    }
    
    // Başarılı yanıt
    ob_clean(); // Output buffer'ı temizle
    echo json_encode([
        'success' => true,
        'response' => $botResponse,
        'response_type' => $responseType,
        'suggested_products' => $suggestedProducts,
        'suggested_categories' => $suggestedCategories
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
    
} catch (PDOException $e) {
    // Output buffer'ı temizle
    ob_clean();
    
    http_response_code(500);
    error_log('Chat API PDO Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    
    $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                      strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                      strpos($_SERVER['HTTP_HOST'] ?? '', 'testkurulum') !== false ||
                      defined('DEBUG') && DEBUG);
    
    $errorMessage = $isDevelopment 
        ? 'Veritabanı hatası: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
        : 'Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.';
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug' => $isDevelopment ? [
            'type' => 'PDOException',
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'code' => $e->getCode()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    // Output buffer'ı temizle
    ob_clean();
    
    http_response_code(500);
    error_log('Chat API Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
    
    $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                      strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                      strpos($_SERVER['HTTP_HOST'] ?? '', 'testkurulum') !== false ||
                      defined('DEBUG') && DEBUG);
    
    $errorMessage = $isDevelopment 
        ? $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')'
        : 'Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.';
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug' => $isDevelopment ? [
            'type' => 'Exception',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Error $e) {
    // Output buffer'ı temizle
    ob_clean();
    
    http_response_code(500);
    error_log('Chat API Fatal Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    
    $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                      strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                      strpos($_SERVER['HTTP_HOST'] ?? '', 'testkurulum') !== false ||
                      defined('DEBUG') && DEBUG);
    
    $errorMessage = $isDevelopment 
        ? 'Fatal Error: ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')'
        : 'Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.';
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug' => $isDevelopment ? [
            'type' => 'Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Beklenmeyen hatalar için catch-all
if (ob_get_level() > 0) {
    ob_clean();
}

// Output buffer'ı kapat
if (ob_get_level() > 0) {
    ob_end_flush();
}
