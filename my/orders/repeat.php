<?php
/**
 * Büfe Kullanıcı Paneli - Sipariş Tekrarlama
 *
 * Enhanced with email notifications on repeat
 * Includes email helper functions directly.
 * Loads all active bread types via PHP for JS.
 * @version 2.3
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/order_functions.php'; // Assumes sendEmail, formatMoney, etc. are here or in init.php

// --- Email Helper Functions (Copied/Adapted) ---
// [Email functions: newOrderAdminEmailTemplate, createPlainTextEmail, isSmtpAvailable, getSmtpSettingsForEmail - Bunlar önceki versiyonda olduğu gibi burada tanımlı kalıyor]
/**
 * Generates an HTML email template for new order notifications to admin
 *
 * @param string $order_number The order reference number
 * @param array $order_data Order information array
 * @param array $order_items Array of items in the order
 * @param array $user_info User information array (first_name, last_name, email, bakery_name)
 * @param string $admin_order_url URL for admin to view the order
 * @param string $site_title Website title
 * @return string HTML formatted email content
 */
function newOrderAdminEmailTemplate($order_number, $order_data, $order_items, $user_info, $admin_order_url, $site_title) {
    // Ensure formatMoney function is available
    if (!function_exists('formatMoney')) {
        function formatMoney($amount) { return number_format($amount, 2, ',', '.'); }
    }

    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    $bakery_name = !empty($user_info['bakery_name']) ? htmlspecialchars($user_info['bakery_name']) : 'Belirtilmemiş';

    $items_html = '';
    $total_amount = 0;

    foreach ($order_items as $item) {
        $sale_type_text = ($item['sale_type'] === 'box') ? 'Kasa' : 'Adet';
        $bread_name = $item['bread_name'] ?? 'Bilinmeyen Ürün';
        $quantity = $item['quantity'] ?? 0;
        $unit_price = $item['unit_price'] ?? 0;
        $item_total_price = $item['total_price'] ?? 0;

        $items_html .= '
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #e3e6f0;">' . htmlspecialchars($bread_name) . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #e3e6f0;">' . htmlspecialchars($sale_type_text) . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #e3e6f0;">' . htmlspecialchars($quantity) . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #e3e6f0;">' . formatMoney($unit_price) . ' ₺</td>
            <td style="padding: 10px; border-bottom: 1px solid #e3e6f0;">' . formatMoney($item_total_price) . ' ₺</td>
        </tr>';

        $total_amount += $item_total_price;
    }

    $display_total = $order_data['total_amount'] ?? $total_amount;

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
                <td style="padding: 10px; border-top: 2px solid #e3e6f0;">' . formatMoney($display_total) . ' ₺</td>
            </tr>
        </tbody>
    </table>
    
    <p><strong>Sipariş Notu:</strong> ' . (!empty($order_data['note']) ? nl2br(htmlspecialchars($order_data['note'])) : 'Belirtilmemiş') . '</p>
    
    <p>Siparişi görüntülemek ve işlemek için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Yeni Sipariş Bildirimi', $content, 'Siparişi Görüntüle', $admin_order_url);
}

/**
 * Function to convert HTML email to plain text
 *
 * @param string $html HTML content
 * @return string Plain text version
 */
