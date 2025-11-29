<?php
/**
 * Admin - Destek Talebi Durum Güncelleme
 * 
 * Enhanced version with email notifications
 * 
 * @version 2.0
 */

// --- init.php Dahil Etme ---
require_once '../../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Ensure admin is logged in

// --- Email Templates ---
/**
 * Generates an HTML email template for ticket status change notifications to user
 * 
 * @param string $ticket_number The ticket reference number
 * @param string $subject The ticket subject
 * @param string $old_status Previous status
 * @param string $new_status New status
 * @param array $user_info User information array (first_name, last_name)
 * @param string $ticket_view_url URL to view the ticket
 * @param string $site_title Website title
 * @return string HTML formatted email content
 */
function statusChangeEmailTemplate($ticket_number, $subject, $old_status, $new_status, $user_info, $ticket_view_url, $site_title) {
    // Status texts in Turkish
    $status_texts = [
        'open' => 'Açık',
        'in_progress' => 'İşlemde', 
        'waiting' => 'Yanıt Bekleniyor',
        'resolved' => 'Çözüldü',
        'closed' => 'Kapatıldı'
    ];
    
    $status_colors = [
        'open' => '#4e73df',
        'in_progress' => '#36b9cc',
        'waiting' => '#f6c23e',
        'resolved' => '#1cc88a',
        'closed' => '#858796'
    ];
    
    $old_status_text = $status_texts[$old_status] ?? ucfirst($old_status);
    $new_status_text = $status_texts[$new_status] ?? ucfirst($new_status);
    $new_status_color = $status_colors[$new_status] ?? '#858796';
    
    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    
    $content = '
    <p>Merhaba ' . $user_full_name . ',</p>
    <p><strong>' . htmlspecialchars($ticket_number) . '</strong> numaralı destek talebinizin durumu değiştirilmiştir.</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>Konu:</strong> ' . htmlspecialchars($subject) . '</p>
        <p style="margin: 5px 0;"><strong>Talep Numarası:</strong> ' . htmlspecialchars($ticket_number) . '</p>
    </div>
    
    <div style="background-color: #f0f7ff; padding: 15px; border-radius: 5px; border-left: 4px solid #4e73df; margin-bottom: 20px;">
        <p style="margin-top: 0; font-weight: bold; color: #4e73df;">Durum Değişikliği:</p>
        <p style="margin: 5px 0;">Önceki durum: <strong>' . htmlspecialchars($old_status_text) . '</strong></p>
        <p style="margin: 5px 0;">Yeni durum: <strong style="color: ' . $new_status_color . ';">' . htmlspecialchars($new_status_text) . '</strong></p>
    </div>
    
    <p>Talebinizi görüntülemek ve takip etmek için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Destek Talebi Durum Değişikliği', $content, 'Destek Talebini Görüntüle', $ticket_view_url);
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
    $text = html_entity_decode($text);
    
    // Clean up whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\s*\n\s*/', "\n", $text);
    $text = preg_replace('/\s*\n\n\s*/', "\n\n", $text);
    
    // Additional replacements to improve readability
    $text = str_replace(' ,', ',', $text);
    $text = str_replace(' .', '.', $text);
    
    return trim($text);
}

/**
 * Checks if SMTP is properly configured and available
 * 
 * @param array $settings Site settings array
 * @return bool True if SMTP is available, false otherwise
 */
