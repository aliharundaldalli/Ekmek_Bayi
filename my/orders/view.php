<?php
/**
 * Büfe Kullanıcı Paneli - Sipariş Detayı
 * Bu sayfa büfe kullanıcısının sipariş detaylarını gösterir.
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';

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

// --- Sipariş ID Kontrolü ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// --- Sipariş Bilgilerini Getir ---
try {
    // Sipariş kontrolü (kullanıcıya ait mi?)
    $stmt_check = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$order_id, $user_id]);
    
    if (!$stmt_check->fetch()) {
        $_SESSION['error_message'] = "Bu siparişe erişim izniniz yok veya sipariş bulunamadı.";
        redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
        exit;
    }
    
    // Ana sipariş bilgileri
    $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
    
    // Sipariş kalemleri
    $stmt_items = $pdo->prepare("
        SELECT oi.*, bt.name as bread_name, bt.description as bread_description
        FROM order_items oi
        JOIN bread_types bt ON oi.bread_id = bt.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş durum geçmişi
    $stmt_history = $pdo->prepare("
        SELECT osh.*, u.first_name, u.last_name, u.role
        FROM order_status_history osh
        LEFT JOIN users u ON osh.created_by = u.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at DESC
    ");
    $stmt_history->execute([$order_id]);
    $status_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    
    // Fatura bilgileri
    $stmt_invoice = $pdo->prepare("
        SELECT * FROM invoices 
        WHERE order_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt_invoice->execute([$order_id]);
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Order Details Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Sipariş detayları yüklenirken bir hata oluştu: " . $e->getMessage();
    redirect(rtrim(BASE_URL, '/') . '/my/orders/index.php');
    exit;
}

// --- Sayfa Başlığı ---
$page_title = 'Sipariş Detayı: ' . $order['order_number'];
$current_page = 'orders';

// --- Para Birimi Formatı ---
function formatMoney($amount) {
    global $pdo;
    static $currency = null;
    
    if ($currency === null) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'currency'");
            $stmt->execute();
            $currency = $stmt->fetchColumn() ?: 'TL';
        } catch (PDOException $e) {
            $currency = 'TL';
        }
    }
    
    return number_format($amount, 2, ',', '.') . ' ' . $currency;
}

// --- Tarih Formatı ---
function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

// --- Sipariş Durumu Metni ---
function getStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'Beklemede';
        case 'processing':
            return 'İşleniyor';
        case 'completed':
            return 'Tamamlandı';
        case 'cancelled':
            return 'İptal Edildi';
        default:
            return $status;
    }
}

// --- Sipariş Durumu CSS Sınıfı ---
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning';
        case 'processing':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/my/header.php';
?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-shopping-basket me-2"></i> Sipariş Detayı
        </h5>
        <div>
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/index.php" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1"></i> Siparişlerime Dön
            </a>
            
            <?php if ($order['status'] === 'pending'): ?>
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/cancel.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-danger ms-2 cancel-order"
               data-bs-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                <i class="fas fa-times me-1"></i> İptal Et
            </a>
            <?php endif; ?>
            
            <?php if ($order['status'] === 'completed'): ?>
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/repeat.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-success ms-2">
                <i class="fas fa-redo me-1"></i> Bu Siparişi Tekrarla
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Sipariş Özeti -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-info-circle me-1"></i> Sipariş Bilgileri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Sipariş Numarası</label>
                                <p class="fs-5 fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Sipariş Tarihi</label>
                                <p><?php echo formatDate($order['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Toplam Tutar</label>
                                <p class="fs-5 fw-bold"><?php echo formatMoney($order['total_amount']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Durum</label>
                                <p>
                                    <span class="badge <?php echo getStatusClass($order['status']); ?>">
                                        <?php echo getStatusText($order['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($order['note'])): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <label class="form-label text-muted">Sipariş Notu</label>
                                <p><?php echo nl2br(htmlspecialchars($order['note'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Fatura Bilgileri -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-file-invoice me-1"></i> Fatura Bilgileri
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($invoice): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Fatura Numarası</label>
                                <p class="fw-bold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Fatura Tarihi</label>
                                <p><?php echo date('d.m.Y', strtotime($invoice['invoice_date'])); ?></p>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12 text-center">
                                <a href="<?php echo rtrim(BASE_URL, '/') . '/' . htmlspecialchars($invoice['invoice_path']); ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-file-pdf me-1"></i> Faturayı Görüntüle
                                </a>
                                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/invoices/download.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-download me-1"></i> İndir
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i> Bu sipariş için henüz fatura oluşturulmamış.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sipariş Kalemleri -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-list me-1"></i> Sipariş Kalemleri
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>Ürün</th>
                                <th>Satış Tipi</th>
                                <th>Miktar</th>
                                <th>Birim Fiyat</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($order_items)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Bu siparişte ürün bulunmuyor.</td>
                            </tr>
                            <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['bread_name']); ?></div>
                                    <?php if (!empty($item['bread_description'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($item['bread_description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['sale_type'] === 'piece'): ?>
                                    <span class="badge bg-info">Adet</span>
                                    <?php elseif ($item['sale_type'] === 'box'): ?>
                                    <span class="badge bg-primary">Kasa</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($item['sale_type']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['sale_type'] === 'piece'): ?>
                                    <?php echo number_format($item['quantity'], 0, ',', '.'); ?> adet
                                    <?php elseif ($item['sale_type'] === 'box'): ?>
                                    <?php echo number_format($item['quantity'], 0, ',', '.'); ?> kasa
                                    (<?php echo number_format($item['pieces_per_box'], 0, ',', '.'); ?> adet/kasa)
                                    <div class="small text-muted">
                                        Toplam: <?php echo number_format($item['quantity'] * $item['pieces_per_box'], 0, ',', '.'); ?> adet
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatMoney($item['unit_price']); ?>
                                    <?php if ($item['sale_type'] === 'box'): ?>
                                    <div class="small text-muted">
                                        <?php echo formatMoney($item['unit_price'] / $item['pieces_per_box']); ?>/adet
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <?php echo formatMoney($item['total_price']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light">
                                <td colspan="5" class="text-end fw-bold">TOPLAM:</td>
                                <td class="fw-bold fs-5">
                                    <?php echo formatMoney($order['total_amount']); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Durum Geçmişi -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-history me-1"></i> Durum Geçmişi
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($status_history)): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-1"></i> Durum geçmişi bulunamadı.
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($status_history as $history): ?>
                    <div class="timeline-item">
                        <div class="timeline-left">
                            <div class="timeline-date small text-muted">
                                <?php echo formatDate($history['created_at']); ?>
                            </div>
                        </div>
                        <div class="timeline-center">
                            <div class="timeline-marker bg-primary"></div>
                        </div>
                        <div class="timeline-right">
                            <div class="timeline-content">
                                <div class="mb-1">
                                    <span class="badge <?php echo getStatusClass($history['status']); ?>">
                                        <?php echo getStatusText($history['status']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($history['note'])): ?>
                                <div class="mb-2">
                                    <?php echo nl2br(htmlspecialchars($history['note'])); ?>
                                </div>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    <?php if ($history['role'] === 'admin'): ?>
                                    <i class="fas fa-user-shield me-1"></i> 
                                    <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?> (Yönetici)
                                    <?php else: ?>
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Siparişlerime Dön
        </a>
        
        <?php if ($order['status'] === 'completed'): ?>
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/my/orders/create.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-1"></i> Yeni Sipariş Oluştur
        </a>
        <?php endif; ?>
    </div>
</div>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/my/footer.php';
?>

<!-- İptal Onay Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelOrderModalLabel">Sipariş İptal Onayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p>
                    <strong id="cancelOrderNumber"></strong> numaralı siparişi iptal etmek istediğinizden emin misiniz?
                </p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i> Bu işlem geri alınamaz.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <a href="#" id="confirmCancelBtn" class="btn btn-danger">Siparişi İptal Et</a>
            </div>
        </div>
    </div>
</div>

<!-- Özel CSS -->
<style>
/* Durum Geçmişi Timeline */
.timeline {
    position: relative;
    padding: 1rem 0;
}

.timeline-item {
    display: flex;
    margin-bottom: 1.5rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-left {
    width: 120px;
    padding-right: 1rem;
    text-align: right;
}

.timeline-center {
    position: relative;
    width: 20px;
}

.timeline-center:before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    height: 100%;
    width: 2px;
    background-color: #e9ecef;
    transform: translateX(-50%);
}

.timeline-marker {
    position: absolute;
    top: 0;
    left: 50%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transform: translateX(-50%);
}

.timeline-right {
    flex: 1;
    padding-left: 1rem;
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.25rem;
}
</style>

<!-- Özel JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // İptal butonları için işlem
    const cancelButtons = document.querySelectorAll('.cancel-order');
    const cancelOrderModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    const cancelOrderNumber = document.getElementById('cancelOrderNumber');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    
    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const orderNumber = this.getAttribute('data-bs-order-number');
            const cancelUrl = this.getAttribute('href');
            
            cancelOrderNumber.textContent = orderNumber;
            confirmCancelBtn.setAttribute('href', cancelUrl);
            
            cancelOrderModal.show();
        });
    });
});
</script>