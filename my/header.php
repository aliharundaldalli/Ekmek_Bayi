<?php
/**
 * Büfe Kullanıcı Paneli - Header
 * Bu dosya büfe kullanıcılarının panel sayfalarının üst kısmını oluşturur.
 */

// Oturum kontrolü
if (!isLoggedIn()) {
    redirect(rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

// Kullanıcı büfe değilse, admin paneline yönlendir
if (isAdmin()) {
    redirect(rtrim(BASE_URL, '/') . '/admin/index.php');
    exit;
}

// Site ayarlarını al
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Site Settings Fetch Error: " . $e->getMessage());
}

// Siparişlerin durumunu kontrol et (açık/kapalı)
$order_system_open = true;
$order_system_message = "";
try {
    $stmt = $pdo->query("SELECT * FROM system_status ORDER BY id DESC LIMIT 1");
    $system_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($system_status && isset($system_status['is_active']) && $system_status['is_active'] == 0) {
        $order_system_open = false;
        $order_system_message = $system_status['reason'] ?? "Sipariş sistemi şu anda kapalıdır.";
    }
} catch (PDOException $e) {
    error_log("System Status Fetch Error: " . $e->getMessage());
}

// Sayfa başlığı (tanımlanmamışsa varsayılan)
$page_title = $page_title ?? 'Büfe Paneli';

// Aktif sayfa (tanımlanmamışsa varsayılan)
$current_page = $current_page ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş Sistemi'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
    
    <?php if (!empty($settings['favicon'])): ?>
    <link rel="icon" href="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($settings['favicon']); ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/style.css">
</head>
<body>
    <div class="d-flex flex-column min-vh-100">
        <!-- Header başlangıç -->
        <header class="bg-info text-white py-3 shadow-sm">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-4 text-md-start mb-2 mb-md-0">
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/index.php" class="text-white text-decoration-none d-inline-flex align-items-center">
                            <?php if (!empty($settings['logo'])): ?>
                            <img src="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($settings['logo']); ?>" alt="<?php echo htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş Sistemi'); ?>" height="40" class="me-2 bg-white p-1 rounded" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="fs-4 fw-bold d-none d-sm-inline"><?php echo htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş Sistemi'); ?></span>
                        </a>
                    </div>
                    
                    <div class="col-md-4 text-center mb-2 mb-md-0">
                        <span class="badge bg-light text-dark fs-6 border border-dark">Büfe Kullanıcı Paneli</span>
                    </div>
                    
                    <div class="col-md-4 text-md-end">
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle btn-sm" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['bakery_name'] ?? $_SESSION['user_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/my/profile/index.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- Header bitiş -->

        <div class="container mt-4 flex-grow-1">
            <?php if (!$order_system_open): ?>
            <div class="alert alert-warning mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Dikkat!</strong> <?php echo htmlspecialchars($order_system_message); ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-3 col-md-4">
                    <!-- Sidebar -->
                    <div class="list-group mb-4 shadow-sm">
                        <span class="list-group-item list-group-item-warning fw-bold text-dark"><i class="fas fa-bars me-2"></i>Menü</span>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i> Yönetim Paneli
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'orders' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-basket fa-fw me-2"></i> Siparişlerim
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/create.php" class="list-group-item list-group-item-action <?php echo $current_page === 'new_order' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle fa-fw me-2"></i> Yeni Sipariş
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/invoices/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'invoices' ? 'active' : ''; ?>">
                            <i class="fas fa-file-invoice fa-fw me-2"></i> Faturalarım
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/reports/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line fa-fw me-2"></i> Raporlarım
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/support/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'support' ? 'active' : ''; ?>">
                            <i class="fas fa-headset fa-fw me-2"></i> Destek Taleplerim
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/profile/index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog fa-fw me-2"></i> Profilim
                        </a>
                    </div>
                    
                    <!-- Hızlı Sipariş Oluştur -->
                    <?php if ($order_system_open): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-shopping-cart me-1"></i> Hızlı Sipariş
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="small">Ekmek siparişi vermek için hemen yeni sipariş oluşturun.</p>
                            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/create.php" class="btn btn-success w-100">
                                <i class="fas fa-plus-circle me-1"></i> Sipariş Oluştur
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- İletişim Bilgileri -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-phone-alt me-1"></i> İletişim
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?></p>
                            <p class="mb-0"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-9 col-md-8">
                    <!-- Ana içerik -->
                    <?php include_once ROOT_PATH . '/admin/includes/messages.php'; ?>