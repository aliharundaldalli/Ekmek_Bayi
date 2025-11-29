<?php
/**
 * Kullanıcı Profil Sayfası
 */

require_once '../../init.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";
$user = null;

// Kullanıcı bilgilerini al
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirect(BASE_URL . '/logout.php');
    }

    // İstatistikleri getir (Sipariş Sayısı ve Toplam Harcama)
    $stats = ['total_orders' => 0, 'total_spend' => 0];
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(id) as total_orders, 
            SUM(total_amount) as total_spend 
        FROM orders 
        WHERE user_id = ? AND status != 'cancelled'
    ");
    $stmt_stats->execute([$user_id]);
    $stats_data = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    if ($stats_data) {
        $stats['total_orders'] = $stats_data['total_orders'];
        $stats['total_spend'] = $stats_data['total_spend'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Profil bilgileri hatası: " . $e->getMessage());
    $error_message = "Profil bilgileri yüklenirken bir sorun oluştu.";
}

// Profil güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
        $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $errors = [];

    if (empty($email)) {
        $errors[] = "E-posta adresi boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi giriniz.";
    } else {
        if ($email != $user['email']) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.";
                }
            } catch (PDOException $e) {
                 $errors[] = "E-posta kontrolü hatası.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET address = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$address, $phone, $email, $user_id]);
            
            // Kullanıcıyı yenile
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success_message = "Profil bilgileriniz güncellendi.";
        } catch (PDOException $e) {
            $error_message = "Veritabanı hatası oluştu.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
    }
}


// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
        $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];

    if (empty($current_password)) $errors[] = "Mevcut şifre gerekli.";
    if (empty($new_password)) $errors[] = "Yeni şifre gerekli.";
    elseif (strlen($new_password) < 6) $errors[] = "Yeni şifre en az 6 karakter olmalı.";
    if ($new_password != $confirm_password) $errors[] = "Yeni şifreler uyuşmuyor.";

    if (empty($errors)) {
        if (password_verify($current_password, $user['password'])) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success_message = "Şifreniz güncellendi.";
            } catch (PDOException $e) {
                $error_message = "Şifre güncellenemedi.";
            }
        } else {
            $errors[] = "Mevcut şifre yanlış.";
        }
    }
    if (!empty($errors)) $error_message = implode("<br>", $errors);
    }
}

// Profil resmi yükleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_profile_image'])) {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
        
        if (!in_array($file_type, $allowed)) {
            $error_message = "Sadece JPG, PNG ve GIF yüklenebilir.";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $error_message = "Dosya boyutu 2MB'ı geçemez.";
        } else {
            $upload_dir = 'uploads/profile/';
            $abs_dir = ROOT_PATH . '/' . $upload_dir;
            if (!file_exists($abs_dir)) mkdir($abs_dir, 0755, true);
            
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $abs_dir . $filename)) {
                // Eski resmi sil
                if (!empty($user['profile_image']) && file_exists(ROOT_PATH . '/' . $user['profile_image'])) {
                    unlink(ROOT_PATH . '/' . $user['profile_image']);
                }
                
                $pdo->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?")->execute([$upload_dir . $filename, $user_id]);
                $user['profile_image'] = $upload_dir . $filename;
                $success_message = "Profil resmi güncellendi.";
            } else {
                $error_message = "Dosya yüklenemedi.";
            }
        }
    } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $error_message = "Dosya yükleme hatası.";
    }
    }
}


$page_title = "Profil Bilgilerim";
$current_page = "profile";
include_once ROOT_PATH . '/my/header.php';

$initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
?>

<div class="container-fluid py-4">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sol Kolon: Profil Kartı -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center pt-5 pb-4">
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $user['profile_image']; ?>" class="img-profile rounded-circle shadow" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-circle mx-auto bg-primary text-white d-flex align-items-center justify-content-center shadow" style="width: 120px; height: 120px; font-size: 3rem; border-radius: 50%;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadImageModal" style="width: 36px; height: 36px;">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <h4 class="font-weight-bold mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['bakery_name']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <span class="badge bg-info text-dark"><i class="fas fa-store me-1"></i>Büfe</span>
                        <?php if($user['status'] == 1): ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Pasif</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Toplam Sipariş</span>
                            <span class="font-weight-bold text-dark"><?php echo number_format($stats['total_orders']); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Toplam Harcama</span>
                            <span class="font-weight-bold text-dark"><?php echo number_format($stats['total_spend'], 2, ',', '.'); ?> ₺</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İletişim Bilgileri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-address-card me-2"></i>İletişim Bilgileri</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">E-posta</small>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-break"><?php echo htmlspecialchars($user['email']); ?></span>
                            <?php if($user['email_verified']): ?>
                                <i class="fas fa-check-circle text-success" title="Doğrulanmış"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle text-warning" title="Doğrulanmamış"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Telefon</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-0">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Adres</small>
                        <div class="fw-bold"><?php echo nl2br(htmlspecialchars($user['address'] ?: '-')); ?></div>
                    </div>
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
                    <form method="post" action="">
                        <?php $csrf_token = generateCSRFToken(); ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <h5 class="mb-3 text-gray-800 border-bottom pb-2">Kişisel Bilgiler</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-uppercase fw-bold text-muted">Ad</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-uppercase fw-bold text-muted">Soyad</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold text-muted">Büfe Adı</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['bakery_name']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold text-muted">TC Kimlik No</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['identity_number'] ?? ''); ?>" readonly>
                        </div>

                        <h5 class="mb-3 text-gray-800 border-bottom pb-2 mt-4">İletişim Bilgileri</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label small text-uppercase fw-bold text-muted">E-posta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-envelope text-gray-400"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label small text-uppercase fw-bold text-muted">Telefon</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-phone text-gray-400"></i></span>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label small text-uppercase fw-bold text-muted">Adres</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt text-gray-400"></i></span>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i>Bilgileri Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-key me-2"></i>Şifre Değiştir</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-3">
                            <label for="current_password" class="form-label small text-uppercase fw-bold text-muted">Mevcut Şifre <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-lock text-gray-400"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label small text-uppercase fw-bold text-muted">Yeni Şifre <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-key text-gray-400"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                </div>
                                <div class="form-text small">En az 6 karakter.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label small text-uppercase fw-bold text-muted">Yeni Şifre (Tekrar) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-key text-gray-400"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" name="update_password" class="btn btn-warning px-4">
                                <i class="fas fa-sync-alt me-2"></i>Şifreyi Değiştir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profil Resmi Yükleme Modalı -->
<!-- Profil Resmi Yükleme Modalı -->
<div class="modal fade" id="uploadImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Profil Resmi Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Resim Seç (JPG, PNG, GIF)</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                        <div class="form-text">Maksimum dosya boyutu: 2MB</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="upload_profile_image" class="btn btn-primary">Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . '/my/footer.php'; ?>
