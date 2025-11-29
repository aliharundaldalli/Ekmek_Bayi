<?php
/**
 * Admin - Sipariş Fatura Yönetimi
 * Bu sayfa sipariş faturalarını görüntüleme, oluşturma ve gönderme işlemlerini sağlar.
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Sipariş ID Kontrolü ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

$order_id = intval($_GET['id']);

// --- Sipariş ve Fatura Bilgilerini Getir ---
try {
    // Sipariş bilgilerini al
    $stmt_order = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.bakery_name, u.phone, u.email, u.address, u.identity_number
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error_message'] = "Sipariş bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
        exit;
    }
    
    // Sipariş kalemleri
    $stmt_items = $pdo->prepare("
        SELECT oi.*, bt.name as bread_name, bt.description as bread_description
        FROM order_items oi
        LEFT JOIN bread_types bt ON oi.bread_id = bt.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Fatura bilgisi
    $stmt_invoice = $pdo->prepare("
        SELECT * FROM invoices 
        WHERE order_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt_invoice->execute([$order_id]);
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);
    
    // Eğer fatura yoksa ve generate parametresi varsa yeni fatura oluştur
    if (!$invoice && isset($_GET['generate'])) {
        $invoice = generateInvoice($order_id);
        
        if ($invoice) {
            $_SESSION['success_message'] = "Fatura başarıyla oluşturuldu.";
            redirect(rtrim(BASE_URL, '/') . '/admin/orders/invoice.php?id=' . $order_id);
            exit;
        } else {
            $_SESSION['error_message'] = "Fatura oluşturulurken bir hata oluştu.";
        }
    }
    
    // Eğer fatura varsa ve download parametresi varsa faturayı indir
    if ($invoice && isset($_GET['download'])) {
        $file_path = ROOT_PATH . '/' . $invoice['invoice_path'];
        
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Pragma: public');
            
            readfile($file_path);
            exit;
        } else {
            $_SESSION['error_message'] = "Fatura dosyası bulunamadı.";
        }
    }
    
    // Eğer fatura varsa ve send parametresi varsa faturayı e-posta ile gönder
    if ($invoice && isset($_GET['send'])) {
        $result = sendInvoiceEmail($order_id, $invoice['id']);
        
        if ($result) {
            // Fatura gönderildi olarak işaretle
            $stmt_update = $pdo->prepare("UPDATE invoices SET is_sent = 1, sent_at = NOW() WHERE id = ?");
            $stmt_update->execute([$invoice['id']]);
            
            $_SESSION['success_message'] = "Fatura e-postası başarıyla gönderildi.";
        } else {
            $_SESSION['error_message'] = "Fatura e-postası gönderilirken bir hata oluştu.";
        }
        
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/invoice.php?id=' . $order_id);
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı hatası: " . $e->getMessage();
    error_log("Invoice Fetch Error: " . $e->getMessage());
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

// --- Sayfa Başlığı ---
$page_title = 'Fatura: ' . ($invoice ? $invoice['invoice_number'] : $order['order_number']);
$current_page = 'orders';

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
    <div>
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Sipariş Detayına Dön
        </a>
        <?php if ($invoice): ?>
        <a href="?id=<?php echo $order_id; ?>&download=1" class="btn btn-sm btn-primary">
            <i class="fas fa-download me-1"></i> Faturayı İndir
        </a>
        <?php if (!$invoice['is_sent']): ?>
        <a href="?id=<?php echo $order_id; ?>&send=1" class="btn btn-sm btn-success">
            <i class="fas fa-paper-plane me-1"></i> E-posta ile Gönder
        </a>
        <?php else: ?>
        <a href="?id=<?php echo $order_id; ?>&send=1" class="btn btn-sm btn-outline-success">
            <i class="fas fa-paper-plane me-1"></i> Tekrar Gönder
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- İçerik Row -->
<div class="row">
    <div class="col-12">
        <?php include_once ROOT_PATH . '/admin/includes/messages.php'; ?>
        
        <?php if (!$invoice): ?>
        <!-- Fatura Yok -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file-invoice me-1"></i> Fatura Bilgisi
                </h6>
            </div>
            <div class="card-body text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-file-invoice fa-3x text-muted"></i>
                </div>
                <h5 class="mb-3">Bu sipariş için henüz fatura oluşturulmamış.</h5>
                
                <?php if ($order['status'] === 'completed'): ?>
                <a href="?id=<?php echo $order_id; ?>&generate=1" class="btn btn-primary">
                    <i class="fas fa-file-invoice me-1"></i> Fatura Oluştur
                </a>
                <?php else: ?>
                <div class="alert alert-info d-inline-block">
                    <i class="fas fa-info-circle me-1"></i> Fatura oluşturmak için siparişin durumu "Tamamlandı" olmalıdır.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Fatura Bilgileri -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-file-invoice me-1"></i> Fatura Bilgileri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Fatura No</h5>
                                <p class="font-weight-bold h5"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Fatura Tarihi</h5>
                                <p>
                                    <?php 
                                        $date = new DateTime($invoice['invoice_date']);
                                        echo $date->format('d.m.Y');
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Oluşturulma Tarihi</h5>
                                <p>
                                    <?php 
                                        $date = new DateTime($invoice['created_at']);
                                        echo $date->format('d.m.Y H:i');
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Durum</h5>
                                <p>
                                    <?php if ($invoice['is_sent']): ?>
                                    <span class="badge bg-success">Gönderildi</span>
                                    <div class="small text-muted mt-1">
                                        <?php 
                                            $date = new DateTime($invoice['sent_at']);
                                            echo $date->format('d.m.Y H:i');
                                        ?> tarihinde
                                    </div>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Gönderilmedi</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h5 class="text-muted mb-1 small">Dosya Yolu</h5>
                                <p class="text-break">
                                    <?php echo htmlspecialchars($invoice['invoice_path']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user me-1"></i> Müşteri Bilgileri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Büfe Adı</h5>
                                <p class="font-weight-bold"><?php echo htmlspecialchars($order['bakery_name']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Ad Soyad</h5>
                                <p><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Telefon</h5>
                                <p><?php echo htmlspecialchars($order['phone']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">E-posta</h5>
                                <p><?php echo htmlspecialchars($order['email']); ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">TC Kimlik / Vergi No</h5>
                                <p><?php echo htmlspecialchars($order['identity_number']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="text-muted mb-1 small">Sipariş No</h5>
                                <p><?php echo htmlspecialchars($order['order_number']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Fatura Önizleme -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file-pdf me-1"></i> Fatura Önizleme
                </h6>
            </div>
            <div class="card-body p-0">
                <?php 
                    // Fatura dosyası var mı kontrol et
                    $file_path = ROOT_PATH . '/' . $invoice['invoice_path'];
                    $file_exists = file_exists($file_path);
                ?>
                
                <?php if ($file_exists): ?>
                <div class="text-center p-3">
                    <div class="embed-responsive">
                        <iframe src="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($invoice['invoice_path']); ?>" width="100%" height="600" style="border: none;"></iframe>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center p-5">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i> Fatura dosyası bulunamadı: <?php echo htmlspecialchars($invoice['invoice_path']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Sayfa Altı -->
<?php include_once ROOT_PATH . '/admin/footer.php'; ?>