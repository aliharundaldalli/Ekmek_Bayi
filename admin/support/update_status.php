<?php
/**
 * Admin - Destek Talebi Durum Güncelleme
 */
date_default_timezone_set('Europe/Istanbul');
// --- init.php Dahil Etme ---
require_once '../../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Ensure admin is logged in

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
    $stmt = $pdo->prepare("SELECT id, status FROM support_tickets WHERE id = ?");
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
    
    // --- Durum Değişikliği Mesajı Ekle (Opsiyonel) ---
    // Bu kısım, durum değişikliğini mesaj olarak talebe ekler
    $status_texts = [
        'open' => 'Açık',
        'in_progress' => 'İşlemde', 
        'waiting' => 'Yanıt Bekleniyor',
        'resolved' => 'Çözüldü',
        'closed' => 'Kapatıldı'
    ];
    
    $admin_id = $_SESSION['user_id'] ?? 0;
    $message = "Durum değiştirildi: " . ($status_texts[$ticket['status']] ?? $ticket['status']) . " → " . ($status_texts[$new_status] ?? $new_status);
    
    $stmt = $pdo->prepare("
        INSERT INTO support_messages (ticket_id, user_id, is_admin, message, is_system_message, created_at) 
        VALUES (?, ?, 1, ?, 1, NOW())
    ");
    $stmt->execute([$ticket_id, $admin_id, $message]);
    
    // --- Başarılı Mesaj ve Yönlendirme ---
    $status_text = $status_texts[$new_status] ?? $new_status;
    $_SESSION['success_message'] = "Talep durumu başarıyla '$status_text' olarak güncellendi.";
    
} catch (PDOException $e) {
    error_log("Ticket status update error: " . $e->getMessage());
    $_SESSION['error_message'] = "Talep durumu güncellenirken bir hata oluştu: " . $e->getMessage();
}

// Referans sayfaya geri dön
header("Location: " . BASE_URL . "/admin/support/$ref_page.php" . ($ref_page == 'ticket' ? "?id=$ticket_id" : ''));
exit;
?>