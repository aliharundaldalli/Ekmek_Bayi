<?php
/**
 * Admin - Sipariş Detay Sayfası
 */

require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/order_functions.php';

if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

$order_id = intval($_GET['id']);

try {
    $stmt_order = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.bakery_name, u.phone, u.email, u.address
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
    
    $page_title = 'Sipariş Detayı: ' . $order['order_number'];
    $current_page = 'orders';
    
    $stmt_items = $pdo->prepare("
        SELECT oi.*, bt.name as bread_name, bt.description as bread_description, bt.image as bread_image
        FROM order_items oi
        LEFT JOIN bread_types bt ON oi.bread_id = bt.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_history = $pdo->prepare("
        SELECT osh.*, u.first_name, u.last_name
        FROM order_status_history osh
        LEFT JOIN users u ON osh.created_by = u.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at DESC
    ");
    $stmt_history->execute([$order_id]);
    $status_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_invoice = $pdo->prepare("SELECT * FROM invoices WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt_invoice->execute([$order_id]);
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı hatası: " . $e->getMessage();
    error_log("Order Detail Fetch Error: " . $e->getMessage());
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

// --- Durum Güncelleme ve Fatura İşlemleri (Mevcut mantık korunuyor) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $note = $_POST['status_note'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (function_exists('validateCSRFToken') && !validateCSRFToken($csrf_token)) {
        $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız oldu.";
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
        exit;
    }
    
    if (!empty($new_status)) {
        $result = updateOrderStatus($order_id, $new_status, $note, $pdo);
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            if ($new_status === 'completed' && (!$invoice || empty($invoice))) {
                generateInvoice($order_id, $pdo);
                $stmt_invoice = $pdo->prepare("SELECT * FROM invoices WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt_invoice->execute([$order_id]);
                $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);
                if ($invoice) sendInvoiceEmail($order_id, $invoice['id'], $pdo);
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
        exit;
    }
}

if (isset($_GET['generate_invoice']) && $_GET['generate_invoice'] === 'true') {
    $result = generateInvoice($order_id, $pdo);
    if ($result) {
        $_SESSION['success_message'] = "Fatura başarıyla oluşturuldu.";
    } else {
        $_SESSION['error_message'] = "Fatura oluşturulurken bir hata oluştu.";
    }
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invoice'])) {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
        exit;
    }

    $invoice_id = $invoice['id'] ?? 0;
    if ($invoice_id > 0) {
        $result = sendInvoiceEmail($order_id, $invoice_id, $pdo);
        if ($result['success']) $_SESSION['success_message'] = $result['message'];
        else $_SESSION['error_message'] = $result['message'];
    } else {
        $_SESSION['error_message'] = "Fatura bulunamadı.";
    }
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/view.php?id=' . $order_id);
    exit;
}

// --- Helper Functions ---
function sendInvoiceEmail($order_id, $invoice_id, $pdo) {
    // ... (Mevcut fonksiyon içeriği korunacak, buraya kopyalanmalı veya include edilmeli)
    // Kısa tutmak için burayı atlıyorum, orijinal dosyadaki fonksiyonu kullanacağız.
    // Ancak bu dosya tamamen yeniden yazıldığı için fonksiyonu buraya eklemeliyim.
    try {
        $stmt_invoice = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt_invoice->execute([$invoice_id]);
        $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) return ['success' => false, 'message' => "Fatura bilgileri bulunamadı."];
        
        $stmt_user = $pdo->prepare("SELECT u.email, u.first_name, u.last_name, u.bakery_name, o.order_number FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmt_user->execute([$order_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if (!$user_data || empty($user_data['email'])) return ['success' => false, 'message' => "Müşteri e-posta adresi bulunamadı."];
        
        $subject = 'Fatura: ' . $invoice['invoice_number'];
        $content = '<p>Sayın <strong>' . htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) . '</strong>,</p>
        <p><strong>' . htmlspecialchars($user_data['bakery_name']) . '</strong> için <strong>' . htmlspecialchars($user_data['order_number']) . '</strong> numaralı siparişinize ait faturayı ekte bulabilirsiniz.</p>
        <div class="info-box"><p style="margin: 5px 0;"><strong>Fatura No:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p><p style="margin: 5px 0;"><strong>Tarih:</strong> ' . date('d.m.Y', strtotime($invoice['invoice_date'])) . '</p></div>
        <p>Teşekkür ederiz.</p>';
        
        $final_body = getStandardEmailTemplate($subject, $content);
        $plain_text = generatePlainTextFromHtml($final_body);
        
        $attachments = [];
        $invoice_path = ROOT_PATH . '/' . $invoice['invoice_path'];
        if (file_exists($invoice_path)) {
            $attachments[] = ['path' => $invoice_path, 'name' => 'fatura_' . $invoice['invoice_number'] . '.pdf'];
        } else {
            return ['success' => false, 'message' => "Fatura dosyası bulunamadı."];
        }
        
        if (sendEmail($user_data['email'], $subject, $final_body, $plain_text, [], $attachments)) {
            $pdo->prepare("UPDATE invoices SET is_sent = 1, sent_at = NOW() WHERE id = ?")->execute([$invoice_id]);
            return ['success' => true, 'message' => "Fatura başarıyla gönderildi: " . $user_data['email']];
        } else {
            return ['success' => false, 'message' => "Fatura gönderilirken bir hata oluştu."];
        }
    } catch (Exception $e) {
        error_log("Invoice Send Error: " . $e->getMessage());
        return ['success' => false, 'message' => "Hata: " . $e->getMessage()];
    }
}

$csrf_token = function_exists('generateCSRFToken') ? generateCSRFToken() : '';

include_once ROOT_PATH . '/admin/header.php';

// Status Badge Helper
$status_badges = [
    'pending' => ['class' => 'bg-warning', 'text' => 'Beklemede', 'icon' => 'fa-clock'],
    'processing' => ['class' => 'bg-primary', 'text' => 'İşleniyor', 'icon' => 'fa-cog fa-spin'],
    'completed' => ['class' => 'bg-success', 'text' => 'Tamamlandı', 'icon' => 'fa-check-circle'],
    'cancelled' => ['class' => 'bg-danger', 'text' => 'İptal Edildi', 'icon' => 'fa-times-circle']
];
$current_status = $status_badges[$order['status']] ?? ['class' => 'bg-secondary', 'text' => $order['status'], 'icon' => 'fa-question'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <span class="text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></span> 
            <span class="text-muted fs-5 ms-2">Sipariş Detayı</span>
        </h1>
        <div class="d-flex gap-2">
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/orders/index.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Listeye Dön
            </a>
            <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="fas fa-sync-alt me-1"></i> Durum Güncelle
            </button>
        </div>
    </div>

    <?php include_once ROOT_PATH . '/admin/includes/messages.php'; ?>

    <div class="row">
        <!-- Sol Kolon: Sipariş ve Müşteri Bilgileri -->
        <div class="col-xl-4 col-lg-5">
            <!-- Sipariş Durumu Kartı -->
            <div class="card shadow mb-4 border-left-<?php echo str_replace('bg-', '', $current_status['class']); ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">Sipariş Durumu</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="badge <?php echo $current_status['class']; ?> p-2">
                                    <i class="fas <?php echo $current_status['icon']; ?> me-1"></i> <?php echo $current_status['text']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <div class="row text-center">
                            <div class="col-6 border-end">
                                <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Tarih</span>
                                <span class="font-weight-bold text-dark"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="col-6">
                                <span class="d-block text-xs font-weight-bold text-uppercase text-muted">Tutar</span>
                                <span class="font-weight-bold text-success"><?php echo number_format($order['total_amount'], 2, ',', '.'); ?> ₺</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Müşteri Bilgileri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user me-2"></i>Müşteri Bilgileri</h6>
                    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/users/view.php?id=<?php echo $order['user_id']; ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3">
                        Profili Gör
                    </a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center rounded-circle me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                            <?php echo mb_strtoupper(mb_substr($order['first_name'], 0, 1) . mb_substr($order['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold text-dark"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($order['bakery_name']); ?></small>
                        </div>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-phone me-2"></i>Telefon</span>
                            <small> <span class="fw-bold"><a href="tel:<?php echo htmlspecialchars($order['phone']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($order['phone']); ?></a></span></small>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-envelope me-2"></i>E-posta</span>
                            <small> <span class="fw-bold"><a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($order['email']); ?></a></span></small>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <small class="text-uppercase text-muted fw-bold">Teslimat Adresi</small>
                        <p class="mb-0 mt-1 bg-light p-2 rounded text-dark small border">
                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                            <small><?php echo nl2br(htmlspecialchars($order['address'])); ?></small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Fatura İşlemleri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice me-2"></i>Fatura</h6>
                </div>
                <div class="card-body">
                    <?php if ($invoice): ?>
                        <div class="text-center mb-3">
                            <div class="h4 font-weight-bold text-dark mb-1"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                            <span class="badge <?php echo $invoice['is_sent'] ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $invoice['is_sent'] ? 'Gönderildi (' . date('d.m.Y', strtotime($invoice['sent_at'])) . ')' : 'Gönderilmedi'; ?>
                            </span>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($invoice['invoice_path']); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-eye me-1"></i> Görüntüle (PDF)
                            </a>
                            <form action="" method="POST" class="d-grid">
                                <input type="hidden" name="send_invoice" value="1">
                                <?php if (!empty($csrf_token)): ?><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><?php endif; ?>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane me-1"></i> <?php echo $invoice['is_sent'] ? 'Tekrar Gönder' : 'Müşteriye Gönder'; ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-center mb-3">
                            <small class="text-muted">Bu sipariş için henüz fatura oluşturulmamış.</small>
                        </div>
                        <?php if ($order['status'] === 'completed'): ?>
                            <div class="d-grid">
                                <a href="?id=<?php echo $order_id; ?>&generate_invoice=true" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Fatura Oluştur
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0 small">
                                <i class="fas fa-exclamation-triangle me-1"></i> Fatura için sipariş tamamlanmalıdır.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Sipariş İçeriği ve Geçmiş -->
        <div class="col-xl-8 col-lg-7">
            <!-- Sipariş Kalemleri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-basket me-2"></i>Sipariş İçeriği</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Ürün</th>
                                    <th class="text-center">Tip</th>
                                    <th class="text-center">Miktar</th>
                                    <th class="text-end">Birim Fiyat</th>
                                    <th class="text-end pe-4">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <?php if(!empty($item['bread_image']) && file_exists(ROOT_PATH . '/uploads/' . $item['bread_image'])): ?>
                                                <img src="<?php echo BASE_URL . 'uploads/' . $item['bread_image']; ?>" class="rounded me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3 text-muted" style="width: 40px; height: 40px;"><i class="fas fa-bread-slice"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['bread_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($item['bread_description'], 0, 50, '...')); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['sale_type'] === 'piece'): ?>
                                            <span class="badge bg-info text-dark">Adet</span>
                                        <?php elseif ($item['sale_type'] === 'box'): ?>
                                            <span class="badge bg-primary">Kasa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($item['sale_type']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?php echo number_format($item['quantity'], 0, ',', '.'); ?></span>
                                        <?php if ($item['sale_type'] === 'box'): ?>
                                            <div class="small text-muted">(x<?php echo $item['pieces_per_box']; ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-muted">
                                        <?php echo number_format($item['unit_price'], 2, ',', '.'); ?> ₺
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">
                                        <?php echo number_format($item['total_price'], 2, ',', '.'); ?> ₺
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light border-top">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold text-uppercase text-muted pt-3">Genel Toplam</td>
                                    <td class="text-end pe-4 pt-3">
                                        <span class="h4 font-weight-bold text-primary"><?php echo number_format($order['total_amount'], 2, ',', '.'); ?> ₺</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sipariş Notu -->
            <?php if (!empty($order['note'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sticky-note me-2"></i>Sipariş Notu</h6>
                </div>
                <div class="card-body bg-warning bg-opacity-10">
                    <p class="mb-0 text-dark"><i class="fas fa-quote-left text-warning me-2"></i><?php echo nl2br(htmlspecialchars($order['note'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Durum Geçmişi -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>İşlem Geçmişi</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($status_history as $history): ?>
                        <div class="timeline-item pb-3 position-relative">
                            <div class="timeline-marker bg-white border border-2 border-primary rounded-circle position-absolute start-0 mt-1" style="width: 12px; height: 12px; z-index: 1;"></div>
                            <div class="ps-4 border-start ms-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge <?php echo $status_badges[$history['status']]['class'] ?? 'bg-secondary'; ?>">
                                        <?php echo $status_badges[$history['status']]['text'] ?? $history['status']; ?>
                                    </span>
                                    <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($history['created_at'])); ?></small>
                                </div>
                                <?php if (!empty($history['note'])): ?>
                                    <p class="mb-1 small text-dark bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($history['note'])); ?></p>
                                <?php endif; ?>
                                <small class="text-muted fst-italic">İşlem: <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Durum Güncelleme Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sipariş Durumunu Güncelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_status" value="1">
                    <?php if (!empty($csrf_token)): ?><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Yeni Durum</label>
                        <select class="form-select" name="new_status" required>
                            <option value="">Seçiniz...</option>
                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>İşleniyor</option>
                            <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Not (Opsiyonel)</label>
                        <textarea class="form-control" name="status_note" rows="3" placeholder="Durum değişikliği ile ilgili not ekleyin..."></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-1"></i> <strong>Tamamlandı</strong> durumunda stok düşülür ve fatura oluşturulur. <strong>İptal</strong> durumunda stok iade edilir.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . '/admin/footer.php'; ?>