<?php
/**
 * Admin Paneli - Stok Ekleme Sayfası
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/inventory_functions.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Stok Ekle';
$current_page = 'inventory';

// --- Ekmek bilgisini GET parametresinden al ---
$bread_id = isset($_GET['bread_id']) && is_numeric($_GET['bread_id']) ? (int)$_GET['bread_id'] : 0;
$bread_info = null;
$current_inventory = null;

// --- Eğer belirtilmişse ekmek bilgilerini getir ---
if ($bread_id > 0) {
    $bread_info = getBreadInfo($bread_id, $pdo);
    $current_inventory = getInventory($bread_id, $pdo);
}

// --- Form Gönderildi mi? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // CSRF Doğrulama
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız oldu.";
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/add.php');
        exit;
    }
    
    // Form verilerini al
    $post_bread_id = isset($_POST['bread_id']) && is_numeric($_POST['bread_id']) ? (int)$_POST['bread_id'] : 0;
    $movement_type = $_POST['movement_type'] ?? 'in';
    $piece_quantity = isset($_POST['piece_quantity']) ? (int)$_POST['piece_quantity'] : 0;
    $box_quantity = isset($_POST['box_quantity']) ? (int)$_POST['box_quantity'] : 0;
    $note = $_POST['note'] ?? '';
    
    // Validasyon
    $errors = [];
    
    if ($post_bread_id <= 0) {
        $errors[] = "Lütfen bir ekmek türü seçin.";
    }
    
    if ($piece_quantity <= 0 && $box_quantity <= 0) {
        $errors[] = "Lütfen en az bir miktar (adet veya kasa) girin.";
    }
    
    // Hata kontrolü
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/add.php?bread_id=' . $post_bread_id);
        exit;
    }
    
    // Stok hareketi ekle
    $movement_data = [
        'bread_id' => $post_bread_id,
        'movement_type' => $movement_type,
        'piece_quantity' => $piece_quantity,
        'box_quantity' => $box_quantity,
        'note' => $note,
        'created_by' => $_SESSION['user_id'] ?? 0
    ];
    
    $result = addInventoryMovement($movement_data, $pdo);
    
    if ($result['success']) {
        $_SESSION['success_message'] = "Stok hareketi başarıyla kaydedildi.";
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php');
        exit;
    } else {
        $_SESSION['error_message'] = "Stok hareketi kaydedilirken hata oluştu: " . $result['message'];
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/add.php?bread_id=' . $post_bread_id);
        exit;
    }
}

// --- Tüm ekmek türlerini getir ---
$bread_types = [];
try {
    // Sadece aktif ekmekleri listele (tümünü göstermek için WHERE status = 1 kaldırılabilir)
    $stmt = $pdo->query("SELECT id, name, sale_type, box_capacity, status FROM bread_types ORDER BY name ASC");
    $bread_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Bread Types Fetch Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Ekmek türleri yüklenirken bir hata oluştu.";
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($page_title); ?>
        </h1>
        <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Stok Listesine Dön
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Stok Hareketi Ekle</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>/admin/inventory/add.php" method="post" id="addInventoryForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="bread_id" class="form-label required">Ekmek Türü</label>
                            <select name="bread_id" id="bread_id" class="form-select" required>
                                <option value="">-- Ekmek Türü Seçin --</option>
                                <?php foreach ($bread_types as $bread): ?>
                                    <option value="<?php echo $bread['id']; ?>" 
                                            data-sale-type="<?php echo $bread['sale_type']; ?>"
                                            data-box-capacity="<?php echo $bread['box_capacity']; ?>"
                                            <?php echo ($bread_id == $bread['id']) ? 'selected' : ''; ?>
                                            <?php echo ($bread['status'] == 0) ? 'class="text-danger"' : ''; ?>>
                                        <?php echo htmlspecialchars($bread['name']); ?>
                                        <?php echo ($bread['status'] == 0) ? ' (Pasif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Stok hareketini ekleyeceğiniz ekmek türünü seçin.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">Hareket Türü</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="movement_type" id="movement_type_in" value="in" checked>
                                <label class="form-check-label" for="movement_type_in">
                                    <span class="text-success"><i class="fas fa-arrow-down"></i> Stok Girişi</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="movement_type" id="movement_type_out" value="out">
                                <label class="form-check-label" for="movement_type_out">
                                    <span class="text-danger"><i class="fas fa-arrow-up"></i> Stok Çıkışı</span>
                                </label>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6" id="pieceQuantityContainer">
                                <label for="piece_quantity" class="form-label">Adet Miktarı</label>
                                <input type="number" class="form-control" id="piece_quantity" name="piece_quantity" min="0" value="0">
                                <small class="form-text text-muted">Tanesi <?php echo formatMoney($bread_info['price'] ?? 0); ?></small>
                            </div>
                            <div class="col-md-6" id="boxQuantityContainer">
                                <label for="box_quantity" class="form-label">Kasa Miktarı</label>
                                <input type="number" class="form-control" id="box_quantity" name="box_quantity" min="0" value="0">
                                <?php if (!empty($bread_info['box_capacity'])): ?>
                                    <small class="form-text text-muted">Kasası <?php echo $bread_info['box_capacity']; ?> adet içerir</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Not</label>
                            <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                            <small class="form-text text-muted">Stok hareketi hakkında opsiyonel açıklama.</small>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Kaydet
                            </button>
                            <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <?php if ($bread_info): ?>
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Mevcut Stok Bilgisi</h6>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($bread_info['name']); ?></h5>
                        
                        <div class="mb-3">
                            <p class="mb-1"><strong>Açıklama:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($bread_info['description'] ?? '')); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-1"><strong>Satış Türü:</strong></p>
                            <p>
                                <?php 
                                    switch ($bread_info['sale_type']) {
                                        case 'piece': echo '<span class="badge bg-primary">Adet</span>'; break;
                                        case 'box': 
                                            echo '<span class="badge bg-info text-dark">Kasa</span>';
                                            if (!empty($bread_info['box_capacity'])) {
                                                echo ' (' . $bread_info['box_capacity'] . ' adet)';
                                            }
                                            break;
                                        case 'both': 
                                            echo '<span class="badge bg-primary">Adet</span> ';
                                            echo '<span class="badge bg-info text-dark">Kasa</span>';
                                            if (!empty($bread_info['box_capacity'])) {
                                                echo ' (' . $bread_info['box_capacity'] . ' adet)';
                                            }
                                            break;
                                        default: echo '<span class="badge bg-secondary">Belirsiz</span>';
                                    }
                                ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-1"><strong>Fiyat:</strong></p>
                            <p class="text-primary font-weight-bold"><?php echo formatMoney($bread_info['price']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-1"><strong>Mevcut Stok:</strong></p>
                            <div class="row">
                                <div class="col-6">
                                    <div class="card bg-light">
                                        <div class="card-body p-2 text-center">
                                            <h5 class="mb-0 <?php echo (($current_inventory['piece_quantity'] ?? 0) > 0) ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($current_inventory['piece_quantity'] ?? 0); ?>
                                            </h5>
                                            <small>Adet</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card bg-light">
                                        <div class="card-body p-2 text-center">
                                            <h5 class="mb-0 <?php echo (($current_inventory['box_quantity'] ?? 0) > 0) ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($current_inventory['box_quantity'] ?? 0); ?>
                                            </h5>
                                            <small>Kasa</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <p class="mb-1"><strong>Son Güncelleme:</strong></p>
                            <p><?php echo formatDate($current_inventory['updated_at'] ?? '', true); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4 border-left-info">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">Bilgi</h6>
                    </div>
                    <div class="card-body">
                        <p>Stok hareketi eklemek için lütfen bir ekmek türü seçin.</p>
                        <p>Seçtiğiniz ekmek türüne göre mevcut stok bilgileri burada görüntülenecektir.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const breadSelect = document.getElementById('bread_id');
    const pieceContainer = document.getElementById('pieceQuantityContainer');
    const boxContainer = document.getElementById('boxQuantityContainer');
    const pieceInput = document.getElementById('piece_quantity');
    const boxInput = document.getElementById('box_quantity');
    const movementTypeIn = document.getElementById('movement_type_in');
    const movementTypeOut = document.getElementById('movement_type_out');
    const inventoryForm = document.getElementById('addInventoryForm');
    
    // Ekmek türü değişikliğinde satış türüne göre alanları göster/gizle
    function updateFieldVisibility() {
        if (!breadSelect.value) return;
        
        const selectedOption = breadSelect.options[breadSelect.selectedIndex];
        const saleType = selectedOption.getAttribute('data-sale-type');
        
        if (saleType === 'piece') {
            pieceContainer.style.display = 'block';
            boxContainer.style.display = 'none';
            boxInput.value = 0;
        } else if (saleType === 'box') {
            pieceContainer.style.display = 'none';
            boxContainer.style.display = 'block';
            pieceInput.value = 0;
        } else {
            // 'both' veya diğer durumlar için
            pieceContainer.style.display = 'block';
            boxContainer.style.display = 'block';
        }
    }
    
    // Sayfa yüklendiğinde alanları güncelle
    updateFieldVisibility();
    
    // Ekmek türü değiştiğinde
    breadSelect.addEventListener('change', function() {
        // Ekmek türü değiştiğinde sayfayı yenile ve seçilen ekmek bilgilerini göster
        if (this.value) {
            window.location.href = '<?php echo BASE_URL; ?>/admin/inventory/add.php?bread_id=' + this.value;
        } else {
            updateFieldVisibility();
        }
    });
    
    // Form gönderilmeden önce kontrol
    inventoryForm.addEventListener('submit', function(e) {
        const pieceQty = parseInt(pieceInput.value) || 0;
        const boxQty = parseInt(boxInput.value) || 0;
        
        if (pieceQty <= 0 && boxQty <= 0) {
            e.preventDefault();
            alert('Lütfen en az bir miktar (adet veya kasa) girin.');
            return false;
        }
        
        // Stok çıkışı için mevcut stok kontrolü
        if (movementTypeOut.checked) {
            <?php if ($current_inventory): ?>
            const currentPieceQty = <?php echo (int)($current_inventory['piece_quantity'] ?? 0); ?>;
            const currentBoxQty = <?php echo (int)($current_inventory['box_quantity'] ?? 0); ?>;
            
            if (pieceQty > currentPieceQty) {
                e.preventDefault();
                alert('Çıkış için yeterli adet stok bulunmuyor. Mevcut stok: ' + currentPieceQty + ' adet');
                return false;
            }
            
            if (boxQty > currentBoxQty) {
                e.preventDefault();
                alert('Çıkış için yeterli kasa stok bulunmuyor. Mevcut stok: ' + currentBoxQty + ' kasa');
                return false;
            }
            <?php endif; ?>
        }
        
        return true;
    });
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>