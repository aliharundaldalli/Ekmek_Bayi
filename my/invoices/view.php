<?php
/**
 * Fatura Görüntüleme Sayfası
 * 
 * Bu sayfa, verilen ID'ye ait faturayı ekranda PDF olarak görüntüler.
 */

require_once '../../init.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

// Fatura ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz fatura ID'si.";
    redirect(BASE_URL . '/my/invoices/index.php');
}

$invoice_id = intval($_GET['id']);

try {
    // Kullanıcının kendi faturalarına erişim kontrolü (eğer admin değilse)
    if (!isAdmin()) {
        $stmt = $pdo->prepare("
            SELECT i.* FROM invoices i
            JOIN orders o ON i.order_id = o.id
            WHERE i.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$invoice_id, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
    }
    
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        $_SESSION['error'] = "Fatura bulunamadı veya görüntüleme yetkiniz yok.";
        redirect(BASE_URL . '/my/invoices/index.php');
    }
    
    // Fatura dosyasının varlığını kontrol et
    if (empty($invoice['invoice_path']) || !file_exists(ROOT_PATH . '/' . $invoice['invoice_path'])) {
        $_SESSION['error'] = "Fatura dosyası bulunamadı.";
        redirect(BASE_URL . '/my/invoices/index.php');
    }
    
    // PDF içeriğini görüntüle
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($invoice['invoice_path']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    $file_path = ROOT_PATH . '/' . $invoice['invoice_path'];
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    error_log("Fatura görüntüleme hatası: " . $e->getMessage());
    $_SESSION['error'] = "Fatura görüntülenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    redirect(BASE_URL . '/my/invoices/index.php');
}
?>