<?php
/**
 * Büfe Kullanıcı Paneli - Sipariş İptal Etme
 *
 * Enhanced with email notifications on cancellation
 * Includes email helper functions directly.
 * @version 2.0
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/order_functions.php'; // Assumes sendEmail, formatMoney, etc. are here or in init.php

// --- Email Helper Functions (Copied/Adapted) ---

/**
 * Generates an HTML email template for order cancellation notifications to admin
 *
 * @param string $order_number The order reference number
 * @param array $order_data Order information array (including status)
 * @param array $user_info User information array (first_name, last_name, email, bakery_name)
 * @param string $cancel_reason Reason for cancellation provided by user
 * @param string $admin_order_url URL for admin to view the order
 * @param string $site_title Website title
 * @return string HTML formatted email content
 */
function orderCancelledAdminEmailTemplate($order_number, $order_data, $user_info, $cancel_reason, $admin_order_url, $site_title) {
    // Ensure formatMoney function is available
    if (!function_exists('formatMoney')) {
        function formatMoney($amount) { return number_format($amount, 2, ',', '.'); }
    }
     // Ensure getOrderStatusText function is available
    if (!function_exists('getOrderStatusText')) {
        function getOrderStatusText($status) { return ucfirst($status); } // Basic fallback
    }


    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    $bakery_name = !empty($user_info['bakery_name']) ? htmlspecialchars($user_info['bakery_name']) : 'Belirtilmemiş';
    $reason_text = !empty($cancel_reason) ? nl2br(htmlspecialchars($cancel_reason)) : 'Belirtilmedi';
    $order_date = isset($order_data['created_at']) ? date('d.m.Y H:i', strtotime($order_data['created_at'])) : 'Bilinmiyor';

    $content = '
    <p>Sayın Yönetici,</p>
    <p>Aşağıdaki sipariş müşteri tarafından iptal edilmiştir:</p>

    <div class="info-box">
        <p style="margin: 5px 0;"><strong>Sipariş No:</strong> ' . htmlspecialchars($order_number) . '</p>
        <p style="margin: 5px 0;"><strong>Fırın:</strong> ' . $bakery_name . '</p>
        <p style="margin: 5px 0;"><strong>Müşteri:</strong> ' . $user_full_name . ' (' . htmlspecialchars($user_info['email']) . ')</p>
        <p style="margin: 5px 0;"><strong>Sipariş Tarihi:</strong> ' . $order_date . '</p>
        <p style="margin: 5px 0;"><strong>Yeni Durum:</strong> <span style="color:#e74a3b; font-weight:bold;">' . getOrderStatusText('cancelled') . '</span></p>
        <p style="margin: 5px 0;"><strong>İptal Tarihi:</strong> ' . date('d.m.Y H:i') . '</p>
    </div>

    <div style="background-color: #fff3cd; border-left: 4px solid #e74a3b; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #5a5c69;">
        <p style="margin-top: 0; font-weight: bold; color: #e74a3b;">Müşterinin İptal Nedeni:</p>
        <p style="margin-bottom: 0;">' . $reason_text . '</p>
    </div>

    <p>İptal edilen siparişi görüntülemek için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Sipariş İptal Bildirimi', $content, 'İptal Edilen Siparişi Görüntüle', $admin_order_url);
}


