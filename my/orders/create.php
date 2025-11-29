<?php
/**
 * Büfe Kullanıcı Paneli - Sipariş Oluşturma
 * 
 * Enhanced with email notifications
 * 
 * @version 2.0
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/order_functions.php';

// --- Email Templates ---
// Email ilgili fonksiyonlar burada (orijinal koduyla aynı)
function newOrderAdminEmailTemplate($order_number, $order_data, $order_items, $user_info, $admin_order_url, $site_title) {
    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    $bakery_name = !empty($user_info['bakery_name']) ? htmlspecialchars($user_info['bakery_name']) : 'Belirtilmemiş';
    
    $items_html = '';
    $total_amount = 0;
    
    foreach ($order_items as $item) {
        $sale_type_text = ($item['sale_type'] === 'box') ? 'Kasa' : 'Adet';
        $items_html .= '
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['bread_name']) . '</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($sale_type_text) . '</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['quantity']) . '</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">' . formatMoney($item['unit_price']) . '</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">' . formatMoney($item['total_price']) . '</td>
        </tr>';
        
        $total_amount += $item['total_price'];
    }
    
    $content = '
    <p>Sayın Yönetici,</p>
    <p>Sisteme yeni bir sipariş girilmiştir. Sipariş detayları aşağıdadır:</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>Sipariş No:</strong> ' . htmlspecialchars($order_number) . '</p>
        <p style="margin: 5px 0;"><strong>Fırın:</strong> ' . $bakery_name . '</p>
        <p style="margin: 5px 0;"><strong>Müşteri:</strong> ' . $user_full_name . ' (' . htmlspecialchars($user_info['email']) . ')</p>
        <p style="margin: 5px 0;"><strong>Sipariş Tarihi:</strong> ' . date('d.m.Y H:i') . '</p>
        <p style="margin: 5px 0;"><strong>Durum:</strong> <span style="color: #f6c23e; font-weight: bold;">Beklemede</span></p>
    </div>
    
    <h3>Sipariş Kalemleri</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="background-color: #f8f9fc;">
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e3e6f0;">Ekmek Çeşidi</th>
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e3e6f0;">Satış Tipi</th>
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e3e6f0;">Miktar</th>
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e3e6f0;">Birim Fiyat</th>
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e3e6f0;">Toplam</th>
            </tr>
        </thead>
        <tbody>
            ' . $items_html . '
            <tr style="background-color: #f8f9fc; font-weight: bold;">
                <td colspan="4" style="padding: 10px; text-align: right; border-top: 2px solid #e3e6f0;">Genel Toplam:</td>
                <td style="padding: 10px; border-top: 2px solid #e3e6f0;">' . formatMoney($total_amount) . '</td>
            </tr>
        </tbody>
    </table>
    
    <p><strong>Sipariş Notu:</strong> ' . (!empty($order_data['note']) ? nl2br(htmlspecialchars($order_data['note'])) : 'Belirtilmemiş') . '</p>
    
    <p>Siparişi görüntülemek ve işlemek için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Yeni Sipariş Bildirimi', $content, 'Siparişi Görüntüle', $admin_order_url);
}

// Diğer fonksiyonlar (değişiklik yok)
function createPlainTextEmail($html) {
    $text = strip_tags($html);
    $text = str_replace('&nbsp;', ' ', $text);
    $text = html_entity_decode($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\s*\n\s*/', "\n", $text);
    $text = preg_replace('/\s*\n\n\s*/', "\n\n", $text);
    $text = str_replace(' ,', ',', $text);
    $text = str_replace(' .', '.', $text);
    return trim($text);
}

function isSmtpAvailable($settings) {
    if (!empty($settings['smtp_status']) && $settings['smtp_status'] == 1 && 
        !empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
        return true;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'smtp_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log("SMTP settings check error: " . $e->getMessage());
    }
    
    return false;
}

