<?php
/**
 * Admin Panel Header (Veritabanından Dinamik Veri + URL Düzeltmesi + Güvenlik)
 */

// init.php dosyasının daha önce dahil edildiğini ve $pdo nesnesinin
// ve BASE_URL sabitinin tanımlı olduğunu varsayıyoruz.
if (!isset($pdo) || !defined('BASE_URL')) {
    // Gerekli $pdo veya BASE_URL yoksa hata günlüğü tut veya çıkış yap
    error_log("Header Error: \$pdo object or BASE_URL constant is not available.");
    exit('Kritik yapılandırma eksik.');
}

// --- Veritabanından Site Ayarlarını Çek ---
try {
    // Tüm ayarları tek sorguda çekip anahtar=>değer çifti olarak alalım
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    // FETCH_KEY_PAIR ile ['setting_key' => 'setting_value', ...] formatında dizi oluştur
    $site_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // Veritabanı hatası durumunda boş dizi ata ve hatayı logla
    $site_settings = [];
    error_log("Header DB Error: " . $e->getMessage());
    // Burada kullanıcıya daha nazik bir hata mesajı göstermek de düşünülebilir
}

// BASE_URL'nin sonundaki / karakterini kaldır (varsa)
$base_url_trimmed = rtrim(BASE_URL, '/');

// Sayfa başlığı için varsayılan değer (Header'ı çağıran script tanımlamalı)
$page_title = $page_title ?? 'Yönetim Paneli'; // $page_title'ın önceden tanımlı olduğunu varsayıyoruz

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($site_settings['site_title'] ?? 'Admin Panel'); // DB'den gelen site başlığı ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_settings['site_description'] ?? ''); // DB'den gelen açıklama ?>">
    <link rel="icon" href="<?php echo $base_url_trimmed . '/' . htmlspecialchars($site_settings['favicon'] ?? 'assets/images/favicon.png'); // DB'den gelen favicon ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo $base_url_trimmed; ?>/assets/css/style.css">

</head>
<body>
    <div class="d-flex flex-column min-vh-100">

        <header class="bg-dark text-white py-3 shadow-sm">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-4 text-md-start mb-2 mb-md-0">
                        <a href="<?php echo $base_url_trimmed; ?>/admin/index.php" class="text-white text-decoration-none d-inline-flex align-items-center">
                            <?php // Logo yolunu DB'den al, yoksa varsayılan kullan ?>
                            <?php $logo_path = $site_settings['logo'] ?? 'assets/images/logo.png'; ?>
                            <img src="<?php echo $base_url_trimmed . '/' . htmlspecialchars($logo_path); ?>"
                                 alt="<?php echo htmlspecialchars($site_settings['site_title'] ?? 'Site Logosu'); ?>"
                                 height="40" class="me-2"
                                 onerror="this.style.display='none'"> <?php // Resim yüklenemezse gizle ?>
                            <span class="fs-4 fw-bold d-none d-sm-inline"><?php echo htmlspecialchars($site_settings['site_title'] ?? 'Admin Panel'); ?></span>
                        </a>
                    </div>

                    <div class="col-md-4 text-center mb-2 mb-md-0">
                        <span class="badge bg-danger fs-6">Yönetici Paneli</span>
                    </div>

                    <div class="col-md-4 text-md-end">
                        <?php // Oturum kontrolü ve kullanıcı bilgisi ?>
                        <?php if(isset($_SESSION['user_name'])): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle btn-sm" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/admin/profile.php"><i class="fas fa-user-edit fa-fw me-2"></i>Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Çıkış Yap</a></li>
                            </ul>
                        </div>
                        <?php else: ?>
                            <a href="<?php echo $base_url_trimmed; ?>/login.php" class="btn btn-outline-light btn-sm">Giriş Yap</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <?php // ----- Sidebar ve Ana İçerik Başlangıcı ----- ?>
        <div class="container mt-4 flex-grow-1">
            <div class="row">
                <div class="col-lg-3 col-md-4">
                    <?php // --- Sidebar Menü --- ?>
                    <div class="list-group shadow-sm mb-4">
                        <span class="list-group-item list-group-item-dark fw-bold"><i class="fas fa-bars me-2"></i>Menü</span>
                        <?php
                            // Aktif menü öğesini belirlemek için fonksiyon
                            function isAdminMenuActive($path_part) {
                                // Mevcut sayfanın path'ini al
                                $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                                // Belirtilen path bölümü mevcut path içinde geçiyor mu kontrol et
                                return strpos($current_path, $path_part) !== false;
                            }
                            // Navigasyon için base URL (tekrar tekrar çağırmamak için)
                            $base_url_nav = rtrim(BASE_URL, '/');
                        ?>
                        <a href="<?php echo $base_url_nav; ?>/admin/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/index.php') ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i> Yönetim Paneli
                        </a>
                        <a href="<?php echo $base_url_nav; ?>/admin/users/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/users/') ? 'active' : ''; ?>">
                            <i class="fas fa-users fa-fw me-2"></i> Kullanıcı Yönetimi
                        </a>
                         <a href="<?php echo $base_url_nav; ?>/admin/bread/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/bread/') ? 'active' : ''; ?>">
                            <i class="fas fa-bread-slice fa-fw me-2"></i> Ekmek Çeşitleri
                        </a>
                        <a href="<?php echo $base_url_nav; ?>/admin/orders/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/orders/') ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-basket fa-fw me-2"></i> Sipariş Yönetimi
                        </a>
                        <a href="<?php echo $base_url_nav; ?>/admin/inventory/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/inventory/') ? 'active' : ''; ?>">
                            <i class="fas fa-boxes fa-fw me-2"></i> Stok Yönetimi
                        </a>
                        <a href="<?php echo $base_url_nav; ?>/admin/reports/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/reports/') ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar fa-fw me-2"></i> Raporlar
                        </a>
                         <a href="<?php echo $base_url_nav; ?>/admin/support/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/support/') ? 'active' : ''; ?>">
                             <i class="fas fa-headset fa-fw me-2"></i> Destek Talepleri
                         </a>
                        <a href="<?php echo $base_url_nav; ?>/admin/system/index.php" class="list-group-item list-group-item-action <?php echo isAdminMenuActive('/admin/system/') ? 'active' : ''; ?>">
                            <i class="fas fa-cog fa-fw me-2"></i> Sistem Ayarları
                        </a>
                    </div>
                </div>

                <div class="col-lg-9 col-md-8">
                     <?php // --- Bildirim Alanı (Session Mesajları için) --- ?>
                     <div id="notificationArea">
                         <?php
                         if (!empty($_SESSION['success_message'])) {
                             echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
                             echo '<i class="fas fa-check-circle me-2"></i> ' . htmlspecialchars($_SESSION['success_message']);
                             echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                             echo '</div>';
                             unset($_SESSION['success_message']);
                         }
                         if (!empty($_SESSION['error_message'])) {
                             echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                             echo '<i class="fas fa-exclamation-circle me-2"></i> ' . htmlspecialchars($_SESSION['error_message']);
                             echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                             echo '</div>';
                             unset($_SESSION['error_message']);
                         }
                         ?>
                     </div>
                     
                     