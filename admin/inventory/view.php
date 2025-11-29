<?php
/**
 * Admin Paneli - Stok Detay Görüntüleme Sayfası
 */

require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/inventory_functions.php';

$page_title = 'Stok Detayı';
$current_page = 'inventory';

$bread_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bread_id <= 0) {
    $_SESSION['error_message'] = "Geçersiz ekmek ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php');
    exit;
}

$bread_info = getBreadInfo($bread_id, $pdo);
$current_inventory = getInventory($bread_id, $pdo);

if (!$bread_info) {
    $_SESSION['error_message'] = "Ekmek türü bulunamadı.";
    redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php');
    exit;
}

// Son Stok Hareketleri
$recent_movements = [];
try {
    $stmt = $pdo->prepare("
        SELECT im.*, u.username as created_by_name, o.order_number
        FROM inventory_movements im
        LEFT JOIN users u ON im.created_by = u.id
        LEFT JOIN orders o ON im.order_id = o.id
        WHERE im.bread_id = ?
        ORDER BY im.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$bread_id]);
    $recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent Movements Fetch Error: " . $e->getMessage());
}

// Stok İstatistikleri (Son 30 gün)
$stats = [
    'total_in_piece' => 0, 'total_out_piece' => 0,
    'total_in_box' => 0, 'total_out_box' => 0,
    'movement_count' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN movement_type = 'in' THEN piece_quantity ELSE 0 END) as total_in_piece,
            SUM(CASE WHEN movement_type = 'out' THEN piece_quantity ELSE 0 END) as total_out_piece,
            SUM(CASE WHEN movement_type = 'in' THEN box_quantity ELSE 0 END) as total_in_box,
            SUM(CASE WHEN movement_type = 'out' THEN box_quantity ELSE 0 END) as total_out_box,
            COUNT(*) as movement_count
        FROM inventory_movements
        WHERE bread_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$bread_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Inventory Stats Error: " . $e->getMessage());
}

// Stok Değeri Hesaplama
$piece_value = ($current_inventory['piece_quantity'] ?? 0) * $bread_info['price'];
$box_value = 0;
if (!empty($bread_info['box_capacity']) && $bread_info['box_capacity'] > 0) {
    $box_value = ($current_inventory['box_quantity'] ?? 0) * ($bread_info['box_capacity'] * $bread_info['price']);
}
$total_stock_value = $piece_value + $box_value;

