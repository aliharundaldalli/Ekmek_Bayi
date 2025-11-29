<?php
/**
 * Kullanıcı Düzenleme Sayfası
 */

// init.php dosyasını dahil et
require_once '../../init.php';

// Kullanıcı girişi ve yetkisi kontrolü
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . '/my/index.php');
}

// ID parametresini kontrol et
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Geçersiz kullanıcı ID\'si.';
    redirect(BASE_URL . '/admin/users/index.php');
}

$user_id = (int)$_GET['id'];

// Kullanıcı bilgilerini getir
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error_message'] = 'Kullanıcı bulunamadı.';
        redirect(BASE_URL . '/admin/users/index.php');
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Veritabanı hatası: ' . $e->getMessage();
    redirect(BASE_URL . '/admin/users/index.php');
}

// Form gönderildi mi kontrol et
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $bakery_name = clean($_POST['bakery_name'] ?? '');
    $first_name = clean($_POST['first_name'] ?? '');
    $last_name = clean($_POST['last_name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $identity_number = clean($_POST['identity_number'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = clean($_POST['role'] ?? 'bakery');
    $status = isset($_POST['status']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    
    // Alanları doğrula
    if (empty($bakery_name)) {
        $errors[] = 'Büfe adı boş bırakılamaz.';
    }
    
    if (empty($first_name)) {
        $errors[] = 'Ad alanı boş bırakılamaz.';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Soyad alanı boş bırakılamaz.';
    }
    
    if (empty($email)) {
        $errors[] = 'E-posta alanı boş bırakılamaz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    } else {
        // E-posta başka bir kullanıcı tarafından kullanılıyor mu kontrol et (kendisi hariç)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
        }
    }
    
    if (empty($phone)) {
        $errors[] = 'Telefon alanı boş bırakılamaz.';
    }
    
    if (empty($identity_number)) {
        $errors[] = 'TC kimlik numarası boş bırakılamaz.';
    } elseif (strlen($identity_number) !== 11 || !is_numeric($identity_number)) {
        $errors[] = 'TC kimlik numarası 11 haneli olmalıdır.';
    } else {
        // TC kimlik numarası başka bir kullanıcı tarafından kullanılıyor mu kontrol et (kendisi hariç)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE identity_number = ? AND id != ?");
        $stmt->execute([$identity_number, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Bu TC kimlik numarası başka bir kullanıcı tarafından kullanılıyor.';
        }
    }
    
    if (empty($address)) {
        $errors[] = 'Adres alanı boş bırakılamaz.';
    }
    
    // Şifre değişikliği varsa kontrol et
    $passwordChanged = false;
    if (!empty($password)) {
        $passwordChanged = true;
        if (strlen($password) < 6) {
            $errors[] = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Şifreler eşleşmiyor.';
        }
    }
    
    if (!in_array($role, ['admin', 'bakery'])) {
        $errors[] = 'Geçersiz kullanıcı rolü.';
    }
    
    // Hata yoksa kullanıcıyı güncelle
    if (empty($errors)) {
        try {
            // E-posta doğrulama durumu değiştiyse
            $verification_token = null;
            if ($email_verified != $user['email_verified'] && !$email_verified) {
                $verification_token = generateToken();
            }
            
            // Şifre değiştirildi mi kontrolü
            if ($passwordChanged) {
                $hashedPassword = hashPassword($password);
                $password_sql = ", password = :password";
            } else {
                $password_sql = "";
                $hashedPassword = null;
            }
            
            // Doğrulama tokeni değişti mi kontrolü
            if ($verification_token !== null) {
                $token_sql = ", email_verification_token = :token";
            } else {
                $token_sql = "";
            }

            // Profil resmi yükleme
            $profile_image_sql = "";
            $profile_image_path = null;
            
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
                
                if (in_array($file_type, $allowed) && $_FILES['profile_image']['size'] <= $max_size) {
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
                        
                        $profile_image_sql = ", profile_image = :profile_image";
                        $profile_image_path = $upload_dir . $filename;
                    }
                }
            }
            
            // SQL sorgusunu oluştur
            $sql = "UPDATE users SET 
                    bakery_name = :bakery_name, 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    email = :email, 
                    phone = :phone, 
                    identity_number = :identity_number, 
                    address = :address, 
                    role = :role, 
                    status = :status, 
                    email_verified = :email_verified
                    {$password_sql}
                    {$token_sql}
                    {$profile_image_sql},
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            
            $params = [
                ':bakery_name' => $bakery_name,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':phone' => $phone,
                ':identity_number' => $identity_number,
                ':address' => $address,
                ':role' => $role,
                ':status' => $status,
                ':email_verified' => $email_verified,
                ':id' => $user_id
            ];
            
            if ($passwordChanged) {
                $params[':password'] = $hashedPassword;
            }
            
            if ($verification_token !== null) {
                $params[':token'] = $verification_token;
            }

            if ($profile_image_path !== null) {
                $params[':profile_image'] = $profile_image_path;
            }
            
            $stmt->execute($params);
            
            // Kullanıcı aktivitesini kaydet
            logActivity($_SESSION['user_id'], 'Kullanıcı güncellendi: ' . $first_name . ' ' . $last_name, $pdo);
            
            // E-posta doğrulama maili gönderimi (istenirse eklenebilir)
            // E-posta doğrulama maili gönderimi
if ($verification_token !== null) {
    // Doğrulama bağlantısı için URL oluştur
    $verificationLink = rtrim(BASE_URL, '/') . '/verify-email.php?email=' . urlencode($email) . '&token=' . $verification_token;
    
    // HTML e-posta içeriği oluştur
    $subject = "E-posta Adresinizi Doğrulayın";
    
    $content = '
    <p>Sayın <strong>' . htmlspecialchars($first_name . ' ' . $last_name) . '</strong>,</p>
    <p>Hesabınız başarıyla güncellendi. Hesabınızı aktifleştirmek için lütfen aşağıdaki butona tıklayarak e-posta adresinizi doğrulayın:</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>E-posta Adresi:</strong> ' . htmlspecialchars($email) . '</p>
        <p style="margin: 5px 0;"><strong>Büfe Adı:</strong> ' . htmlspecialchars($bakery_name) . '</p>
    </div>
    
    <p style="font-size: 14px; color: #777;">Ya da aşağıdaki bağlantıyı tarayıcınıza kopyalayabilirsiniz:</p>
    <p style="font-size: 12px; color: #999; word-break: break-all;">' . $verificationLink . '</p>
    <p>Bu doğrulama bağlantısı 24 saat süreyle geçerlidir.</p>';
    
    $htmlMessage = getStandardEmailTemplate($subject, $content, 'E-posta Adresimi Doğrula', $verificationLink);
    
    // Düz metin içeriği oluştur
    $plainMessage = generatePlainTextFromHtml($htmlMessage);
    
    // E-postayı gönder
    if (sendEmail($email, $subject, $htmlMessage, $plainMessage)) {
        // Değişkeni kontrol et ve düzgün bir şekilde ayarla
        if (!isset($_SESSION['success_message'])) {
            $_SESSION['success_message'] = 'Kullanıcı bilgileri başarıyla güncellendi. Kullanıcıya doğrulama e-postası gönderildi.';
        } else {
            $_SESSION['success_message'] .= ' Kullanıcıya doğrulama e-postası gönderildi.';
        }
        
        // Doğrulama e-postası gönderildiğini kaydet
        logActivity($_SESSION['user_id'], 'Doğrulama e-postası gönderildi: ' . $email, $pdo);
    } else {
        // Hata olursa mesajı ekle ama işlemi durdurmadan devam et
        error_log("Doğrulama e-postası gönderilemedi: $email");
    }
}
            
            $success = true;
            $_SESSION['success_message'] = 'Kullanıcı bilgileri başarıyla güncellendi.';
            
            // Kullanıcı bilgilerini yeniden yükle
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

// Sayfa başlığı
$page_title = 'Kullanıcı Düzenle';

// Header'ı dahil et
include_once ROOT_PATH . '/admin/header.php';
?>

<!-- Ana içerik -->
<!-- Ana içerik -->
<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kullanıcı Düzenle</h1>
        <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>



    <form action="edit.php?id=<?php echo $user_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="row">
            <!-- Sol Kolon: Profil Kartı & Hesap Durumu -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Profil Özeti</h6>
                    </div>
                    <div class="card-body text-center">
                        <?php 
                            $initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
                            $bg_color = 'bg-primary';
                            if ($user['role'] === 'admin') $bg_color = 'bg-danger';
                            elseif ($user['role'] === 'approver') $bg_color = 'bg-warning text-dark';
                        ?>
                        <div class="avatar-circle <?php echo $bg_color; ?> text-white d-flex align-items-center justify-content-center rounded-circle mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem; overflow: hidden;">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo BASE_URL . '/' . $user['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <h5 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user['bakery_name']); ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i> Admin</span>
                            <?php elseif ($user['role'] === 'approver'): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-user-check me-1"></i> Onaylayıcı</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark"><i class="fas fa-store me-1"></i> Bayi</span>
                            <?php endif; ?>
                            
                            <?php if ($user['status'] == 1): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pasif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Hesap Durumu</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="role" class="form-label fw-bold">Kullanıcı Rolü</label>
                            <select class="form-select" id="role" name="role">
                                <option value="bakery" <?php echo $user['role'] === 'bakery' ? 'selected' : ''; ?>>Bayi</option>
                                <option value="approver" <?php echo $user['role'] === 'approver' ? 'selected' : ''; ?>>Onaylayıcı</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo $user['status'] == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Hesap Aktif</label>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="email_verified" name="email_verified" <?php echo $user['email_verified'] == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_verified">E-posta Doğrulanmış</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Detaylı Bilgiler -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Kullanıcı Bilgileri</h6>
                    </div>
                    <div class="card-body">
                        <h6 class="heading-small text-muted mb-4">Kişisel Bilgiler</h6>
                        
                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Profil Resmi</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                            <div class="form-text">JPG, PNG veya GIF. Maksimum 2MB.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="identity_number" class="form-label">TC Kimlik No <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="identity_number" name="identity_number" value="<?php echo htmlspecialchars($user['identity_number']); ?>" required maxlength="11">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bakery_name" class="form-label">Büfe Adı <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                                    <input type="text" class="form-control" id="bakery_name" name="bakery_name" value="<?php echo htmlspecialchars($user['bakery_name']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="heading-small text-muted mb-4">İletişim Bilgileri</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="heading-small text-muted mb-4">Güvenlik</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Yeni Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                                </div>
                                <div class="form-text">Şifreyi değiştirmek istemiyorsanız boş bırakın.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password_confirm" class="form-label">Yeni Şifre (Tekrar)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// Footer'ı dahil et
include_once ROOT_PATH . '/admin/footer.php';
?>