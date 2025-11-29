<?php
/**
 * Admin - Support Ticket Delete Action
 * 
 * Handles ticket deletion with proper order of operations to avoid SQL constraint errors
 * 
 * @version 1.0
 */

// --- Include init.php ---
require_once '../../../init.php';

// --- Admin Check ---
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Ensure admin is logged in

// --- Check for Ticket ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz destek talebi ID'si.";
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
}

$ticket_id = intval($_GET['id']);
$ref_page = isset($_GET['ref']) && $_GET['ref'] === 'ticket' ? 'ticket' : 'index';

// --- Confirm Ticket Exists ---
try {
    $stmt = $pdo->prepare("SELECT id, ticket_number FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Belirtilen destek talebi bulunamadı.";
        redirect(BASE_URL . '/admin/support/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Ticket fetch error: " . $e->getMessage());
    $_SESSION['error_message'] = "Destek talebi bilgileri alınırken bir hata oluştu.";
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
}

// --- Delete confirmation required if not provided ---
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    redirect(BASE_URL . '/admin/support/confirm_delete.php?id=' . $ticket_id . '&ref=' . $ref_page);
    exit;
}

// --- Start Deletion Process ---
try {
    // Start transaction to ensure all-or-nothing deletion
    $pdo->beginTransaction();
    
    // 1. First, get all message IDs associated with this ticket
    $stmt = $pdo->prepare("SELECT id FROM support_messages WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    $message_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. If messages exist, delete related attachments first
    if (!empty($message_ids)) {
        // 2.1 Get file paths to physically delete attachment files
        $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT file_path FROM support_attachments WHERE message_id IN ($placeholders)");
        $stmt->execute($message_ids);
        $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 2.2 Delete physical files
        foreach ($attachments as $file_path) {
            if (!empty($file_path)) {
                $full_path = ROOT_PATH . '/' . ltrim($file_path, '/');
                if (file_exists($full_path)) {
                    @unlink($full_path); // Try to delete the file
                }
            }
        }
        
        // 2.3 Delete attachment records from database
        if (!empty($message_ids)) {
            $stmt = $pdo->prepare("DELETE FROM support_attachments WHERE message_id IN ($placeholders)");
            $stmt->execute($message_ids);
        }
    }
    
    // 3. Delete messages
    $stmt = $pdo->prepare("DELETE FROM support_messages WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    
    // 4. Delete history records
    $stmt = $pdo->prepare("DELETE FROM support_ticket_history WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    
    // 5. Delete note records if they exist
    // Check if table exists to avoid errors
    $tables_query = $pdo->query("SHOW TABLES LIKE 'support_notes'");
    if ($tables_query->rowCount() > 0) {
        $stmt = $pdo->prepare("DELETE FROM support_notes WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
    }
    
    // 6. Finally, delete the ticket itself
    $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    // Commit all changes
    $pdo->commit();
    
    // Set success message and redirect
    $_SESSION['success_message'] = "Destek talebi (#" . htmlspecialchars($ticket['ticket_number']) . ") başarıyla silindi.";
    redirect(BASE_URL . '/admin/support/index.php');
    
} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log detailed error for admin debugging
    error_log("Admin ticket deletion error: " . $e->getMessage() . " - SQL State: " . $e->getCode());
    
    // More user-friendly error with code for reference
    $_SESSION['error_message'] = "Destek talebi silinirken bir veritabanı hatası oluştu. Hata Kodu: " . $e->getCode();
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
} catch (Exception $e) {
    // Handle any other exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admin ticket deletion general error: " . $e->getMessage());
    $_SESSION['error_message'] = "Destek talebi silinirken beklenmeyen bir hata oluştu: " . $e->getMessage();
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
}