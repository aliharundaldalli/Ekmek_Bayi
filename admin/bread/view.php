<?php
/**
 * Ekmek Çeşidi Görüntüleme Sayfası
 */

// --- Yol Tanımlamaları ---
define('BASE_PATH', dirname(dirname(__DIR__)));

// --- init.php Dahil Etme ---
require_once BASE_PATH . '/init.php';

// Kullanıcı girişi ve yetkisi kontrolü
if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . 'my/index.php');
}

// --- ID Al ve Doğrula ---
$bread_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bread_id) {
    $_SESSION['error_message'] = 'Geçersiz Ekmek ID.';
    redirect(BASE_URL . 'admin/bread/index.php');
    exit;
}

// --- Veri Çekme ---
$bread = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM bread_types WHERE id = ?");
    $stmt->execute([$bread_id]);
    $bread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bread) {
        $_SESSION['error_message'] = 'Ekmek çeşidi bulunamadı (ID: ' . $bread_id . ').';
        redirect(BASE_URL . 'admin/bread/index.php');
        exit;
    }
} catch (PDOException $e) {
     error_log("Bread Fetch Error (View): " . $e->getMessage());
     $_SESSION['error_message'] = 'Veritabanı hatası oluştu.';
     redirect(BASE_URL . 'admin/bread/index.php');
     exit;
}

// Sayfa başlığı
$page_title = 'Ekmek Detayı: ' . htmlspecialchars($bread['name']);

// Header'ı dahil et
include_once BASE_PATH . '/admin/header.php';

// Yardımcı Değişkenler
$status_badge = $bread['status'] 
    ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aktif</span>' 
    : '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Pasif</span>';

$sale_type_badge = '';
switch($bread['sale_type']) {
    case 'piece': $sale_type_badge = '<span class="badge bg-info text-dark">Adet</span>'; break;
    case 'box': $sale_type_badge = '<span class="badge bg-primary">Kasa</span>'; break;
    case 'both': $sale_type_badge = '<span class="badge bg-info text-dark">Adet</span> <span class="badge bg-primary">Kasa</span>'; break;
    default: $sale_type_badge = '<span class="badge bg-secondary">Bilinmiyor</span>';
}
?>

<div class="container-fluid">
    <!-- Başlık ve Aksiyonlar -->
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($bread['name']); ?></h1>
        <div>
            <a href="<?php echo BASE_URL; ?>admin/bread/index.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Listeye Dön
            </a>
            <a href="<?php echo BASE_URL; ?>admin/bread/edit.php?id=<?php echo $bread['id']; ?>" class="btn btn-primary btn-sm shadow-sm ms-2">
                <i class="fas fa-edit me-1"></i> Düzenle
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sol Kolon: Görsel ve Temel Bilgiler -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center pt-4">
                    <?php
                        $image_file_path = BASE_PATH . '/uploads/' . $bread['image'];
                        $image_web_path = BASE_URL . 'uploads/' . htmlspecialchars($bread['image']);
                        $fallback_image_web_path = BASE_URL . 'assets/images/no-image.png';
                        
                        $img_src = (!empty($bread['image']) && file_exists($image_file_path)) ? $image_web_path : $fallback_image_web_path;
                    ?>
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($bread['name']); ?>" class="img-fluid rounded shadow-sm mb-4" style="max-height: 250px; width: auto;">
                    
                    <h4 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($bread['name']); ?></h4>
                    <div class="mb-3"><?php echo $status_badge; ?></div>
                    
                    <div class="h3 font-weight-bold text-primary mb-3">
                        <?php echo isset($formatMoney) ? formatMoney($bread['price']) : number_format($bread['price'], 2, ',', '.') . ' ₺'; ?>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <?php echo $sale_type_badge; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted"><small>Oluşturulma</small></span>
                            <span class="font-weight-bold text-dark"><?php echo $bread['created_at'] ? date('d.m.Y', strtotime($bread['created_at'])) : '-'; ?></span>
                        </div>
                        <div class="col-6">
                            <span class="d-block text-xs font-weight-bold text-uppercase text-muted"><small>Güncelleme</small></span>
                            <span class="font-weight-bold text-dark"><?php echo $bread['updated_at'] ? date('d.m.Y', strtotime($bread['updated_at'])) : '-'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Detaylı Bilgiler -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle me-2"></i>Ürün Detayları</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($bread['description'])): ?>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted small fw-bold">Açıklama</h6>
                        <p class="text-dark"><?php echo nl2br(htmlspecialchars($bread['description'])); ?></p>
                    </div>
                    <hr>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold">Satış Tipi</h6>
                            <div class="font-weight-bold text-dark">
                                <?php
                                    switch($bread['sale_type']) {
                                        case 'piece': echo 'Sadece Adet'; break;
                                        case 'box': echo 'Sadece Kasa'; break;
                                        case 'both': echo 'Adet ve Kasa'; break;
                                        default: echo '-';
                                    }
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($bread['sale_type'] === 'box' || $bread['sale_type'] === 'both'): ?>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold">Kasa Kapasitesi</h6>
                            <div class="font-weight-bold text-dark">
                                <i class="fas fa-box me-2 text-gray-400"></i>
                                <?php echo $bread['box_capacity'] ? htmlspecialchars($bread['box_capacity']) . ' adet' : '-'; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6 mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold">Paket Durumu</h6>
                            <div class="font-weight-bold text-dark">
                                <?php if ($bread['is_packaged']): ?>
                                    <span class="text-success"><i class="fas fa-check me-2"></i>Paketli Ürün</span>
                                <?php else: ?>
                                    <span class="text-secondary"><i class="fas fa-times me-2"></i>Paketsiz</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($bread['is_packaged']): ?>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold">Paket Ağırlığı</h6>
                            <div class="font-weight-bold text-dark">
                                <i class="fas fa-weight-hanging me-2 text-gray-400"></i>
                                <?php echo $bread['package_weight'] ? htmlspecialchars($bread['package_weight']) . ' gram' : '-'; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold">Ürün ID</h6>
                            <div class="font-weight-bold text-dark">#<?php echo $bread['id']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stok Durumu Kısayolu -->
            <div class="card shadow mb-4 border-left-info">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Stok Yönetimi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Bu ürünün stok hareketlerini inceleyin</div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>admin/inventory/view.php?id=<?php echo $bread['id']; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-boxes me-2"></i>Stok Detayına Git
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once BASE_PATH . '/admin/footer.php';
?>
