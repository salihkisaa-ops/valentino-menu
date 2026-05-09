<?php
require_once '../includes/config.php';

// Zaten giriş yapılmışsa yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = null;

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalize_admin_login_field($_POST['username'] ?? '');
    $password = normalize_admin_login_field($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    
    if (admin_login_matches($username, $password)) {
        // Session zaten config.php'de başlatılıyor
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        
        // Beni hatırla
        if ($remember) {
            setcookie('admin_remember', base64_encode($username . ':' . $password), time() + (86400 * 30), '/', '', false, true);
        }
        
        // Basit relative URL kullan
        header('Location: index.php');
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı!';
    }
}

// Cookie'den otomatik giriş
if (isset($_COOKIE['admin_remember']) && !isset($_SESSION['admin_logged_in'])) {
    $rememberData = base64_decode($_COOKIE['admin_remember']);
    $colonPos = strpos($rememberData, ':');
    if ($colonPos !== false) {
        $cookieUsername = substr($rememberData, 0, $colonPos);
        $cookiePassword = substr($rememberData, $colonPos + 1);
    } else {
        $cookieUsername = '';
        $cookiePassword = '';
    }

    $cookieUsername = normalize_admin_login_field($cookieUsername);
    $cookiePassword = normalize_admin_login_field($cookiePassword);

    if (admin_login_matches($cookieUsername, $cookiePassword)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $cookieUsername;
        header('Location: index.php');
        exit;
    }
}

$cafeName = getDisplayCafeName();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | <?= e($cafeName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-field:focus { outline: none; border-color: #2d4433; box-shadow: 0 0 0 3px rgba(45,68,51,0.08); }
        input[type="checkbox"]:checked { background-color: #2d4433; border-color: #2d4433; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <!-- Logo & Başlık -->
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 bg-[#2d4433] rounded-2xl flex items-center justify-center shadow-lg mb-4">
                <i class="fas fa-mug-hot text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight"><?= e($cafeName) ?></h1>
            <p class="text-gray-500 text-sm mt-1">Yönetim Paneli</p>
        </div>

        <!-- Kart -->
        <div class="bg-white rounded-2xl shadow-md p-8">
            <?php if ($error): ?>
            <div class="mb-5 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm font-medium flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-400"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kullanıcı Adı</label>
                    <input type="text" name="username" required autofocus
                        class="input-field w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 placeholder:text-gray-400 transition-all"
                        placeholder="Kullanıcı adınız">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Şifre</label>
                    <div class="relative">
                        <input type="password" id="passwordField" name="password" required
                            class="input-field w-full px-4 py-3 pr-11 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 placeholder:text-gray-400 transition-all"
                            placeholder="Şifreniz">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                            <i id="eyeIcon" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 pt-1">
                    <input type="checkbox" name="remember" id="remember"
                        class="w-4 h-4 rounded border-gray-300 cursor-pointer accent-[#2d4433]">
                    <label for="remember" class="text-sm text-gray-700 cursor-pointer select-none">Beni hatırla</label>
                </div>

                <button type="submit"
                    class="w-full bg-[#2d4433] hover:bg-[#1a2e1f] text-white font-bold py-3.5 rounded-xl transition-colors duration-200 text-sm mt-2">
                    Giriş Yap
                </button>
            </form>
        </div>

        <!-- Alt bilgi -->
        <p class="text-center text-gray-400 text-xs mt-6">&copy; 2026 <?= e($cafeName) ?></p>
    </div>

    <script>
    function togglePassword() {
        const field = document.getElementById('passwordField');
        const icon  = document.getElementById('eyeIcon');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>

</body>
</html>
