<?php
/**
 * Admin - Sipariş Silme İşlemi
 * Bu sayfa sipariş silme işlemini gerçekleştirir.
 * Not: Sadece iptal edilmiş (cancelled) siparişler silinebilir.
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- POST Request ve Onay Kontrolü ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id']) || !isset($_POST['confirm_delete'])) {
    $_SESSION['error_message'] = "Geçersiz istek.";
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

$order_id = intval($_POST['order_id']);

try {
    // Siparişi kontrol et (sadece iptal edilmiş siparişler silinebilir)
    $stmt_check = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE id = ?");
    $stmt_check->execute([$order_id]);
    $order = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error_message'] = "Sipariş bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
        exit;
    }
    
    if ($order['status'] !== 'cancelled') {
        $_SESSION['error_message'] = "Sadece iptal edilmiş siparişler silinebilir. Önce siparişi iptal edin.";
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
        exit;
    }
    
    // İşlem başlat
    $pdo->beginTransaction();
    
    // 1. Durum geçmişini sil
    $stmt_del_history = $pdo->prepare("DELETE FROM order_status_history WHERE order_id = ?");
    $stmt_del_history->execute([$order_id]);
    
    // 2. Fatura bilgilerini sil
    $stmt_del_invoice = $pdo->prepare("DELETE FROM invoices WHERE order_id = ?");
    $stmt_del_invoice->execute([$order_id]);
    
    // 3. Sipariş kalemlerini sil
    $stmt_del_items = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt_del_items->execute([$order_id]);
    
    // 4. Ana sipariş kaydını sil
    $stmt_del_order = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt_del_order->execute([$order_id]);
    
    // İşlemi tamamla
    $pdo->commit();
    
    // Log kaydı
    logUserActivity($_SESSION['user_id'], 'order_delete', "Sipariş silindi: " . $order['order_number']);
    
    // Başarı mesajı
    $_SESSION['success_message'] = "Sipariş başarıyla silindi: " . htmlspecialchars($order['order_number']);
    
} catch (PDOException $e) {
    // Hata durumunda geri al
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Sipariş silinirken bir hata oluştu: " . $e->getMessage();
    error_log("Order Delete Error: " . $e->getMessage());
}

// Ana sayfaya yönlendir
redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
exit;
?>