function createPlainTextEmail($html) {
    // Remove HTML tags
    $text = strip_tags($html);

    // Replace HTML entities
    $text = str_replace('&nbsp;', ' ', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Specify encoding

    // Clean up whitespace (more robustly)
    $text = preg_replace('/[\t ]+/', ' ', $text); // Replace multiple spaces/tabs with single space
    $text = preg_replace('/(\s*\n\s*){3,}/', "\n\n", $text); // Replace 3+ line breaks with 2
    $text = preg_replace('/^\s+|\s+$/m', '', $text); // Trim whitespace from start/end of each line

    // Additional replacements to improve readability
    $text = str_replace(' ,', ',', $text);
    $text = str_replace(' .', '.', $text);
    $text = str_replace(' ₺', '₺', $text); // Remove space before currency symbol

    return trim($text);
}

/**
 * Checks if SMTP is properly configured and available
 *
 * @param array $settings Site settings array
 * @return bool True if SMTP is available, false otherwise
 */
function isSmtpAvailable($settings) {
    // First try with standard settings from site_settings array
    if (!empty($settings['smtp_status']) && $settings['smtp_status'] == 1 &&
        !empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
        // Basic check passed, might need password check depending on sendEmail function
        return true;
    }

    // If smtp_settings table exists in database, check it too
    global $pdo; // Need PDO connection
    if (!isset($pdo)) return false; // Cannot check if $pdo is not available

    try {
        // Check if table exists efficiently
        $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'smtp_settings' LIMIT 1");
        if ($stmt && $stmt->fetch()) {
            // Table exists, check for active settings
            $stmt = $pdo->query("SELECT 1 FROM smtp_settings WHERE status = 1 LIMIT 1");
            if ($stmt && $stmt->fetch()) {
                // Active SMTP setting found in the dedicated table
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log("SMTP settings check error: " . $e->getMessage());
    }

    return false;
}

/**
 * Gets SMTP settings from either smtp_settings table or site_settings array
 *
 * @return array|null SMTP settings array or null if not found/configured
 */
function getSmtpSettingsForEmail() {
    global $pdo, $settings; // Need PDO and potentially the site settings array
    if (!isset($pdo)) return null; // Cannot check DB without $pdo

    // 1. Check dedicated smtp_settings table first
    try {
         $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'smtp_settings' LIMIT 1");
         if ($stmt && $stmt->fetch()) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            $smtp_db_settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($smtp_db_settings) {
                // Found active settings in DB, format them
                 // Ensure all expected keys exist, provide defaults if necessary
                return [
                    'smtp_status' => $smtp_db_settings['status'] ?? 0,
                    'smtp_host' => $smtp_db_settings['host'] ?? null,
                    'smtp_port' => $smtp_db_settings['port'] ?? null,
                    'smtp_username' => $smtp_db_settings['username'] ?? null,
                    'smtp_password' => $smtp_db_settings['password'] ?? null,
                    'smtp_encryption' => $smtp_db_settings['encryption'] ?? null, // e.g., 'tls', 'ssl'
                    'smtp_from_email' => $smtp_db_settings['from_email'] ?? null,
                    'smtp_from_name' => $smtp_db_settings['from_name'] ?? null
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Dedicated SMTP settings fetch error: " . $e->getMessage());
    }

    // 2. Fallback to site_settings array if available and configured
    if (isset($settings) && !empty($settings['smtp_status']) && $settings['smtp_status'] == 1) {
         // Ensure all expected keys exist from the $settings array
         return [
            'smtp_status' => $settings['smtp_status'] ?? 0,
            'smtp_host' => $settings['smtp_host'] ?? null,
            'smtp_port' => $settings['smtp_port'] ?? null,
            'smtp_username' => $settings['smtp_username'] ?? null,
            'smtp_password' => $settings['smtp_password'] ?? null, // Check if key exists
            'smtp_encryption' => $settings['smtp_encryption'] ?? null, // Check if key exists
            'smtp_from_email' => $settings['smtp_from_email'] ?? $settings['admin_email'] ?? null, // Fallback for from email
            'smtp_from_name' => $settings['smtp_from_name'] ?? $settings['site_title'] ?? null // Fallback for from name
        ];
    }

    // No active SMTP configuration found
    return null;
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

// --- ID Kontrolü ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// --- Kullanıcının kendi siparişi mi? ---
if (!function_exists('canViewOrder') || !canViewOrder($order_id, $user_id, $pdo)) {
     if (!function_exists('canViewOrder')) {
         error_log("Error: canViewOrder function does not exist.");
         $_SESSION['error_message'] = "Sistem hatası: Sipariş yetki kontrolü yapılamadı.";
     } else {
        $_SESSION['error_message'] = "Bu siparişi tekrarlama yetkiniz bulunmamaktadır.";
     }
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

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
if (!function_exists('checkDailyOrderLimit')) {
     error_log("Error: checkDailyOrderLimit function does not exist.");
     $_SESSION['error_message'] = "Sistem hatası: Günlük limit kontrolü yapılamadı.";
     redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
     exit;
}
$daily_order_count = checkDailyOrderLimit($user_id, $pdo);

if ($daily_order_count === false) {
    $_SESSION['warning_message'] = "Günlük sipariş limitine ulaştınız (2 sipariş). Lütfen yarın tekrar deneyin.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Sipariş Bilgilerini Getir ---
try {
    if (!function_exists('getOrderById') || !function_exists('getOrderItems')) {
         error_log("Error: getOrderById or getOrderItems function does not exist.");
         $_SESSION['error_message'] = "Sistem hatası: Sipariş fonksiyonları bulunamadı.";
         redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
         exit;
    }
    $order = getOrderById($order_id, $pdo);

    if (!$order) {
        $_SESSION['error_message'] = "Sipariş bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
        exit;
    }
    $order_items = getOrderItems($order_id, $pdo);

    if (empty($order_items)) {
        $_SESSION['error_message'] = "Siparişte ürün bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/view.php?id=' . $order_id);
        exit;
    }

} catch (PDOException $e) {
    error_log("Order Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Sipariş detayları yüklenirken bir hata oluştu.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Kullanıcı Bilgilerini Getir (Email için gerekli) ---
try {
    $stmt_user = $pdo->prepare("SELECT id, first_name, last_name, email, bakery_name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        error_log("User fetch error for email: User ID $user_id not found.");
        $user_info = ['id' => $user_id, 'first_name' => 'Bilinmeyen', 'last_name' => 'Kullanıcı', 'email' => '', 'bakery_name' => 'Bilinmeyen'];
    }
} catch (Exception $e) {
    error_log("User fetch exception for email: " . $e->getMessage());
    $user_info = ['id' => $user_id, 'first_name' => 'Bilinmeyen', 'last_name' => 'Kullanıcı', 'email' => '', 'bakery_name' => 'Bilinmeyen'];
}

// --- Aktif Ürünleri Kontrol Et ve Güncel Fiyatları Al (Orijinal Siparişten) ---
$active_products = [];
$inactive_products = [];
foreach ($order_items as $item) {
    $bread_id_from_item = $item['bread_id'] ?? null;
    if ($bread_id_from_item === null) continue;

    $stmt = $pdo->prepare("SELECT id, name, status, price, box_capacity FROM bread_types WHERE id = ?");
    $stmt->execute([$bread_id_from_item]);
    $bread = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bread && $bread['status'] == 1) {
        if (!function_exists('calculateBoxPrice')) { function calculateBoxPrice($unit_price, $capacity) { return $unit_price * $capacity; } }
        $current_unit_price = floatval($bread['price']);
        $current_pieces_per_box = intval($bread['box_capacity']) > 0 ? intval($bread['box_capacity']) : 1;
        $current_box_price = calculateBoxPrice($current_unit_price, $current_pieces_per_box);

        $active_products[] = [
            'bread_id' => $bread['id'],
            'bread_name' => $bread['name'],
            'sale_type' => $item['sale_type'],
            'quantity' => $item['quantity'],
            'current_unit_price' => $current_unit_price,
            'current_box_price' => $current_box_price,
            'current_pieces_per_box' => $current_pieces_per_box,
        ];
    } else {
        $inactive_products[] = [
            'bread_name' => $item['bread_name'] ?? ($bread['name'] ?? 'Bilinmeyen Ürün'),
            'sale_type' => $item['sale_type'],
            'quantity' => $item['quantity']
        ];
    }
}

// *** YENİ: Tüm Aktif Ekmek Çeşitlerini Getir (JS için) ***
try {
    $stmt_all_breads = $pdo->query("SELECT id, name, price, box_capacity FROM bread_types WHERE status = 1 ORDER BY name ASC");
    $all_active_bread_types = $stmt_all_breads->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("All Active Bread Types Fetch Error: " . $e->getMessage());
    $all_active_bread_types = []; // Hata durumunda boş dizi ata
}
// *** BİTTİ: Tüm Aktif Ekmek Çeşitlerini Getir ***


// --- Site Ayarlarını Getir ---
if (!function_exists('getSiteSettings')) { function getSiteSettings($pdo) { return []; } }
$settings = getSiteSettings($pdo);
$order_prefix = $settings['order_prefix'] ?? 'SIP-';


// --- Form Gönderildi mi? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Kontrolü
    if (!function_exists('validateCSRFToken')) {
         error_log("Error: validateCSRFToken function not found.");
         $_SESSION['error_message'] = 'Sistem hatası: Form güvenliği doğrulanamadı.';
         redirect($_SERVER['REQUEST_URI']);
         exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
         $_SESSION['error_message'] = 'Geçersiz form gönderimi veya oturum süresi doldu.';
         redirect($_SERVER['REQUEST_URI']);
         exit;
    }

    // Form verilerini al
    $bread_ids_posted = isset($_POST['bread_id']) ? $_POST['bread_id'] : [];
    $sale_types_posted = isset($_POST['sale_type']) ? $_POST['sale_type'] : [];
    $quantities_posted = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    // Temel doğrulama
    $errors = [];
    if (empty($bread_ids_posted)) {
        $errors[] = "En az bir ekmek çeşidi seçmelisiniz.";
    }
    if (count($bread_ids_posted) !== count($sale_types_posted) || count($bread_ids_posted) !== count($quantities_posted)) {
        $errors[] = "Form verilerinde tutarsızlık var.";
        error_log("Form data count mismatch in repeat order.");
    }

    $new_total_amount = 0;
    $order_items_for_email = [];

    // Hata yoksa siparişi kaydet
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Yeni Sipariş oluştur
            if (!function_exists('generateOrderNumber')) { throw new Exception("Sistem hatası: Sipariş numarası üretilemedi."); }
            $order_number = generateOrderNumber($order_prefix, $user_id, $daily_order_count);
            $stmt_order = $pdo->prepare("INSERT INTO orders (order_number, user_id, total_amount, status, note, created_at, updated_at) VALUES (?, ?, ?, 'pending', ?, NOW(), NOW())");
            $stmt_order->execute([$order_number, $user_id, 0, $note]);
            $new_order_id = $pdo->lastInsertId();
            if (!$new_order_id) { throw new Exception("Yeni sipariş ID'si alınamadı."); }

            // 2. Sipariş kalemlerini ekle
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, bread_id, sale_type, quantity, pieces_per_box, unit_price, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            foreach ($bread_ids_posted as $index => $bread_id) {
                if (!isset($sale_types_posted[$index]) || !isset($quantities_posted[$index])) { continue; }
                $bread_id = filter_var($bread_id, FILTER_VALIDATE_INT);
                if (empty($bread_id)) continue;
                $sale_type = $sale_types_posted[$index];
                $quantity = intval($quantities_posted[$index]);
                if (empty($sale_type) || !in_array($sale_type, ['piece', 'box']) || $quantity <= 0) { continue; }

                $stmt_bread = $pdo->prepare("SELECT id, name, status, price, box_capacity FROM bread_types WHERE id = ? AND status = 1");
                $stmt_bread->execute([$bread_id]);
                $bread = $stmt_bread->fetch(PDO::FETCH_ASSOC);
                if (!$bread) { continue; }

                $unit_price = floatval($bread['price']);
                $total_price = 0;
                $pieces_per_box = 1;
                if ($sale_type === 'box') {
                    $pieces_per_box = intval($bread['box_capacity']);
                    if ($pieces_per_box <= 0) { $sale_type = 'piece'; $pieces_per_box = 1; $total_price = $unit_price * $quantity; }
                    else { if (!function_exists('calculateBoxPrice')) { throw new Exception("calculateBoxPrice function not found."); } $box_price = calculateBoxPrice($unit_price, $pieces_per_box); $total_price = $box_price * $quantity; }
                } else { $total_price = $unit_price * $quantity; }

                $new_total_amount += $total_price;
                $item_executed = $stmt_item->execute([$new_order_id, $bread_id, $sale_type, $quantity, $pieces_per_box, $unit_price, $total_price]);
                if (!$item_executed) { throw new Exception("Sipariş kalemi eklenirken hata oluştu: Bread ID " . $bread_id); }

                 $order_items_for_email[] = ['bread_id' => $bread_id, 'bread_name' => $bread['name'], 'sale_type' => $sale_type, 'quantity' => $quantity, 'pieces_per_box' => $pieces_per_box, 'unit_price' => $unit_price, 'total_price' => $total_price];
            }
            if (empty($order_items_for_email)) { throw new Exception("Tekrarlanan siparişe eklenecek geçerli ürün bulunamadı."); }

            // 3. Sipariş toplam tutarını güncelle
            $stmt_update = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $updated = $stmt_update->execute([$new_total_amount, $new_order_id]);
            if (!$updated) { throw new Exception("Sipariş toplam tutarı güncellenemedi."); }

            // 4. Durum geçmişine kaydet
            $stmt_history = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_by, created_at) VALUES (?, 'pending', ?, ?, NOW())");
            $note_text = "Sipariş oluşturuldu (Orijinal: #" . $order['order_number'] . ")";
            $history_saved = $stmt_history->execute([$new_order_id, $note_text, $user_id]);
            if (!$history_saved) { error_log("Failed to save order status history for new order ID: $new_order_id"); }

            // 5. Kullanıcı aktivitesini kaydet
             if (function_exists('logActivity')) { logActivity($user_id, 'order_repeat', $pdo, $new_order_id, 'order', "Sipariş tekrarlandı. Orijinal: #" . $order['order_number'] . ", Yeni: #" . $order_number); }
             else { error_log("logActivity function not found, skipping activity log for order repeat."); }

            $pdo->commit();

            // --- Send Email Notification to Admin ---
            if (function_exists('isSmtpAvailable') && function_exists('sendEmail') && function_exists('newOrderAdminEmailTemplate') && function_exists('getSmtpSettingsForEmail') && function_exists('createPlainTextEmail')) {
                $email_settings = getSmtpSettingsForEmail();
                $smtp_available = ($email_settings !== null);
                error_log("[Order Repeat - New ID:{$new_order_id}, Orig ID: {$order_id}] SMTP Available Check: " . ($smtp_available ? 'Yes' : 'No'));
                if ($smtp_available) {
                    $admin_notify_email = $settings['orders_notification_email'] ?? $settings['admin_email'] ?? null;
                    if (empty($admin_notify_email) || !filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                         try {
                             $stmt_admin = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1");
                             if ($stmt_admin && $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC)) { $admin_notify_email = $admin_row['email']; error_log("[Order Repeat - New ID:{$new_order_id}] Using admin email from database: {$admin_notify_email}"); }
                             else { $admin_notify_email = null; error_log("[Order Repeat - New ID:{$new_order_id}] No admin email found in settings or database."); }
                         } catch (PDOException $e) { $admin_notify_email = null; error_log("[Order Repeat - New ID:{$new_order_id}] Database error fetching admin email. Error: " . $e->getMessage()); }
                    }
                    if (!empty($admin_notify_email) && filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                        $admin_order_url = rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $new_order_id;
                        $site_title = $settings['site_title'] ?? 'Ekmek Sipariş Sistemi';
                        $new_order_data_for_email = ['order_number' => $order_number, 'total_amount' => $new_total_amount, 'status' => 'pending', 'note' => $note, 'created_at' => date('Y-m-d H:i:s')];
                        $subject_admin = "Yeni Sipariş: #{$order_number} - " . htmlspecialchars($user_info['bakery_name'] ?? ($user_info['first_name'] . ' ' . $user_info['last_name']));
                        $body_admin_html = newOrderAdminEmailTemplate($order_number, $new_order_data_for_email, $order_items_for_email, $user_info, $admin_order_url, $site_title);
                        $admin_plain_text = createPlainTextEmail($body_admin_html);
                        try {
                            $stmt_admins = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL AND email != '' ORDER BY id ASC");
                            $admin_users = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
                            $admin_notification_count = 0; $sent_to_emails = [];
                            if (!empty($admin_users)) {
                                foreach ($admin_users as $admin) {
                                    $current_admin_email = $admin['email'];
                                    if (!empty($current_admin_email) && filter_var($current_admin_email, FILTER_VALIDATE_EMAIL)) {
                                        if (!function_exists('sendEmail')) { error_log("sendEmail function not found. Cannot send notification to {$current_admin_email}."); continue; }
                                        $admin_name = $admin['first_name'] . ' ' . $admin['last_name'];
                                        $send_admin_status = sendEmail($current_admin_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                        if ($send_admin_status) { $admin_notification_count++; $sent_to_emails[] = $current_admin_email; error_log("[Order Repeat - New ID:{$new_order_id}] Admin notification sent to: {$admin_name} ({$current_admin_email})"); }
                                        else { error_log("[Order Repeat - New ID:{$new_order_id}] Failed to send admin notification to: {$admin_name} ({$current_admin_email})"); }
                                    }
                                }
                                if ($admin_notification_count == 0) { error_log("[Order Repeat - New ID:{$new_order_id}] No admin notifications were sent successfully."); }
                                else { error_log("[Order Repeat - New ID:{$new_order_id}] Successfully sent $admin_notification_count admin notifications to: " . implode(', ', $sent_to_emails)); }
                            } else { error_log("[Order Repeat - New ID:{$new_order_id}] No active admin users with valid emails found to notify."); }
                        } catch (Exception $e) { error_log("[Order Repeat - New ID:{$new_order_id}] Error during admin notification sending loop: " . $e->getMessage()); }
                    } else { error_log("[Order Repeat - New ID:{$new_order_id}] No valid primary admin email found for notifications."); }
                } else { error_log("[Order Repeat - New ID:{$new_order_id}] Email notifications skipped: SMTP not configured or available."); }
            } else { error_log("[Order Repeat - New ID:{$new_order_id}] Email notifications skipped: One or more required email helper functions not found."); }
            // --- End Email Notification Logic ---

            $_SESSION['success_message'] = "Siparişiniz başarıyla oluşturuldu: #$order_number";
            redirect(rtrim(BASE_URL, '/') . "/my/orders/view.php?id=$new_order_id");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Order Repeat/Create PDO Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            $_SESSION['error_message'] = "Sipariş oluşturulurken bir veritabanı hatası oluştu. Lütfen tekrar deneyin.";
        } catch (Exception $e) {
             $pdo->rollBack();
             error_log("Order Repeat/Create General Error: " . $e->getMessage());
             $_SESSION['error_message'] = "Sipariş oluşturulurken bir hata oluştu: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
     redirect($_SERVER['REQUEST_URI']);
     exit;
}

// --- Sayfa Başlığı ---
$page_title = 'Siparişi Tekrarla: #' . htmlspecialchars($order['order_number']);
$current_page = 'orders';

// --- Yardımcı Fonksiyonlar ---
if (!function_exists('getSaleTypeText')) { function getSaleTypeText($sale_type) { return ($sale_type === 'box') ? 'Kasa' : 'Adet'; } }
if (!function_exists('formatMoney')) { function formatMoney($amount) { return number_format($amount, 2, ',', '.'); } }

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
                            <h5 class="card-title mb-0"><?php echo $page_title; ?></h5>
                        </div>
                        <div class="col-md-6 col-sm-12 text-md-right text-center">
                            <a href="<?php echo BASE_URL; ?>/my/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Orijinal Siparişe Dön</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php
                    if (file_exists(ROOT_PATH . '/includes/show_messages.php')) { include ROOT_PATH . '/includes/show_messages.php'; }
                    else { /* Manual message display fallback */ }
                    ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle mr-2"></i> 
                        <span>Bugün verebileceğiniz sipariş hakkı: <strong><?php echo max(0, 2 - ($daily_order_count - 1)); ?></strong> adet kaldı. (Mevcut: <?php echo max(0, $daily_order_count - 1); ?> / 2)</span>
                    </div>
                    <?php if (!empty($inactive_products)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i> 
                            <div>
                                <strong>Uyarı!</strong> Orijinal siparişteki aşağıdaki ürünler artık aktif değil veya stokta yok ve yeni siparişe otomatik olarak eklenmedi:
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($inactive_products as $product): ?>
                                        <li><?php echo htmlspecialchars($product['bread_name']); ?> - <?php echo htmlspecialchars(getSaleTypeText($product['sale_type'])); ?> - <?php echo number_format($product['quantity'], 0, ',', '.'); ?> adet/kasa</li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="d-block mt-2">Bu ürünleri isterseniz manuel olarak "Başka Ekmek Ekle" butonu ile ekleyebilirsiniz.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="" method="post" id="orderForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Yeni Sipariş Kalemleri (Güncel Fiyatlarla)</h6>
                                    </div>
                                    <div class="card-body p-0 p-md-3">
                                        <!-- Mobil Görünüm (Kartlar) -->
                                        <div class="d-md-none">
                                            <div id="mobileOrderItems" class="p-3">
                                                <?php if (!empty($active_products)): ?>
                                                    <?php foreach ($active_products as $index => $product): ?>
                                                        <div class="order-item-card mb-3 p-3 border rounded">
                                                            <div class="form-group">
                                                                <label class="font-weight-bold">Ekmek Çeşidi</label>
                                                                <input type="hidden" name="bread_id[]" value="<?php echo htmlspecialchars($product['bread_id']); ?>">
                                                                <p class="mb-0"><?php echo htmlspecialchars($product['bread_name']); ?></p>
                                                                <span class="js-data" style="display:none;" data-price="<?php echo htmlspecialchars($product['current_unit_price']); ?>" data-box-capacity="<?php echo htmlspecialchars($product['current_pieces_per_box']); ?>"></span>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="form-group">
                                                                        <label class="font-weight-bold">Satış Tipi</label>
                                                                        <input type="hidden" name="sale_type[]" value="<?php echo htmlspecialchars($product['sale_type']); ?>">
                                                                        <p class="mb-0">
                                                                            <?php echo htmlspecialchars(getSaleTypeText($product['sale_type'])); ?>
                                                                            <?php if ($product['sale_type'] === 'box'): ?><small class="text-muted d-block box-capacity-info">(<?php echo htmlspecialchars($product['current_pieces_per_box']); ?> adet/kasa)</small><?php endif; ?>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="form-group">
                                                                        <label class="font-weight-bold">Miktar</label>
                                                                        <input type="number" name="quantity[]" class="form-control quantity-input" min="1" value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row align-items-center mt-2">
                                                                <div class="col-7">
                                                                    <div class="form-group mb-0">
                                                                        <label class="font-weight-bold">Birim Fiyat</label>
                                                                        <div class="input-group">
                                                                            <input type="text" class="form-control unit-price" value="<?php echo htmlspecialchars(formatMoney($product['sale_type'] === 'box' ? $product['current_box_price'] : $product['current_unit_price'])); ?>" readonly>
                                                                            <div class="input-group-append">
                                                                                <span class="input-group-text">₺</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-5 text-right">
                                                                    <button type="button" class="btn btn-danger remove-item">
                                                                        <i class="fas fa-trash"></i> Kaldır
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-center py-3 text-muted">
                                                        <p>Orijinal siparişteki aktif ürün bulunamadı.</p>
                                                        <p>Eklemek için "Başka Ekmek Ekle" butonunu kullanın.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-center mb-3 p-3">
                                                <button type="button" class="btn btn-success btn-block" id="addMobileItemBtn">
                                                    <i class="fas fa-plus"></i> Başka Ekmek Ekle
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Masaüstü Görünüm (Tablo) -->
                                        <div class="table-responsive d-none d-md-block">
                                            <table class="table table-bordered" id="orderItemsTable">
                                                <thead>
                                                    <tr>
                                                        <th width="35%">Ekmek Çeşidi</th>
                                                        <th width="20%">Satış Tipi</th>
                                                        <th width="15%">Miktar</th>
                                                        <th width="20%">Birim Fiyat (₺)</th>
                                                        <th width="10%">İşlem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($active_products)): ?>
                                                        <?php foreach ($active_products as $index => $product): ?>
                                                            <tr class="item-row">
                                                                <td>
                                                                    <input type="hidden" name="bread_id[]" value="<?php echo htmlspecialchars($product['bread_id']); ?>">
                                                                    <?php echo htmlspecialchars($product['bread_name']); ?>
                                                                    <span class="js-data" style="display:none;" data-price="<?php echo htmlspecialchars($product['current_unit_price']); ?>" data-box-capacity="<?php echo htmlspecialchars($product['current_pieces_per_box']); ?>"></span>
                                                                </td>
                                                                <td>
                                                                    <input type="hidden" name="sale_type[]" value="<?php echo htmlspecialchars($product['sale_type']); ?>">
                                                                    <?php echo htmlspecialchars(getSaleTypeText($product['sale_type'])); ?>
                                                                    <?php if ($product['sale_type'] === 'box'): ?><small class="text-muted d-block box-capacity-info">(<?php echo htmlspecialchars($product['current_pieces_per_box']); ?> adet/kasa)</small><?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <input type="number" name="quantity[]" class="form-control quantity-input" min="1" value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                                                                </td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input type="text" class="form-control unit-price" value="<?php echo htmlspecialchars(formatMoney($product['sale_type'] === 'box' ? $product['current_box_price'] : $product['current_unit_price'])); ?>" readonly>
                                                                        <div class="input-group-append">
                                                                            <span class="input-group-text">₺</span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-danger remove-item" title="Bu ürünü kaldır">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr class="item-row-placeholder">
                                                            <td colspan="5" class="text-center text-muted">
                                                                Orijinal siparişteki aktif ürün bulunamadı. Eklemek için "Başka Ekmek Ekle" butonunu kullanın.
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5" class="text-center">
                                                            <button type="button" class="btn btn-success" id="addItemBtn">
                                                                <i class="fas fa-plus"></i> Başka Ekmek Ekle
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
                                    <label for="note">Yeni Sipariş Notu</label>
                                    <textarea name="note" id="note" class="form-control" rows="3" placeholder="Yeni siparişinizle ilgili eklemek istediğiniz notlar..."><?php echo htmlspecialchars($order['note'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <hr>
                                <div class="d-flex justify-content-between flex-wrap">
                                    <a href="<?php echo BASE_URL; ?>/my/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary mb-2 mb-sm-0">
                                        <i class="fas fa-times"></i> İptal
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitOrderBtn" <?php echo empty($active_products) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check"></i> Yeni Siparişi Oluştur
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

<style>
@media (max-width: 767.98px) {
    #mobileOrderItems .order-item-card {
        background-color: #fff;
        transition: all 0.2s ease;
    }
    
    #mobileOrderItems .order-item-card:hover {
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    
    #mobileOrderItems .form-group label {
        color: #495057;
        font-size: 0.9rem;
    }
    
    #mobileOrderItems .form-group p {
        font-size: 1.05rem;
    }
    
    .alert {
        border-radius: 0.5rem;
        border-left: 4px solid;
    }
    
    .alert-info {
        border-left-color: #17a2b8;
    }
    
    .alert-warning {
        border-left-color: #ffc107;
    }
    
    .card {
        border-radius: 0.5rem;
        overflow: hidden;
    }
}

.btn-lg {
    font-weight: bold;
    padding: 0.75rem 1.5rem;
}

/* Toast için stil */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    padding: 12px 20px;
    color: white;
    border-radius: 4px;
    margin-bottom: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    animation: slideIn 0.3s ease forwards;
}

.toast-success { background-color: #28a745; }
.toast-warning { background-color: #ffc107; color: #212529; }
.toast-danger { background-color: #dc3545; }
.toast-info { background-color: #17a2b8; }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.fade-out {
    animation: fadeOut 0.5s ease forwards;
}
</style>

<script>
    // *** PHP'den gelen ekmek listesini JS'e aktar ***
const allActiveBreadTypes = <?php echo json_encode($all_active_bread_types); ?>;
const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Toast mesaj konteyneri oluştur
    createToastContainer();
    
    // Masaüstü: Yeni kalem ekle
    setupDesktopAddButton();
    
    // Mobil: Yeni kalem ekle
    setupMobileAddButton();
    
    // Silme işlemleri için click event delegation
    setupRemoveButtons();
    
    // Miktar değişimlerini dinle
    setupQuantityListeners();
    
    // Tüm select değişikliklerini dinle
    setupSelectListeners();
    
    // Form doğrulama
    setupFormValidation();
    
    // Form yüklendiğinde buton durumunu ayarla
    updateSubmitButton();
    
    // ===================== FUNCTION DEFINITIONS =====================
    
    // Toast mesaj konteyneri oluşturma
    function createToastContainer() {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Toast mesaj gösterme fonksiyonu
    function showToast(message, type = 'info', duration = 3000) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = message;
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, duration);
        
        return toast;
    }
    
    // Masaüstü ekle butonu kurulumu
    function setupDesktopAddButton() {
        const addItemBtn = document.getElementById('addItemBtn');
        if (addItemBtn) {
            addItemBtn.addEventListener('click', function() {
                if (isValidBreadTypesAvailable()) {
                    addNewDesktopRow();
                    updateSubmitButton();
                } else {
                    showToast('Aktif ekmek çeşidi bulunamadı.', 'warning');
                }
            });
        }
    }
    
    // Mobil ekle butonu kurulumu
    function setupMobileAddButton() {
        const addMobileItemBtn = document.getElementById('addMobileItemBtn');
        if (addMobileItemBtn) {
            addMobileItemBtn.addEventListener('click', function() {
                if (isValidBreadTypesAvailable()) {
                    addNewMobileCard();
                    updateSubmitButton();
                } else {
                    showToast('Aktif ekmek çeşidi bulunamadı.', 'warning');
                }
            });
        }
    }
    
    // Ekmek tiplerinin geçerli olup olmadığını kontrol et
    function isValidBreadTypesAvailable() {
        return allActiveBreadTypes && 
               Array.isArray(allActiveBreadTypes) && 
               allActiveBreadTypes.length > 0;
    }
    
    // Silme butonları için olay dinleyicileri kurulumu
    function setupRemoveButtons() {
        document.addEventListener('click', function(event) {
            const removeBtn = event.target.closest('.remove-item');
            if (!removeBtn) return;
            
            if (removeBtn.closest('#orderItemsTable')) {
                handleRemoveDesktopItem(removeBtn);
            } else if (removeBtn.closest('#mobileOrderItems')) {
                handleRemoveMobileItem(removeBtn);
            }
        });
    }
    
    // Miktar giriş alanları için olay dinleyicileri kurulumu
    function setupQuantityListeners() {
        document.addEventListener('input', function(event) {
            if (event.target.matches('input[name="quantity[]"]')) {
                validateNumericInput(event.target);
                updateSubmitButton();
            }
        });
    }
    
    // Sayısal girişleri doğrula (minimum 1)
    function validateNumericInput(input) {
        const value = parseFloat(input.value);
        if (isNaN(value) || value <= 0) {
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    }
    
    // Select değişikliklerini dinleme
    function setupSelectListeners() {
        document.addEventListener('change', function(event) {
            // Ekmek seçimi değiştiğinde
            if (event.target.classList.contains('bread-select')) {
                const container = event.target.closest('.item-row, .order-item-card');
                if (container) {
                    const isMobile = container.classList.contains('order-item-card');
                    if (isMobile) {
                        updateCardPrice(container);
                    } else {
                        updateRowPrice(container);
                    }
                    updateSubmitButton();
                }
            }
            
            // Satış tipi değiştiğinde
            if (event.target.classList.contains('sale-type-select')) {
                const container = event.target.closest('.item-row, .order-item-card');
                if (container) {
                    const isMobile = container.classList.contains('order-item-card');
                    if (isMobile) {
                        updateCardPrice(container);
                    } else {
                        updateRowPrice(container);
                    }
                }
            }
        });
    }
    

// Form doğrulama kurulumu
function setupFormValidation() {
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Önce formu durdur
            
            if (!validateOrderForm()) {
                return false;
            }
            
            // Hangi görünüm aktif bakıyoruz (mobil/masaüstü)
            const isMobileActive = window.innerWidth < 768;
            
            // Diğer görünümdeki tüm form elemanlarını geçici olarak formdan kaldır
            let removedElements = [];
            
            if (isMobileActive) {
                // Masaüstü görünümündeki form elemanlarını kaldır
                const desktopInputs = document.querySelectorAll('#orderItemsTable input, #orderItemsTable select');
                desktopInputs.forEach(input => {
                    if (input.name) {
                        const parent = input.parentNode;
                        removedElements.push({ element: input, parent: parent, nextSibling: input.nextSibling });
                        parent.removeChild(input);
                    }
                });
            } else {
                // Mobil görünümdeki form elemanlarını kaldır
                const mobileInputs = document.querySelectorAll('#mobileOrderItems input, #mobileOrderItems select');
                mobileInputs.forEach(input => {
                    if (input.name) {
                        const parent = input.parentNode;
                        removedElements.push({ element: input, parent: parent, nextSibling: input.nextSibling });
                        parent.removeChild(input);
                    }
                });
            }
            
            // Şimdi formu normal şekilde gönder - orijinal form elemanları ve CSRF token ile
            this.submit();
            
            // Form gönderildikten sonra kaldırılan elemanları geri ekle (sayfada hata olursa)
            setTimeout(() => {
                removedElements.forEach(item => {
                    if (item.nextSibling) {
                        item.parent.insertBefore(item.element, item.nextSibling);
                    } else {
                        item.parent.appendChild(item.element);
                    }
                });
            }, 500);
            
            return true;
        });
    }
}

// Sipariş formunu doğrula
function validateOrderForm() {
    // Hangi görünüm aktif?
    const isMobileActive = window.innerWidth < 768;
    
    // Sadece aktif görünümdeki öğeleri kontrol et
    let hasValidItems = false;
    
    if (isMobileActive) {
        // Sadece mobil elemanları kontrol et
        document.querySelectorAll('#mobileOrderItems .order-item-card').forEach(card => {
            card.style.border = '1px solid #dee2e6'; // Önce hata işaretlerini temizle
            
            const breadId = card.querySelector('input[name="bread_id[]"]')?.value;
            const quantity = parseFloat(card.querySelector('input[name="quantity[]"]')?.value || 0);
            
            if (breadId && quantity > 0) {
                hasValidItems = true;
            } else if (breadId) {
                // Ekmek seçilmiş ama miktar hatalı
                card.style.border = '2px solid red';
            }
        });
    } else {
        // Sadece masaüstü elemanları kontrol et
        document.querySelectorAll('#orderItemsTable .item-row').forEach(row => {
            row.style.border = ''; // Önce hata işaretlerini temizle
            
            const breadId = row.querySelector('input[name="bread_id[]"]')?.value;
            const quantity = parseFloat(row.querySelector('input[name="quantity[]"]')?.value || 0);
            
            if (breadId && quantity > 0) {
                hasValidItems = true;
            } else if (breadId) {
                // Ekmek seçilmiş ama miktar hatalı
                row.style.border = '2px solid red';
            }
        });
    }
    
    if (!hasValidItems) {
        showToast('Lütfen en az bir ekmek çeşidi seçin ve miktarı doğru girin.', 'danger');
        return false;
    }
    
    return true;
}
    // Geçerli ürün var mı kontrol et
    function checkValidItems() {
        let hasValidItems = false;
        
        // Masaüstü satırlarını kontrol et
        document.querySelectorAll('#orderItemsTable .item-row').forEach(row => {
            row.style.border = ''; // Önce hata işaretlerini temizle
            
            const breadId = row.querySelector('select[name="bread_id[]"]')?.value || 
                           row.querySelector('input[name="bread_id[]"]')?.value;
            const quantity = parseFloat(row.querySelector('input[name="quantity[]"]')?.value || 0);
            
            if (breadId && quantity > 0) {
                hasValidItems = true;
            } else if (breadId) {
                // Ekmek seçilmiş ama miktar hatalı
                row.style.border = '2px solid red';
            }
        });
        
        // Mobil kartları kontrol et
        document.querySelectorAll('#mobileOrderItems .order-item-card').forEach(card => {
            card.style.border = '1px solid #dee2e6'; // Önce hata işaretlerini temizle
            
            const breadId = card.querySelector('select[name="bread_id[]"]')?.value || 
                           card.querySelector('input[name="bread_id[]"]')?.value;
            const quantity = parseFloat(card.querySelector('input[name="quantity[]"]')?.value || 0);
            
            if (breadId && quantity > 0) {
                hasValidItems = true;
            } else if (breadId) {
                // Ekmek seçilmiş ama miktar hatalı
                card.style.border = '2px solid red';
            }
        });
        
        return hasValidItems;
    }
    
    // Form alanlarının tutarlılığını kontrol et
    function checkFormConsistency() {
        const breadIdElements = document.querySelectorAll('select[name="bread_id[]"], input[name="bread_id[]"]');
        const saleTypeElements = document.querySelectorAll('select[name="sale_type[]"], input[name="sale_type[]"]');
        const quantityElements = document.querySelectorAll('input[name="quantity[]"]');
        
        // Eleman sayıları eşit mi?
        if (breadIdElements.length !== saleTypeElements.length || 
            breadIdElements.length !== quantityElements.length) {
            return false;
        }
        
        // Tüm elemanlarda değer var mı?
        for (let i = 0; i < breadIdElements.length; i++) {
            if (!breadIdElements[i].value || 
                !saleTypeElements[i].value || 
                !quantityElements[i].value || 
                parseFloat(quantityElements[i].value) <= 0) {
                return false;
            }
        }
        
        return true;
    }
    
    // Masaüstü: Yeni satır ekle
    function addNewDesktopRow() {
        const tbody = document.querySelector('#orderItemsTable tbody');
        if (!tbody) return;
        
        // Placeholder satırını kaldır
        const placeholderRow = tbody.querySelector('.item-row-placeholder');
        if (placeholderRow) {
            placeholderRow.remove();
        }
        
        // Yeni satır oluştur
        const newRow = document.createElement('tr');
        newRow.className = 'item-row';
        
        // Ekmek seçenekleri için HTML oluştur
        const breadOptionsHtml = createBreadOptionsHtml();
        
        // Satır içeriğini oluştur
        newRow.innerHTML = `
            <td>
                <select name="bread_id[]" class="form-control bread-select" required>
                    <option value="">Ekmek Çeşidi Seçin</option>
                    ${breadOptionsHtml}
                </select>
            </td>
            <td>
                <select name="sale_type[]" class="form-control sale-type-select" required>
                    <option value="piece">Adet</option>
                    <option value="box" disabled>Kasa</option>
                </select>
                <small class="text-muted d-block box-capacity-info" style="display: none;"></small>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control quantity-input" min="1" value="1" required>
            </td>
            <td>
                <div class="input-group">
                    <input type="text" class="form-control unit-price" readonly placeholder="Fiyat">
                    <div class="input-group-append">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm remove-item" title="Bu ürünü kaldır">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(newRow);
        updateRowPrice(newRow);
    }
    
    // Mobil: Yeni kart ekle
    function addNewMobileCard() {
        const mobileContainer = document.getElementById('mobileOrderItems');
        if (!mobileContainer) return;
        
        // İçeriği temizle (eğer "ürün bulunamadı" mesajı varsa)
        const emptyMessage = mobileContainer.querySelector('.text-center.py-3.text-muted');
        if (emptyMessage) {
            mobileContainer.innerHTML = '';
        }
        
        // Yeni kart oluştur
        const newCard = document.createElement('div');
        newCard.className = 'order-item-card mb-3 p-3 border rounded';
        
        // Ekmek seçenekleri için HTML oluştur
        const breadOptionsHtml = createBreadOptionsHtml();
        
        // Kart içeriğini oluştur
        newCard.innerHTML = `
            <div class="form-group">
                <label class="font-weight-bold">Ekmek Çeşidi</label>
                <select name="bread_id[]" class="form-control bread-select" required>
                    <option value="">Ekmek Çeşidi Seçin</option>
                    ${breadOptionsHtml}
                </select>
            </div>
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="font-weight-bold">Satış Tipi</label>
                        <select name="sale_type[]" class="form-control sale-type-select" required>
                            <option value="piece">Adet</option>
                            <option value="box" disabled>Kasa</option>
                        </select>
                        <small class="text-muted d-block box-capacity-info" style="display: none;"></small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="font-weight-bold">Miktar</label>
                        <input type="number" name="quantity[]" class="form-control quantity-input" min="1" value="1" required>
                    </div>
                </div>
            </div>
            <div class="row align-items-center mt-2">
                <div class="col-7">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Birim Fiyat</label>
                        <div class="input-group">
                            <input type="text" class="form-control unit-price" readonly placeholder="Fiyat">
                            <div class="input-group-append">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-5 text-right">
                    <button type="button" class="btn btn-danger remove-item">
                        <i class="fas fa-trash"></i> Kaldır
                    </button>
                </div>
            </div>
        `;
        
        mobileContainer.appendChild(newCard);
        updateCardPrice(newCard);
    }
    
    // Ekmek seçenekleri için HTML oluştur
    function createBreadOptionsHtml() {
        let optionsHtml = '';
        
        allActiveBreadTypes.forEach(function(bread) {
            if (!bread || typeof bread !== 'object' || !bread.id || !bread.name || typeof bread.price === 'undefined') return;
            
            const unitPrice = parseFloat(bread.price);
            if (isNaN(unitPrice)) return;
            
            const unitPriceFormatted = formatMoneyJs(unitPrice);
            const boxCapacity = parseInt(bread.box_capacity, 10) || 0;
            
            let boxInfo = '';
            if (boxCapacity > 0) {
                const boxPrice = unitPrice * boxCapacity;
                boxInfo = ` | Kasa (${boxCapacity} adet): ${formatMoneyJs(boxPrice)} ₺`;
            }
            
            optionsHtml += `<option value="${escapeHtml(bread.id.toString())}" 
                               data-price="${escapeHtml(unitPrice.toString())}" 
                               data-box-capacity="${escapeHtml(boxCapacity.toString())}">
                               ${escapeHtml(bread.name)} - Adet: ${unitPriceFormatted} ₺${boxInfo}
                           </option>`;
        });
        
        return optionsHtml;
    }
    
    // Masaüstü: Satır silme
    function handleRemoveDesktopItem(button) {
        const row = button.closest('.item-row');
        if (!row) return;
        
        const tbody = row.closest('tbody');
        if (!tbody) return;
        
        // Rows sayısını silmeden önce kontrol et
        const allRows = tbody.querySelectorAll('.item-row');
        if (allRows.length <= 1) {
            showToast('En az bir ekmek çeşidi bulunmalıdır.', 'warning');
            return;
        }
        
        row.remove();
        
        // Son satır silindiyse placeholder ekle
        if (tbody.querySelectorAll('.item-row').length === 0) {
            const placeholderHTML = `
                <tr class="item-row-placeholder">
                    <td colspan="5" class="text-center text-muted">
                        Lütfen eklemek için "Başka Ekmek Ekle" butonunu kullanın.
                    </td>
                </tr>`;
            tbody.innerHTML = placeholderHTML;
        }
        
        updateSubmitButton();
    }
    
    // Mobil: Kart silme
    function handleRemoveMobileItem(button) {
        const card = button.closest('.order-item-card');
        if (!card) return;
        
        const container = card.closest('#mobileOrderItems');
        if (!container) return;
        
        // Cards sayısını silmeden önce kontrol et
        const allCards = container.querySelectorAll('.order-item-card');
        if (allCards.length <= 1) {
            showToast('En az bir ekmek çeşidi bulunmalıdır.', 'warning');
            return;
        }
        
        card.remove();
        
        // Son kart silindiyse placeholder ekle
        if (container.querySelectorAll('.order-item-card').length === 0) {
            const placeholderHTML = `
                <div class="text-center py-3 text-muted">
                    <p>Orijinal siparişteki aktif ürün bulunamadı.</p>
                    <p>Eklemek için "Başka Ekmek Ekle" butonunu kullanın.</p>
                </div>`;
            container.innerHTML = placeholderHTML;
        }
        
        updateSubmitButton();
    }
    
    // Masaüstü: Satır fiyatını güncelle
    function updateRowPrice(row) {
        const breadSelect = row.querySelector('.bread-select');
        const saleTypeSelect = row.querySelector('.sale-type-select');
        const unitPriceInput = row.querySelector('.unit-price');
        const boxCapacityInfo = row.querySelector('.box-capacity-info');
        
        if (!breadSelect || !saleTypeSelect || !unitPriceInput || !boxCapacityInfo) {
            return;
        }
        
        // Seçilen ekmek bilgilerini al
        const selectedOption = breadSelect.options[breadSelect.selectedIndex];
        if (!selectedOption || breadSelect.value === '') {
            resetPriceDisplay(unitPriceInput, boxCapacityInfo, saleTypeSelect);
            return;
        }
        
        updatePriceBasedOnSelection(selectedOption, saleTypeSelect, unitPriceInput, boxCapacityInfo);
    }
    
    // Mobil: Kart fiyatını güncelle
    function updateCardPrice(card) {
        const breadSelect = card.querySelector('.bread-select');
        const saleTypeSelect = card.querySelector('.sale-type-select');
        const unitPriceInput = card.querySelector('.unit-price');
        const boxCapacityInfo = card.querySelector('.box-capacity-info');
        
        if (!breadSelect || !saleTypeSelect || !unitPriceInput || !boxCapacityInfo) {
            return;
        }
        
        // Seçilen ekmek bilgilerini al
        const selectedOption = breadSelect.options[breadSelect.selectedIndex];
        if (!selectedOption || breadSelect.value === '') {
            resetPriceDisplay(unitPriceInput, boxCapacityInfo, saleTypeSelect);
            return;
        }
        
        updatePriceBasedOnSelection(selectedOption, saleTypeSelect, unitPriceInput, boxCapacityInfo);
    }
    
    // Fiyat gösterimini sıfırla
    function resetPriceDisplay(unitPriceInput, boxCapacityInfo, saleTypeSelect) {
        unitPriceInput.value = '';
        boxCapacityInfo.style.display = 'none';
        boxCapacityInfo.textContent = '';
        
        const boxOption = saleTypeSelect.querySelector('option[value="box"]');
        if (boxOption) boxOption.disabled = true;
    }
    
    // Seçime göre fiyatı güncelle
    function updatePriceBasedOnSelection(selectedOption, saleTypeSelect, unitPriceInput, boxCapacityInfo) {
        const unitPrice = parseFloat(selectedOption.dataset.price);
        const boxCapacity = parseInt(selectedOption.dataset.boxCapacity, 10) || 0;
        const saleType = saleTypeSelect.value;
        
        // Veri türlerini doğrula
        if (isNaN(unitPrice)) {
            resetPriceDisplay(unitPriceInput, boxCapacityInfo, saleTypeSelect);
            return;
        }
        
        // Kasa seçeneğini güncelle
        const boxOption = saleTypeSelect.querySelector('option[value="box"]');
        if (boxOption) {
            boxOption.disabled = !(boxCapacity > 0);
        }
        
        // Satış tipine göre fiyat ve bilgi göster
        if (saleType === 'box') {
            if (boxCapacity > 0) {
                const boxPrice = unitPrice * boxCapacity;
                unitPriceInput.value = formatMoneyJs(boxPrice);
                boxCapacityInfo.textContent = `(${boxCapacity} adet/kasa)`;
                boxCapacityInfo.style.display = 'block';
            } else {
                saleTypeSelect.value = 'piece';
                unitPriceInput.value = formatMoneyJs(unitPrice);
                boxCapacityInfo.style.display = 'none';
                boxCapacityInfo.textContent = '';
                showToast('Bu ekmek çeşidi için kasa satışı yapılamamaktadır.', 'warning');
            }
        } else {
            unitPriceInput.value = formatMoneyJs(unitPrice);
            boxCapacityInfo.style.display = 'none';
            boxCapacityInfo.textContent = '';
        }
    }
    
    // Gönder butonunu güncelle
    function updateSubmitButton() {
        const submitButton = document.getElementById('submitOrderBtn');
        if (!submitButton) return;
        
        const hasValidItems = checkValidItems();
        submitButton.disabled = !hasValidItems;
    }
    
    // Para formatı
    function formatMoneyJs(amount) {
        const num = parseFloat(amount);
        if (isNaN(num)) return '';
        return num.toFixed(2).replace('.', ',');
    }
    
    // HTML kaçış fonksiyonu
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/my/footer.php';
?>