function getSmtpSettingsForEmail() {
    global $pdo, $settings;
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'smtp_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            $smtp_settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($smtp_settings) {
                return [
                    'smtp_status' => $smtp_settings['status'],
                    'smtp_host' => $smtp_settings['host'],
                    'smtp_port' => $smtp_settings['port'],
                    'smtp_username' => $smtp_settings['username'],
                    'smtp_password' => $smtp_settings['password'],
                    'smtp_encryption' => $smtp_settings['encryption'],
                    'smtp_from_email' => $smtp_settings['from_email'],
                    'smtp_from_name' => $smtp_settings['from_name']
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("SMTP settings fetch error: " . $e->getMessage());
    }
    
    return $settings;
}

// --- Kullanıcı Kontrolü ---
if (!isLoggedIn()) {
    redirect(rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

// Kullanıcı admin ise, admin paneline yönlendir
if (isAdmin()) {
    redirect(rtrim(BASE_URL, '/') . '/admin/index.php');
    exit;
}

// --- Sayfa Başlığı ---
$page_title = 'Yeni Sipariş Oluştur';
$current_page = 'new_order';

// --- Sipariş Sistemi Kontrolü ---
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

// --- Sipariş Sistemi Kapalıysa Yönlendir ---
if (!$order_system_open) {
    $_SESSION['warning_message'] = "Sipariş sistemi şu anda kapalıdır: " . $order_system_message;
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Günlük Sipariş Limit Kontrolü ---
$user_id = $_SESSION['user_id'];
$daily_order_count = checkDailyOrderLimit($user_id, $pdo);

if ($daily_order_count === false) {
    $_SESSION['warning_message'] = "Günlük sipariş limitine ulaştınız (2 sipariş). Lütfen yarın tekrar deneyin.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Kullanıcı Bilgilerini Getir ---
try {
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email, bakery_name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) {
        throw new Exception("Kullanıcı bilgileri alınamadı.");
    }
} catch (Exception $e) {
    error_log("User fetch error: " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcı bilgileri alınamadı.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Ekmek Çeşitlerini Getir ---
try {
    $stmt = $pdo->query("
        SELECT *
        FROM bread_types
        WHERE status = 1
        ORDER BY name ASC
    ");
    $bread_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bread_types)) {
        $_SESSION['warning_message'] = "Aktif ekmek çeşidi bulunamadı. Lütfen yöneticinizle iletişime geçin.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Bread Types Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Ekmek çeşitleri yüklenirken bir hata oluştu: " . $e->getMessage();
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Site Ayarlarını Getir ---
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Site Settings Fetch Error: " . $e->getMessage());
}

// --- Sipariş Numarası Ön Eki ---
$order_prefix = $settings['order_prefix'] ?? 'SIP-';

// --- Form Gönderildi mi? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $bread_ids = isset($_POST['bread_id']) ? $_POST['bread_id'] : [];
    $sale_types = isset($_POST['sale_type']) ? $_POST['sale_type'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    
    // Temel doğrulama
    $errors = [];
    
    if (empty($bread_ids)) {
        $errors[] = "En az bir ekmek çeşidi seçmelisiniz.";
    }
    
    // Hata yoksa siparişi kaydet
    if (empty($errors)) {
        try {
            // İşlem başlat
            $pdo->beginTransaction();
            
            // 1. Sipariş oluştur
            $order_number = generateOrderNumber($order_prefix, $user_id, $daily_order_count);
            $total_amount = 0;
            
            $stmt_order = $pdo->prepare("
                INSERT INTO orders 
                (order_number, user_id, total_amount, status, note, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', ?, NOW(), NOW())
            ");
            
            $stmt_order->execute([$order_number, $user_id, $total_amount, $note]);
            $order_id = $pdo->lastInsertId();
            
            // 2. Sipariş kalemlerini ekle
            $stmt_item = $pdo->prepare("
                INSERT INTO order_items 
                (order_id, bread_id, sale_type, quantity, pieces_per_box, unit_price, total_price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Store order items for email
            $order_items_for_email = [];
            
            foreach ($bread_ids as $index => $bread_id) {
                if (empty($bread_id)) continue;
                
                $sale_type = $sale_types[$index] ?? '';
                $quantity = floatval($quantities[$index] ?? 0);
                
                // Gerekli alanları kontrol et
                if (empty($sale_type) || $quantity <= 0) continue;
                
                // Ekmek bilgilerini al
                $stmt_bread = $pdo->prepare("SELECT * FROM bread_types WHERE id = ?");
                $stmt_bread->execute([$bread_id]);
                $bread = $stmt_bread->fetch(PDO::FETCH_ASSOC);
                
                if (!$bread) continue;
                
                // Değerleri hesapla
                $unit_price = floatval($bread['price']);
                $total_price = 0;
                $pieces_per_box = 1;
                
                if ($sale_type === 'box') {
                    $pieces_per_box = intval($bread['box_capacity']);
                    $box_price = calculateBoxPrice($unit_price, $pieces_per_box);
                    $total_price = $box_price * $quantity;
                } else {
                    $total_price = $unit_price * $quantity;
                }
                
                // Toplam tutarı güncelle
                $total_amount += $total_price;
                
                // Sipariş kalemini ekle
                $stmt_item->execute([
                    $order_id,
                    $bread_id,
                    $sale_type,
                    $quantity,
                    $pieces_per_box,
                    $unit_price,
                    $total_price
                ]);
                
                // Add to email items
                $order_items_for_email[] = [
                    'bread_id' => $bread_id,
                    'bread_name' => $bread['name'],
                    'sale_type' => $sale_type,
                    'quantity' => $quantity,
                    'pieces_per_box' => $pieces_per_box,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price
                ];
            }
            
            // 3. Sipariş toplam tutarını güncelle
            $stmt_update = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt_update->execute([$total_amount, $order_id]);
            
            // 4. Durum geçmişine kaydet
            $stmt_history = $pdo->prepare("
                INSERT INTO order_status_history 
                (order_id, status, note, created_by, created_at)
                VALUES (?, 'pending', 'Sipariş oluşturuldu', ?, NOW())
            ");
            $stmt_history->execute([$order_id, $user_id]);
            
            // 5. Kullanıcı aktivitesini kaydet
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            logActivity($user_id, 'order_create', $pdo);
            
            // İşlemi tamamla
            $pdo->commit();
            
            // --- Send Email Notification to Admin ---
            $smtp_available = isSmtpAvailable($settings);
            error_log("[Order Create - ID:{$order_id}] SMTP Available: " . ($smtp_available ? 'Yes' : 'No'));
            
            if ($smtp_available && function_exists('sendEmail')) {
                // Get admin email address
                $admin_notify_email = $settings['orders_notification_email'] ?? $settings['admin_email'] ?? null;
                
                // If admin email is empty, get it from the first admin in the database
                if (empty($admin_notify_email) || !filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $stmt_admin = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL LIMIT 1");
                        if ($stmt_admin && $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC)) {
                            $admin_notify_email = $admin_row['email'];
                            error_log("[Order Create - ID:{$order_id}] Using admin email from database: {$admin_notify_email}");
                        } else {
                            // Hardcode a default if needed
                            $admin_notify_email = "matematikmku@gmail.com"; 
                            error_log("[Order Create - ID:{$order_id}] Using hardcoded admin email: {$admin_notify_email}");
                        }
                    } catch (PDOException $e) {
                        // If database query fails, use hardcoded admin email
                        $admin_notify_email = "matematikmku@gmail.com";
                        error_log("[Order Create - ID:{$order_id}] Database error, using hardcoded admin email: {$admin_notify_email}");
                    }
                }
                
                if (!empty($admin_notify_email) && filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                    $admin_order_url = BASE_URL . '/admin/orders/view.php?id=' . $order_id;
                    $site_title = $settings['site_title'] ?? 'Ekmek Sipariş Sistemi';
                    
                    // Prepare order data for email
                    $order_data = [
                        'order_number' => $order_number,
                        'total_amount' => $total_amount,
                        'status' => 'pending',
                        'note' => $note,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Email settings
                    $email_settings = getSmtpSettingsForEmail();
                    
                    // Create email content
                    $subject_admin = "Yeni Sipariş: #{$order_number} - " . htmlspecialchars($user_info['bakery_name'] ?? $user_info['first_name'] . ' ' . $user_info['last_name']);
                    
                    $body_admin_html = newOrderAdminEmailTemplate(
                        $order_number,
                        $order_data,
                        $order_items_for_email,
                        $user_info,
                        $admin_order_url,
                        $site_title
                    );
                    
                    $admin_plain_text = createPlainTextEmail($body_admin_html);
                    
                    // Try to notify all admins
                    try {
                        // Get all active admin users
                        $stmt_admins = $pdo->query("
                            SELECT id, first_name, last_name, email 
                            FROM users 
                            WHERE role = 'admin' AND status = 1 AND email IS NOT NULL
                            ORDER BY id ASC
                        ");
                        $admin_users = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
                        
                        $admin_notification_count = 0;
                        
                        if (!empty($admin_users)) {
                            // Send to each admin user
                            foreach ($admin_users as $admin) {
                                if (!empty($admin['email']) && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                                    $admin_name = $admin['first_name'] . ' ' . $admin['last_name'];
                                    $send_admin_status = sendEmail($admin['email'], $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                    
                                    if ($send_admin_status) {
                                        $admin_notification_count++;
                                        error_log("[Order Create - ID:{$order_id}] Admin notification sent to: {$admin_name} ({$admin['email']})");
                                    } else {
                                        error_log("[Order Create - ID:{$order_id}] Failed to send admin notification to: {$admin_name} ({$admin['email']})");
                                    }
                                }
                            }
                            
                            if ($admin_notification_count == 0) {
                                // Fallback to single admin email
                                $send_admin_status = sendEmail($admin_notify_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                error_log("[Order Create - ID:{$order_id}] Fallback admin notification to {$admin_notify_email}: " . ($send_admin_status ? 'Success' : 'FAILED'));
                            }
                        } else {
                            // Fallback to single admin email if no admin users found
                            $send_admin_status = sendEmail($admin_notify_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                            error_log("[Order Create - ID:{$order_id}] Direct admin notification to {$admin_notify_email}: " . ($send_admin_status ? 'Success' : 'FAILED'));
                        }
                    } catch (Exception $e) {
                        error_log("[Order Create - ID:{$order_id}] Error sending admin notifications: " . $e->getMessage());
                    }
                } else {
                    error_log("[Order Create - ID:{$order_id}] No valid admin email found for notifications.");
                }
            } else {
                error_log("[Order Create - ID:{$order_id}] Email notifications skipped: SMTP disabled or sendEmail function unavailable.");
            }
            
            // Başarı mesajı
            $_SESSION['success_message'] = "Siparişiniz başarıyla oluşturuldu: $order_number";
            
            // Sipariş detay sayfasına yönlendir
            redirect(rtrim(BASE_URL, '/') . "/my/orders/view.php?id=$order_id");
            exit;
            
        } catch (PDOException $e) {
            // Hata durumunda işlemi geri al
            $pdo->rollBack();
            
            error_log("Order Create Error: " . $e->getMessage());
            $_SESSION['error_message'] = "Sipariş oluşturulurken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        // Hata mesajlarını göster
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6 col-sm-12 mb-2 mb-md-0">
                            <h5 class="card-title mb-0">Yeni Sipariş Oluştur</h5>
                        </div>
                        <div class="col-md-6 col-sm-12 text-md-right text-center">
                            <a href="<?php echo BASE_URL; ?>/my/orders/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Siparişlere Dön</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle mr-2"></i> 
                        <span>Bugün verdiğiniz sipariş sayısı: <strong><?php echo $daily_order_count - 1; ?></strong> / 2</span>
                    </div>
                    
                    <form action="" method="post" id="orderForm">
            
                        <?php generateCSRFToken(); // Token oluştur veya mevcut olanı kullan ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Sipariş Kalemleri</h6>
                                    </div>
                                    <div class="card-body px-0 px-md-3">
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="orderItemsTable">
                                                <thead class="d-none d-md-table-header-group">
                                                    <tr>
                                                        <th width="35%">Ekmek Çeşidi</th>
                                                        <th width="20%">Satış Tipi</th>
                                                        <th width="15%">Miktar</th>
                                                        <th width="20%">Birim Fiyat</th>
                                                        <th width="10%">İşlem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="item-row">
                                                        <td class="align-middle">
                                                            <label class="d-md-none font-weight-bold mb-2">Ekmek Çeşidi</label>
                                                            <select name="bread_id[]" class="form-control bread-select" required>
                                                                <option value="">Ekmek Çeşidi Seçin</option>
                                                                <?php foreach ($bread_types as $bread): ?>
                                                                    <option value="<?php echo $bread['id']; ?>" 
                                                                            data-price="<?php echo $bread['price']; ?>"
                                                                            data-box-capacity="<?php echo $bread['box_capacity']; ?>">
                                                                        <?php echo $bread['name']; ?> - 
                                                                        Adet: <?php echo formatMoney($bread['price']); ?>
                                                                        <?php if ($bread['box_capacity'] > 0): ?>
                                                                            | Kasa (<?php echo $bread['box_capacity']; ?> adet): 
                                                                            <?php echo formatMoney($bread['price'] * $bread['box_capacity']); ?>
                                                                        <?php endif; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td class="align-middle">
                                                            <label class="d-md-none font-weight-bold mb-2">Satış Tipi</label>
                                                            <select name="sale_type[]" class="form-control sale-type-select" required>
                                                                <option value="piece">Adet</option>
                                                                <option value="box">Kasa</option>
                                                            </select>
                                                        </td>
                                                        <td class="align-middle">
                                                            <label class="d-md-none font-weight-bold mb-2">Miktar</label>
                                                            <input type="number" name="quantity[]" class="form-control quantity-input" min="1" value="1" required>
                                                        </td>
                                                        <td class="align-middle">
                                                            <label class="d-md-none font-weight-bold mb-2">Birim Fiyat</label>
                                                            <div class="input-group">
                                                                <input type="text" class="form-control unit-price" readonly>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">₺</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="align-middle text-center">
                                                            <button type="button" class="btn btn-danger remove-item">
                                                                <i class="fas fa-trash"></i>
                                                                <span class="d-inline d-md-none ml-1">Kaldır</span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-md-left">
                                                            <button type="button" class="btn btn-success" id="addItemBtn">
                                                                <i class="fas fa-plus"></i> Ekmek Ekle
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="note">Sipariş Notu</label>
                                    <textarea name="note" id="note" class="form-control" rows="3" placeholder="Siparişinizle ilgili eklemek istediğiniz notlar..."><?php echo isset($note) ? $note : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <hr>
                                <div class="d-flex justify-content-between flex-wrap">
                                    <a href="<?php echo BASE_URL; ?>/my/orders/index.php" class="btn btn-secondary mb-2 mb-sm-0">
                                        <i class="fas fa-times"></i> İptal
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check"></i> Siparişi Oluştur
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript kodları -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa yüklendiğinde fiyatları güncelle
    updateAllPrices();
    
    // Responsive tasarım için satır sınıflarını düzenle
    adjustRowClasses();
    
    // Ekran boyutu değiştiğinde satır sınıflarını tekrar düzenle
    window.addEventListener('resize', adjustRowClasses);
    
    // Yeni kalem ekle
    document.getElementById('addItemBtn').addEventListener('click', function() {
        var lastRow = document.querySelector('.item-row:last-child');
        var newRow = lastRow.cloneNode(true);
        
        // Değerleri sıfırla
        newRow.querySelector('.bread-select').value = '';
        newRow.querySelector('.quantity-input').value = '1';
        newRow.querySelector('.unit-price').value = '';
        
        // Event listener'ları yeniden ekle
        addEventListeners(newRow);
        
        // Satır sınıflarını düzenle
        adjustRowStyling(newRow);
        
        lastRow.parentNode.appendChild(newRow);
    });
    
    // Mevcut satırlar için event listener'ları ekle
    document.querySelectorAll('.item-row').forEach(function(row) {
        addEventListeners(row);
    });
    
    function addEventListeners(row) {
        // Ekmek seçimi değiştiğinde
        row.querySelector('.bread-select').addEventListener('change', function() {
            updateRowPrice(row);
        });
        
        // Satış tipi değiştiğinde
        row.querySelector('.sale-type-select').addEventListener('change', function() {
            updateRowPrice(row);
        });
        
        // Miktar değiştiğinde
        row.querySelector('.quantity-input').addEventListener('input', function() {
            updateRowPrice(row);
        });
        
        // Satır silme
        row.querySelector('.remove-item').addEventListener('click', function() {
            var rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                alert('En az bir ekmek çeşidi seçmelisiniz.');
            }
        });
    }
    
    // Ekran boyutuna göre satır sınıflarını düzenle
    function adjustRowClasses() {
        var isMobile = window.innerWidth < 768;
        var rows = document.querySelectorAll('.item-row');
        
        rows.forEach(function(row) {
            adjustRowStyling(row, isMobile);
        });
    }
    
    // Bir satırın ekran boyutuna göre görünümünü ayarla
    function adjustRowStyling(row, isMobile) {
        if (isMobile === undefined) {
            isMobile = window.innerWidth < 768;
        }
        
        var cells = row.querySelectorAll('td');
        
        if (isMobile) {
            row.classList.add('mobile-row');
            cells.forEach(function(cell) {
                cell.classList.add('d-block', 'w-100', 'mb-3');
            });
        } else {
            row.classList.remove('mobile-row');
            cells.forEach(function(cell) {
                cell.classList.remove('d-block', 'w-100', 'mb-3');
            });
        }
    }
    
    // Tüm satırların fiyatlarını güncelle
    function updateAllPrices() {
        document.querySelectorAll('.item-row').forEach(function(row) {
            updateRowPrice(row);
        });
    }
    
    // Bir satırın fiyatını güncelle
    function updateRowPrice(row) {
        var breadSelect = row.querySelector('.bread-select');
        var saleTypeSelect = row.querySelector('.sale-type-select');
        var quantityInput = row.querySelector('.quantity-input');
        var unitPriceInput = row.querySelector('.unit-price');
        
        if (breadSelect.value === '') {
            unitPriceInput.value = '';
            return;
        }
        
        var selectedOption = breadSelect.options[breadSelect.selectedIndex];
        var unitPrice = parseFloat(selectedOption.dataset.price);
        var boxCapacity = parseInt(selectedOption.dataset.boxCapacity);
        var saleType = saleTypeSelect.value;
        
        if (saleType === 'box') {
            if (boxCapacity > 0) {
                var boxPrice = unitPrice * boxCapacity;
                unitPriceInput.value = formatMoney(boxPrice);
            } else {
                saleTypeSelect.value = 'piece';
                unitPriceInput.value = formatMoney(unitPrice);
                alert('Bu ekmek çeşidi için kasa satışı yapılamamaktadır.');
            }
        } else {
            unitPriceInput.value = formatMoney(unitPrice);
        }
    }
    
    // Para formatı
    function formatMoney(amount) {
        return amount.toFixed(2).replace('.', ',');
    }
    
    // Form gönderilmeden önce kontrol
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        var breadSelects = document.querySelectorAll('.bread-select');
        var isValid = false;
        
        breadSelects.forEach(function(select) {
            if (select.value !== '') {
                isValid = true;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Lütfen en az bir ekmek çeşidi seçin.');
        }
    });
});
</script>

<style>
@media (max-width: 767.98px) {
    /* Mobil görünüm stilleri */
    .table > tbody > tr.mobile-row {
        display: block;
        position: relative;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        padding: 1rem;
        background-color: #fff;
    }
    
    .table > tbody > tr.mobile-row:nth-of-type(odd) {
        background-color: rgba(0,0,0,.02);
    }
    
    .table > tbody > tr.mobile-row > td {
        border: none;
        padding: 0.5rem 0;
    }
    
    .table > tbody > tr.mobile-row > td:last-child {
        padding-bottom: 0;
    }
    
    .table > tbody > tr.mobile-row > td:first-child {
        padding-top: 0;
    }
    
    /* Sil düğmesi konumlandırma */
    .table > tbody > tr.mobile-row > td:last-child {
        position: relative;
        text-align: right;
    }
    
    /* Formların düzenlenmesi */
    .table select.form-control,
    .table input.form-control {
        max-width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Genel stil iyileştirmeleri */
.btn-lg {
    font-weight: bold;
    padding: 0.75rem 1.5rem;
}

.card {
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

/* Bootstrap 4 dışında kullanılan tablolar için */
@media (max-width: 767.98px) {
    table.table thead {
        border: none;
        clip: rect(0 0 0 0);
        height: 1px;
        margin: -1px;
        overflow: hidden;
        padding: 0;
        position: absolute;
        width: 1px;
    }
}
</style>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/my/footer.php';
?>