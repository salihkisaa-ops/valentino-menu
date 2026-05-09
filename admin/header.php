<?php
require_once '../includes/config.php';
requireAuth(); // Admin paneli koruması

$cafeName = getDisplayCafeName();
$adminName = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Yönetim Paneli | <?= e($cafeName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS (Modal için gerekli) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2d4433;
            --accent: #c5a059;
            --bg-body: #fbfcfb;
            --sidebar-width: 300px;
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-body);
            color: #1a1a1a;
            overflow-x: hidden;
        }
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: var(--sidebar-width);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            background: white;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s ease;
        }
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 20px 0 50px rgba(0,0,0,0.1);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.4);
                backdrop-filter: blur(4px);
                z-index: 999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .overlay.active {
                display: block;
                opacity: 1;
            }
        }
        .sidebar-link {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            border-radius: 12px;
            margin: 4px 16px;
            padding: 12px 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        .sidebar-link i {
            width: 20px;
            font-size: 1.1rem;
            text-align: center;
        }
        .sidebar-link:hover {
            background-color: #f8fafc;
            color: var(--primary);
        }
        .sidebar-link.active {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(45, 68, 51, 0.2);
        }
        .glass-card {
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 8px 24px rgba(0, 0, 0, 0.02);
            border-radius: 20px;
        }
        .stat-card {
            border-radius: 24px;
            padding: 24px;
            background: white;
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.05);
        }
        
        /* Mobil Tab Bar Alt Menü Fix */
        @media (max-width: 640px) {
            .mobile-header {
                height: 64px;
            }
            .main-content {
                padding-top: 0;
            }
            /* Table optimization for very small screens */
            table td {
                padding: 12px 8px !important;
            }
            table th {
                padding: 12px 8px !important;
            }
            .stat-card {
                padding: 16px;
            }
            .glass-card {
                padding: 16px !important;
            }
        }
    </style>
</head>
<body class="flex min-h-screen">
    <div class="overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar border-r border-slate-100 flex flex-col" id="sidebar">
        <div class="p-6 mb-2 flex items-start justify-between gap-2">
            <div class="flex items-start gap-3 min-w-0 flex-1">
                <div class="w-10 h-10 bg-[var(--primary)] rounded-xl flex items-center justify-center shadow-lg shadow-emerald-900/20 flex-shrink-0">
                    <i class="fas fa-leaf text-white text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="font-extrabold text-slate-800 text-[11px] sm:text-xs leading-snug tracking-normal break-words"><?= eu($cafeName) ?></h1>
                    <span class="text-[10px] font-bold text-slate-400 tracking-[0.2em]">Admin Panel</span>
                </div>
            </div>
            <button class="lg:hidden w-8 h-8 flex-shrink-0 flex items-center justify-center text-slate-400 hover:text-rose-500 transition-colors" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="flex-grow overflow-y-auto py-4 scrollbar-hide">
            <div class="px-8 mb-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest opacity-60">Genel</div>
            <a href="index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Panel Özeti</span>
            </a>
            
            <div class="px-8 mt-8 mb-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest opacity-60">İçerik Yönetimi</div>
            <a href="products.php" class="sidebar-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add-product.php', 'edit-product.php']) ? 'active' : ''; ?>">
                <i class="fas fa-coffee"></i>
                <span>Ürünler</span>
            </a>
            <a href="categories.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Kategoriler</span>
            </a>
            <a href="home-recommended.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'home-recommended.php' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Öne Çıkanlar</span>
            </a>
            <a href="category-tree.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'category-tree.php' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap"></i>
                <span>Sıralama</span>
            </a>
            
            <div class="px-8 mt-8 mb-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest opacity-60">Özel Araçlar</div>
            <a href="time-based-menu.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'time-based-menu.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Zamanlı Menü</span>
            </a>
            <a href="chat-assistant.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'chat-assistant.php' ? 'active' : ''; ?>">
                <i class="fas fa-robot"></i>
                <span>Akıllı Menü</span>
            </a>
            
            <div class="px-8 mt-8 mb-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest opacity-60">Sistem</div>
            <a href="settings.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>Genel Ayarlar</span>
            </a>
            <a href="appearance.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'appearance.php' ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Görünüm</span>
            </a>
            <a href="languages.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'languages.php' ? 'active' : ''; ?>">
                <i class="fas fa-language"></i>
                <span>Dil Ayarları</span>
            </a>
        </nav>

        <div class="p-6 border-t border-slate-50">
            <a href="logout.php" class="flex items-center space-x-3 p-4 rounded-xl text-rose-500 hover:bg-rose-50 transition-all font-bold text-sm">
                <i class="fas fa-power-off"></i>
                <span>Güvenli Çıkış</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content flex-grow flex flex-col min-h-screen">
        <!-- Top Navigation -->
        <header class="h-16 sm:h-20 bg-white/90 backdrop-blur-md border-b border-slate-100 flex items-center justify-between px-4 lg:px-10 sticky top-0 z-40">
            <div class="flex items-center">
                <button class="lg:hidden mr-3 w-10 h-10 flex items-center justify-center text-slate-600 hover:bg-slate-50 rounded-xl transition-colors" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <h2 class="text-slate-800 font-extrabold text-sm sm:text-base lg:text-lg truncate max-w-[150px] sm:max-w-none">
                <?php 
                    $page = basename($_SERVER['PHP_SELF']);
                        if($page == 'index.php') echo 'Panel Özeti';
                        elseif($page == 'products.php' || $page == 'add-product.php' || $page == 'edit-product.php') echo 'Ürünler';
                        elseif($page == 'categories.php') echo 'Kategoriler';
                        elseif($page == 'home-recommended.php') echo 'Öne Çıkanlar';
                        elseif($page == 'category-tree.php') echo 'Sıralama';
                    elseif($page == 'settings.php') echo 'Genel Ayarlar';
                        elseif($page == 'appearance.php') echo 'Görünüm';
                        elseif($page == 'chat-assistant.php') echo 'Akıllı Menü';
                        elseif($page == 'time-based-menu.php') echo 'Zamanlı Menü';
                        else echo 'Yönetim';
                ?>
            </h2>
            </div>
            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="../index.php" target="_blank" class="h-9 sm:h-11 flex items-center bg-slate-50 px-3 sm:px-4 rounded-xl text-[10px] sm:text-xs font-bold text-slate-500 hover:text-[var(--primary)] hover:bg-emerald-50 transition-all group">
                    <i class="fas fa-external-link-alt mr-2 group-hover:scale-110 transition-transform"></i> <span class="hidden sm:inline">Siteyi Gör</span>
                </a>
                <div class="h-8 w-px bg-slate-100 mx-1"></div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 font-bold border border-slate-200 shadow-sm">
                        <?= strtoupper(substr($adminName, 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    if (sidebar.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// Sayfa değişiminde sidebar'ı kapat (mobil için)
document.addEventListener('DOMContentLoaded', () => {
    if (window.innerWidth < 1024) {
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarOverlay').classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }
});
</script>
