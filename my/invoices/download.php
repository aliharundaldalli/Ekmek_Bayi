<?php
/**
 * Fatura İndirme Sayfası
 * 
 * Bu sayfa, verilen ID'ye ait faturayı kullanıcının bilgisayarına indirmesini sağlar.
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
        $_SESSION['error'] = "Fatura bulunamadı veya indirme yetkiniz yok.";
        redirect(BASE_URL . '/my/invoices/index.php');
    }
    
    // Fatura dosyasının varlığını kontrol et
    if (empty($invoice['invoice_path']) || !file_exists(ROOT_PATH . '/' . $invoice['invoice_path'])) {
        $_SESSION['error'] = "Fatura dosyası bulunamadı.";
        redirect(BASE_URL . '/my/invoices/index.php');
    }
    
    // Sipariş bilgilerini al (fatura adını oluşturmak için)
    $order_number = "Siparis";
    try {
        $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
        $stmt->execute([$invoice['order_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order && !empty($order['order_number'])) {
            $order_number = $order['order_number'];
        }
    } catch (PDOException $e) {
        // Hata durumunda varsayılan adı kullan
    }
    
    // İndirme için PDF dosyasını hazırla
    $file_path = ROOT_PATH . '/' . $invoice['invoice_path'];
    $file_name = 'Fatura_' . $order_number . '_' . $invoice['invoice_number'] . '.pdf';
    
    // PDF dosyasını indir
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    error_log("Fatura indirme hatası: " . $e->getMessage());
    $_SESSION['error'] = "Fatura indirilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    redirect(BASE_URL . '/my/invoices/index.php');
}
?>