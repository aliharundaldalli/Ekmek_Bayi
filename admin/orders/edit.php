<?php
/**
 * Admin - Sipariş Düzenleme Sayfası
 * Bu sayfa sipariş bilgilerini düzenleme ve güncelleme işlemlerini sağlar.
 */

// --- init.php Dahil Etme ---
// --- init.php Dahil Etme ---
require_once '../../init.php';

// --- Order Functions ---
require_once ROOT_PATH . '/admin/includes/order_functions.php';

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Sipariş ID'si Kontrolü ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz sipariş ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

$order_id = intval($_GET['id']);

// --- Mevcut Sipariş Bilgilerini Getir ---
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
    
    // Ekmek çeşitlerini al (seçenekler için)
    $stmt_bread = $pdo->query("
        SELECT id, name, description, price, sale_type, box_capacity, is_packaged, package_weight, status
        FROM bread_types
        WHERE status = 1
        ORDER BY name ASC
    ");
    $bread_types = $stmt_bread->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı hatası: " . $e->getMessage();
    error_log("Order Edit Fetch Error: " . $e->getMessage());
    redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php');
    exit;
}

// --- Sayfa Başlığı ---
$page_title = 'Sipariş Düzenle: ' . $order['order_number'];
$current_page = 'orders';

// --- Form Gönderildi mi Kontrolü ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect(rtrim(BASE_URL, '/') . '/admin/orders/edit.php?id=' . $order_id);
        exit;
    }
    
    // Form verilerini al
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    
    // Sipariş öğeleri (mevcut ve yeni)
    $item_ids = isset($_POST['item_id']) ? $_POST['item_id'] : [];
    $bread_ids = isset($_POST['bread_id']) ? $_POST['bread_id'] : [];
    $sale_types = isset($_POST['sale_type']) ? $_POST['sale_type'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $action = isset($_POST['action']) ? $_POST['action'] : [];
    
    try {
        // İşlem başlatma
        $pdo->beginTransaction();
        
        // 1. Siparişi güncelle
        $stmt_update_order = $pdo->prepare("
            UPDATE orders 
            SET note = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt_update_order->execute([$note, $order_id]);
        
        // 2. Sipariş kalemlerini güncelle, ekle veya sil
        $total_amount = 0;
        
        // Mevcut öğeleri işle
        foreach ($item_ids as $index => $item_id) {
            if (isset($action[$index]) && $action[$index] === 'delete') {
                // Öğeyi sil
                $stmt_delete_item = $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                $stmt_delete_item->execute([intval($item_id), $order_id]);
                continue;
            }
            
            // Diğer bilgileri al
            $bread_id = isset($bread_ids[$index]) ? intval($bread_ids[$index]) : 0;
            $sale_type = isset($sale_types[$index]) ? $sale_types[$index] : '';
            $quantity = isset($quantities[$index]) ? floatval($quantities[$index]) : 0;
            
            // Geçerlilik kontrolü
            if ($bread_id <= 0 || empty($sale_type) || $quantity <= 0) {
                continue;
            }
            
            // Ekmek bilgilerini al
            $stmt_bread = $pdo->prepare("SELECT price, box_capacity FROM bread_types WHERE id = ?");
            $stmt_bread->execute([$bread_id]);
            $bread = $stmt_bread->fetch(PDO::FETCH_ASSOC);
            
            if (!$bread) {
                continue;
            }
            
            // Birim fiyatı ve toplam fiyatı hesapla
            $unit_price = floatval($bread['price']);
            $total_price = $unit_price * $quantity;
            $pieces_per_box = $sale_type === 'box' ? intval($bread['box_capacity']) : 1;
            
            // Toplam tutara ekle
            $total_amount += $total_price;
            
            // Öğeyi güncelle
            $stmt_update_item = $pdo->prepare("
                UPDATE order_items 
                SET bread_id = ?, sale_type = ?, quantity = ?, pieces_per_box = ?, 
                    unit_price = ?, total_price = ?
                WHERE id = ? AND order_id = ?
            ");
            
            $stmt_update_item->execute([
                $bread_id, $sale_type, $quantity, $pieces_per_box,
                $unit_price, $total_price, intval($item_id), $order_id
            ]);
        }
        
        // Yeni öğeleri ekle
        if (isset($_POST['new_bread_id'])) {
            $new_bread_ids = $_POST['new_bread_id'];
            $new_sale_types = $_POST['new_sale_type'];
            $new_quantities = $_POST['new_quantity'];
            
            foreach ($new_bread_ids as $index => $bread_id) {
                if (empty($bread_id) || intval($bread_id) <= 0) {
                    continue;
                }
                
                $sale_type = isset($new_sale_types[$index]) ? $new_sale_types[$index] : '';
                $quantity = isset($new_quantities[$index]) ? floatval($new_quantities[$index]) : 0;
                
                // Geçerlilik kontrolü
                if (empty($sale_type) || $quantity <= 0) {
                    continue;
                }
                
                // Ekmek bilgilerini al
                $stmt_bread = $pdo->prepare("SELECT price, box_capacity FROM bread_types WHERE id = ?");
                $stmt_bread->execute([intval($bread_id)]);
                $bread = $stmt_bread->fetch(PDO::FETCH_ASSOC);
                
                if (!$bread) {
                    continue;
                }
                
                // Birim fiyatı ve toplam fiyatı hesapla
                $unit_price = floatval($bread['price']);
                $total_price = $unit_price * $quantity;
                $pieces_per_box = $sale_type === 'box' ? intval($bread['box_capacity']) : 1;
                
                // Toplam tutara ekle
                $total_amount += $total_price;
                
                // Yeni öğe ekle
                $stmt_add_item = $pdo->prepare("
                    INSERT INTO order_items 
                    (order_id, bread_id, sale_type, quantity, pieces_per_box, unit_price, total_price, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt_add_item->execute([
                    $order_id, intval($bread_id), $sale_type, $quantity, $pieces_per_box,
                    $unit_price, $total_price
                ]);
            }
        }
        
        // 3. Toplam tutarı güncelle
        $stmt_update_total = $pdo->prepare("
            UPDATE orders 
            SET total_amount = ?
            WHERE id = ?
        ");
        $stmt_update_total->execute([$total_amount, $order_id]);
        
        // İşlemi tamamla
        $pdo->commit();
        
        // Başarı mesajı
        $_SESSION['success_message'] = "Sipariş başarıyla güncellendi.";
        
        // Sipariş detay sayfasına yönlendir
        redirect(rtrim(BASE_URL, '/') . "/admin/orders/view.php?id=$order_id");
        exit;
        
    } catch (PDOException $e) {
        // Hata durumunda geri al
        $pdo->rollBack();
        
        $_SESSION['error_message'] = "Sipariş güncellenirken bir hata oluştu: " . $e->getMessage();
        error_log("Order Update Error: " . $e->getMessage());
    }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
    <div>
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/orders/view.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Sipariş Detayına Dön
        </a>
    </div>
</div>

<!-- İçerik Row -->
<div class="row">
    <div class="col-12">
        <?php include_once ROOT_PATH . '/admin/includes/messages.php'; ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-edit me-1"></i> Sipariş Bilgilerini Düzenle
                </h6>
            </div>
            <div class="card-body">
                <form action="" method="POST" id="edit-order-form">
                    <?php $csrf_token = generateCSRFToken(); ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <!-- Sipariş Genel Bilgileri -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label text-muted">Sipariş Numarası</label>
                                <p class="form-control-static font-weight-bold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label text-muted">Sipariş Tarihi</label>
                                <p class="form-control-static">
                                    <?php 
                                        $date = new DateTime($order['created_at']);
                                        echo $date->format('d.m.Y H:i');
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label text-muted">Müşteri</label>
                                <p class="form-control-static">
                                    <?php echo htmlspecialchars($order['bakery_name']); ?>
                                    <span class="d-block small text-muted"><?php echo htmlspecialchars($order['phone']); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label text-muted">Durum</label>
                                <p class="form-control-static">
                                    <?php 
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        switch($order['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                $status_text = 'Beklemede';
                                                break;
                                            case 'processing':
                                                $status_class = 'bg-primary';
                                                $status_text = 'İşleniyor';
                                                break;
                                            case 'completed':
                                                $status_class = 'bg-success';
                                                $status_text = 'Tamamlandı';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                $status_text = 'İptal Edildi';
                                                break;
                                            default:
                                                $status_class = 'bg-secondary';
                                                $status_text = $order['status'];
                                        }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <span class="d-block small mt-1">
                                        <a href="view.php?id=<?php echo $order_id; ?>#statusModal" class="text-primary">
                                            <i class="fas fa-sync-alt"></i> Durum değiştir
                                        </a>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sipariş Notu -->
                    <div class="form-group mb-4">
                        <label for="note" class="form-label">Sipariş Notu</label>
                        <textarea class="form-control" id="note" name="note" rows="2"><?php echo htmlspecialchars($order['note']); ?></textarea>
                    </div>
                    
                    <hr>
                    
                    <!-- Sipariş Kalemleri -->
                    <h5 class="text-primary mb-3"><i class="fas fa-list me-1"></i> Sipariş Kalemleri</h5>
                    
                    <div id="order-items-container">
                        <?php if (!empty($order_items)): ?>
                        <?php foreach ($order_items as $index => $item): ?>
                        <div class="order-item-row mb-3 p-3 border rounded">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label">Ürün</label>
                                    <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                    <select class="form-select bread-select" name="bread_id[]" required>
                                        <option value="">-- Ürün Seçin --</option>
                                        <?php foreach ($bread_types as $bread): ?>
                                        <option value="<?php echo $bread['id']; ?>" data-sale-type="<?php echo $bread['sale_type']; ?>" data-box-capacity="<?php echo $bread['box_capacity']; ?>" <?php echo ($bread['id'] == $item['bread_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($bread['name']); ?> (<?php echo number_format($bread['price'], 2, ',', '.'); ?> TL)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Satış Tipi</label>
                                    <select class="form-select sale-type-select" name="sale_type[]" required>
                                        <option value="piece" <?php echo ($item['sale_type'] == 'piece') ? 'selected' : ''; ?>>Adet</option>
                                        <option value="box" <?php echo ($item['sale_type'] == 'box') ? 'selected' : ''; ?>>Kasa</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Miktar</label>
                                    <input type="number" class="form-control quantity-input" name="quantity[]" min="1" step="1" value="<?php echo $item['quantity']; ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Birim/Toplam</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?php echo number_format($item['unit_price'], 2, ',', '.'); ?> TL" readonly>
                                        <input type="text" class="form-control" value="<?php echo number_format($item['total_price'], 2, ',', '.'); ?> TL" readonly>
                                    </div>
                                    <?php if ($item['sale_type'] == 'box'): ?>
                                    <div class="small text-muted mt-1">
                                        Kasada <?php echo $item['pieces_per_box']; ?> adet, toplam <?php echo $item['quantity'] * $item['pieces_per_box']; ?> adet
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-1">
                                    <div class="d-flex flex-column align-items-center mt-4">
                                        <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <input type="hidden" name="action[]" class="item-action" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Yeni Kalem Ekleme -->
                    <div id="new-items-container">
                        <!-- JavaScript ile dinamik olarak doldurulacak -->
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <button type="button" id="add-item-btn" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i> Yeni Kalem Ekle
                            </button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Toplam Tutar -->
                    <div class="row mb-4">
                        <div class="col-md-9">
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text font-weight-bold">TOPLAM:</span>
                                <input type="text" id="total-amount" class="form-control font-weight-bold" value="<?php echo number_format($order['total_amount'], 2, ',', '.'); ?> TL" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Gönder -->
                    <div class="row">
                        <div class="col-12 text-end">
                            <a href="view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-1"></i> İptal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Değişiklikleri Kaydet
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sayfa Altı -->
<?php include_once ROOT_PATH . '/admin/footer.php'; ?>

<!-- Sayfa Özel JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const breadTypes = <?php echo json_encode($bread_types); ?>;
    
    // Ekmek seçimi dinleyicisi
    document.querySelectorAll('.bread-select').forEach(select => {
        select.addEventListener('change', updateSaleTypeOptions);
    });
    
    // Kalem hesaplama işlemlerini güncelle
    document.querySelectorAll('.quantity-input, .sale-type-select, .bread-select').forEach(field => {
        field.addEventListener('change', updateItemCalculations);
    });
    
    // Kalem silme düğmeleri
    document.querySelectorAll('.remove-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('.order-item-row');
            row.classList.add('bg-light');
            row.querySelector('.item-action').value = 'delete';
            
            // Kalem silme onayı
            if (confirm('Bu kalemi silmek istediğinizden emin misiniz?')) {
                row.style.display = 'none';
                updateTotalAmount();
            } else {
                row.classList.remove('bg-light');
                row.querySelector('.item-action').value = '';
            }
        });
    });
    
    // Yeni kalem ekle düğmesi
    document.getElementById('add-item-btn').addEventListener('click', addNewItem);
    
    // Form gönderimi kontrolü
    document.getElementById('edit-order-form').addEventListener('submit', function(e) {
        // En az bir kalem var mı kontrol et
        const visibleItems = document.querySelectorAll('.order-item-row:not([style*="display: none"])');
        const newItems = document.querySelectorAll('.new-item-row');
        
        if (visibleItems.length === 0 && newItems.length === 0) {
            e.preventDefault();
            alert('Sipariş en az bir kalem içermelidir.');
            return false;
        }
        
        return true;
    });
    
    // Satış tipi seçeneklerini güncelle
    function updateSaleTypeOptions() {
        const breadId = this.value;
        const row = this.closest('.order-item-row') || this.closest('.new-item-row');
        const saleTypeSelect = row.querySelector('.sale-type-select');
        
        if (!breadId) {
            return;
        }
        
        // Seçilen ekmeğin bilgilerini bul
        const selectedBread = breadTypes.find(bread => bread.id == breadId);
        
        if (selectedBread) {
            // Satış tipi seçeneklerini güncelle
            saleTypeSelect.innerHTML = '';
            
            if (selectedBread.sale_type === 'both' || selectedBread.sale_type === 'piece') {
                const pieceOption = document.createElement('option');
                pieceOption.value = 'piece';
                pieceOption.textContent = 'Adet';
                saleTypeSelect.appendChild(pieceOption);
            }
            
            if (selectedBread.sale_type === 'both' || selectedBread.sale_type === 'box') {
                const boxOption = document.createElement('option');
                boxOption.value = 'box';
                boxOption.textContent = 'Kasa';
                saleTypeSelect.appendChild(boxOption);
            }
            
            // Satış tipi seçimi değişti sinyali
            const event = new Event('change');
            saleTypeSelect.dispatchEvent(event);
        }
    }
    
// Kalem hesaplamalarını güncelle
    function updateItemCalculations() {
        const row = this.closest('.order-item-row') || this.closest('.new-item-row');
        const breadSelect = row.querySelector('.bread-select') || row.querySelector('.new-bread-select');
        const saleTypeSelect = row.querySelector('.sale-type-select') || row.querySelector('.new-sale-type-select');
        const quantityInput = row.querySelector('.quantity-input') || row.querySelector('.new-quantity-input');
        
        const breadId = breadSelect.value;
        const saleType = saleTypeSelect.value;
        const quantity = parseFloat(quantityInput.value) || 0;
        
        if (!breadId || !saleType || quantity <= 0) {
            return;
        }
        
        // Seçilen ekmeğin bilgilerini bul
        const selectedBread = breadTypes.find(bread => bread.id == breadId);
        
        if (selectedBread) {
            const unitPrice = parseFloat(selectedBread.price);
            
            // Birim ve toplam fiyat alanlarını güncelle
            const priceInputs = row.querySelectorAll('input[readonly]');
            
            if (saleType === 'box' && selectedBread.box_capacity > 0) {
                const boxCapacity = parseInt(selectedBread.box_capacity);
                const totalPieces = quantity * boxCapacity;
                
                // Kasa fiyatı = Birim fiyat × Kasadaki adet sayısı
                const boxPrice = unitPrice * boxCapacity;
                const totalPrice = boxPrice * quantity;
                
                if (priceInputs.length >= 2) {
                    priceInputs[0].value = formatCurrency(boxPrice) + ' TL';
                    priceInputs[1].value = formatCurrency(totalPrice) + ' TL';
                }
                
                // Kasa bilgisini güncelle
                const infoDiv = row.querySelector('.item-info');
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <div class="alert alert-info mb-0">
                            <strong>${quantity} kasa:</strong> Her kasada ${boxCapacity} adet, toplam ${totalPieces} adet ekmek.
                            <br><strong>Kasa Fiyatı:</strong> ${formatCurrency(boxPrice)} / kasa
                            <br><strong>Toplam:</strong> ${formatCurrency(totalPrice)}
                        </div>
                    `;
                }
            } else {
                const totalPrice = unitPrice * quantity;
                
                if (priceInputs.length >= 2) {
                    priceInputs[0].value = formatCurrency(unitPrice) + ' TL';
                    priceInputs[1].value = formatCurrency(totalPrice) + ' TL';
                }
                
                // Adet bilgisini güncelle
                const infoDiv = row.querySelector('.item-info');
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <div class="alert alert-info mb-0">
                            <strong>${quantity} adet ekmek</strong>
                            <br><strong>Birim Fiyat:</strong> ${formatCurrency(unitPrice)} / adet
                            <br><strong>Toplam:</strong> ${formatCurrency(totalPrice)}
                        </div>
                    `;
                }
            }
            
            // Toplam tutarı güncelle
            updateTotalAmount();
        }
    }
  
    // Para birimini formatla
    function formatCurrency(amount) {
        return amount.toFixed(2).replace('.', ',');
    }
    
    // Yeni kalem ekle
    function addNewItem() {
        const newItemsContainer = document.getElementById('new-items-container');
        const itemIndex = document.querySelectorAll('.new-item-row').length;
        
        const itemRow = document.createElement('div');
        itemRow.className = 'new-item-row mb-3 p-3 border rounded bg-light';
        
        // HTML içeriği
        itemRow.innerHTML = `
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label class="form-label">Ürün</label>
                    <select class="form-select new-bread-select" name="new_bread_id[${itemIndex}]" required>
                        <option value="">-- Ürün Seçin --</option>
                        ${breadTypes.map(bread => `
                            <option value="${bread.id}" data-sale-type="${bread.sale_type}" data-box-capacity="${bread.box_capacity}">
                                ${bread.name} (${formatCurrency(parseFloat(bread.price))} TL)
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Satış Tipi</label>
                    <select class="form-select new-sale-type-select" name="new_sale_type[${itemIndex}]" required>
                        <option value="piece">Adet</option>
                        <option value="box">Kasa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Miktar</label>
                    <input type="number" class="form-control new-quantity-input" name="new_quantity[${itemIndex}]" min="1" step="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Birim/Toplam</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="0,00 TL" readonly>
                        <input type="text" class="form-control" value="0,00 TL" readonly>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="d-flex flex-column align-items-center mt-4">
                        <button type="button" class="btn btn-sm btn-danger remove-new-item-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Olayları bağla
        newItemsContainer.appendChild(itemRow);
        
        const newBreadSelect = itemRow.querySelector('.new-bread-select');
        const newSaleTypeSelect = itemRow.querySelector('.new-sale-type-select');
        const newQuantityInput = itemRow.querySelector('.new-quantity-input');
        
        newBreadSelect.addEventListener('change', updateSaleTypeOptions);
        newBreadSelect.addEventListener('change', updateItemCalculations);
        newSaleTypeSelect.addEventListener('change', updateItemCalculations);
        newQuantityInput.addEventListener('change', updateItemCalculations);
        
        itemRow.querySelector('.remove-new-item-btn').addEventListener('click', function() {
            itemRow.remove();
            updateTotalAmount();
        });
        
        // İlk değerleri hesapla
        const event = new Event('change');
        newBreadSelect.dispatchEvent(event);
    }
});
</script>