/**
 * Function to convert HTML email to plain text
 *
 * @param string $html HTML content
 * @return string Plain text version
 */


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
        return true;
    }

    // If smtp_settings table exists in database, check it too
    global $pdo;
    if (!isset($pdo)) return false;

    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'smtp_settings' LIMIT 1");
        if ($stmt && $stmt->fetch()) {
            $stmt = $pdo->query("SELECT 1 FROM smtp_settings WHERE status = 1 LIMIT 1");
            if ($stmt && $stmt->fetch()) {
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
    global $pdo, $settings;
    if (!isset($pdo)) return null;

    // 1. Check dedicated smtp_settings table first
    try {
         $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'smtp_settings' LIMIT 1");
         if ($stmt && $stmt->fetch()) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            $smtp_db_settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($smtp_db_settings) {
                return [
                    'smtp_status' => $smtp_db_settings['status'] ?? 0,
                    'smtp_host' => $smtp_db_settings['host'] ?? null,
                    'smtp_port' => $smtp_db_settings['port'] ?? null,
                    'smtp_username' => $smtp_db_settings['username'] ?? null,
                    'smtp_password' => $smtp_db_settings['password'] ?? null,
                    'smtp_encryption' => $smtp_db_settings['encryption'] ?? null,
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
         return [
            'smtp_status' => $settings['smtp_status'] ?? 0,
            'smtp_host' => $settings['smtp_host'] ?? null,
            'smtp_port' => $settings['smtp_port'] ?? null,
            'smtp_username' => $settings['smtp_username'] ?? null,
            'smtp_password' => $settings['smtp_password'] ?? null,
            'smtp_encryption' => $settings['smtp_encryption'] ?? null,
            'smtp_from_email' => $settings['smtp_from_email'] ?? $settings['admin_email'] ?? null,
            'smtp_from_name' => $settings['smtp_from_name'] ?? $settings['site_title'] ?? null
        ];
    }

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

// --- CSRF Koruması ---
// Ensure generateCSRFToken function exists
if (!function_exists('generateCSRFToken')) {
     error_log("Error: generateCSRFToken function not found.");
     // Handle error appropriately, maybe redirect with error or show generic error
     $_SESSION['error_message'] = "Sistem hatası: Güvenlik anahtarı oluşturulamadı.";
     redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
     exit;
}
$csrf_token = generateCSRFToken();

// --- ID Kontrolü ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// --- Kullanıcının kendi siparişi mi? ---
// Ensure canViewOrder function exists
if (!function_exists('canViewOrder')) {
     error_log("Error: canViewOrder function not found.");
     $_SESSION['error_message'] = "Sistem hatası: Sipariş yetki kontrolü yapılamadı.";
     redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
     exit;
}
if (!canViewOrder($order_id, $user_id, $pdo)) {
    $_SESSION['error_message'] = "Bu siparişi iptal etme yetkiniz bulunmamaktadır.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Sipariş Bilgilerini Getir ---
try {
    // Ensure getOrderById function exists
    if (!function_exists('getOrderById')) {
         error_log("Error: getOrderById function not found.");
         $_SESSION['error_message'] = "Sistem hatası: Sipariş bilgileri alınamadı.";
         redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
         exit;
    }
    $order = getOrderById($order_id, $pdo);

    if (!$order) {
        $_SESSION['error_message'] = "Sipariş bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
        exit;
    }

    // Sadece beklemede ve işleniyor durumundaki siparişler iptal edilebilir
    // Ensure isOrderCancellable function exists
    if (!function_exists('isOrderCancellable')) {
         error_log("Error: isOrderCancellable function not found.");
          // Basic fallback: only allow cancelling 'pending' orders
         function isOrderCancellable($status) { return $status === 'pending'; }
    }
    if (!isOrderCancellable($order['status'])) {
        $_SESSION['error_message'] = "Bu sipariş iptal edilemez. Durumu: " . htmlspecialchars(getOrderStatusText($order['status']));
        redirect(rtrim(BASE_URL, '/') . '/my/orders/view.php?id=' . $order_id);
        exit;
    }

} catch (PDOException $e) {
    error_log("Order Fetch Error (Cancel Page): " . $e->getMessage());
    $_SESSION['error_message'] = "Sipariş bilgileri yüklenirken bir veritabanı hatası oluştu.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Form Gönderildi mi? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Doğrulama
     // Ensure validateCSRFToken function exists
    if (!function_exists('validateCSRFToken')) {
         error_log("Error: validateCSRFToken function not found.");
         $_SESSION['error_message'] = 'Sistem hatası: Form güvenliği doğrulanamadı.';
         redirect($_SERVER['REQUEST_URI']);
         exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız veya oturum süresi doldu. Lütfen tekrar deneyin.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/view.php?id=' . $order_id);
        exit;
    }

    // Use trim for reason, no need for clean() unless it does more specific sanitization
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';

    // İşlemi gerçekleştir
    try {
        // Ensure updateOrderStatus function exists
        if (!function_exists('updateOrderStatus')) {
             error_log("Error: updateOrderStatus function not found.");
             throw new Exception("Sistem hatası: Sipariş durumu güncellenemedi.");
        }
        // Pass user_id as the changer ID
        $result = updateOrderStatus($order_id, 'cancelled', $cancel_reason, $pdo, $user_id);

        if ($result['success']) {
            // Kullanıcı aktivitesi kaydet
            // Ensure logActivity function exists
             if (function_exists('logActivity')) {
                logActivity($user_id, 'order_cancel', $pdo, $order_id, 'order', 'Sipariş iptal edildi: ' . $order['order_number']);
             } else {
                 error_log("logActivity function not found. Skipping activity log for order cancel.");
             }

            // --- Send Cancellation Email Notification ---
            // Fetch required info if not already available
            // Site Settings
            if (!function_exists('getSiteSettings')) { function getSiteSettings($pdo) { return []; } } // Basic fallback
            $settings = getSiteSettings($pdo);
            // User Info
             try {
                 $stmt_user_email = $pdo->prepare("SELECT id, first_name, last_name, email, bakery_name FROM users WHERE id = ?");
                 $stmt_user_email->execute([$user_id]);
                 $user_info_email = $stmt_user_email->fetch(PDO::FETCH_ASSOC);
                 if (!$user_info_email) { $user_info_email = ['id' => $user_id, 'first_name' => 'Bilinmeyen', 'last_name' => 'Kullanıcı', 'email' => '', 'bakery_name' => 'Bilinmeyen']; }
             } catch (Exception $e) {
                 error_log("User fetch exception for cancel email: " . $e->getMessage());
                 $user_info_email = ['id' => $user_id, 'first_name' => 'Bilinmeyen', 'last_name' => 'Kullanıcı', 'email' => '', 'bakery_name' => 'Bilinmeyen'];
             }


            // Check if email functions exist
            if (function_exists('isSmtpAvailable') && function_exists('sendEmail') && function_exists('orderCancelledAdminEmailTemplate') && function_exists('getSmtpSettingsForEmail') && function_exists('generatePlainTextFromHtml'))
            {
                 $email_settings = getSmtpSettingsForEmail(); // Get settings
                 $smtp_available = ($email_settings !== null);

                 error_log("[Order Cancel - ID:{$order_id}] SMTP Available Check: " . ($smtp_available ? 'Yes' : 'No'));

                 if ($smtp_available) {
                     // Get admin email address(es)
                     $admin_notify_email = $settings['orders_notification_email'] ?? $settings['admin_email'] ?? null;
                     if (empty($admin_notify_email) || !filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                         try {
                             $stmt_admin = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1");
                             if ($stmt_admin && $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC)) {
                                 $admin_notify_email = $admin_row['email'];
                                 error_log("[Order Cancel - ID:{$order_id}] Using admin email from database: {$admin_notify_email}");
                             } else { $admin_notify_email = null; }
                         } catch (PDOException $e) {
                             $admin_notify_email = null;
                             error_log("[Order Cancel - ID:{$order_id}] DB error fetching admin email: " . $e->getMessage());
                         }
                     }

                     if (!empty($admin_notify_email) && filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                         $admin_order_url = rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id;
                         $site_title = $settings['site_title'] ?? 'Ekmek Sipariş Sistemi';

                         // Prepare order data (already have $order)
                         $subject_admin = "Sipariş İptal Edildi: #{$order['order_number']} - " . htmlspecialchars($user_info_email['bakery_name'] ?? ($user_info_email['first_name'] . ' ' . $user_info_email['last_name']));

                         $body_admin_html = orderCancelledAdminEmailTemplate(
                             $order['order_number'],
                             $order, // Pass the original order data
                             $user_info_email, // User who cancelled
                             $cancel_reason, // Reason provided
                             $admin_order_url,
                             $site_title
                         );

                         $admin_plain_text = generatePlainTextFromHtml($body_admin_html);

                         // Try to notify all admins
                         try {
                             $stmt_admins = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL AND email != '' ORDER BY id ASC");
                             $admin_users = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
                             $admin_notification_count = 0;
                             $sent_to_emails = [];

                             if (!empty($admin_users)) {
                                 foreach ($admin_users as $admin) {
                                     $current_admin_email = $admin['email'];
                                     if (!empty($current_admin_email) && filter_var($current_admin_email, FILTER_VALIDATE_EMAIL)) {
                                         if (!function_exists('sendEmail')) {
                                             error_log("sendEmail function not found. Cannot send cancel notification to {$current_admin_email}.");
                                             continue;
                                         }
                                         $admin_name = $admin['first_name'] . ' ' . $admin['last_name'];
                                         $send_admin_status = sendEmail($current_admin_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);

                                         if ($send_admin_status) {
                                             $admin_notification_count++;
                                             $sent_to_emails[] = $current_admin_email;
                                             error_log("[Order Cancel - ID:{$order_id}] Admin cancel notification sent to: {$admin_name} ({$current_admin_email})");
                                         } else {
                                             error_log("[Order Cancel - ID:{$order_id}] Failed to send admin cancel notification to: {$admin_name} ({$current_admin_email})");
                                         }
                                     }
                                 }
                                 if ($admin_notification_count > 0) {
                                      error_log("[Order Cancel - ID:{$order_id}] Successfully sent $admin_notification_count admin cancel notifications to: " . implode(', ', $sent_to_emails));
                                 } else {
                                      error_log("[Order Cancel - ID:{$order_id}] No admin cancel notifications were sent successfully.");
                                 }
                             } else {
                                  error_log("[Order Cancel - ID:{$order_id}] No active admin users with valid emails found to notify.");
                             }
                         } catch (Exception $e) {
                             error_log("[Order Cancel - ID:{$order_id}] Error during admin cancel notification sending loop: " . $e->getMessage());
                         }
                     } else {
                         error_log("[Order Cancel - ID:{$order_id}] No valid primary admin email found for cancel notifications.");
                     }
                 } else {
                      error_log("[Order Cancel - ID:{$order_id}] Email notifications skipped: SMTP not configured or available.");
                 }
            } else {
                  error_log("[Order Cancel - ID:{$order_id}] Email notifications skipped: One or more required email helper functions not found.");
            }
            // --- End Cancellation Email ---

            $_SESSION['success_message'] = $result['message']; // Use message from updateOrderStatus
            redirect(rtrim(BASE_URL, '/') . '/my/orders/view.php?id=' . $order_id);
            exit;
        } else {
            // updateOrderStatus returned success=false
            $_SESSION['error_message'] = $result['message'];
            redirect(rtrim(BASE_URL, '/') . '/my/orders/cancel.php?id=' . $order_id);
            exit;
        }
    } catch (Exception $e) {
        // Catch exceptions from updateOrderStatus or email sending
        error_log("Order Cancel Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Sipariş iptal edilirken bir hata oluştu: " . htmlspecialchars($e->getMessage());
        redirect(rtrim(BASE_URL, '/') . '/my/orders/cancel.php?id=' . $order_id);
        exit;
    }
}

// --- Sayfa Başlığı ---
$page_title = 'Sipariş İptal: #' . htmlspecialchars($order['order_number']);
$current_page = 'orders';

// --- Gerekli Yardımcı Fonksiyonlar (Eğer global olarak yüklenmediyse) ---
if (!function_exists('formatDate')) {
    function formatDate($dateString, $includeTime = false) {
        if (empty($dateString)) return '';
        $format = $includeTime ? 'd.m.Y H:i' : 'd.m.Y';
        try {
            $date = new DateTime($dateString);
            return $date->format($format);
        } catch (Exception $e) {
            return $dateString; // Return original string if parsing fails
        }
    }
}
if (!function_exists('getOrderStatusBadgeClass')) {
    function getOrderStatusBadgeClass($status) {
        $classes = [
            'pending' => 'badge-warning',
            'processing' => 'badge-info',
            'shipped' => 'badge-primary',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
            'refunded' => 'badge-secondary',
        ];
        return $classes[$status] ?? 'badge-light';
    }
}
if (!function_exists('getOrderStatusText')) {
     function getOrderStatusText($status) {
         $texts = [
            'pending' => 'Beklemede',
            'processing' => 'İşleniyor',
            'shipped' => 'Kargolandı',
            'delivered' => 'Teslim Edildi',
            'cancelled' => 'İptal Edildi',
            'refunded' => 'İade Edildi',
         ];
         return $texts[$status] ?? ucfirst($status);
     }
}
if (!function_exists('formatMoney')) {
     function formatMoney($amount) {
         return number_format($amount, 2, ',', '.');
     }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0">
                                <?php echo $page_title; ?>
                            </h5>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <a href="<?php echo BASE_URL; ?>/my/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Siparişe Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                     <?php
                    // Ensure show_messages.php exists or handle message display manually
                    if (file_exists(ROOT_PATH . '/includes/show_messages.php')) {
                        include ROOT_PATH . '/includes/show_messages.php';
                    } else {
                        if (!empty($_SESSION['error_message'])) {
                            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
                            unset($_SESSION['error_message']);
                        }
                         if (!empty($_SESSION['warning_message'])) {
                            echo '<div class="alert alert-warning">' . $_SESSION['warning_message'] . '</div>';
                            unset($_SESSION['warning_message']);
                        }
                        // Success messages are usually shown on the view page after redirect
                    }
                    ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Uyarı!</strong> Bu işlem geri alınamaz. Siparişi iptal etmek istediğinize emin misiniz?
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Sipariş Bilgileri</h6>
                                </div>
                                <div class="card-body p-0"> <table class="table table-bordered table-striped mb-0"> <tr>
                                            <th width="40%">Sipariş No:</th>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Sipariş Tarihi:</th>
                                            <td><?php echo formatDate($order['created_at'], true); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Durum:</th>
                                            <td>
                                                <span class="badge <?php echo getOrderStatusBadgeClass($order['status']); ?>">
                                                    <?php echo getOrderStatusText($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Toplam Tutar:</th>
                                            <td><?php echo formatMoney($order['total_amount']); ?> ₺</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="" method="post" id="cancelForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="form-group">
                            <label for="cancel_reason">İptal Nedeni</label>
                            <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="3" placeholder="İptal nedeninizi belirtiniz (isteğe bağlı)..."></textarea>
                        </div>

                        <div class="form-group text-right">
                            <a href="<?php echo BASE_URL; ?>/my/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Vazgeç
                            </a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Bu siparişi iptal etmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');">
                                <i class="fas fa-check"></i> Siparişi İptal Et
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/my/footer.php';
?>