function isSmtpAvailable($settings) {
    // First try with standard settings
    if (!empty($settings['smtp_status']) && $settings['smtp_status'] == 1 && 
        !empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
        return true;
    }
    
    // If smtp_settings table exists in database, check it too
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

/**
 * Gets SMTP settings from either site_settings or smtp_settings table
 * 
 * @return array|null SMTP settings or null if not found
 */
function getSmtpSettingsForEmail() {
    global $pdo, $settings;
    
    // First check if we have a dedicated smtp_settings table
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'smtp_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            $smtp_settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($smtp_settings) {
                // Convert to the format expected by sendEmail
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
    
    // Fallback to regular settings
    return $settings;
}

// --- Parametreleri Al ---
$ticket_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$new_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$ref_page = isset($_GET['ref']) ? trim($_GET['ref']) : 'index'; // Yönlendirme sayfası (index veya ticket)

// Geçerli durumlar
$valid_statuses = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

// --- Hata Kontrolü ---
if ($ticket_id <= 0) {
    $_SESSION['error_message'] = "Geçersiz talep ID'si.";
    header("Location: " . BASE_URL . "/admin/support/$ref_page.php");
    exit;
}

if (!in_array($new_status, $valid_statuses)) {
    $_SESSION['error_message'] = "Geçersiz durum değeri.";
    header("Location: " . BASE_URL . "/admin/support/$ref_page.php" . ($ref_page == 'ticket' ? "?id=$ticket_id" : ''));
    exit;
}

// --- Talebin Varlığını Kontrol Et ---
try {
    // Get full ticket details including user info for email
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.first_name, u.last_name, u.email
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Belirtilen ID'ye sahip destek talebi bulunamadı.";
        header("Location: " . BASE_URL . "/admin/support/$ref_page.php");
        exit;
    }
    
    // Eğer durum zaten aynıysa işlem yapma
    if ($ticket['status'] === $new_status) {
        $_SESSION['info_message'] = "Talep zaten '$new_status' durumunda.";
        header("Location: " . BASE_URL . "/admin/support/$ref_page.php" . ($ref_page == 'ticket' ? "?id=$ticket_id" : ''));
        exit;
    }
    
    // Start transaction to ensure all changes are committed or rolled back together
    $pdo->beginTransaction();
    
    // --- Durumu Güncelle ---
    $update_data = [
        'status' => $new_status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Eğer durum 'closed' veya 'resolved' ise kapatma tarihi ekle
    if (in_array($new_status, ['closed', 'resolved'])) {
        $update_data['closed_at'] = date('Y-m-d H:i:s');
    }
    
    // SQL sorgusunu hazırla
    $set_clauses = [];
    foreach ($update_data as $field => $value) {
        $set_clauses[] = "$field = :$field";
    }
    $sql = "UPDATE support_tickets SET " . implode(', ', $set_clauses) . " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $ticket_id, PDO::PARAM_INT);
    
    foreach ($update_data as $field => $value) {
        $stmt->bindParam(":$field", $update_data[$field]);
    }
    
    $stmt->execute();
    
    // --- Durum değişikliği için history kaydı ekle ---
    $admin_id = $_SESSION['user_id'] ?? 0;
    $old_status = $ticket['status'];
    
    $stmt = $pdo->prepare("
        INSERT INTO support_ticket_history 
        (ticket_id, user_id, action, old_value, new_value, note, created_at) 
        VALUES (?, ?, 'status_change', ?, ?, ?, NOW())
    ");
    
    // Türkçe durum metinleri
    $status_texts = [
        'open' => 'Açık',
        'in_progress' => 'İşlemde', 
        'waiting' => 'Yanıt Bekleniyor',
        'resolved' => 'Çözüldü',
        'closed' => 'Kapatıldı'
    ];
    
    $note = "Durum yönetici tarafından değiştirildi";
    $stmt->execute([$ticket_id, $admin_id, $old_status, $new_status, $note]);
    
    // --- Durum Değişikliği Mesajı Ekle ---
    $message = "Durum değiştirildi: " . ($status_texts[$old_status] ?? $old_status) . " → " . ($status_texts[$new_status] ?? $new_status);
    
    $stmt = $pdo->prepare("
        INSERT INTO support_messages (ticket_id, user_id, sender_type, message, is_internal, created_at) 
        VALUES (?, ?, 'admin', ?, 0, NOW())
    ");
    $stmt->execute([$ticket_id, $admin_id, $message]);
    
    // Commit the transaction
    $pdo->commit();
    
    // --- Send Email Notification to User ---
    $smtp_available = isSmtpAvailable($settings);
    error_log("[Ticket Status Update - ID:{$ticket_id}] SMTP Available: " . ($smtp_available ? 'Yes' : 'No'));
    
    if ($smtp_available && function_exists('sendEmail') && !empty($ticket['email'])) {
        $user_info = [
            'first_name' => $ticket['first_name'],
            'last_name' => $ticket['last_name'],
            'email' => $ticket['email']
        ];
        
        $ticket_view_url = BASE_URL . '/my/support/view.php?id=' . $ticket_id;
        $site_title = $settings['site_title'] ?? 'Destek Sistemi';
        
        // Email settings
        $email_settings = getSmtpSettingsForEmail();
        
        // Create email content
        $subject_email = "Destek Talebi Durumu Güncellendi: #" . htmlspecialchars($ticket['ticket_number']);
        
        $body_html = statusChangeEmailTemplate(
            $ticket['ticket_number'],
            $ticket['subject'],
            $old_status,
            $new_status,
            $user_info,
            $ticket_view_url,
            $site_title
        );
        
        $plain_text = createPlainTextEmail($body_html);
        
        // Send email
        $send_status = sendEmail($ticket['email'], $subject_email, $body_html, $plain_text, $email_settings);
        error_log("[Ticket Status Update - ID:{$ticket_id}] Email notification sent: " . ($send_status ? 'Success' : 'FAILED'));
        
        if (!$send_status) {
            error_log("[Ticket Status Update - ID:{$ticket_id}] Failed to send email notification to: {$ticket['email']}");
        }
    }
    
    // --- Başarılı Mesaj ve Yönlendirme ---
    $status_text = $status_texts[$new_status] ?? $new_status;
    $_SESSION['success_message'] = "Talep durumu başarıyla '$status_text' olarak güncellendi.";
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Ticket status update error: " . $e->getMessage());
    $_SESSION['error_message'] = "Talep durumu güncellenirken bir hata oluştu: " . $e->getMessage();
}

// Referans sayfaya geri dön
header("Location: " . BASE_URL . "/admin/support/$ref_page.php" . ($ref_page == 'ticket' ? "?id=$ticket_id" : ''));
exit;
?>