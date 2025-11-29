<?php
/**
 * My Account - Support Ticket Close/Feedback Page
 * 
 * Enhanced version with email notifications and modern UI
 * 
 * @version 2.1
 */

require_once '../../init.php';

// Authentication checks
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}
date_default_timezone_set('Europe/Istanbul');
if (isAdmin()) {
    redirect(BASE_URL . '/admin/index.php');
}

// --- Email Templates ---
/**
 * Generates an HTML email template for ticket closure notifications to admin
 * 
 * @param string $ticket_number The ticket reference number
 * @param string $subject The ticket subject
 * @param string $reason The closure reason
 * @param string $feedback User feedback (if any)
 * @param bool $satisfied Whether the user was satisfied
 * @param array $user_info User information array (first_name, last_name, email)
 * @param string $admin_ticket_url URL for admin to view the ticket
 * @param string $site_title Website title
 * @return string HTML formatted email content
 */
function closeTicketAdminEmailTemplate($ticket_number, $subject, $reason, $feedback, $satisfied, $user_info, $admin_ticket_url, $site_title) {
    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    
    // Get human-readable reason
    $reason_text = '';
    switch($reason) {
        case 'resolved':
            $reason_text = 'Sorun çözüldü';
            break;
        case 'no_longer_needed':
            $reason_text = 'Artık gerekli değil';
            break;
        case 'other':
            $reason_text = 'Diğer sebep';
            break;
        default:
            $reason_text = $reason;
    }
    
    // Satisfaction indicator
    $satisfaction_text = $satisfied ? 'Memnun' : 'Memnun değil';
    $satisfaction_icon = $satisfied ? '✅' : '❌';
    
    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Destek Talebi Kapatıldı</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            h2 { color: #0066cc; border-bottom: 1px solid #eee; padding-bottom: 10px; }
            .ticket-details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .feedback-content { background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0066cc; }
            .footer { font-size: 12px; text-align: center; margin-top: 30px; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
            .button { display: inline-block; background-color: #0066cc; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
            .button:hover { background-color: #004c99; }
            .satisfied { color: #28a745; }
            .unsatisfied { color: #dc3545; }
        </style>
    </head>
    <body>
        <h2>' . htmlspecialchars($site_title) . ' - Destek Talebi Kapatıldı</h2>
        <p><strong>' . $user_full_name . '</strong> tarafından <strong>' . htmlspecialchars($ticket_number) . '</strong> numaralı destek talebi kapatılmıştır.</p>
        <div class="ticket-details">
            <p><strong>Talep No:</strong> ' . htmlspecialchars($ticket_number) . '</p>
            <p><strong>Konu:</strong> ' . htmlspecialchars($subject) . '</p>
            <p><strong>Kullanıcı:</strong> ' . $user_full_name . ' (' . htmlspecialchars($user_info['email']) . ')</p>
            <p><strong>Kapatma Sebebi:</strong> ' . htmlspecialchars($reason_text) . '</p>
            <p><strong>Memnuniyet:</strong> <span class="' . ($satisfied ? 'satisfied' : 'unsatisfied') . '">' . $satisfaction_icon . ' ' . $satisfaction_text . '</span></p>
        </div>';
        
    // Add feedback section if provided
    if (!empty($feedback)) {
        $email_body = $email_body . '
        <div class="feedback-content">
            <p><strong>Kullanıcı Geri Bildirimi:</strong></p>
            <p>' . nl2br(htmlspecialchars($feedback)) . '</p>
        </div>';
    }
    
    $email_body = $email_body . '
        <p>Destek talebini görüntülemek için aşağıdaki bağlantıyı kullanabilirsiniz:</p>
        <p><a href="' . $admin_ticket_url . '" class="button">Destek Talebini Görüntüle</a></p>
        <div class="footer">
            <p>Bu e-posta ' . htmlspecialchars($site_title) . ' destek sistemi tarafından otomatik olarak gönderilmiştir.</p>
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($site_title) . '. Tüm hakları saklıdır.</p>
        </div>
    </body>
    </html>';
    
    return $email_body;
}

/**
 * Function to convert HTML email to plain text
 * This function is useful for email clients that prefer or require plain text
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

// Check for ticket ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz destek talebi ID'si.";
    redirect(BASE_URL . '/my/support/index.php');
}

$ticket_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// --- Kullanıcı Bilgilerini Getir ---
try {
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("Giriş yapmış kullanıcı detayı bulunamadı (ID: {$user_id}).");
    }
} catch (PDOException | Exception $e) {
    error_log("Kullanıcı detayı alınamadı (ID: {$user_id}): " . $e->getMessage());
    $_SESSION['error'] = "Hesap bilgileriniz yüklenirken bir sorun oluştu.";
    redirect(BASE_URL . '/my/support/index.php');
    exit;
}

// Get ticket details
try {
    $stmt = $pdo->prepare("
        SELECT * FROM support_tickets
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error'] = "Destek talebi bulunamadı veya bu talebe erişim izniniz yok.";
        redirect(BASE_URL . '/my/support/index.php');
    }
    
    // Check if ticket is already closed
    if ($ticket['status'] == 'closed') {
        $_SESSION['error'] = "Bu destek talebi zaten kapatılmış.";
        redirect(BASE_URL . '/my/support/view.php?id=' . $ticket_id);
    }
    
} catch (PDOException $e) {
    error_log("Ticket fetch error: " . $e->getMessage());
    $_SESSION['error'] = "Destek talebi bilgileri alınırken bir hata oluştu.";
    redirect(BASE_URL . '/my/support/index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect(BASE_URL . '/my/support/close.php?id=' . $ticket_id);
        exit;
    }

    $reason = trim($_POST['reason'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    $satisfied = isset($_POST['satisfied']) ? 1 : 0;
    
    // Validation
    if (empty($reason)) {
        $error = "Lütfen bir kapatma sebebi seçin.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Reason mapping
            $reason_map = [
                'resolved' => 'Sorun çözüldü',
                'no_longer_needed' => 'Artık gerekli değil',
                'other' => 'Diğer sebep'
            ];
            $reason_text = $reason_map[$reason] ?? $reason;
            
            // Update ticket status (sadece status güncellenir)
            $stmt = $pdo->prepare("
                UPDATE support_tickets
                SET status = 'closed', 
                    closed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$ticket_id]);
            
            // Detaylı history kaydı
            $history_note = "Müşteri tarafından kapatıldı.\n";
            $history_note .= "Sebep: {$reason_text}\n";
            $history_note .= "Memnuniyet: " . ($satisfied ? '✅ Memnun' : '❌ Memnun değil');
            if (!empty($feedback)) {
                $history_note .= "\nGeri bildirim: " . mb_substr($feedback, 0, 200) . (mb_strlen($feedback) > 200 ? '...' : '');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_history 
                (ticket_id, user_id, action, old_value, new_value, note, created_at) 
                VALUES (?, ?, 'ticket_closed', ?, 'closed', ?, NOW())
            ");
            $stmt->execute([
                $ticket_id, 
                $user_id, 
                $ticket['status'],
                $history_note
            ]);
            
            // Kapsamlı mesaj kaydı (her durumda)
            $closing_message = "Destek Talebi Kapatıldı\n\n";
            $closing_message .= "Kapatma Sebebi: {$reason_text}\n";
            $closing_message .= "Memnuniyet Durumu:" . ($satisfied ? 'Memnun' : 'Memnun değil') . "\n";
            
            if (!empty($feedback)) {
                $closing_message .= "\n---\n\n";
                $closing_message .= "Kullanıcı Geri Bildirimi:\n\n";
                $closing_message .= $feedback;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO support_messages 
                (ticket_id, user_id, message, is_internal, created_at) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $closing_message]);
            
            // Satisfaction rating ayrı bir kayıt olarak
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_history 
                (ticket_id, user_id, action, old_value, new_value, note, created_at) 
                VALUES (?, ?, 'satisfaction_rating', NULL, ?, 'Memnuniyet değerlendirmesi', NOW())
            ");
            $stmt->execute([
                $ticket_id, 
                $user_id,
                $satisfied ? 'satisfied' : 'unsatisfied'
            ]);
            
            // Closing reason ayrı kayıt
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_history 
                (ticket_id, user_id, action, old_value, new_value, note, created_at) 
                VALUES (?, ?, 'closing_reason', NULL, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticket_id, 
                $user_id,
                $reason,
                $reason_text
            ]);
            
            $pdo->commit();
            
            // ... Email notifications (aynı kalacak)
            
            $_SESSION['success'] = "Destek talebiniz başarıyla kapatılmıştır. Geri bildiriminiz için teşekkür ederiz.";
            redirect(BASE_URL . '/my/support/view.php?id=' . $ticket_id);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Ticket close error: " . $e->getMessage());
            $error = "Destek talebi kapatılırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}

// Status and priority mapping
$status_map = [
    'new' => ['text' => 'Yeni', 'class' => 'warning', 'icon' => 'fa-star'],
    'open' => ['text' => 'Açık', 'class' => 'primary', 'icon' => 'fa-ticket-alt'],
    'in_progress' => ['text' => 'İşlemde', 'class' => 'info', 'icon' => 'fa-spinner fa-spin'],
    'waiting' => ['text' => 'Yanıt Bekleniyor', 'class' => 'warning', 'icon' => 'fa-clock'],
    'resolved' => ['text' => 'Çözüldü', 'class' => 'success', 'icon' => 'fa-check-circle'],
    'closed' => ['text' => 'Kapatıldı', 'class' => 'secondary', 'icon' => 'fa-lock']
];

$priority_map = [
    'low' => ['text' => 'Düşük', 'class' => 'success', 'icon' => 'fa-arrow-down'],
    'medium' => ['text' => 'Orta', 'class' => 'warning', 'icon' => 'fa-minus'],
    'high' => ['text' => 'Yüksek', 'class' => 'danger', 'icon' => 'fa-exclamation']
];

// Page setup
$page_title = 'Destek Talebi Kapat - ' . $ticket['subject'];
$current_page = 'support';

// --- Include Header ---
include_once ROOT_PATH . '/my/header.php';
?>

<!-- Modern Ticket Close Page - Enhanced UI -->
<div class="container py-4">
    <!-- Page Header with Gradient -->
    <div class="bg-gradient-primary-to-secondary text-white p-4 mb-4 rounded-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-times-circle fs-4 me-2"></i>
                    <h1 class="h3 mb-0 fw-bold">Destek Talebi Kapat</h1>
                </div>
                <p class="mb-0 text-truncate"><?php echo htmlspecialchars($ticket['subject']); ?></p>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="<?php echo BASE_URL; ?>/my/support/view.php?id=<?php echo $ticket_id; ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Talebe Dön
                </a>
            </div>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fs-4 me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Main Column - Close Form -->
        <div class="col-lg-8">
            <!-- Warning Card -->
            <div class="card shadow-sm mb-4 border-warning rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex">
                        <div class="warning-icon me-3 text-warning">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-warning">Destek Talebini Kapatmadan Önce</h5>
                            <p class="mb-2">Bu işlem sonucunda:</p>
                            <ul class="mb-0">
                                <li>Destek talebiniz 'Kapalı' durumuna geçecektir</li>
                                <li>Talebe yeni yanıt ekleyemeyeceksiniz</li>
                                <li>Talep arşive kaldırılacaktır</li>
                            </ul>
                            <p class="mt-2 mb-0">İhtiyaç duyarsanız daha sonra yeni bir destek talebi oluşturabilirsiniz.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Close Form Card -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="fas fa-clipboard-check me-2"></i> Geri Bildirim Formu
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="post" id="closeTicketForm" class="needs-validation" novalidate>
                        <?php $csrf_token = generateCSRFToken(); ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <!-- Reason Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Talebi Kapatma Sebebi <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="reason-card h-100 border rounded p-3" data-reason="resolved">
                                        <div class="form-check h-100 d-flex flex-column">
                                            <div class="d-flex align-items-center mb-2">
                                                <input class="form-check-input reason-radio me-2" type="radio" name="reason" id="reason_resolved" value="resolved" required>
                                                <label class="form-check-label fw-semibold" for="reason_resolved">
                                                    Sorunum çözüldü
                                                </label>
                                            </div>
                                            <div class="text-center mt-auto mb-2">
                                                <i class="fas fa-check-circle text-success fa-3x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="reason-card h-100 border rounded p-3" data-reason="no_longer_needed">
                                        <div class="form-check h-100 d-flex flex-column">
                                            <div class="d-flex align-items-center mb-2">
                                                <input class="form-check-input reason-radio me-2" type="radio" name="reason" id="reason_no_longer_needed" value="no_longer_needed" required>
                                                <label class="form-check-label fw-semibold" for="reason_no_longer_needed">
                                                    Artık gerekli değil
                                                </label>
                                            </div>
                                            <div class="text-center mt-auto mb-2">
                                                <i class="fas fa-ban text-danger fa-3x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="reason-card h-100 border rounded p-3" data-reason="other">
                                        <div class="form-check h-100 d-flex flex-column">
                                            <div class="d-flex align-items-center mb-2">
                                                <input class="form-check-input reason-radio me-2" type="radio" name="reason" id="reason_other" value="other" required>
                                                <label class="form-check-label fw-semibold" for="reason_other">
                                                    Diğer sebep
                                                </label>
                                            </div>
                                            <div class="text-center mt-auto mb-2">
                                                <i class="fas fa-question-circle text-info fa-3x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="invalid-feedback">Lütfen bir kapatma sebebi seçin.</div>
                        </div>
                        
                        <!-- Feedback -->
                        <div class="mb-4">
                            <label for="feedback" class="form-label fw-semibold">
                                <i class="fas fa-comment-alt text-primary me-1"></i> Görüş ve Önerileriniz (Opsiyonel)
                            </label>
                            <textarea name="feedback" id="feedback" class="form-control" rows="4" placeholder="Destek sürecimiz hakkında görüşlerinizi ve varsa önerilerinizi paylaşabilirsiniz. Bu bilgiler hizmet kalitemizi artırmak için kullanılacaktır."></textarea>
                            <div class="form-text">
                                <i class="fas fa-lightbulb me-1"></i> İpucu: Görüşleriniz hizmet kalitemizi iyileştirmemize yardımcı olur.
                            </div>
                        </div>
                        
                        <!-- Satisfaction Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-star text-warning me-1"></i> Memnuniyet Değerlendirmesi
                            </label>
                            <div class="satisfaction-card bg-light rounded p-3 border">
                                <div class="d-flex align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="satisfied" id="satisfied" checked>
                                        <label class="form-check-label ms-2" for="satisfied">
                                            <span id="satisfactionText" class="fw-semibold text-success">Aldığım destek hizmetinden memnun kaldım</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="satisfaction-emoji text-center mt-3">
                                    <i id="satisfactionEmoji" class="far fa-smile-beam text-success fa-3x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <a href="<?php echo BASE_URL; ?>/my/support/view.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Talebe Dön
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times-circle me-1"></i> Talebi Kapat
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Ticket Details -->
        <div class="col-lg-4">
            <!-- Ticket Information Card -->
            <div class="card shadow-sm mb-4 border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i> Talep Bilgileri
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="ticket-details">
                        <div class="detail-item mb-3 pb-3 border-bottom">
                            <div class="detail-label text-muted small mb-1">Talep Numarası</div>
                            <div class="detail-value fw-bold">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                        </div>
                        
                        <div class="detail-item mb-3 pb-3 border-bottom">
                            <div class="detail-label text-muted small mb-1">Durum</div>
                            <div class="detail-value">
                                <span class="badge bg-<?php echo $status_map[$ticket['status']]['class']; ?> d-inline-flex align-items-center">
                                    <i class="fas <?php echo $status_map[$ticket['status']]['icon']; ?> me-1"></i>
                                    <?php echo $status_map[$ticket['status']]['text']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item mb-3 pb-3 border-bottom">
                            <div class="detail-label text-muted small mb-1">Öncelik</div>
                            <div class="detail-value">
                                <span class="badge bg-<?php echo $priority_map[$ticket['priority']]['class']; ?> d-inline-flex align-items-center">
                                    <i class="fas <?php echo $priority_map[$ticket['priority']]['icon']; ?> me-1"></i>
                                    <?php echo $priority_map[$ticket['priority']]['text']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item mb-3 pb-3 border-bottom">
                            <div class="detail-label text-muted small mb-1">Kategori</div>
                            <div class="detail-value fw-medium"><?php echo htmlspecialchars($ticket['category']); ?></div>
                        </div>
                        
                        <div class="detail-item mb-3 pb-3 border-bottom">
                            <div class="detail-label text-muted small mb-1">Oluşturulma Tarihi</div>
                            <div class="detail-value">
                                <i class="far fa-calendar-alt me-1 text-primary"></i>
                                <?php echo date('d F Y, H:i', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label text-muted small mb-1">Son Güncelleme</div>
                            <div class="detail-value">
                                <i class="far fa-clock me-1 text-info"></i>
                                <?php echo date('d F Y, H:i', strtotime($ticket['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Information Card -->
            <div class="card shadow-sm mb-4 border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="fas fa-user me-2"></i> Başvuru Sahibi
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="avatar bg-primary text-white">
                                <?php 
                                    $initials = mb_substr($user_info['first_name'], 0, 1) . mb_substr($user_info['last_name'], 0, 1);
                                    echo strtoupper($initials); 
                                ?>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h6>
                            <p class="mb-0 text-muted small">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user_info['email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern Ticket Close Page Styles */
/* Main styles */
.bg-gradient-primary-to-secondary {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

/* Avatar styles */
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Reason card styles */
.reason-card {
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 0.5rem;
}

.reason-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    border-color: #adb5bd;
}

.reason-card.active {
    border-color: #0d6efd;
    background-color: #f0f7ff;
    box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.15);
}

/* Satisfaction styles */
.satisfaction-card {
    transition: all 0.2s ease;
}

.form-check-input:checked ~ .form-check-label .text-danger {
    color: #198754 !important;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .avatar {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
    
    .reason-card {
        margin-bottom: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic Bootstrap validation script
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })();
    
    // Reason card selection enhancement
    const reasonCards = document.querySelectorAll('.reason-card');
    const reasonRadios = document.querySelectorAll('.reason-radio');
    
    reasonCards.forEach(card => {
        card.addEventListener('click', function() {
            const reasonValue = this.dataset.reason;
            const radio = document.getElementById('reason_' + reasonValue);
            
            // Uncheck all radios
            reasonRadios.forEach(radio => {
                radio.checked = false;
            });
            
            // Remove active class from all cards
            reasonCards.forEach(card => {
                card.classList.remove('active');
            });
            
            // Check selected radio and add active class to card
            radio.checked = true;
            this.classList.add('active');
        });
    });
    
    // Set active class when radio is checked directly
    reasonRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            reasonCards.forEach(card => {
                card.classList.remove('active');
            });
            
            if (this.checked) {
                const card = document.querySelector(`.reason-card[data-reason="${this.value}"]`);
                if (card) {
                    card.classList.add('active');
                }
            }
        });
    });
    
    // Satisfaction toggle enhancement
    const satisfiedCheckbox = document.getElementById('satisfied');
    const satisfactionText = document.getElementById('satisfactionText');
    const satisfactionEmoji = document.getElementById('satisfactionEmoji');
    
    satisfiedCheckbox.addEventListener('change', function() {
        if (this.checked) {
            satisfactionText.textContent = 'Aldığım destek hizmetinden memnun kaldım';
            satisfactionText.classList.remove('text-danger');
            satisfactionText.classList.add('text-success');
            satisfactionEmoji.classList.remove('fa-angry', 'text-danger');
            satisfactionEmoji.classList.add('fa-smile-beam', 'text-success');
        } else {
            satisfactionText.textContent = 'Aldığım destek hizmetinden memnun kalmadım';
            satisfactionText.classList.remove('text-success');
            satisfactionText.classList.add('text-danger');
            satisfactionEmoji.classList.remove('fa-smile-beam', 'text-success');
            satisfactionEmoji.classList.add('fa-angry', 'text-danger');
        }
    });
    
    // Form confirmation
    document.getElementById('closeTicketForm').addEventListener('submit', function(event) {
        const isValid = this.checkValidity();
        
        if (isValid) {
            const confirmed = confirm('Destek talebini kapatmak istediğinizden emin misiniz? Bu işlem geri alınamaz.');
            if (!confirmed) {
                event.preventDefault();
            }
        }
    });
});
</script>

<?php
// --- Include Footer ---
include_once ROOT_PATH . '/my/footer.php';
?>