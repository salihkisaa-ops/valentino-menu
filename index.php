<?php
require_once 'includes/config.php';

// Site ayarları
$cafeName = getDisplayCafeName();
$cafeTagline = getSetting('cafe_tagline', 'Coffee & More');
$welcomeLine1 = trim(getSetting('welcome_line_1', '')) ?: 'Hoş geldiniz';
$welcomeLine2 = trim(getSetting('welcome_line_2', '')) ?: 'Mutluluk olsun';
$heroVideo = getSetting('hero_video', 'gallant-video.webm');

// Gradyan renkleri
$gradientTop = getSetting('gradient_top', '#C1AE65');
$gradientBottom = getSetting('gradient_bottom', '#2D4434');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include __DIR__ . '/includes/google-analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($cafeName) ?> | Hoş Geldiniz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Dinamik Gradyan Renkleri */
        .main-wrapper {
            --gradient-top: <?= e($gradientTop) ?>;
            --gradient-bottom: <?= e($gradientBottom) ?>;
        }
        
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        .video-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            background-color: #000;
        }

        video {
            min-width: 100%;
            min-height: 100%;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            object-fit: cover;
            object-position: center;
            -webkit-object-fit: cover;
            -webkit-object-position: center;
            background-color: #000;
        }
        
        /* Mobil cihazlar için ekstra optimizasyon */
        @media (max-width: 768px) {
            .video-container {
                position: fixed;
            }
            
            video {
                width: 100vw;
                height: 100vh;
                min-width: 100%;
                min-height: 100%;
            }
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.7));
            z-index: 1;
        }

        .content {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            padding: 20px;
            max-width: 100%;
            margin: 0;
            background: rgba(0,0,0,0.2); /* Hafif karartma eklendi ki yazı daha iyi okunsun */
        }

        .logo-text {
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: 0.15em;
            margin-bottom: 1rem;
            text-shadow: 
                0 0 20px rgba(0,0,0,0.8),
                0 4px 15px rgba(0,0,0,0.6),
                0 8px 30px rgba(0,0,0,0.4),
                0 0 40px rgba(197, 160, 89, 0.3);
            animation: fadeInDown 1.2s ease-out, glowPulse 3s ease-in-out infinite 1.2s;
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            line-height: 1.2;
        }

        .logo-text::before {
            content: '';
            position: absolute;
            inset: -4px;
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.3), rgba(255, 255, 255, 0.1));
            border-radius: 20px;
            filter: blur(20px);
            z-index: -1;
            opacity: 0.6;
            animation: glowPulse 3s ease-in-out infinite 1.2s;
        }

        /* Eski .tagline artık kullanılmıyor — welcome-lines bunu karşılıyor */
        .tagline { display: none; }

        .welcome-lines {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            margin-bottom: 2.8rem;
            animation: fadeInUp 1.2s ease-out 0.4s both;
        }

        .welcome-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
        }

        .wline-text {
            font-size: clamp(0.7rem, 2.5vw, 0.95rem);
            font-weight: 800;
            letter-spacing: 0.22em;
            color: rgba(197, 160, 89, 0.97);
            text-shadow:
                0 0 14px rgba(197, 160, 89, 0.5),
                0 2px 8px rgba(0,0,0,0.55);
            white-space: nowrap;
        }

        .wline-bar {
            display: block;
            height: 1px;
            width: clamp(28px, 10vw, 80px);
            flex-shrink: 0;
        }
        .wline-bar--l { background: linear-gradient(to right,  transparent, rgba(197,160,89,0.9)); }
        .wline-bar--r { background: linear-gradient(to left, transparent, rgba(197,160,89,0.9)); }

        .cta-button {
            background: #c5a059;
            color: white;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(197, 160, 89, 0.4);
            background: #d4b16a;
        }

        .cta-button span {
            font-size: 1.2rem;
            line-height: 1;
            transition: transform 0.3s ease;
            margin-left: 8px;
        }

        .cta-button:hover span {
            transform: translateX(5px);
        }

        @media (max-width: 640px) {
            .logo-text {
                font-size: 2rem;
                letter-spacing: 0.1em;
                margin-bottom: 0.8rem;
            }


            .cta-button {
                padding: 14px 36px;
                font-size: 0.85rem;
                letter-spacing: 0.1em;
            }
        }

        @media (min-width: 1024px) {
            .logo-text {
                font-size: 3.5rem;
            }

            .cta-button {
                padding: 18px 48px;
                font-size: 1rem;
            }
        }

        @keyframes fadeInDown {
            from { 
                opacity: 0; 
                transform: translateY(-40px) scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        @keyframes scaleIn {
            from { 
                opacity: 0; 
                transform: scale(0.7) translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
        }

        @keyframes glowPulse {
            0%, 100% {
                text-shadow: 
                    0 0 20px rgba(0,0,0,0.8),
                    0 4px 15px rgba(0,0,0,0.6),
                    0 8px 30px rgba(0,0,0,0.4),
                    0 0 40px rgba(197, 160, 89, 0.3);
            }
            50% {
                text-shadow: 
                    0 0 30px rgba(0,0,0,0.9),
                    0 6px 20px rgba(0,0,0,0.7),
                    0 12px 40px rgba(0,0,0,0.5),
                    0 0 60px rgba(197, 160, 89, 0.5);
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
    <div class="video-container">
        <video id="bgVideo" 
               autoplay 
               muted 
               loop 
               playsinline 
               webkit-playsinline 
               preload="auto"
               x-webkit-airplay="allow">
            <?php 
            // Video dosya yollarını kontrol et
            // Hero video ayarı .webm veya .mp4 ile bitiyor olabilir
            $videoBaseName = preg_replace('/\.(webm|mp4)$/i', '', $heroVideo);
            
            $videoMp4 = __DIR__ . '/assets/img/' . $videoBaseName . '.mp4';
            $videoWebm = __DIR__ . '/assets/img/' . $videoBaseName . '.webm';
            
            $hasMp4 = file_exists($videoMp4);
            $hasWebm = file_exists($videoWebm);
            
            // Mobil için önce MP4 (iOS Safari uyumluluğu için - ÖNEMLİ!)
            if ($hasMp4):
            ?>
            <source src="assets/img/<?= e($videoBaseName) ?>.mp4" type="video/mp4">
            <?php endif; ?>
            
            <?php if ($hasWebm): ?>
            <!-- WebM formatı (Chrome, Firefox, Edge için) -->
            <source src="assets/img/<?= e($videoBaseName) ?>.webm" type="video/webm">
            <?php endif; ?>
            
            <?php if (!$hasMp4 && !$hasWebm): ?>
            <!-- Video dosyası bulunamadı -->
            <p style="color: white; text-align: center; padding: 20px;">Video dosyası bulunamadı.</p>
            <?php endif; ?>
        </video>
        <div class="overlay"></div>
    </div>

    <script>
        // Mobil cihazlar için gelişmiş video yükleme ve hata yönetimi
        (function() {
            var video = document.getElementById('bgVideo');
            var container = document.querySelector('.video-container');
            var videoLoaded = false;
            var attempts = 0;
            var maxAttempts = 3;
            
            // Video hatasını logla
            function logVideoError(error, context) {
                console.log("Video " + context + ":", error);
                if (video.error) {
                    console.log("Video error code:", video.error.code);
                    console.log("Video error message:", video.error.message);
                    
                    // Error code açıklamaları
                    var errorMessages = {
                        1: "MEDIA_ERR_ABORTED - Video yüklemesi kullanıcı tarafından durduruldu",
                        2: "MEDIA_ERR_NETWORK - Ağ hatası, video yüklenirken hata oluştu",
                        3: "MEDIA_ERR_DECODE - Video yüklenirken kod çözme hatası",
                        4: "MEDIA_ERR_SRC_NOT_SUPPORTED - Video formatı desteklenmiyor veya kaynak bulunamadı"
                    };
                    
                    if (errorMessages[video.error.code]) {
                        console.log("Hata açıklaması:", errorMessages[video.error.code]);
                    }
                }
            }
            
            // Video oynatma denemesi
            var playAttempt = function() {
                attempts++;
                
                // Video hazır mı kontrol et
                if (video.readyState >= 2) {
                    var playPromise = video.play();
                    
                    if (playPromise !== undefined) {
                        playPromise.then(function() {
                            // Başarılı
                            videoLoaded = true;
                            container.style.backgroundColor = 'transparent';
                            console.log("Video başarıyla oynatıldı.");
                        }).catch(function(error) {
                            // Autoplay engellendi veya başka bir hata
                            logVideoError(error, "autoplay hatası");
                            
                            // Kullanıcı etkileşimi için bekle
                            if (attempts < maxAttempts) {
                                console.log("Video oynatma denemesi:", attempts);
                            } else {
                                container.style.backgroundColor = '#000';
                            }
                        });
                    }
                } else {
                    // Video henüz hazır değil
                    if (attempts < maxAttempts) {
                        setTimeout(playAttempt, 500);
                    } else {
                        console.log("Video yükleme zaman aşımı.");
                        container.style.backgroundColor = '#000';
                    }
                }
            };
            
            // Video yükleme başarılı
            video.addEventListener('loadedmetadata', function() {
                console.log("Video metadata yüklendi.");
            });
            
            video.addEventListener('loadeddata', function() {
                console.log("Video data yüklendi.");
                playAttempt();
            });
            
            video.addEventListener('canplay', function() {
                console.log("Video oynatılmaya hazır.");
                if (!videoLoaded) {
                    playAttempt();
                }
            });
            
            video.addEventListener('canplaythrough', function() {
                console.log("Video kesintisiz oynatılmaya hazır.");
                if (!videoLoaded) {
                    playAttempt();
                }
            });
            
            // Video yükleme hatası
            video.addEventListener('error', function(e) {
                logVideoError(e, "yükleme hatası");
                
                // Desteklenen formatları kontrol et
                var sources = video.querySelectorAll('source');
                if (sources.length === 0) {
                    console.error("HATA: Video kaynağı bulunamadı. Lütfen MP4 formatında video ekleyin (iOS Safari için gerekli).");
                } else {
                    console.warn("HATA: Video formatları desteklenmiyor olabilir. iOS Safari için MP4 formatı gereklidir.");
                    sources.forEach(function(source, index) {
                        console.log("Kaynak " + (index + 1) + ":", source.src, "- Tip:", source.type);
                    });
                }
                
                container.style.backgroundColor = '#000';
            });
            
            // Video zorla yükle
            video.load();
            
            // Sayfa yüklendiğinde video oynatmayı dene
            window.addEventListener('load', function() {
                console.log("Sayfa yüklendi, video oynatma deneniyor...");
                setTimeout(playAttempt, 300);
            });
            
            // DOMContentLoaded'da da dene
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    console.log("DOM yüklendi, video oynatma deneniyor...");
                    setTimeout(playAttempt, 200);
                });
            } else {
                setTimeout(playAttempt, 200);
            }
            
            // Kullanıcı etkileşimi sonrası video oynat (mobil için - önemli!)
            var touchHandler = function(e) {
                if (video.paused || !videoLoaded) {
                    console.log("Kullanıcı etkileşimi algılandı, video oynatılıyor...");
                    playAttempt();
                }
            };
            
            // İlk dokunma için dinle
            document.addEventListener('touchstart', touchHandler, { passive: true, once: true });
            document.addEventListener('click', touchHandler, { once: true });
            
            // Sayfa görünür olduğunda video oynat
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && video.paused && !videoLoaded) {
                    playAttempt();
                }
            });
            
            // Intersection Observer ile video görünür olduğunda oynat
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting && video.paused && !videoLoaded) {
                            playAttempt();
                        }
                    });
                }, { threshold: 0.1 });
                observer.observe(video);
            }
        })();
    </script>

    <div class="content">
        <h1 class="logo-text"><?= eu($cafeName) ?></h1>

        <!-- Altın çizgili iki satır -->
        <div class="welcome-lines">
            <div class="welcome-line">
                <span class="wline-bar wline-bar--l"></span>
                <span class="wline-text"><?= eu($welcomeLine1) ?></span>
                <span class="wline-bar wline-bar--r"></span>
            </div>
            <div class="welcome-line">
                <span class="wline-bar wline-bar--l"></span>
                <span class="wline-text"><?= eu($welcomeLine2) ?></span>
                <span class="wline-bar wline-bar--r"></span>
            </div>
        </div>

        <a href="menu" class="cta-button">
                MENU<span style="font-weight: 900;">→</span>
        </a>
        </div>
    </div>
</body>
</html>
