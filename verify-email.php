<?php
/**
 * E-posta Doğrulama ve Gönderim Sistemi
 */
// Çıktı tamponlama başlat (header yönlendirmesi için)
ob_start();
// init.php dosyasını dahil et
require_once 'init.php';
// Token ve e-posta kontrolü
$email = clean($_GET['email'] ?? '');
$token = clean($_GET['token'] ?? '');
// Yeniden gönderim butonu tıklandıysa
$resend = isset($_GET['resend']) && $_GET['resend'] == '1';
// Hata ve başarı mesajları için değişkenler
$error = '';
$success = '';
$site_title = htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş');
$logo_path = htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png');

/**
 * Doğrulama e-postası gönderme fonksiyonu
 */
function sendVerificationEmail($user, $baseUrl = null) {
    global $settings;
  
    // Base URL belirtilmemişse, otomatik algıla
    if ($baseUrl === null) {
        $baseUrl = BASE_URL;
    }
  
    // Doğrulama bağlantısı
    $verificationLink = $baseUrl . 'verify-email.php?email=' . urlencode($user['email']) . '&token=' . $user['email_verification_token'];
  
    // E-posta içeriği
    $subject = "E-posta Adresinizi Doğrulayın";
    
    $user_full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
    
    $content = '
    <p>Merhaba ' . $user_full_name . ',</p>
    <p>Hesabınız başarıyla oluşturuldu. Hesabınızı aktifleştirmek için aşağıdaki butona tıklayarak e-posta adresinizi doğrulayın.</p>
    
    <p style="margin-top: 20px; font-size: 14px; color: #777;">Ya da aşağıdaki bağlantıyı tarayıcınıza kopyalayabilirsiniz:</p>
    <p style="font-size: 12px; color: #999; word-break: break-all;">' . $verificationLink . '</p>';
    
    // HTML içerik
    $htmlMessage = getStandardEmailTemplate($subject, $content, 'E-posta Adresimi Doğrula', $verificationLink);
  
    // Düz metin içerik
    $plainMessage = generatePlainTextFromHtml($htmlMessage);
  
    // E-postayı gönder
    return sendEmail($user['email'], $subject, $htmlMessage, $plainMessage);
}
// Yeniden gönderim işlemi
if ($resend && !empty($email)) {
    // Kullanıcı kontrolü
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND email_verified = 0 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  
    if ($user) {
        // Son token oluşturma zamanını kontrol et (eğer updated_at varsa ve son 15 dakika içindeyse)
        $canResend = true;
      
        if (!empty($user['updated_at'])) {
            $updatedTime = strtotime($user['updated_at']);
            $currentTime = time();
            $diffMinutes = ($currentTime - $updatedTime) / 60;
          
            // 1 dakikadan az süre geçtiyse yeniden göndermek için çok erken
            if ($diffMinutes < 1) {
                $canResend = false;
                $error = 'Son yeniden gönderim talebinizden bu yana 1 dakika geçmeden yeni bir doğrulama e-postası gönderemezsiniz.';
            }
        }
      
        if ($canResend) {
            // Yeni token oluştur
            $newToken = bin2hex(random_bytes(32));
          
            // Token güncelle
            $stmt = $pdo->prepare("UPDATE users SET email_verification_token = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newToken, $user['id']]);
          
            // E-posta gönder
            $user['email_verification_token'] = $newToken;
            if (sendVerificationEmail($user)) {
                $success = 'Doğrulama e-postası başarıyla tekrar gönderildi. Lütfen gelen kutunuzu kontrol edin.';
              
                // Kullanıcı aktivitesini kaydet
                logActivity($user['id'], 'Doğrulama e-postası yeniden gönderildi', $pdo);
            } else {
                $error = 'Doğrulama e-postası gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    } else {
        $error = 'Bu e-posta adresi sistemde kayıtlı değil veya zaten doğrulanmış.';
    }
} else if (empty($email) || empty($token)) {
    // E-posta ve token yoksa ana sayfaya yönlendir
    redirect('index.php');
}
// E-posta ve token doğrulama kontrolü
if (!$resend && !empty($email) && !empty($token)) {
    // E-posta doğrulama işlemi
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND email_verification_token = ? AND email_verified = 0 LIMIT 1");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  
    if ($user) {
        // E-posta doğrulama
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
      
        // Kullanıcı aktivitesini kaydet
        logActivity($user['id'], 'E-posta doğrulandı', $pdo);
      
        // Başarı mesajı
        $success = 'E-posta adresiniz başarıyla doğrulandı. Şimdi giriş yapabilirsiniz.';
      
        // 5 saniye sonra giriş sayfasına yönlendir - JavaScript ile
        $_SESSION['success_message'] = $success;
        header("refresh:5;url=index.php?verified=1");
    } else {
        // Hata mesajı
        $error = 'Geçersiz veya kullanılmış doğrulama bağlantısı.';
    }
}
// Sayfa başlığını belirle (header.php için)
$page_title = 'E-posta Doğrulama';
// Header'ı dahil et
// include 'includes/header.php'; // Modern tasarımda header'a gerek yok
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-posta Doğrulama - <?php echo $site_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?php echo $logo_path; ?>" alt="Logo" class="auth-logo" onerror="this.style.display='none'">
                <h4>E-posta Doğrulama</h4>
                <p>Hesap güvenliğiniz için e-posta adresinizi doğrulayın.</p>
            </div>
            
            <div class="auth-body-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                   
                    <?php if (!$resend && !empty($email)): ?>
                        <div class="mt-4 text-center">
                            <p class="text-muted mb-3">Bağlantınızın süresi dolmuş olabilir.</p>
                            <a href="verify-email.php?email=<?php echo urlencode($email); ?>&resend=1" class="btn btn-modern">
                                <i class="fas fa-sync-alt me-2"></i> Yeni Doğrulama E-postası Gönder
                            </a>
                        </div>
                    <?php endif; ?>
                   
                    <div class="auth-footer">
                        <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Ana Sayfaya Dön</a>
                    </div>
                <?php endif; ?>
               
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                   
                    <?php if (strpos($success, 'doğrulandı') !== false): ?>
                        <div class="mt-4 text-center">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                            <p class="text-muted">Giriş sayfasına yönlendiriliyorsunuz...</p>
                            <a href="index.php" class="btn btn-modern mt-2">
                                <i class="fas fa-sign-in-alt me-2"></i> Hemen Giriş Yap
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="auth-footer">
                            <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Ana Sayfaya Dön</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
               
                <?php if (empty($error) && empty($success) && $resend): ?>
                    <form action="verify-email.php" method="get" class="needs-validation" novalidate>
                        <input type="hidden" name="resend" value="1">
                        <div class="form-floating mb-4">
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="name@example.com" required>
                            <label for="email">E-posta Adresiniz</label>
                            <div class="invalid-feedback">Lütfen geçerli bir e-posta girin.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-paper-plane me-2"></i> Doğrulama E-postası Gönder
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Ana Sayfaya Dön</a>
                    </div>
                <?php endif; ?>
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
<?php
// Çıktı tamponlama bitir
ob_end_flush();
?>