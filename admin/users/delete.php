<?php
/**
 * Kullanıcı Silme Sayfası
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

// Admin kendisini silmeye çalışıyor mu kontrol et
if ($user_id === (int)$_SESSION['user_id']) {
    $_SESSION['error_message'] = 'Kendi hesabınızı silemezsiniz.';
    redirect(BASE_URL . '/admin/users/index.php');
}

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

$errors = [];
$success = false;

// Silme işlemi onaylandı mı kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    try {
        // Kullanıcıyı sil
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Kullanıcı aktivitesini kaydet
        logActivity($_SESSION['user_id'], 'Kullanıcı silindi: ' . $user['first_name'] . ' ' . $user['last_name'], $pdo);
        
        $_SESSION['success_message'] = 'Kullanıcı başarıyla silindi.';
        redirect(BASE_URL . '/admin/users/index.php');
    } catch (PDOException $e) {
        $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

// Sayfa başlığı
$page_title = 'Kullanıcı Sil';

// Header'ı dahil et
include_once ROOT_PATH . '/admin/header.php';
?>

<!-- Ana içerik -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user-times me-2"></i><?php echo $page_title; ?>
        </h6>
        <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Kullanıcı Listesine Dön
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5 class="alert-heading">Hata!</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Uyarı!</h5>
            <p>
                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong> 
                adlı kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.
            </p>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Kullanıcı Bilgileri</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">ID:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['id']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">Büfe Adı:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['bakery_name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">Ad Soyad:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">E-posta:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">Telefon:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['phone']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label fw-bold">Kullanıcı Rolü:</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?php echo ($user['role'] === 'admin') ? 'Admin' : 'Büfe'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="post" action="" class="mt-4 text-center">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash me-2"></i>Evet, Kullanıcıyı Sil
            </button>
            
            <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-secondary ms-2">
                <i class="fas fa-times me-2"></i>İptal
            </a>
        </form>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once ROOT_PATH . '/admin/footer.php';
?>