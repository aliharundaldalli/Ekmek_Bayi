<?php
/**
 * Giriş sayfası (init.php Kullanılıyor, Header/Footer Yok, Sabit Favicon)
 */
// Temel yapılandırma dosyasını dahil et
// init.php'nin $pdo, $settings, BASE_URL, ROOT_PATH, fonksiyonlar ve session'ı hazırladığını varsayıyoruz.
require_once 'init.php';
// Gerekli sabitler/değişkenler tanımlı mı? (Sağlamlık için)
// init.php'nin bunları ve $settings dizisini (site_settings'ten) tanımladığını varsayıyoruz
if (!defined('BASE_URL')) { define('BASE_URL', '/'); error_log('BASE_URL not defined in init.php'); }
if (!isset($settings) || !is_array($settings)) {
    $settings = []; // Boş başlat, init.php'nin doldurması beklenir
    error_log('Site settings ($settings array) not populated by init.php');
    // Acil durum: Temel ayarları DB'den çekmeyi deneyebiliriz, ama ideal olan init.php'de olması.
    // try {
    // if (isset($pdo)) {
    // $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    // while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
    // $settings[$row['setting_key']] = $row['setting_value'];
    // }
    // }
    // } catch (PDOException $e) {
    // error_log('Fallback settings fetch failed: ' . $e->getMessage());
    // }
}
if (session_status() == PHP_SESSION_NONE) { @session_start(); } // @ production'da önerilmez
// Güvenli URL için
$base_url_trimmed = rtrim(BASE_URL, '/');
// --- Yönlendirme ve Form İşleme ---
// Kullanıcı zaten giriş yapmışsa yönlendir
if (function_exists('isLoggedIn') && isLoggedIn()) {
    // Yönlendirme URL'sini belirle (Önce session'a bak, sonra role göre belirle, sonra varsayılan)
    $redirect_url = $_SESSION['redirect_url'] ?? null; // user_check.php tarafından ayarlanmış olabilir
    unset($_SESSION['redirect_url']); // Kullandıktan sonra temizle
    if (!$redirect_url) {
        $user_role = $_SESSION['user_role'] ?? 'guest'; // init.php veya setUserSession ayarlamalı
        switch ($user_role) {
            case 'admin':
                $redirect_url = $base_url_trimmed . '/admin/index.php'; // Admin paneli
                break;
            case 'bakery':
                $redirect_url = $base_url_trimmed . '/my/index.php'; // Kullanıcı (fırın) paneli
                break;
            default:
                 $redirect_url = $base_url_trimmed . '/'; // Varsayılan ana sayfa
                 break;
        }
    }
    // Kaydedilmiş veya belirlenmiş URL'ye yönlendir
    if (function_exists('redirect')) { redirect($redirect_url); } else { header('Location: ' . $redirect_url); }
    exit;
}
// Form gönderildi mi kontrol et
$error = '';
$success = '';
// Session'dan gelen mesajları al (PRG sonrası)
if (isset($_SESSION['form_success_message'])) { $success = $_SESSION['form_success_message']; unset($_SESSION['form_success_message']); }
if (isset($_SESSION['form_error_message'])) { $error = $_SESSION['form_error_message']; unset($_SESSION['form_error_message']); }
// GET mesajlarını al (eğer başka session mesajı yoksa ve GET parametresi varsa)
if (empty($success) && empty($error)) { // Sadece başka mesaj yoksa GET'i kontrol et
    if (isset($_GET['logout']) && $_GET['logout'] == 1) $success = 'Başarıyla çıkış yaptınız.';
    if (isset($_GET['reset']) && $_GET['reset'] == 1) $success = 'Şifreniz başarıyla sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz. Yeni şifrenizle giriş yapabilirsiniz.';
    if (isset($_GET['verified']) && $_GET['verified'] == 1) $success = 'E-posta adresiniz doğrulandı. Şimdi giriş yapabilirsiniz.';
    if (isset($_GET['registered']) && $_GET['registered'] == 1) $success = 'Kaydınız başarıyla oluşturuldu. E-posta adresinize gönderilen doğrulama linkine tıklayınız.'; // Örnek kayıt sonrası mesaj
    if (isset($_GET['error'])) { // Genel GET hata parametresi
         switch($_GET['error']) {
            case 'inactive': $error = 'Hesabınız aktif değil. Lütfen yönetici ile iletişime geçin.'; break;
            case 'unverified': $error = 'Giriş yapmadan önce e-posta adresinizi doğrulamanız gerekmektedir.'; break;
            // Diğer özel hatalar...
            default: $error = 'Bilinmeyen bir hata oluştu.'; break;
        }
    }
}
// --- POST İsteğini İşle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gerekli fonksiyonlar ve $pdo var mı?
    $can_proceed = function_exists('clean') &&
                   isset($pdo) && ($pdo instanceof PDO) &&
                   function_exists('verifyPassword') &&
                   function_exists('setUserSession') &&
                   function_exists('logActivity') &&
                   function_exists('redirect');
    if (!$can_proceed) {
        $error = 'Sistem hatası: Oturum açılamıyor. Lütfen yönetici ile iletişime geçin.';
        error_log("Login Error: Missing required functions (clean, verifyPassword, setUserSession, logActivity, redirect) or \$pdo object.");
    } else {
        // Hem e-posta hem de fırın adı için aynı input'u kullanıyoruz
        $login_identifier = clean($_POST['email'] ?? ''); // Input name'i 'email' olarak kaldı, ama içerik email veya bakery_name olabilir
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']); // Beni Hatırla (kullanımı size bağlı, genellikle cookie ile yapılır)
        if (empty($login_identifier) || empty($password)) {
             $error = 'Lütfen e-posta/fırın adı ve şifrenizi giriniz.';
        }
        // Email format kontrolünü kaldırdık, çünkü bakery_name de olabilir
        // elseif (!filter_var($login_identifier, FILTER_VALIDATE_EMAIL)) { $error = 'Lütfen geçerli bir e-posta adresi giriniz.'; }
        else {
            try {
                // Kullanıcıyı DB'den e-posta VEYA fırın adına göre kontrol et
                // Ayrıca status=1 (aktif) kontrolü ekliyoruz
                $stmt = $pdo->prepare("
                    SELECT * FROM users
                    WHERE (email = ? OR bakery_name = ?)
                    LIMIT 1
                ");
                $stmt->execute([$login_identifier, $login_identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                // Kullanıcı bulundu mu ve şifre doğru mu?
                if ($user && verifyPassword($password, $user['password'])) {
                     // Ek Kontroller: Kullanıcı durumu (aktif mi?)
                    if ($user['status'] != 1) {
                        $error = 'Hesabınız aktif değil veya askıya alınmış. Lütfen yönetici ile iletişime geçin.';
                        // İsteğe bağlı: logActivity($user['id'], 'Başarısız giriş denemesi (hesap pasif)', $pdo);
                    }
                    // Ek Kontroller: E-posta doğrulaması (gerekliyse)
                    // Site ayarlarında e-posta doğrulamanın zorunlu olup olmadığını kontrol edebilirsiniz.
                    // Örneğin: if (($settings['require_email_verification'] ?? false) && $user['email_verified'] != 1) {
                    elseif ($user['email_verified'] != 1) { // Şimdilik her zaman kontrol edelim
                         $error = 'Giriş yapmadan önce e-posta adresinizi doğrulamanız gerekmektedir. <br><a href="' . $base_url_trimmed . '/verify-email.php?resend=1&email=' . urlencode($user['email']) . '" class="alert-link">Doğrulama e-postası almadıysanız buraya tıklayarak yeniden gönderim isteyin.</a>';
                         // İsteğe bağlı: logActivity($user['id'], 'Başarısız giriş denemesi (e-posta doğrulanmamış)', $pdo);
                    }
                    // Tüm kontroller başarılı ise giriş yap
                    else {
                        // Session başlat/ayarla (setUserSession fonksiyonu init.php'den gelmeli)
                        setUserSession($user); // Bu fonksiyon user_id, user_role, first_name vb. session'a atmalı
                        // Aktivite logla (logActivity fonksiyonu init.php'den gelmeli)
                        logActivity($user['id'], 'Sisteme giriş yapıldı', $pdo);
                        // Son giriş zamanını güncelle (isteğe bağlı)
                        try {
                             $stmt_update_login = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                             $stmt_update_login->execute([$user['id']]);
                        } catch (PDOException $e) {
                            error_log("Failed to update last_login_at for user {$user['id']}: " . $e->getMessage());
                        }
                        // Giriş sonrası yönlendirme
                        $redirect_url = $_SESSION['redirect_url'] ?? null; // user_check.php'den gelmiş olabilir
                        unset($_SESSION['redirect_url']);
                         if (!$redirect_url) {
                             // Rol bazlı varsayılan yönlendirme (yukarıdaki kodla aynı)
                            $user_role = $_SESSION['user_role'] ?? 'guest';
                            switch ($user_role) {
                                case 'admin': $redirect_url = $base_url_trimmed . '/admin/index.php'; break;
                                case 'bakery': $redirect_url = $base_url_trimmed . '/my/index.php'; break;
                                default: $redirect_url = $base_url_trimmed . '/'; break;
                            }
                         }
                        redirect($redirect_url);
                        exit;
                    } // Başarılı giriş bloğu bitti
                } else {
                     // Kullanıcı bulunamadı VEYA şifre yanlış
                    $error = 'Geçersiz e-posta, fırın adı veya şifre.';
                     // İsteğe bağlı: Genel bir loglama veya IP bazlı deneme sayacı eklenebilir.
                    // error_log("Failed login attempt for identifier: $login_identifier");
                }
            } catch (PDOException $e) {
                error_log("Login PDOException: " . $e->getMessage());
                $error = 'Giriş sırasında bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.';
            } catch (Throwable $th) { // Diğer beklenmedik hatalar (örn: şifreleme fonksiyonu hatası)
                error_log("Login General Error: " . $th->getMessage());
                $error = 'Giriş sırasında beklenmedik bir sistem hatası oluştu.';
            }
        } // Boş olmayan input kontrolü bitti
    } // Fonksiyon/PDO varlığı kontrolü bitti
    // Hata varsa sayfayı yeniden yükle (PRG - Post-Redirect-Get Pattern)
    if (!empty($error)) {
        $_SESSION['form_error_message'] = $error;
        // Form verisini (sadece giriş tanımlayıcı) session'da sakla ki input dolu kalsın
        $_SESSION['form_post_data'] = ['email' => $login_identifier]; // Şifreyi ASLA saklama!
        redirect($base_url_trimmed . '/login.php'); // Kendine redirect et
        exit;
    }
} // POST işlemi bitti
// Hata durumunda form verisini session'dan al (varsa ve sadece 'email' anahtarını)
$form_post_data = $_SESSION['form_post_data'] ?? [];
unset($_SESSION['form_post_data']);
$previous_identifier = htmlspecialchars($form_post_data['email'] ?? ''); // Sadece email/identifier'ı al
// Sayfa başlığı (Ayarlardan alınmalı)
$page_title = 'Giriş Yap';
$site_title = htmlspecialchars($settings['site_title'] ?? ''); // init.php'den gelmeli
$site_description = htmlspecialchars($settings['site_description'] ?? ''); // init.php'den gelmeli
$favicon_path = htmlspecialchars($settings['favicon'] ?? 'assets/images/favicon.png'); // init.php'den gelmeli
$logo_path_login = htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png'); // init.php'den gelmeli
$settings = []; // Ayarları tutacak dizi
try {
    // $pdo değişkeninin db.php içinde tanımlandığını varsayıyoruz
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Anahtar-değer çifti olarak al
        if ($db_settings) {
            $settings = $db_settings; // Veritabanı ayarlarını $settings dizisine ata
        } else {
             error_log("site_settings tablosundan ayarlar çekilemedi veya tablo boş.");
             // Burada varsayılan ayarları yükleyebilir veya hata verebilirsiniz.
        }
    } else {
        throw new Exception("PDO veritabanı bağlantı nesnesi bulunamadı.");
    }
} catch (PDOException $e) {
    error_log("Veritabanından ayarlar çekilirken PDO hatası: " . $e->getMessage());
    // Kritik hata, belki varsayılan ayarlarla devam et veya işlemi durdur
    // $settings = [ /* ... varsayılan ayarlar ... */ ];
    // die("Site ayarları yüklenemedi. Lütfen yönetici ile iletişime geçin."); // Opsiyonel
} catch (Exception $e) {
     error_log("Ayarlar çekilirken genel hata: " . $e->getMessage());
     // die("Site ayarları yüklenemedi. Lütfen yönetici ile iletişime geçin."); // Opsiyonel
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo $site_title; ?></title>
    <meta name="description" content="<?php echo $site_description; ?>">
    <link rel="icon" href="<?php echo $favicon_path; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?php echo $logo_path_login; ?>" alt="<?php echo $site_title; ?>" class="auth-logo" onerror="this.style.display='none'">
                <h4><?php echo $site_title; ?></h4>
                <p>Bayi Giriş Sistemi</p>
            </div>
            
            <div class="auth-body-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo $base_url_trimmed; ?>/login.php" class="needs-validation" novalidate>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="email" name="email" placeholder="E-posta veya Fırın Adı" required value="<?php echo $previous_identifier; ?>" autofocus>
                        <label for="email">E-posta veya Fırın Adı</label>
                        <div class="invalid-feedback">Lütfen giriş bilginizi girin.</div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Şifre" required>
                        <label for="password">Şifre</label>
                        <div class="invalid-feedback">Lütfen şifrenizi girin.</div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label small text-muted" for="remember">Beni Hatırla</label>
                        </div>
                        <a href="<?php echo $base_url_trimmed; ?>/forgot-password.php" class="small text-decoration-none">Şifremi Unuttum?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-modern">
                        <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
                    </button>
                </form>
                
            </div>
        </div>
        <div class="text-center mt-3 text-white-50 small">
            &copy; <?php echo date('Y'); ?> <?php echo $site_title; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })();
        
        $(document).ready(function() {
             window.setTimeout(function() {
                $(".alert").not('.alert-dismissible-none').fadeTo(500, 0).slideUp(500, function(){
                    $(this).alert('close'); 
                 });
             }, 5000);
        });
    </script>
</body>
</html>