<?php
/**
 * Kullanıcı Ekleme Sayfası
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
        // E-posta başka bir kullanıcı tarafından kullanılıyor mu kontrol et
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
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
        // TC kimlik numarası başka bir kullanıcı tarafından kullanılıyor mu kontrol et
        $stmt = $pdo->prepare("SELECT id FROM users WHERE identity_number = ?");
        $stmt->execute([$identity_number]);
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Bu TC kimlik numarası başka bir kullanıcı tarafından kullanılıyor.';
        }
    }
    
    if (empty($address)) {
        $errors[] = 'Adres alanı boş bırakılamaz.';
    }
    
    if (empty($password)) {
        $errors[] = 'Şifre alanı boş bırakılamaz.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Şifreler eşleşmiyor.';
    }
    
    if (!in_array($role, ['admin', 'bakery'])) {
        $errors[] = 'Geçersiz kullanıcı rolü.';
    }
    
    // Hata yoksa kullanıcıyı ekle
    if (empty($errors)) {
        try {
            $hashedPassword = hashPassword($password);
            $verification_token = null;
            
            // Eğer e-posta doğrulanmamışsa bir doğrulama tokeni oluştur
            if (!$email_verified) {
                $verification_token = generateToken();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    bakery_name, 
                    first_name, 
                    last_name, 
                    email, 
                    phone, 
                    identity_number, 
                    address, 
                    password, 
                    role, 
                    status, 
                    email_verified, 
                    email_verification_token, 
                    created_at, 
                    updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $bakery_name,
                $first_name,
                $last_name,
                $email,
                $phone,
                $identity_number,
                $address,
                $hashedPassword,
                $role,
                $status,
                $email_verified,
                $verification_token
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Kullanıcı aktivitesini kaydet
            logActivity($_SESSION['user_id'], 'Yeni kullanıcı oluşturuldu: ' . $first_name . ' ' . $last_name, $pdo);
            
            // E-posta doğrulama maili gönderimi (istenirse eklenebilir)
            if (!$email_verified && $verification_token) {
                // Doğrulama e-postası gönderme kodu burada yer alabilir
            }
            
            $success = true;
            $_SESSION['success_message'] = 'Kullanıcı başarıyla oluşturuldu.';
            
            // Başarıyla oluşturuldu, kullanıcı listesine yönlendir
            redirect(BASE_URL . '/admin/users/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

// Sayfa başlığı
$page_title = 'Yeni Kullanıcı Ekle';

// Header'ı dahil et
include_once ROOT_PATH . '/admin/header.php';
?>

<!-- Ana içerik -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user-plus me-2"></i><?php echo $page_title; ?>
        </h6>
        <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Kullanıcı Listesine Dön
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5 class="alert-heading">Lütfen aşağıdaki hataları düzeltin:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <h5 class="alert-heading">Başarılı!</h5>
                <p>Kullanıcı başarıyla oluşturuldu.</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6">
                    <h5>Kişisel Bilgiler</h5>
                    <div class="mb-3">
                        <label for="bakery_name" class="form-label">Büfe Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bakery_name" name="bakery_name" value="<?php echo isset($_POST['bakery_name']) ? $_POST['bakery_name'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? $_POST['first_name'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? $_POST['last_name'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="identity_number" class="form-label">TC Kimlik No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="identity_number" name="identity_number" value="<?php echo isset($_POST['identity_number']) ? $_POST['identity_number'] : ''; ?>" maxlength="11" required>
                        <div class="form-text">11 haneli TC kimlik numarası</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5>İletişim ve Hesap Bilgileri</h5>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">En az 6 karakter</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Kullanıcı Rolü <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role">
                            <option value="bakery" <?php echo (isset($_POST['role']) && $_POST['role'] === 'bakery') ? 'selected' : ''; ?>>Büfe</option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="status" name="status" <?php echo (!isset($_POST['status']) || isset($_POST['status'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Aktif</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="email_verified" name="email_verified" <?php echo (isset($_POST['email_verified'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_verified">E-posta Doğrulandı</label>
                        <div class="form-text">Bu seçenek işaretlenmezse, kullanıcıya e-posta doğrulama bağlantısı gönderilecektir.</div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Kullanıcı Ekle
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-secondary ms-2">
                    <i class="fas fa-times me-2"></i>İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once ROOT_PATH . '/admin/footer.php';
?>