<?php
// --- Hata Gösterimi (Sadece Geliştirme İçin!) ---
// Sorunu bulduktan sonra bu satırları silin veya yorum satırı yapın!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- Hata Gösterimi Bitiş ---

/**
 * Kullanıcı Profil Sayfası
 */

// --- init.php Dahil Etme ---
require_once '../init.php';

// --- Auth Check ---
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    if (function_exists('redirect') && defined('BASE_URL')) {
         redirect(rtrim(BASE_URL, '/') . '/login.php');
    } else {
        header('Location: /login.php');
        exit;
    }
    exit;
}

// --- Page Title ---
$page_title = 'Profil Bilgilerim';

// --- Giriş Yapmış Kullanıcı ID'sini Al ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    $_SESSION['error_message'] = 'Profilinize erişmek için lütfen giriş yapın.';
     if (function_exists('redirect') && defined('BASE_URL')) {
         redirect(rtrim(BASE_URL, '/') . '/login.php');
     } else {
        header('Location: /login.php');
        exit;
     }
    exit;
}

// --- PDO Bağlantısını Kontrol Et ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
     error_log("CRITICAL ERROR in profile.php: \$pdo object is not available or invalid.");
     die("Veritabanı bağlantısı kurulamadı. Lütfen site yöneticisi ile iletişime geçin.");
}


// --- Kullanıcı Verilerini Çek ---
$user = null;
$fetch_error = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error_message'] = 'Kullanıcı bilgileri veritabanında bulunamadı.';
        if (function_exists('redirect') && defined('BASE_URL')) {
            redirect(rtrim(BASE_URL, '/') . '/logout.php');
        } else {
            header('Location: /logout.php');
            exit;
        }
        exit;
    }
} catch (PDOException $e) {
    error_log("User Profile Fetch Error for ID " . $user_id . ": " . $e->getMessage());
    $fetch_error = 'Profil bilgileri yüklenirken bir veritabanı hatası oluştu.';
    $user = [];
} catch (Throwable $th) {
    error_log("General Error during User Profile Fetch for ID " . $user_id . ": " . $th->getMessage());
    $fetch_error = 'Profil bilgileri yüklenirken beklenmedik bir hata oluştu.';
    $user = [];
}


// --- Form Gönderildi mi? (POST Metodu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect(rtrim(BASE_URL, '/') . '/admin/profile.php');
        exit;
    }

    $update_errors = [];
    $update_data = [];
    $update_params = [];

    // --- Alanları Al ve Doğrula ---
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $bakery_name = trim($_POST['bakery_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    // Zorunlu alanlar
    if (empty($first_name)) $update_errors[] = 'Ad boş bırakılamaz.';
    if (empty($last_name)) $update_errors[] = 'Soyad boş bırakılamaz.';
    if (empty($email)) $update_errors[] = 'Geçerli bir e-posta adresi giriniz.';

    // E-posta değiştiyse, benzersizlik kontrolü
    if ($user && $email && $email !== $user['email']) {
        try {
            $stmt_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_email->execute([$email, $user_id]);
            if ($stmt_email->fetch()) {
                $update_errors[] = 'Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.';
            } else {
                 $update_data[] = 'email = ?';
                 $update_params[] = $email;
                 $update_data[] = 'email_verified = 0'; // E-posta değişirse doğrulamayı sıfırla
            }
        } catch (PDOException $e) {
             $update_errors[] = 'E-posta kontrolü sırasında veritabanı hatası.';
             error_log("Email check error during profile update: " . $e->getMessage());
        }
    }

    // Diğer alanları güncelleme listesine ekle
    if ($user) {
        if ($first_name !== $user['first_name']) { $update_data[] = 'first_name = ?'; $update_params[] = $first_name; }
        if ($last_name !== $user['last_name']) { $update_data[] = 'last_name = ?'; $update_params[] = $last_name; }
        if ($bakery_name !== $user['bakery_name']) { $update_data[] = 'bakery_name = ?'; $update_params[] = $bakery_name; }
        if ($address !== $user['address']) { $update_data[] = 'address = ?'; $update_params[] = $address; }
        if ($phone !== $user['phone']) { $update_data[] = 'phone = ?'; $update_params[] = $phone; }
    }

    // --- Şifre Değiştirme ---
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $update_errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
        } elseif ($new_password !== $confirm_password) {
            $update_errors[] = 'Yeni şifreler uyuşmuyor.';
        } else {
            if (!function_exists('password_hash')) {
                 $update_errors[] = 'Sunucu şifreleme fonksiyonunu desteklemiyor.';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_data[] = 'password = ?';
                $update_params[] = $hashed_password;
            }
        }
    }

    // --- Veritabanını Güncelle ---
    if (empty($update_errors) && !empty($update_data)) {
        $update_data[] = 'updated_at = NOW()';
        $sql = "UPDATE users SET " . implode(', ', $update_data) . " WHERE id = ?";
        $update_params[] = $user_id;

        try {
            $stmt_update = $pdo->prepare($sql);
            if ($stmt_update->execute($update_params)) {
                $_SESSION['success_message'] = 'Profil bilgileriniz başarıyla güncellendi.';
                if ($user && ($first_name !== $user['first_name'] || $last_name !== $user['last_name'])) {
                     $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                }
                redirect(rtrim(BASE_URL, '/') . '/admin/profile.php');
                exit;
            } else {
                 $update_errors[] = 'Profil güncellenirken bir SQL hatası oluştu.';
            }
        } catch (PDOException $e) {
             $update_errors[] = 'Profil güncellenirken bir veritabanı hatası oluştu: ' . $e->getMessage();
        }
    } elseif (empty($update_errors) && empty($update_data) && empty($new_password)) {
        $_SESSION['info_message'] = 'Herhangi bir değişiklik yapılmadı.';
        redirect(rtrim(BASE_URL, '/') . '/admin/profile.php');
        exit;
    }

    // Hata varsa session'a kaydet
    if (!empty($update_errors)) {
        $_SESSION['error_message'] = implode('<br>', array_map('htmlspecialchars', $update_errors));
        $_SESSION['form_data_profile'] = $_POST;
        redirect(rtrim(BASE_URL, '/') . '/admin/profile.php');
        exit;
    }
}

