<?php
/**
 * Kullanıcı Görüntüleme Sayfası
 */

// init.php dosyasını dahil et
require_once '../../init.php';

// Admin yetkisi kontrolü
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
    $_SESSION['error_message'] = 'Veritabanı hatası: ' . $e->getMessage();
    redirect(BASE_URL . '/admin/users/index.php');
}

// Kullanıcı aktivitesini kaydet
logActivity($_SESSION['user_id'], 'Kullanıcı görüntülendi: ' . $user['first_name'] . ' ' . $user['last_name'], $pdo);

// Sayfa başlığı
$page_title = 'Kullanıcı Detayları';

// Header'ı dahil et
include_once ROOT_PATH . '/admin/header.php';

// Yardımcı değişkenler
$role_badge = ($user['role'] === 'admin') 
    ? '<span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i>Admin</span>' 
    : '<span class="badge bg-info text-dark"><i class="fas fa-store me-1"></i>Büfe</span>';

$status_badge = ($user['status'] == 1) 
    ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aktif</span>' 
    : '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Pasif</span>';

$email_verified_badge = ($user['email_verified'] == 1) 
    ? '<span class="badge bg-success" title="Doğrulanmış"><i class="fas fa-check me-1"></i>Onaylı</span>' 
    : '<span class="badge bg-warning text-dark" title="Doğrulanmamış"><i class="fas fa-exclamation-triangle me-1"></i>Onaysız</span>';

$initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
?>

<!-- Ana içerik -->
<div class="container-fluid">
    
    <!-- Üst Başlık ve Aksiyonlar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
        <div>
            <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Listeye Dön
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/users/edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm shadow-sm ms-2">
                <i class="fas fa-edit me-1"></i> Düzenle
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/users/delete.php?id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm shadow-sm ms-2" onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                <i class="fas fa-trash me-1"></i> Sil
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sol Kolon: Profil Kartı -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center pt-5 pb-4">
                    <div class="avatar-circle mb-3 mx-auto bg-primary text-white d-flex align-items-center justify-content-center shadow" style="width: 100px; height: 100px; font-size: 2.5rem; border-radius: 50%; overflow: hidden;">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $user['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-weight-bold mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['bakery_name']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <?php echo $role_badge; ?>
                        <?php echo $status_badge; ?>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-envelope me-2 text-primary"></i> E-posta Gönder
                        </a>
                        <?php if (!empty($user['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-phone me-2 text-success"></i> Ara
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Kayıt Tarihi</span>
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

            <!-- İletişim Bilgileri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-address-card me-2"></i>İletişim Bilgileri</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">E-posta</small>
                        <div class="d-flex justify-content-start align-items-center gap-2">
                            <span class="fw-bold"><?php echo htmlspecialchars($user['email']); ?></span>
                           
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

        <!-- Sağ Kolon: Detaylar ve İstatistikler -->
        <div class="col-xl-8 col-lg-7">
            
            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Toplam Sipariş</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_orders']); ?> Adet</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Harcama</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_spend'], 2, ',', '.'); ?> TL</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kişisel Bilgiler -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle me-2"></i>Kişisel Bilgiler</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Ad Soyad</label>
                            <div class="h6 font-weight-bold text-dark"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted text-uppercase fw-bold">TC Kimlik No</label>
                            <div class="h6 font-weight-bold text-dark"><?php echo htmlspecialchars($user['identity_number'] ?: '-'); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Büfe Adı</label>
                            <div class="h6 font-weight-bold text-dark"><?php echo htmlspecialchars($user['bakery_name']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Kullanıcı ID</label>
                            <div class="h6 font-weight-bold text-dark">#<?php echo $user['id']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hesap Bilgileri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-cog me-2"></i>Hesap Detayları</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless table-sm m-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 150px;">Hesap Durumu:</td>
                                    <td class="fw-bold"><?php echo $status_badge; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Yetki Seviyesi:</td>
                                    <td class="fw-bold"><?php echo $role_badge; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">E-posta Onayı:</td>
                                    <td class="fw-bold"><?php echo $email_verified_badge; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Oluşturulma:</td>
                                    <td class="fw-bold"><?php echo date('d.m.Y H:i:s', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Son Güncelleme:</td>
                                    <td class="fw-bold"><?php echo ($user['updated_at']) ? date('d.m.Y H:i:s', strtotime($user['updated_at'])) : '-'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once ROOT_PATH . '/admin/footer.php';
?>