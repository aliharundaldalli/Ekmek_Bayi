<?php
/**
 * Genel Site Header (Dinamik Veri + URL Düzeltmesi)
 */

// Config dosyasını dahil et
// Bu dosyanın $settings dizisini, BASE_URL'yi, session'ı ve helper fonksiyonları
// hazırladığını varsayıyoruz.
require_once 'config/config.php';

// Gerekli değişken/sabitler tanımlı mı kontrol edelim (opsiyonel ama iyi pratik)
if (!isset($settings) || !is_array($settings)) { 
    // Site settings tablosundan verileri al
    $settings = [];
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log("Site Settings Error: " . $e->getMessage());
        }
    }
}

if (!defined('BASE_URL')) { define('BASE_URL', '/'); /* error_log(...); */ }
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Güvenli URL için
$base_url_trimmed = rtrim(BASE_URL, '/');

// Sayfa başlığı (çağıran script tarafından tanımlanmalı, varsayılan değer atandı)
$page_title = $page_title ?? '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars($settings['site_title'] ?? 'Site Başlığı'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
    <link rel="icon" href="<?php echo $base_url_trimmed . '/' . htmlspecialchars($settings['favicon'] ?? 'favicon.ico'); ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="<?php echo $base_url_trimmed; ?>/assets/css/style.css">
</head>
<body>
    <div class="d-flex flex-column min-vh-100">

        <header class="bg-dark text-white text-center py-3 shadow-sm">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-4 text-md-start mb-2 mb-md-0">
                        <a href="<?php echo $base_url_trimmed; ?>/" class="text-white text-decoration-none d-inline-flex align-items-center">
                             <?php $logo_path = $settings['logo'] ?? 'assets/images/logo.png'; ?>
                            <img src="<?php echo $base_url_trimmed . '/' . htmlspecialchars($logo_path); ?>"
                                 alt="<?php echo htmlspecialchars($settings['site_title'] ?? 'Site Logosu'); ?>"
                                 height="40" class="me-2"
                                 onerror="this.style.display='none'">
                             <span class="fs-4 fw-bold d-none d-sm-inline"><?php echo htmlspecialchars($settings['site_title'] ?? 'Site Başlığı'); ?></span>
                        </a>
                    </div>

                    <?php // Kullanıcı giriş yapmışsa orta ve sağ sütunları göster ?>
                    <?php if(function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <div class="col-md-4 text-center mb-2 mb-md-0">
                            <p class="mb-0">
                                <?php // Rol kontrolü (fonksiyonların varlığını kontrol etmek iyi pratiktir) ?>
                                <?php if(function_exists('isBakery') && isBakery() && isset($_SESSION['bakery_name'])): ?>
                                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($_SESSION['bakery_name']); ?></span>
                                <?php elseif(function_exists('isAdmin') && isAdmin()): ?>
                                    <span class="badge bg-danger fs-6">Yönetici</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-light dropdown-toggle btn-sm" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="userDropdown">
                                    <?php if(function_exists('isAdmin') && isAdmin()): ?>
                                        <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/admin/index.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Yönetim Paneli</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/admin/profile.php"><i class="fas fa-user-edit fa-fw me-2"></i>Profil (Admin)</a></li>
                                    <?php elseif(function_exists('isBakery') && isBakery()): // isBakery fonksiyonu varsa ?>
                                        <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/my/index.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Büfe Paneli</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/my/profile.php"><i class="fas fa-user-edit fa-fw me-2"></i>Profil (Büfe)</a></li>
                                    <?php else: // Diğer roller veya tanımsız durum (nadiren olmalı) ?>
                                         <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/my/profile.php"><i class="fas fa-user fa-fw me-2"></i>Profilim</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo $base_url_trimmed; ?>/logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Çıkış Yap</a></li>
                                </ul>
                            </div>
                        </div>
                    <?php else: // Kullanıcı giriş yapmamışsa ?>
                         <div class="col-md-8 text-md-end"> 
                            <a href="<?php echo $base_url_trimmed; ?>/login.php" class="btn btn-outline-light btn-sm">Giriş Yap</a>
                         </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="container mt-4 flex-grow-1">
            
            <?php
            // Session mesajlarını göster (başarı, hata, bilgi)
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $_SESSION['error_message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                unset($_SESSION['error_message']);
            }
            if (isset($_SESSION['info_message'])) {
                echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['info_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                unset($_SESSION['info_message']);
            }
            ?>