// --- GET İsteği İçin Form Verilerini Hazırla ---
$form_data = $_SESSION['form_data_profile'] ?? $user;
unset($_SESSION['form_data_profile']);

// --- Header'ı Dahil Et ---
if (!defined('ROOT_PATH') || !file_exists(ROOT_PATH . '/admin/header.php')) {
     die("Hata: Header dosyası bulunamadı veya ROOT_PATH tanımlı değil.");
}
include_once ROOT_PATH . '/admin/header.php';

// Yardımcı değişkenler
$initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
$role_badge = ($user['role'] === 'admin') 
    ? '<span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i>Admin</span>' 
    : '<span class="badge bg-info text-dark"><i class="fas fa-store me-1"></i>Büfe</span>';
$status_badge = ($user['status'] == 1) 
    ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aktif</span>' 
    : '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Pasif</span>';
?>

<div class="container-fluid">
    <!-- Başlık -->
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
    </div>

    <!-- Mesajlar -->
    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error_message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['error_message']);
    }
    if (isset($_SESSION['info_message'])) {
        echo '<div class="alert alert-info alert-dismissible fade show" role="alert"><i class="fas fa-info-circle me-2"></i>' . htmlspecialchars($_SESSION['info_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['info_message']);
    }
    if (isset($fetch_error)) {
        echo '<div class="alert alert-warning">' . htmlspecialchars($fetch_error) . '</div>';
    }
    ?>

    <?php if (!empty($user) && !isset($fetch_error)): ?>
    <div class="row">
        <!-- Sol Kolon: Profil Kartı -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center pt-5 pb-4">
                    <div class="avatar-circle mb-3 mx-auto bg-primary text-white d-flex align-items-center justify-content-center shadow" style="width: 100px; height: 100px; font-size: 2.5rem; border-radius: 50%;">
                        <?php echo $initials; ?>
                    </div>
                    <h4 class="font-weight-bold mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <?php echo $role_badge; ?>
                        <?php echo $status_badge; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Kayıt</span>
                            <span class="font-weight-bold text-dark"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Son Giriş</span>
                            <span class="font-weight-bold text-dark">
                                <?php echo ($user['last_login_at']) ? date('d.m.Y', strtotime($user['last_login_at'])) : '-'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Yardımcı Bilgi -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shield-alt me-2"></i>Hesap Güvenliği</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        Hesap güvenliğiniz için şifrenizi düzenli aralıklarla değiştirmeniz önerilir. Şifreniz en az 6 karakterden oluşmalıdır.
                    </p>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Düzenleme Formu -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-edit me-2"></i>Bilgileri Düzenle</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo rtrim(BASE_URL, '/'); ?>/admin/profile.php" method="POST">
                        <?php $csrf_token = generateCSRFToken(); ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <h5 class="mb-3 text-gray-800 border-bottom pb-2">Kişisel Bilgiler</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label small text-uppercase fw-bold text-muted">Ad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-gray-400"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label small text-uppercase fw-bold text-muted">Soyad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-gray-400"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label small text-uppercase fw-bold text-muted">E-posta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-gray-400"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                </div>
                                <?php if(isset($user['email_verified']) && $user['email_verified']): ?>
                                    <small class="text-success d-block mt-1"><i class="fas fa-check-circle me-1"></i>Doğrulanmış</small>
                                <?php else: ?>
                                     <small class="text-warning d-block mt-1"><i class="fas fa-exclamation-triangle me-1"></i>Doğrulanmamış</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label small text-uppercase fw-bold text-muted">Telefon</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-phone text-gray-400"></i></span>
                                    <input type="tel" class="form-control border-start-0 ps-0" id="phone" name="phone" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="bakery_name" class="form-label small text-uppercase fw-bold text-muted">Büfe / İşletme Adı</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-store text-gray-400"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="bakery_name" name="bakery_name" value="<?php echo htmlspecialchars($form_data['bakery_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label small text-uppercase fw-bold text-muted">Adres</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-map-marker-alt text-gray-400"></i></span>
                                <textarea class="form-control border-start-0 ps-0" id="address" name="address" rows="3"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="identity_number" class="form-label small text-uppercase fw-bold text-muted">TC Kimlik / Vergi No</label>
                            <input type="text" class="form-control bg-light" id="identity_number" value="<?php echo htmlspecialchars($user['identity_number'] ?? ''); ?>" disabled readonly>
                            <small class="form-text text-muted">Bu alan güvenlik nedeniyle değiştirilemez.</small>
                        </div>

                        <h5 class="mb-3 text-gray-800 border-bottom pb-2 mt-5">Şifre Değiştir</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label small text-uppercase fw-bold text-muted">Yeni Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-gray-400"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="new_password" name="new_password">
                                </div>
                                <div class="form-text small">Değiştirmek istemiyorsanız boş bırakın.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label small text-uppercase fw-bold text-muted">Yeni Şifre (Tekrar)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-gray-400"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// --- Footer'ı Dahil Et ---
if (!defined('ROOT_PATH') || !file_exists(ROOT_PATH . '/admin/footer.php')) {
     die("Hata: Footer dosyası bulunamadı veya ROOT_PATH tanımlı değil.");
}
include_once ROOT_PATH . '/admin/footer.php';
?>
