<?php
/**
 * Sistem Ayarları Yönetim Sayfası (Dosya Yükleme ve SMTP Ayarları Eklendi)
 */

// --- init.php Dahil Etme ---
require_once '../../init.php'; // init.php'nin ROOT_PATH ve BASE_URL tanımladığını varsayıyoruz

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Page Title ---
$page_title = 'Sistem Ayarları';

// --- Ayarları Veritabanından Çek ---
$settings = [];
$setting_details = [];
try {
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id ASC");
    $all_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
        $setting_details[$setting['setting_key']] = $setting;
    }
} catch (PDOException $e) {
    error_log("System Settings Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Ayarlar yüklenirken bir veritabanı hatası oluştu.';
    $fetch_error = 'Ayarlar yüklenirken bir veritabanı hatası oluştu.';
}

// --- SMTP Ayarlarını Veritabanından Çek ---
$smtp_settings = null;
try {
    $stmt_smtp = $pdo->query("SELECT * FROM smtp_settings ORDER BY id ASC LIMIT 1");
    $smtp_settings = $stmt_smtp->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SMTP Settings Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = ($_SESSION['error_message'] ?? '') . ' SMTP ayarları yüklenirken bir veritabanı hatası oluştu.';
    $fetch_error = ($fetch_error ?? '') . ' SMTP ayarları yüklenirken bir veritabanı hatası oluştu.';
}

// --- Form Gönderildi mi? (POST Metodu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $update_errors = [];
    $updated_count = 0;
    $upload_dir = ROOT_PATH . '/assets/images/'; // Yükleme klasörü (ROOT_PATH kullanıldı) - BU KLASÖRÜN OLUŞTURULDUĞUNDAN VE YAZILABİLİR OLDUĞUNDAN EMİN OLUN!
    $upload_relative_dir = 'assets/images/'; // Veritabanına kaydedilecek göreli yol

    // Klasör yoksa oluşturmayı dene
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0775, true); // 0775 izinleri genellikle yeterlidir
    }
    // Yazılabilir değilse hata ver
    if (!is_writable($upload_dir)) {
         $update_errors[] = "Yükleme klasörü ('{$upload_relative_dir}') yazılabilir değil. Lütfen izinleri kontrol edin.";
    }

    // --- Dosya Yükleme İşlemleri ---
    $uploaded_files_paths = []; // Veritabanına kaydedilecek yeni dosya yollarını tut

    foreach (['logo', 'favicon'] as $file_key) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$file_key];
            $max_size = 2 * 1024 * 1024; // 2MB limit
            $allowed_types = [
                'logo' => ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                'favicon' => ['image/vnd.microsoft.icon', 'image/x-icon', 'image/png', 'image/gif'] // .ico, .png, .gif
            ];
            $allowed_extensions = [
                'logo' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
                'favicon' => ['ico', 'png', 'gif']
            ];

            // Boyut kontrolü
            if ($file['size'] > $max_size) {
                $update_errors[] = ucfirst($file_key) . ": Dosya boyutu çok büyük (Maksimum 2MB).";
                continue; // Bu dosyayı atla, diğerlerine devam et
            }

            // Tip kontrolü (MIME ve uzantı)
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_mime_type = mime_content_type($file['tmp_name']); // Daha güvenilir MIME kontrolü

            if (!in_array($file_mime_type, $allowed_types[$file_key]) || !in_array($file_extension, $allowed_extensions[$file_key])) {
                 $update_errors[] = ucfirst($file_key) . ": Geçersiz dosya türü. İzin verilenler: " . implode(', ', $allowed_extensions[$file_key]);
                 continue;
            }

            // Yeni dosya adı oluştur (logo.[ext] veya favicon.[ext])
            $new_filename = $file_key . '.' . $file_extension;
            $destination = $upload_dir . $new_filename;
            $relative_path = $upload_relative_dir . $new_filename; // DB'ye kaydedilecek yol

            // Dosyayı taşı
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $uploaded_files_paths[$file_key] = $relative_path; // Başarılı yükleme, DB güncellemesi için sakla

                // Eski dosyayı sil (eğer farklıysa)
                $old_relative_path = $settings[$file_key] ?? null;
                if ($old_relative_path && $old_relative_path !== $relative_path) {
                    $old_absolute_path = ROOT_PATH . '/' . $old_relative_path;
                    if (file_exists($old_absolute_path)) {
                        @unlink($old_absolute_path);
                    }
                }
            } else {
                $update_errors[] = ucfirst($file_key) . ": Dosya yüklenirken sunucu hatası oluştu.";
            }
        } elseif (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
            // Yükleme sırasında başka bir hata oluştuysa
            $update_errors[] = ucfirst($file_key) . ": Dosya yüklenirken hata oluştu (Hata Kodu: " . $_FILES[$file_key]['error'] . ").";
        }
    } // End foreach file_key


    // --- Diğer Ayarları Güncelleme ---
    foreach ($_POST as $key => $value) {
        // Dosya inputlarını, SMTP ayarlarını ve zaten işlenenleri atla
        if ($key === 'logo' || $key === 'favicon' || !array_key_exists($key, $settings) ||
            in_array($key, ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
                      'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'smtp_status'])) {
            continue;
        }

        $trimmed_value = trim($value);

        // Değer değiştiyse güncelle
        if ($settings[$key] !== $trimmed_value) {
            try {
                $stmt_update = $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                if ($stmt_update->execute([$trimmed_value, $key])) {
                    if ($stmt_update->rowCount() > 0) {
                        $updated_count++;
                        $settings[$key] = $trimmed_value; // Yerel diziyi güncelle
                    }
                } else {
                     $update_errors[] = "'$key' ayarı güncellenirken SQL hatası.";
                     error_log("Settings Update SQL Error for key '$key': " . print_r($stmt_update->errorInfo(), true));
                }
            } catch (PDOException $e) {
                $update_errors[] = "'$key' ayarı güncellenirken veritabanı hatası: " . $e->getMessage();
                error_log("Settings Update PDOException for key '$key': " . $e->getMessage());
            }
        }
    } // End foreach POST


    // --- Yüklenen Dosyaların Yollarını Veritabanında Güncelle ---
    foreach ($uploaded_files_paths as $key => $new_path) {
         try {
            $stmt_update_file = $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            if ($stmt_update_file->execute([$new_path, $key])) {
                 if ($stmt_update_file->rowCount() > 0) {
                    $updated_count++;
                    $settings[$key] = $new_path; // Yerel diziyi güncelle
                 }
            } else {
                 $update_errors[] = "'$key' dosya yolu güncellenirken SQL hatası.";
                 error_log("Settings File Path Update SQL Error for key '$key': " . print_r($stmt_update_file->errorInfo(), true));
            }
        } catch (PDOException $e) {
            $update_errors[] = "'$key' dosya yolu güncellenirken veritabanı hatası: " . $e->getMessage();
            error_log("Settings File Path Update PDOException for key '$key': " . $e->getMessage());
        }
    }

    // --- SMTP Ayarlarını Güncelleme ---
    if (isset($_POST['smtp_host'])) {
        // SMTP ayarlarını hazırla
        $smtp_data = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => intval($_POST['smtp_port'] ?? 587),
            'username' => trim($_POST['smtp_username'] ?? ''),
            'password' => trim($_POST['smtp_password'] ?? ''),
            'encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
            'from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'from_name' => trim($_POST['smtp_from_name'] ?? ''),
            'status' => isset($_POST['smtp_status']) && $_POST['smtp_status'] == '1' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Eğer şifre alanı boşsa ve mevcut bir SMTP kaydı varsa, mevcut şifreyi kullan
        if (empty($smtp_data['password']) && $smtp_settings && !empty($smtp_settings['password'])) {
            $smtp_data['password'] = $smtp_settings['password'];
        }
        
        try {
            if ($smtp_settings) {
                // Mevcut SMTP ayarlarını güncelle
                $smtp_update = $pdo->prepare("UPDATE smtp_settings SET 
                    host = :host, 
                    port = :port, 
                    username = :username, 
                    password = :password, 
                    encryption = :encryption, 
                    from_email = :from_email, 
                    from_name = :from_name,
                    status = :status,
                    updated_at = :updated_at
                    WHERE id = :id");
                
                $smtp_update->bindParam(':id', $smtp_settings['id']);
                $smtp_update->bindParam(':host', $smtp_data['host']);
                $smtp_update->bindParam(':port', $smtp_data['port']);
                $smtp_update->bindParam(':username', $smtp_data['username']);
                $smtp_update->bindParam(':password', $smtp_data['password']);
                $smtp_update->bindParam(':encryption', $smtp_data['encryption']);
                $smtp_update->bindParam(':from_email', $smtp_data['from_email']);
                $smtp_update->bindParam(':from_name', $smtp_data['from_name']);
                $smtp_update->bindParam(':status', $smtp_data['status']);
                $smtp_update->bindParam(':updated_at', $smtp_data['updated_at']);
                
                if ($smtp_update->execute()) {
                    $updated_count++;
                    // Yerel değişkeni güncelle
                    $smtp_settings = array_merge($smtp_settings, $smtp_data);
                } else {
                    $update_errors[] = "SMTP ayarları güncellenirken SQL hatası.";
                    error_log("SMTP Settings Update SQL Error: " . print_r($smtp_update->errorInfo(), true));
                }
            } else {
                // Yeni SMTP ayarı oluştur
                $smtp_insert = $pdo->prepare("INSERT INTO smtp_settings 
                    (host, port, username, password, encryption, from_email, from_name, status, created_at, updated_at) 
                    VALUES 
                    (:host, :port, :username, :password, :encryption, :from_email, :from_name, :status, :created_at, :updated_at)");
                
                $created_at = date('Y-m-d H:i:s');
                $smtp_insert->bindParam(':host', $smtp_data['host']);
                $smtp_insert->bindParam(':port', $smtp_data['port']);
                $smtp_insert->bindParam(':username', $smtp_data['username']);
                $smtp_insert->bindParam(':password', $smtp_data['password']);
                $smtp_insert->bindParam(':encryption', $smtp_data['encryption']);
                $smtp_insert->bindParam(':from_email', $smtp_data['from_email']);
                $smtp_insert->bindParam(':from_name', $smtp_data['from_name']);
                $smtp_insert->bindParam(':status', $smtp_data['status']);
                $smtp_insert->bindParam(':created_at', $created_at);
                $smtp_insert->bindParam(':updated_at', $smtp_data['updated_at']);
                
                if ($smtp_insert->execute()) {
                    $updated_count++;
                    // Eklenen kaydı al
                    $smtp_settings = $smtp_data;
                    $smtp_settings['id'] = $pdo->lastInsertId();
                    $smtp_settings['created_at'] = $created_at;
                } else {
                    $update_errors[] = "SMTP ayarları oluşturulurken SQL hatası.";
                    error_log("SMTP Settings Insert SQL Error: " . print_r($smtp_insert->errorInfo(), true));
                }
            }
        } catch (PDOException $e) {
            $update_errors[] = "SMTP ayarları güncellenirken veritabanı hatası: " . $e->getMessage();
            error_log("SMTP Settings PDOException: " . $e->getMessage());
        }
    }

    // --- Sonuç Mesajlarını Ayarla ---
    if (!empty($update_errors)) {
        $_SESSION['error_message'] = "Bazı ayarlar güncellenirken hatalar oluştu:<br>" . implode("<br>", array_map('htmlspecialchars', $update_errors));
    } elseif ($updated_count > 0) {
        $_SESSION['success_message'] = $updated_count . ' ayar başarıyla güncellendi.';
    } else {
         // Hiçbir şey değişmediyse veya hata yoksa
         // $_SESSION['info_message'] = 'Herhangi bir ayar değiştirilmedi.';
    }

    // Sayfayı yeniden yönlendir
    redirect(rtrim(BASE_URL, '/') . '/admin/system/index.php');
    exit;
} // End POST request handling


// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-cog me-2"></i>Sistem Ayarları
        </h6>
    </div>
    <div class="card-body">
        <?php
        // Session mesajları (header.php'ye taşınmadıysa)
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            // Hataları gösterirken <br> tag'lerinin çalışması için htmlspecialchars KULLANMIYORUZ
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $_SESSION['error_message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['error_message']);
        }
        if (isset($fetch_error)) {
            echo '<div class="alert alert-warning">' . htmlspecialchars($fetch_error) . '</div>';
        }
        ?>

        <?php if (!empty($settings)): ?>
            <form action="<?php echo rtrim(BASE_URL, '/'); ?>/admin/system/index.php" method="POST" enctype="multipart/form-data">
                
                <!-- Tab Navs -->
                <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-sliders-h me-2"></i>Genel
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">
                            <i class="fas fa-address-book me-2"></i>İletişim
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="images-tab" data-bs-toggle="tab" data-bs-target="#images" type="button" role="tab" aria-controls="images" aria-selected="false">
                            <i class="fas fa-images me-2"></i>Görseller
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab" aria-controls="smtp" aria-selected="false">
                            <i class="fas fa-envelope me-2"></i>E-posta (SMTP)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab" aria-controls="finance" aria-selected="false">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Sipariş & Finans
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    
                    <!-- Genel Ayarlar -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="site_title" class="form-label fw-bold">Site Başlığı</label>
                                <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="site_description" class="form-label fw-bold">Site Açıklaması</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- İletişim Ayarları -->
                    <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label fw-bold">İletişim E-postası</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_phone" class="form-label fw-bold">İletişim Telefonu</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Görsel Ayarları -->
                    <div class="tab-pane fade" id="images" role="tabpanel" aria-labelledby="images-tab">
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-1"></i> Yeni bir dosya yüklemek mevcut olanı değiştirir. İzin verilen türler: Logo (jpg, png, gif, svg, webp), Favicon (ico, png, gif). Maksimum boyut: 2MB.
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header py-2 bg-light fw-bold">Logo</div>
                                    <div class="card-body text-center">
                                        <?php if(!empty($settings['logo']) && file_exists(ROOT_PATH . '/' . $settings['logo'])): ?>
                                            <div class="mb-3 p-2 border rounded bg-light d-inline-block">
                                                <img src="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($settings['logo']) . '?t=' . time(); ?>" height="60" alt="Mevcut Logo">
                                            </div>
                                            <div class="small text-muted mb-3"><?php echo htmlspecialchars($settings['logo']); ?></div>
                                        <?php else: ?>
                                            <div class="mb-3 text-muted fst-italic">Logo yüklenmemiş</div>
                                        <?php endif; ?>
                                        <input class="form-control form-control-sm" type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header py-2 bg-light fw-bold">Favicon</div>
                                    <div class="card-body text-center">
                                        <?php if(!empty($settings['favicon']) && file_exists(ROOT_PATH . '/' . $settings['favicon'])): ?>
                                            <div class="mb-3 p-2 border rounded bg-light d-inline-block">
                                                <img src="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($settings['favicon']) . '?t=' . time(); ?>" height="32" width="32" alt="Mevcut Favicon">
                                            </div>
                                            <div class="small text-muted mb-3"><?php echo htmlspecialchars($settings['favicon']); ?></div>
                                        <?php else: ?>
                                            <div class="mb-3 text-muted fst-italic">Favicon yüklenmemiş</div>
                                        <?php endif; ?>
                                        <input class="form-control form-control-sm" type="file" id="favicon" name="favicon" accept="image/vnd.microsoft.icon,image/x-icon,image/png,image/gif">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Ayarları -->
                    <div class="tab-pane fade" id="smtp" role="tabpanel" aria-labelledby="smtp-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="smtp_host" class="form-label fw-bold">SMTP Sunucu</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_settings['host'] ?? ''); ?>" placeholder="Örn: smtp.gmail.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="smtp_port" class="form-label fw-bold">SMTP Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?php echo htmlspecialchars($smtp_settings['port'] ?? '587'); ?>">
                                <div class="form-text">Genellikle: 587 (TLS), 465 (SSL)</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="smtp_username" class="form-label fw-bold">SMTP Kullanıcı Adı</label>
                                <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_settings['username'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="smtp_password" class="form-label fw-bold">SMTP Şifre</label>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo !empty($smtp_settings['password']) ? '••••••••' : ''; ?>" placeholder="Değiştirmek için yeni şifre girin">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="smtp_encryption" class="form-label fw-bold">Şifreleme</label>
                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === '') ? 'selected' : ''; ?>>Yok</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="smtp_from_email" class="form-label fw-bold">Gönderici E-posta</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp_settings['from_email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="smtp_from_name" class="form-label fw-bold">Gönderici Adı</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp_settings['from_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="smtp_status" name="smtp_status" value="1" <?php echo (isset($smtp_settings['status']) && $smtp_settings['status'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="smtp_status">SMTP E-posta Gönderimini Etkinleştir</label>
                            </div>
                        </div>
                    </div>

                    <!-- Finans Ayarları -->
                    <div class="tab-pane fade" id="finance" role="tabpanel" aria-labelledby="finance-tab">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="currency" class="form-label fw-bold">Para Birimi Sembolü</label>
                                <input type="text" class="form-control" id="currency" name="currency" value="<?php echo htmlspecialchars($settings['currency'] ?? 'TL'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="order_prefix" class="form-label fw-bold">Sipariş Öneki</label>
                                <input type="text" class="form-control" id="order_prefix" name="order_prefix" value="<?php echo htmlspecialchars($settings['order_prefix'] ?? 'SIP-'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="invoice_prefix" class="form-label fw-bold">Fatura Öneki</label>
                                <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'FTR-'); ?>">
                            </div>
                        </div>
                    </div>

                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Ayarları Kaydet
                    </button>
                </div>
            </form>
        <?php else: ?>
            <?php if(!isset($fetch_error)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>Veritabanında hiç site ayarı bulunamadı. Lütfen veritabanı kurulumunu kontrol edin.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div> </div> <?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>