include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <span class="text-primary"><?php echo htmlspecialchars($bread_info['name']); ?></span>
            <span class="text-muted fs-5 ms-2">Stok Detayı</span>
        </h1>
        <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Listeye Dön
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/add.php?bread_id=<?php echo $bread_id; ?>" class="btn btn-success btn-sm shadow-sm">
                <i class="fas fa-plus me-1"></i> Stok Ekle
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/edit.php?id=<?php echo $bread_id; ?>" class="btn btn-warning btn-sm shadow-sm">
                <i class="fas fa-edit me-1"></i> Düzenle
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sol Kolon: Ekmek Bilgisi ve Mevcut Stok -->
        <div class="col-xl-4 col-lg-5">
            <!-- Ekmek Bilgisi Kartı -->
            <div class="card shadow mb-4">
                <div class="card-body text-center pt-4">
                    <?php if (!empty($bread_info['image']) && file_exists(ROOT_PATH . '/uploads/' . $bread_info['image'])): ?>
                        <img src="<?php echo BASE_URL; ?>/uploads/<?php echo $bread_info['image']; ?>" 
                             alt="<?php echo htmlspecialchars($bread_info['name']); ?>" 
                             class="img-fluid rounded shadow-sm mb-3" 
                             style="max-height: 150px;">
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto mb-3 text-muted" style="width: 100px; height: 100px; font-size: 2rem;">
                            <i class="fas fa-bread-slice"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h5 class="font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($bread_info['name']); ?></h5>
                    <div class="mb-3">
                        <?php if ($bread_info['status'] == 1): ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Pasif</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row text-start mt-4">
                        <div class="col-6 mb-2">
                            <small class="text-uppercase text-muted fw-bold">Satış Türü</small>
                            <div class="text-dark fw-bold">
                                <?php 
                                    switch ($bread_info['sale_type']) {
                                        case 'piece': echo 'Adet'; break;
                                        case 'box': echo 'Kasa'; break;
                                        case 'both': echo 'Adet & Kasa'; break;
                                        default: echo '-';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-uppercase text-muted fw-bold">Birim Fiyat</small>
                            <div class="text-success fw-bold"><?php echo formatMoney($bread_info['price']); ?></div>
                        </div>
                        <?php if (!empty($bread_info['box_capacity'])): ?>
                        <div class="col-6 mb-2">
                            <small class="text-uppercase text-muted fw-bold">Kasa Kapasitesi</small>
                            <div class="text-dark fw-bold"><?php echo $bread_info['box_capacity']; ?> adet</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($bread_info['is_packaged']): ?>
                        <div class="col-6 mb-2">
                            <small class="text-uppercase text-muted fw-bold">Paket Ağırlığı</small>
                            <div class="text-dark fw-bold"><?php echo $bread_info['package_weight']; ?>g</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light text-center">
                    <a href="<?php echo BASE_URL; ?>/admin/bread/view.php?id=<?php echo $bread_id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i> Ürün Detayına Git
                    </a>
                </div>
            </div>

            <!-- Mevcut Stok Kartı -->
            <div class="card shadow mb-4 border-left-success">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-warehouse me-2"></i>Mevcut Stok</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h1 font-weight-bold text-dark mb-0"><?php echo number_format($current_inventory['piece_quantity'] ?? 0); ?></div>
                            <small class="text-uppercase text-muted fw-bold">Adet</small>
                            <div class="text-success small mt-1 fw-bold"><?php echo formatMoney($piece_value); ?></div>
                        </div>
                        <div class="col-6 border-start">
                            <div class="h1 font-weight-bold text-dark mb-0"><?php echo number_format($current_inventory['box_quantity'] ?? 0); ?></div>
                            <small class="text-uppercase text-muted fw-bold">Kasa</small>
                            <div class="text-success small mt-1 fw-bold"><?php echo formatMoney($box_value); ?></div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold text-uppercase">Toplam Stok Değeri</span>
                        <span class="h5 mb-0 font-weight-bold text-success"><?php echo formatMoney($total_stock_value); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: İstatistikler ve Hareketler -->
        <div class="col-xl-8 col-lg-7">
            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Son 30 Gün (Giriş)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_in_piece'] ?? 0); ?> Adet / 
                                        <?php echo number_format($stats['total_in_box'] ?? 0); ?> Kasa
                                    </div>
                                </div>
                                <div class="col-auto"><i class="fas fa-arrow-down fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Son 30 Gün (Çıkış)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_out_piece'] ?? 0); ?> Adet / 
                                        <?php echo number_format($stats['total_out_box'] ?? 0); ?> Kasa
                                    </div>
                                </div>
                                <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Hareketler -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>Son Hareketler</h6>
                    <a href="<?php echo BASE_URL; ?>/admin/inventory/movements.php?bread_id=<?php echo $bread_id; ?>" class="btn btn-sm btn-outline-primary">
                        Tümünü Gör
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Tarih</th>
                                    <th class="text-center">Tür</th>
                                    <th class="text-center">Miktar</th>
                                    <th>Açıklama</th>
                                    <th class="text-end pe-3">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_movements)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Henüz stok hareketi yok.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_movements as $movement): ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap">
                                            <div class="fw-bold text-dark"><?php echo date('d.m.Y', strtotime($movement['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($movement['created_at'])); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($movement['movement_type'] == 'in'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-arrow-down me-1"></i>Giriş</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger"><i class="fas fa-arrow-up me-1"></i>Çıkış</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($movement['piece_quantity'] > 0): ?>
                                                <div class="fw-bold text-dark"><?php echo number_format($movement['piece_quantity']); ?> Adet</div>
                                            <?php endif; ?>
                                            <?php if ($movement['box_quantity'] > 0): ?>
                                                <div class="fw-bold text-dark"><?php echo number_format($movement['box_quantity']); ?> Kasa</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($movement['order_id'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/orders/view.php?id=<?php echo $movement['order_id']; ?>" class="badge bg-primary text-decoration-none">
                                                    #<?php echo $movement['order_number']; ?>
                                                </a>
                                            <?php endif; ?>
                                            <span class="text-muted small ms-1"><?php echo htmlspecialchars($movement['note'] ?? ''); ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <small class="text-muted fst-italic"><?php echo htmlspecialchars($movement['created_by_name'] ?? 'Sistem'); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . '/admin/footer.php'; ?>