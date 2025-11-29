<?php
/**
 * Şifre Sıfırlama Sayfası
 */
ob_start();
require_once 'config/config.php';
require_once 'init.php';

// Gerekli sabitler/değişkenler tanımlı mı?
if (!defined('BASE_URL')) { define('BASE_URL', '/'); }
if (!isset($settings) || !is_array($settings)) {
    $settings = [];
}

$site_title = htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş');
$logo_path = htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png');

$error = '';
$success = '';
$valid_link = false;

// GET parametrelerini al
$email = clean($_GET['email'] ?? '');
$token = clean($_GET['token'] ?? '');

// Token doğrulama
if (!empty($email) && !empty($token)) {
    // Token kontrolü
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$email, $token]);
    $reset_request = $stmt->fetch();

    if ($reset_request) {
        $valid_link = true;
    } else {
        $error = 'Geçersiz veya süresi dolmuş sıfırlama bağlantısı.';
    }
} else {
    // Parametre yoksa login'e yönlendir
    redirect('login.php');
    exit;
}

// POST İşlemi (Şifre Güncelleme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_link) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password) || empty($password_confirm)) {
        $error = 'Lütfen yeni şifrenizi ve onayını giriniz.';
    } elseif ($password !== $password_confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            // Şifreyi güncelle
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            // Token'ı sil (kullanıldı)
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $success = 'Şifreniz başarıyla güncellendi. Giriş sayfasına yönlendiriliyorsunuz...';
            $valid_link = false; // Formu gizle

            // 3 saniye sonra login sayfasına yönlendir
            header("refresh:3;url=login.php?reset=1");
        } catch (PDOException $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $error = 'Şifre güncellenirken bir hata oluştu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - <?php echo $site_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?php echo $logo_path; ?>" alt="Logo" class="auth-logo" onerror="this.style.display='none'">
                <h4>Şifre Sıfırlama</h4>
                <p>Yeni şifrenizi belirleyin.</p>
            </div>
            
            <div class="auth-body-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <?php if (!$valid_link && empty($success)): ?>
                        <div class="text-center mt-3">
                            <a href="forgot-password.php" class="btn btn-modern">
                                <i class="fas fa-sync-alt me-2"></i> Yeni Bağlantı İste
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="text-center mt-3">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                        <p class="text-muted">Giriş sayfasına yönlendiriliyorsunuz...</p>
                    </div>
                <?php endif; ?>

                <?php if ($valid_link && empty($success)): ?>
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Yeni Şifre" required minlength="6">
                            <label for="password">Yeni Şifre</label>
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                        </div>

                        <div class="form-floating mb-4">
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Yeni Şifre (Tekrar)" required>
                            <label for="password_confirm">Yeni Şifre (Tekrar)</label>
                            <div class="invalid-feedback">Lütfen şifrenizi tekrar girin.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i> Şifreyi Güncelle
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-footer">
                    <a href="login.php"><i class="fas fa-arrow-left me-1"></i> Giriş Sayfasına Dön</a>
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
                        // Şifre eşleşme kontrolü (client-side)
                        var password = document.getElementById('password');
                        var confirm = document.getElementById('password_confirm');
                        if (password && confirm && password.value !== confirm.value) {
                            confirm.setCustomValidity('Şifreler eşleşmiyor.');
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            if(confirm) confirm.setCustomValidity('');
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                    
                    // Input değiştiğinde validasyonu temizle
                    var confirmInput = document.getElementById('password_confirm');
                    if(confirmInput) {
                        confirmInput.addEventListener('input', function(){
                            this.setCustomValidity('');
                        });
                    }
                })
        })();
    </script>
</body>
</html>
<?php ob_end_flush(); ?>