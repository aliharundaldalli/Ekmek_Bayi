<?php
/**
 * Admin Dashboard - Modern Arayüz
 */

require_once '../init.php';

// Yetki Kontrolü
if (!isLoggedIn()) { redirect(BASE_URL . 'login.php'); exit; }
if (!isAdmin()) { redirect(BASE_URL . 'my/index.php'); exit; }

// --- Veri Çekme İşlemleri ---
try {
    // İstatistikler
    $active_bakeries = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bakery' AND status = 1")->fetchColumn();
    $active_breads = $pdo->query("SELECT COUNT(*) FROM bread_types WHERE status = 1")->fetchColumn();
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    
    // Bugünün Özeti
    $todays_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $todays_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Son Siparişler
    $stmt_orders = $pdo->query("
        SELECT o.*, u.bakery_name, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC LIMIT 5
    ");
    $recent_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // Son Aktiviteler
    $stmt_activities = $pdo->query("
        SELECT a.*, u.first_name, u.last_name, u.role, u.bakery_name
        FROM user_activities a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $recent_activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $active_bakeries = $active_breads = $total_orders = $todays_orders = $todays_revenue = 0;
    $recent_orders = $recent_activities = [];
}

$page_title = 'Yönetim Paneli';
include_once 'header.php';
?>

<div class="container-fluid">

    <!-- Karşılama ve Özet -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Yönetim Paneli</h1>
            <p class="mb-0 text-muted">Hoş geldiniz, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Yönetici'); ?></strong>! İşte bugünün özeti.</p>
        </div>
        <div class="d-none d-sm-inline-block">
            <a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-chart-line fa-sm text-white-50 me-1"></i> Raporları Görüntüle
            </a>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="row">
        <!-- Günlük Ciro -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Bugünkü Ciro</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatMoney($todays_revenue); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Günlük Sipariş -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Bugünkü Siparişler</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $todays_orders; ?> Adet</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktif Büfeler -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Aktif Büfeler</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_bakeries; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toplam Ekmek Çeşidi -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Ekmek Çeşitleri</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_breads; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bread-slice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Son Siparişler -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Son Siparişler</h6>
                    <a href="<?php echo BASE_URL; ?>admin/orders/index.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Sipariş No</th>
                                    <th>Müşteri</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th class="text-end pe-4">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Henüz sipariş bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($order['bakery_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></small>
                                        </td>
                                        <td class="fw-bold"><?php echo formatMoney($order['total_amount']); ?></td>
                                        <td>
                                            <?php
                                                $status_class = 'secondary';
                                                $status_text = $order['status'];
                                                switch($order['status']) {
                                                    case 'pending': $status_class = 'warning'; $status_text = 'Beklemede'; break;
                                                    case 'processing': $status_class = 'primary'; $status_text = 'İşleniyor'; break;
                                                    case 'completed': $status_class = 'success'; $status_text = 'Tamamlandı'; break;
                                                    case 'cancelled': $status_class = 'danger'; $status_text = 'İptal'; break;
                                                }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?php echo BASE_URL; ?>admin/orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light text-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
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

        <!-- Son Aktiviteler -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Son Aktiviteler</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <div class="text-center text-muted py-3">Henüz aktivite yok.</div>
                    <?php else: ?>
                        <div class="timeline-simple">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar-circle-sm bg-light text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-muted mb-1"><?php echo date('d.m H:i', strtotime($activity['created_at'])); ?></div>
                                    <p class="mb-0 text-dark small">
                                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                        <?php echo htmlspecialchars($activity['activity_type']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center bg-light">
                    <a href="<?php echo BASE_URL; ?>admin/system/activity_log.php" class="text-primary small fw-bold text-decoration-none">Tüm Aktiviteleri Gör <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include_once 'footer.php'; ?>