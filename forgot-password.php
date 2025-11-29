<?php
require_once 'config/config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'init.php';

// Gerekli sabitler/değişkenler tanımlı mı? (Sağlamlık için)
if (!defined('BASE_URL')) { define('BASE_URL', '/'); error_log('BASE_URL not defined in init.php'); }
if (!isset($settings) || !is_array($settings)) {
    $settings = []; // Boş başlat, init.php'nin doldurması beklenir
    error_log('Site settings ($settings array) not populated by init.php');
}

// Prevent logged-in users from accessing
if (isLoggedIn()) {
    redirect($_SESSION['dashboard']);
}

$error = '';
$success = '';
$site_title = htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş');
$logo_path = htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
  
    if (empty($email)) {
        $error = 'Lütfen e-posta adresinizi giriniz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen geçerli bir e-posta adresi giriniz.';
    } else {
        // Check user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
      
        if ($user) {
            try {
                // Clear old tokens
                $pdo->prepare("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()")->execute([$email]);
              
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
              
                // Save token
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$email, $token, $expires_at]);
              
                // Create reset link (FIXED: Using BASE_URL)
                $reset_link = BASE_URL . "reset-password.php?email=" . urlencode($email) . "&token=" . $token;
              
                // Prepare enhanced email content
                $subject = 'Şifre Sıfırlama Talebiniz - ' . $site_title;
                
                $content = '
                <p>Merhaba ' . htmlspecialchars($user['first_name']) . ',</p>
                <p>Şifrenizi sıfırlamak için bir talep aldık. Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz.</p>
                
                <p style="margin-top: 20px; font-size: 14px; color: #777;">Bu bağlantı 1 saat boyunca geçerlidir. Eğer bu talebi siz yapmadıysanız, lütfen bu e-postayı dikkate almayınız.</p>';
                
                $body = getStandardEmailTemplate($subject, $content, 'Şifremi Sıfırla', $reset_link);
                $plainBody = generatePlainTextFromHtml($body);
                
                // Send email using global function
                if (sendEmail($email, $subject, $body, $plainBody)) {
                    $success = 'Şifre sıfırlama talimatları e-posta adresinize gönderildi.';
                } else {
                    $error = 'E-posta gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
                }
              
            } catch (Exception $e) {
                error_log('Şifre sıfırlama hatası: ' . $e->getMessage());
                $error = 'E-posta gönderilirken bir hata oluştu.';
            }
        } else {
            // Güvenlik için kullanıcı bulunamadı demeyelim
            $success = 'Şifre sıfırlama talimatları e-posta adresinize gönderildi.';
            error_log('Bulunamayan email için şifre sıfırlama denemesi: ' . $email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum - <?php echo $site_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?php echo $logo_path; ?>" alt="Logo" class="auth-logo" onerror="this.style.display='none'">
                <h4>Şifremi Unuttum</h4>
                <p>E-posta adresinizi girin, size sıfırlama bağlantısı gönderelim.</p>
            </div>
            
            <div class="auth-body-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
              
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="needs-validation" novalidate>
                    <div class="form-floating mb-4">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autofocus>
                        <label for="email">E-posta Adresi</label>
                        <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                    </div>
                  
                    <button type="submit" class="btn btn-modern">
                        <i class="fas fa-paper-plane me-2"></i> Sıfırlama Bağlantısı Gönder
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="mb-0">Hatırladınız mı? <a href="login.php">Giriş Yap</a></p>
                </div>
            </div>
        </div>
        <div class="text-center mt-3 text-white-50 small">
            &copy; <?php echo date('Y'); ?> <?php echo $site_title; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
</body>
</html>