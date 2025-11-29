<?php
/**
 * Büfe Kullanıcı Paneli - Dashboard
 * Modern ve profesyonel arayüz
 */

require_once '../init.php';

// Kullanıcı Kontrolü
if (!isLoggedIn()) { redirect(BASE_URL . 'login.php'); exit; }
if (isAdmin()) { redirect(BASE_URL . 'admin/index.php'); exit; }

$page_title = 'Büfe Paneli';
$current_page = 'dashboard';
$user_id = $_SESSION['user_id'];

// --- Veri Çekme İşlemleri ---
try {
    // İstatistikler
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id")->fetchColumn();
    $pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id AND status IN ('pending', 'processing')")->fetchColumn();
    $completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id AND status = 'completed'")->fetchColumn();
    
    // Son 30 Gün Harcama
    $stmt_spent = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt_spent->execute([$user_id]);
    $last_30_days_spent = $stmt_spent->fetchColumn();

    // Son Siparişler
    $stmt_orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_orders->execute([$user_id]);
    $recent_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // Son Aktiviteler
    $stmt_activities = $pdo->prepare("SELECT * FROM user_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_activities->execute([$user_id]);
    $recent_activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $total_orders = $pending_orders = $completed_orders = $last_30_days_spent = 0;
    $recent_orders = $recent_activities = [];
}

include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">

    <!-- Karşılama ve Hızlı İşlem -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Büfe Paneli</h1>
            <p class="mb-0 text-muted">Hoş geldiniz, <strong><?php echo htmlspecialchars($_SESSION['bakery_name'] ?? $_SESSION['user_name']); ?></strong>! İşlerinizi buradan yönetebilirsiniz.</p>
        </div>
        <div class="mt-3 mt-sm-0">
            <a href="<?php echo BASE_URL; ?>my/orders/create.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus-circle fa-sm text-white-50 me-2"></i> Yeni Sipariş Oluştur
            </a>
        </div>
    </div>

    <?php if ($pending_orders > 0): ?>
    <div class="alert alert-info border-left-info shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <div class="alert-icon-aside">
                <i class="fas fa-info-circle fa-lg"></i>
            </div>
            <div class="alert-content ms-3">
                <h6 class="alert-heading fw-bold mb-1">Bekleyen Siparişleriniz Var</h6>
                <p class="mb-0 small">Şu anda onay bekleyen veya hazırlanan <strong><?php echo $pending_orders; ?></strong> adet siparişiniz bulunmaktadır.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="row">
        <!-- Toplam Sipariş -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Sipariş</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_orders); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-basket fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bekleyen Sipariş -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bekleyen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_orders); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tamamlanan Sipariş -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Tamamlanan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($completed_orders); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son 30 Gün Harcama -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Son 30 Gün Harcama</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatMoney($last_30_days_spent); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wallet fa-2x text-gray-300"></i>
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
                    <h6 class="m-0 font-weight-bold text-primary">Son Siparişlerim</h6>
                    <a href="<?php echo BASE_URL; ?>my/orders/index.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Sipariş No</th>
                                    <th>Tarih</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th class="text-end pe-4">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Henüz siparişiniz bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
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
                                            <a href="<?php echo BASE_URL; ?>my/orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light text-primary">
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
                                        <?php
                                            $icon = 'fa-history';
                                            switch($activity['activity_type']) {
                                                case 'login': $icon = 'fa-sign-in-alt'; break;
                                                case 'logout': $icon = 'fa-sign-out-alt'; break;
                                                case 'order_create': $icon = 'fa-shopping-cart'; break;
                                                case 'profile_update': $icon = 'fa-user-edit'; break;
                                            }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-muted mb-1"><?php echo date('d.m H:i', strtotime($activity['created_at'])); ?></div>
                                    <p class="mb-0 text-dark small">
                                        <?php echo htmlspecialchars($activity['activity_type']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include_once ROOT_PATH . '/my/footer.php'; ?>