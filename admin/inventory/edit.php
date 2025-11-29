<?php
/**
 * Admin Paneli - Stok Düzenleme Sayfası
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/inventory_functions.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Stok Düzenle';
$current_page = 'inventory';

// --- ID Kontrolü ---
$bread_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bread_id <= 0) {
    $_SESSION['error_message'] = "Geçersiz ekmek ID'si.";
    redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php');
    exit;
}

// --- Ekmek ve Stok Bilgilerini Getir ---
$bread_info = getBreadInfo($bread_id, $pdo);
$current_inventory = getInventory($bread_id, $pdo);

if (!$bread_info) {
    $_SESSION['error_message'] = "Ekmek türü bulunamadı.";
    redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php');
    exit;
}

// --- Form Gönderildi mi? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // CSRF Doğrulama
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız oldu.";
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/edit.php?id=' . $bread_id);
        exit;
    }
    
    // Form verilerini al
    $adjustment_type = $_POST['adjustment_type'] ?? 'set'; // set veya adjust
    $piece_quantity = isset($_POST['piece_quantity']) ? (int)$_POST['piece_quantity'] : 0;
    $box_quantity = isset($_POST['box_quantity']) ? (int)$_POST['box_quantity'] : 0;
    $note = trim($_POST['note'] ?? '');
    
    // Validasyon
    $errors = [];
    
    if ($piece_quantity < 0 || $box_quantity < 0) {
        $errors[] = "Stok miktarları negatif olamaz.";
    }
    
    // Hata kontrolü
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/edit.php?id=' . $bread_id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mevcut stok değerlerini al
        $current_piece = $current_inventory['piece_quantity'] ?? 0;
        $current_box = $current_inventory['box_quantity'] ?? 0;
        
        $new_piece = 0;
        $new_box = 0;
        $movement_piece = 0;
        $movement_box = 0;
        $movement_type = 'adjustment';
        
        if ($adjustment_type === 'set') {
            // Direkt ayarlama (set)
            $new_piece = $piece_quantity;
            $new_box = $box_quantity;
            
            // Hareket miktarlarını hesapla
            $movement_piece = $new_piece - $current_piece;
            $movement_box = $new_box - $current_box;
            
        } else {
            // Artırma/azaltma (adjust)
            $new_piece = $current_piece + $piece_quantity;
            $new_box = $current_box + $box_quantity;
            
            // Negatif stok kontrolü
            if ($new_piece < 0 || $new_box < 0) {
                throw new Exception("Düzeltme sonrası stok negatif olamaz.");
            }
            
            $movement_piece = $piece_quantity;
            $movement_box = $box_quantity;
        }
        
        // Stok hareketi ekle (sadece değişiklik varsa)
        if ($movement_piece != 0 || $movement_box != 0) {
            $movement_note = "Stok düzeltmesi: " . $bread_info['name'];
            if (!empty($note)) {
                $movement_note .= " - " . $note;
            }
            
            // Hareket tipini belirle
            $actual_movement_type = 'in'; // Varsayılan giriş
            if ($movement_piece < 0 || $movement_box < 0) {
                $actual_movement_type = 'out';
                // Negatif değerleri pozitif yap
                $movement_piece = abs($movement_piece);
                $movement_box = abs($movement_box);
            }
            
            $stmt_movement = $pdo->prepare("
                INSERT INTO inventory_movements 
                (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
                VALUES (?, ?, ?, ?, NULL, ?, ?, NOW())
            ");
            $stmt_movement->execute([
                $bread_id, 
                $actual_movement_type, 
                $movement_piece, 
                $movement_box, 
                $movement_note, 
                $_SESSION['user_id'] ?? 0
            ]);
        }
        
        // Stok tablosunu güncelle veya oluştur
        if ($current_inventory) {
            // Mevcut stok kaydını güncelle
            $stmt_update = $pdo->prepare("
                UPDATE inventory 
                SET piece_quantity = ?, 
                    box_quantity = ?,
                    updated_at = NOW()
                WHERE bread_id = ?
            ");
            $stmt_update->execute([$new_piece, $new_box, $bread_id]);
        } else {
            // Yeni stok kaydı oluştur
            $stmt_insert = $pdo->prepare("
                INSERT INTO inventory 
                (bread_id, piece_quantity, box_quantity, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt_insert->execute([$bread_id, $new_piece, $new_box]);
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Stok başarıyla güncellendi.";
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/view.php?id=' . $bread_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Inventory Edit Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Stok güncellenirken bir hata oluştu: " . $e->getMessage();
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/edit.php?id=' . $bread_id);
        exit;
    }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo htmlspecialchars($page_title); ?>
        </h1>
        <div class="btn-group" role="group">
            <a href="<?php echo BASE_URL; ?>/admin/inventory/view.php?id=<?php echo $bread_id; ?>" class="btn btn-sm btn-info">
                <i class="fas fa-eye"></i> Stok Detayı
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Stok Listesi
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sol Kolon: Düzenleme Formu -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-warning text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-edit"></i> Stok Miktarlarını Düzenle
                    </h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>/admin/inventory/edit.php?id=<?php echo $bread_id; ?>" method="post" id="editInventoryForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Düzenleme Tipi Seçimi -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Düzenleme Türü</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-primary" style="cursor: pointer;" onclick="selectAdjustmentType('set')">
                                        <div class="card-body text-center">
                                            <input class="form-check-input" type="radio" name="adjustment_type" id="adjustment_type_set" value="set" checked>
                                            <label class="form-check-label d-block mt-2" for="adjustment_type_set" style="cursor: pointer;">
                                                <i class="fas fa-sliders-h fa-2x text-primary mb-2"></i>
                                                <h6>Direkt Ayarla</h6>
                                                <small class="text-muted">Stok miktarını direkt olarak belirlenen değere ayarlar</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-info" style="cursor: pointer;" onclick="selectAdjustmentType('adjust')">
                                        <div class="card-body text-center">
                                            <input class="form-check-input" type="radio" name="adjustment_type" id="adjustment_type_adjust" value="adjust">
                                            <label class="form-check-label d-block mt-2" for="adjustment_type_adjust" style="cursor: pointer;">
                                                <i class="fas fa-plus-minus fa-2x text-info mb-2"></i>
                                                <h6>Artır/Azalt</h6>
                                                <small class="text-muted">Mevcut stoka ekleme veya çıkarma yapar (+ veya -)</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bilgilendirme Mesajı -->
                        <div class="alert alert-info" id="adjustmentInfo">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Direkt Ayarlama Modu:</strong> Girdiğiniz değerler yeni stok miktarı olarak ayarlanacaktır.
                        </div>

                        <!-- Stok Miktarları -->
                        <div class="row mb-3">
                            <div class="col-md-6" id="pieceQuantityContainer">
                                <label for="piece_quantity" class="form-label fw-bold">
                                    <i class="fas fa-bread-slice me-1"></i> Adet Miktarı
                                </label>
                                <input type="number" 
                                       class="form-control form-control-lg" 
                                       id="piece_quantity" 
                                       name="piece_quantity" 
                                       value="<?php echo $current_inventory['piece_quantity'] ?? 0; ?>"
                                       min="0"
                                       step="1">
                                <small class="form-text text-muted d-block mt-2">
                                    <strong>Mevcut Stok:</strong> <span class="text-primary"><?php echo number_format($current_inventory['piece_quantity'] ?? 0); ?> adet</span>
                                    <br>
                                    <strong>Birim Fiyat:</strong> <?php echo formatMoney($bread_info['price']); ?>
                                </small>
                            </div>
                            <div class="col-md-6" id="boxQuantityContainer">
                                <label for="box_quantity" class="form-label fw-bold">
                                    <i class="fas fa-boxes me-1"></i> Kasa Miktarı
                                </label>
                                <input type="number" 
                                       class="form-control form-control-lg" 
                                       id="box_quantity" 
                                       name="box_quantity" 
                                       value="<?php echo $current_inventory['box_quantity'] ?? 0; ?>"
                                       min="0"
                                       step="1">
                                <small class="form-text text-muted d-block mt-2">
                                    <strong>Mevcut Stok:</strong> <span class="text-primary"><?php echo number_format($current_inventory['box_quantity'] ?? 0); ?> kasa</span>
                                    <?php if (!empty($bread_info['box_capacity'])): ?>
                                        <br>
                                        <strong>Kasa Kapasitesi:</strong> <?php echo $bread_info['box_capacity']; ?> adet
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <!-- Önizleme Paneli -->
                        <div class="alert alert-secondary" id="previewPanel" style="display: none;">
                            <h6 class="alert-heading"><i class="fas fa-eye me-2"></i>Önizleme</h6>
                            <div id="previewContent"></div>
                        </div>

                        <!-- Not Alanı -->
                        <div class="mb-4">
                            <label for="note" class="form-label fw-bold">
                                <i class="fas fa-sticky-note me-1"></i> Not (Opsiyonel)
                            </label>
                            <textarea class="form-control" 
                                      id="note" 
                                      name="note" 
                                      rows="3" 
                                      placeholder="Stok düzeltmesi hakkında açıklama yazabilirsiniz..."></textarea>
                            <small class="form-text text-muted">Bu not stok hareketlerinde görüntülenecektir.</small>
                        </div>
                        
                        <!-- İşlem Butonları -->
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-success btn-lg" id="saveBtn">
                                <i class="fas fa-save me-2"></i> Kaydet ve Uygula
                            </button>
                            <a href="<?php echo BASE_URL; ?>/admin/inventory/view.php?id=<?php echo $bread_id; ?>" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sağ Kolon: Ekmek ve Mevcut Stok Bilgileri -->
        <div class="col-lg-4">
            <!-- Ekmek Bilgileri -->
            <div class="card shadow mb-4 border-left-primary">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Ekmek Bilgileri
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($bread_info['image']) && file_exists(ROOT_PATH . '/uploads/' . $bread_info['image'])): ?>
                        <div class="text-center mb-3">
                            <img src="<?php echo BASE_URL; ?>/uploads/<?php echo $bread_info['image']; ?>" 
                                 alt="<?php echo htmlspecialchars($bread_info['name']); ?>" 
                                 class="img-fluid rounded shadow-sm" 
                                 style="max-height: 150px;">
                        </div>
                    <?php endif; ?>

                    <h5 class="card-title text-center"><?php echo htmlspecialchars($bread_info['name']); ?></h5>
                    
                    <table class="table table-sm table-borderless">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 50%;"><strong>Durum:</strong></td>
                                <td>
                                    <?php if ($bread_info['status'] == 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted"><strong>Satış Türü:</strong></td>
                                <td>
                                    <?php 
                                        switch ($bread_info['sale_type']) {
                                            case 'piece': echo '<span class="badge bg-primary">Adet</span>'; break;
                                            case 'box': echo '<span class="badge bg-info text-dark">Kasa</span>'; break;
                                            case 'both': echo '<span class="badge bg-primary">Adet</span> <span class="badge bg-info text-dark">Kasa</span>'; break;
                                            default: echo '<span class="badge bg-secondary">Belirsiz</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted"><strong>Fiyat:</strong></td>
                                <td class="text-success fw-bold"><?php echo formatMoney($bread_info['price']); ?></td>
                            </tr>
                            <?php if (!empty($bread_info['box_capacity'])): ?>
                                <tr>
                                    <td class="text-muted"><strong>Kasa Kapasitesi:</strong></td>
                                    <td><?php echo $bread_info['box_capacity']; ?> adet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Uyarı Kartı -->
            <div class="card shadow mb-4 border-left-warning">
                <div class="card-header py-3 bg-warning">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-exclamation-triangle"></i> Dikkat!
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Stok düzeltmeleri sistem tarafından kaydedilir ve takip edilir.</li>
                        <li class="mb-2">Düzeltme işlemi geri alınamaz.</li>
                        <li class="mb-2">Önemli değişiklikler için mutlaka not ekleyin.</li>
                        <li>Stok hareketleri raporunda tüm değişiklikler görüntülenebilir.</li>
                    </ul>
                </div>
            </div>

            <!-- Hızlı İşlemler -->
            <div class="card shadow mb-4 border-left-info">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-bolt"></i> Hızlı İşlemler
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="quickSet('piece', 0)">
                            <i class="fas fa-eraser"></i> Adet Stokunu Sıfırla
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="quickSet('box', 0)">
                            <i class="fas fa-eraser"></i> Kasa Stokunu Sıfırla
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="quickSet('both', 0)">
                            <i class="fas fa-sync"></i> Tüm Stoku Sıfırla
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editInventoryForm');
    const adjustmentTypeSet = document.getElementById('adjustment_type_set');
    const adjustmentTypeAdjust = document.getElementById('adjustment_type_adjust');
    const pieceInput = document.getElementById('piece_quantity');
    const boxInput = document.getElementById('box_quantity');
    const adjustmentInfo = document.getElementById('adjustmentInfo');
    const previewPanel = document.getElementById('previewPanel');
    const previewContent = document.getElementById('previewContent');
    const saveBtn = document.getElementById('saveBtn');
    
    // Mevcut stok değerleri
    const currentPiece = <?php echo (int)($current_inventory['piece_quantity'] ?? 0); ?>;
    const currentBox = <?php echo (int)($current_inventory['box_quantity'] ?? 0); ?>;
    
    // Düzenleme tipi değiştiğinde
    function updateAdjustmentType() {
        const isSet = adjustmentTypeSet.checked;
        
        if (isSet) {
            adjustmentInfo.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Direkt Ayarlama Modu:</strong> Girdiğiniz değerler yeni stok miktarı olarak ayarlanacaktır.';
            adjustmentInfo.className = 'alert alert-info';
            pieceInput.value = currentPiece;
            boxInput.value = currentBox;
            pieceInput.min = 0;
            boxInput.min = 0;
            pieceInput.placeholder = 'Yeni stok miktarı';
            boxInput.placeholder = 'Yeni stok miktarı';
        } else {
            adjustmentInfo.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Artır/Azalt Modu:</strong> Pozitif değerler stok ekler, negatif değerler stok azaltır.';
            adjustmentInfo.className = 'alert alert-warning';
            pieceInput.value = 0;
            boxInput.value = 0;
            pieceInput.removeAttribute('min');
            boxInput.removeAttribute('min');
            pieceInput.placeholder = '+ ekleme / - çıkarma';
            boxInput.placeholder = '+ ekleme / - çıkarma';
        }
        
        updatePreview();
    }
    
    // Önizleme güncelleme
    function updatePreview() {
        const isSet = adjustmentTypeSet.checked;
        const pieceValue = parseInt(pieceInput.value) || 0;
        const boxValue = parseInt(boxInput.value) || 0;
        
        let newPiece, newBox;
        
        if (isSet) {
            newPiece = pieceValue;
            newBox = boxValue;
        } else {
            newPiece = currentPiece + pieceValue;
            newBox = currentBox + boxValue;
        }
        
        // Negatif kontrol
        if (newPiece < 0 || newBox < 0) {
            previewPanel.className = 'alert alert-danger';
            previewContent.innerHTML = '<strong>HATA:</strong> Stok miktarları negatif olamaz!';
            previewPanel.style.display = 'block';
            saveBtn.disabled = true;
            return;
        }
        
        // Değişiklik var mı kontrol
        if (newPiece === currentPiece && newBox === currentBox) {
            previewPanel.style.display = 'none';
            saveBtn.disabled = false;
            return;
        }
        
        previewPanel.className = 'alert alert-secondary';
        previewPanel.style.display = 'block';
        saveBtn.disabled = false;
        
        let html = '<div class="row">';
        
        // Adet değişimi
        const pieceDiff = newPiece - currentPiece;
        html += '<div class="col-md-6"><strong>Adet:</strong><br>';
        html += '<span class="text-muted">' + currentPiece + '</span> → ';
        html += '<span class="text-primary fw-bold">' + newPiece + '</span>';
        if (pieceDiff !== 0) {
            const color = pieceDiff > 0 ? 'success' : 'danger';
            const icon = pieceDiff > 0 ? 'arrow-up' : 'arrow-down';
            html += ' <span class="text-' + color + '">(<i class="fas fa-' + icon + '"></i> ' + Math.abs(pieceDiff) + ')</span>';
        }
        html += '</div>';
        
        // Kasa değişimi
        const boxDiff = newBox - currentBox;
        html += '<div class="col-md-6"><strong>Kasa:</strong><br>';
        html += '<span class="text-muted">' + currentBox + '</span> → ';
        html += '<span class="text-primary fw-bold">' + newBox + '</span>';
        if (boxDiff !== 0) {
            const color = boxDiff > 0 ? 'success' : 'danger';
            const icon = boxDiff > 0 ? 'arrow-up' : 'arrow-down';
            html += ' <span class="text-' + color + '">(<i class="fas fa-' + icon + '"></i> ' + Math.abs(boxDiff) + ')</span>';
        }
        html += '</div>';
        
        html += '</div>';
        previewContent.innerHTML = html;
    }
    
    // Event listeners
    adjustmentTypeSet.addEventListener('change', updateAdjustmentType);
    adjustmentTypeAdjust.addEventListener('change', updateAdjustmentType);
    pieceInput.addEventListener('input', updatePreview);
    boxInput.addEventListener('input', updatePreview);
    
    // Form gönderme kontrolü
    form.addEventListener('submit', function(e) {
        const isSet = adjustmentTypeSet.checked;
        const pieceValue = parseInt(pieceInput.value) || 0;
        const boxValue = parseInt(boxInput.value) || 0;
        
        let newPiece, newBox;
        
        if (isSet) {
            newPiece = pieceValue;
            newBox = boxValue;
        } else {
            newPiece = currentPiece + pieceValue;
            newBox = currentBox + boxValue;
        }
        
        if (newPiece < 0 || newBox < 0) {
            e.preventDefault();
            alert('Stok miktarları negatif olamaz!');
            return false;
        }
        
        if (newPiece === currentPiece && newBox === currentBox) {
            e.preventDefault();
            alert('Stok miktarlarında bir değişiklik yapmadınız.');
            return false;
        }
        
        // Onay mesajı
        let message = 'Stok miktarlarını güncellemek istediğinize emin misiniz?\n\n';
        message += 'Adet: ' + currentPiece + ' → ' + newPiece + '\n';
        message += 'Kasa: ' + currentBox + ' → ' + newBox;
        
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Sayfa yüklendiğinde önizlemeyi güncelle
    updateAdjustmentType();
});

// Hızlı işlemler için global fonksiyonlar
function selectAdjustmentType(type) {
    if (type === 'set') {
        document.getElementById('adjustment_type_set').checked = true;
    } else {
        document.getElementById('adjustment_type_adjust').checked = true;
    }
    document.getElementById('adjustment_type_' + type).dispatchEvent(new Event('change'));
}

function quickSet(type, value) {
    const pieceInput = document.getElementById('piece_quantity');
    const boxInput = document.getElementById('box_quantity');
    
    // Direkt ayarlama moduna geç
    document.getElementById('adjustment_type_set').checked = true;
    document.getElementById('adjustment_type_set').dispatchEvent(new Event('change'));
    
    if (type === 'piece') {
        pieceInput.value = value;
    } else if (type === 'box') {
        boxInput.value = value;
    } else if (type === 'both') {
        pieceInput.value = value;
        boxInput.value = value;
    }
    
    pieceInput.dispatchEvent(new Event('input'));
    boxInput.dispatchEvent(new Event('input'));
}
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>