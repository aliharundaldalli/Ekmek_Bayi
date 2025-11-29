<?php
/**
 * Admin Paneli - Stok Yönetimi Ana Sayfası
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php'; // init.php'nin ROOT_PATH, BASE_URL, $pdo vb. tanımladığını varsayıyoruz
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Admin kontrolü
require_once ROOT_PATH . '/admin/includes/inventory_functions.php'; // Stok fonksiyonları

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Stok Yönetimi';
$current_page = 'inventory'; // Sidebar için

// --- Filtreleme Değişkenleri ---
$bread_id = isset($_GET['bread_id']) && is_numeric($_GET['bread_id']) ? (int)$_GET['bread_id'] : 0;
$sale_type = $_GET['sale_type'] ?? '';
$status = isset($_GET['status']) && is_numeric($_GET['status']) ? (int)$_GET['status'] : -1; // -1 = tümü
$search = $_GET['search'] ?? '';
$min_quantity = isset($_GET['min_quantity']) && is_numeric($_GET['min_quantity']) ? (int)$_GET['min_quantity'] : 0;

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25; // Sayfa başına kayıt sayısı
$offset = ($page - 1) * $per_page;

// --- Sorgu Oluşturma (Filtrelerle) ---
// Temel sorgu yapısı - Ekmek türleri ve envanter bilgilerini birleştir
$base_query = "FROM bread_types b 
               LEFT JOIN inventory i ON b.id = i.bread_id 
               WHERE 1=1";
$params = []; // Ana sorgu için parametreler
$count_params = []; // COUNT sorgusu için parametreler

// Filtreleri ekle
if (!empty($bread_id)) {
    $base_query .= " AND b.id = :bread_id";
    $params[':bread_id'] = $bread_id;
    $count_params[':bread_id'] = $bread_id;
}
if (!empty($sale_type)) {
    $base_query .= " AND (b.sale_type = :sale_type OR b.sale_type = 'both')";
    $params[':sale_type'] = $sale_type;
    $count_params[':sale_type'] = $sale_type;
}
if ($status != -1) { // -1 = tümü (filtresiz)
    $base_query .= " AND b.status = :status";
    $params[':status'] = $status;
    $count_params[':status'] = $status;
}
if (!empty($search)) {
    $search_like = "%" . $search . "%";
    $base_query .= " AND (b.name LIKE :search OR b.description LIKE :search)";
    $params[':search'] = $search_like;
    $count_params[':search'] = $search_like;
}
if ($min_quantity > 0) {
    $base_query .= " AND ((i.piece_quantity >= :min_quantity) OR (i.box_quantity >= :min_quantity))";
    $params[':min_quantity'] = $min_quantity;
    $count_params[':min_quantity'] = $min_quantity;
}

// --- Toplam Kayıt Sayısını Hesapla ---
$total_count = 0;
try {
    $count_query = "SELECT COUNT(DISTINCT b.id) " . $base_query;
    $stmt_count = $pdo->prepare($count_query);
    $stmt_count->execute($count_params);
    $total_count = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Inventory Count Error: " . $e->getMessage());
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['error_message'] = "Toplam stok sayısı alınırken hata oluştu.";
    }
}

$total_pages = ceil($total_count / $per_page);

// --- Stok Bilgilerini Getir (Limit ve Sıralama ile) ---
$inventory_items = [];
try {
    // Seçilecek sütunları belirle
    $query = "SELECT b.id, b.name, b.description, b.price, b.status, b.sale_type, 
                    b.box_capacity, b.is_packaged, b.package_weight, b.image,
                    i.piece_quantity, i.box_quantity, i.created_at, i.updated_at "
           . $base_query
           . " ORDER BY b.name ASC LIMIT :offset, :per_page"; // Ekmek adına göre sırala

    $stmt = $pdo->prepare($query);

    // LIMIT/OFFSET parametrelerini ekle (PDO::PARAM_INT ile)
    $params[':offset'] = $offset;
    $params[':per_page'] = $per_page;
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

    // Diğer filtre parametrelerini bind et
    foreach ($count_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Inventory Fetch Error: " . $e->getMessage());
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['error_message'] = "Stok bilgileri yüklenirken bir veritabanı hatası oluştu.";
    }
    $inventory_items = []; // Hata durumunda boş dizi
}

// --- Toplu İşlem (Bulk Action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['bread_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $bread_ids = $_POST['bread_ids']; // Bu zaten bir dizi olmalı
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF Doğrulama
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız oldu.";
        redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php?' . http_build_query($_GET));
        exit;
    }

    $success_count = 0;
    $error_count = 0;
    $error_messages = [];

    // Kullanıcı ID'si (hareketleri kim yaptı)
    $admin_user_id = $_SESSION['user_id'] ?? 0;

    foreach ($bread_ids as $bread_id) {
        if (!is_numeric($bread_id)) continue; // Geçersiz ID'leri atla
        $bread_id = (int)$bread_id;

        if ($bulk_action === 'add_stock' && isset($_POST['piece_quantity'], $_POST['box_quantity'])) {
            $piece_quantity = (int)$_POST['piece_quantity'];
            $box_quantity = (int)$_POST['box_quantity'];
            
            if ($piece_quantity <= 0 && $box_quantity <= 0) {
                $error_count++;
                $error_messages[] = "ID $bread_id: En az bir miktar girmelisiniz.";
                continue;
            }

            try {
                // Stok hareketi ve stok güncellemesi yap
                $pdo->beginTransaction();
                
                // Hareket notu oluştur
                $bread_info = getBreadInfo($bread_id, $pdo);
                $bread_name = $bread_info['name'] ?? "Ekmek #$bread_id";
                $note = "Toplu stok ekleme: $bread_name";
                if ($piece_quantity > 0) $note .= " - $piece_quantity adet";
                if ($box_quantity > 0) $note .= " - $box_quantity kasa";
                
                // Stok hareketi ekle
                $stmt_movement = $pdo->prepare("
                    INSERT INTO inventory_movements 
                    (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
                    VALUES (?, 'in', ?, ?, NULL, ?, ?, NOW())
                ");
                $stmt_movement->execute([$bread_id, $piece_quantity, $box_quantity, $note, $admin_user_id]);
                
                // Mevcut stok kontrolü
                $stmt_inventory = $pdo->prepare("SELECT * FROM inventory WHERE bread_id = ?");
                $stmt_inventory->execute([$bread_id]);
                $inventory = $stmt_inventory->fetch(PDO::FETCH_ASSOC);
                
                if ($inventory) {
                    // Stok güncelle
                    $stmt_update = $pdo->prepare("
                        UPDATE inventory 
                        SET piece_quantity = piece_quantity + ?, 
                            box_quantity = box_quantity + ?,
                            updated_at = NOW()
                        WHERE bread_id = ?
                    ");
                    $stmt_update->execute([$piece_quantity, $box_quantity, $bread_id]);
                } else {
                    // Yeni stok kaydı oluştur
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO inventory 
                        (bread_id, piece_quantity, box_quantity, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                    ");
                    $stmt_insert->execute([$bread_id, $piece_quantity, $box_quantity]);
                }
                
                $pdo->commit();
                $success_count++;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Bulk Stock Add Error ID $bread_id: " . $e->getMessage());
                $error_count++;
                $error_messages[] = "ID $bread_id: Veritabanı hatası (" . $e->getCode() . ")";
            }
        }
        elseif ($bulk_action === 'set_status' && isset($_POST['new_status'])) {
            $new_status = (int)$_POST['new_status'];
            if ($new_status !== 0 && $new_status !== 1) {
                $error_count++;
                $error_messages[] = "ID $bread_id: Geçersiz durum değeri.";
                continue;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE bread_types SET status = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$new_status, $bread_id]);
                
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $error_messages[] = "ID $bread_id: Durum güncellenemedi.";
                }
            } catch (PDOException $e) {
                error_log("Bulk Status Update Error ID $bread_id: " . $e->getMessage());
                $error_count++;
                $error_messages[] = "ID $bread_id: Veritabanı hatası (" . $e->getCode() . ")";
            }
        }
    }

    // İşlem sonrası mesajlar
    if ($success_count > 0) {
        $_SESSION['success_message'] = "$success_count ekmek türü için işlem başarıyla tamamlandı.";
    }
    if ($error_count > 0) {
        // Hata mesajlarını birleştir
        $error_details = implode("\n", array_map('htmlspecialchars', $error_messages));
        error_log("Bulk action ($bulk_action) errors: \n" . implode("\n", $error_messages));
        $_SESSION['error_message'] = "$error_count ekmek türü işlenirken hata oluştu. Detaylar için sistem loglarına bakınız.";
    }

    // Filtreleri koruyarak sayfayı yenile
    redirect(rtrim(BASE_URL, '/') . '/admin/inventory/index.php?' . http_build_query($_GET));
    exit;
}

// --- Ekmek Tiplerini Getir (Filtre dropdown için) ---
$bread_types = [];
try {
    $stmt_bread = $pdo->query("SELECT id, name FROM bread_types ORDER BY name ASC");
    $bread_types = $stmt_bread->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Bread Types Fetch for Filter Error: " . $e->getMessage());
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800">Stok Yönetimi</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class="fas fa-filter"></i> Filtrele
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/add.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Stok Ekle
            </a>
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle shadow-sm" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download fa-sm text-white-50 me-1"></i> Dışa Aktar
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                    <li>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/inventory/export.php?<?php echo http_build_query($_GET); ?>">
                            <i class="fas fa-file-excel text-success me-2"></i> Excel / CSV İndir
                        </a>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" id="printInventoryBtn">
                            <i class="fas fa-print text-secondary me-2"></i> Yazdır
                        </button>
                    </li>
                </ul>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/movements.php" class="btn btn-info shadow-sm">
                <i class="fas fa-exchange-alt fa-sm text-white-50 me-1"></i> Hareketler
            </a>
        </div>
    </div>

    <!-- Filtreleme Alanı -->
    <div class="card shadow mb-4 collapse d-md-block" id="filterCollapse">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-1"></i> Filtrele</h6>
        </div>
        <div class="card-body">
            <form action="" method="get" id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="bread_id" class="form-label small fw-bold">Ekmek Türü</label>
                    <select name="bread_id" id="bread_id" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($bread_types as $bread): ?>
                            <option value="<?php echo $bread['id']; ?>" <?php echo ($bread_id == $bread['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bread['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sale_type" class="form-label small fw-bold">Satış Türü</label>
                    <select name="sale_type" id="sale_type" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="piece" <?php echo ($sale_type === 'piece') ? 'selected' : ''; ?>>Adet</option>
                        <option value="box" <?php echo ($sale_type === 'box') ? 'selected' : ''; ?>>Kasa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label small fw-bold">Durum</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="-1">Tümü</option>
                        <option value="1" <?php echo ($status === 1) ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo ($status === 0) ? 'selected' : ''; ?>>Pasif</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="min_quantity" class="form-label small fw-bold">Min. Stok</label>
                    <input type="number" name="min_quantity" id="min_quantity" class="form-control form-control-sm" placeholder="Örn: 10" value="<?php echo htmlspecialchars($min_quantity); ?>">
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label small fw-bold">Ara</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" id="search" class="form-control" placeholder="Ekmek adı..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-secondary" title="Sıfırla"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Stok Listesi</h6>
            <span class="badge bg-primary"><?php echo $total_count; ?> Kayıt</span>
        </div>
        <div class="card-body">
            
            <?php if (!empty($inventory_items)): ?>
                <form action="" method="post" id="bulkActionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Toplu İşlem Toolbar -->
                    <div class="bg-light p-2 rounded mb-3 border d-flex flex-wrap align-items-center gap-2">
                        <div class="form-check ms-2">
                            <input class="form-check-input" type="checkbox" id="checkAll" title="Tümünü Seç">
                        </div>
                        <select name="bulk_action" class="form-select form-select-sm" style="width: auto; min-width: 150px;">
                            <option value="">Toplu İşlem Seç...</option>
                            <option value="add_stock">Stok Ekle</option>
                            <option value="set_status">Durumu Değiştir</option>
                        </select>

                        <div id="stockAddContainer" style="display: none;" class="d-flex gap-2">
                            <input type="number" name="piece_quantity" class="form-control form-control-sm" placeholder="Adet" min="0" style="width: 80px;">
                            <input type="number" name="box_quantity" class="form-control form-control-sm" placeholder="Kasa" min="0" style="width: 80px;">
                        </div>

                        <div id="statusSelectContainer" style="display: none;">
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="1">Aktif</option>
                                <option value="0">Pasif</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm" id="applyBulkAction" disabled>
                            <i class="fas fa-check me-1"></i> Uygula
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle" id="inventoryTable" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30px;"></th>
                                    <th style="width: 60px;">Görsel</th>
                                    <th>Ürün Bilgisi</th>
                                    <th>Satış Türü</th>
                                    <th class="text-end">Fiyat</th>
                                    <th style="width: 15%;">Adet Stok</th>
                                    <th style="width: 15%;">Kasa Stok</th>
                                    <th class="text-center">Durum</th>
                                    <th class="text-end">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input bread-checkbox" type="checkbox" name="bread_ids[]" value="<?php echo $item['id']; ?>" id="bread_<?php echo $item['id']; ?>">
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($item['image']) && file_exists(ROOT_PATH . '/uploads/' . $item['image'])): ?>
                                                <img src="<?php echo BASE_URL; ?>/uploads/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="rounded img-thumbnail p-0 border" style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 50px; height: 50px;">
                                                    <i class="fas fa-bread-slice"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <?php if ($item['is_packaged']): ?>
                                                <div class="small text-muted"><i class="fas fa-box-open me-1"></i><?php echo $item['package_weight']; ?>g</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                switch ($item['sale_type']) {
                                                    case 'piece': echo '<span class="badge bg-secondary">Adet</span>'; break;
                                                    case 'box': echo '<span class="badge bg-info text-dark">Kasa</span>'; break;
                                                    case 'both': echo '<span class="badge bg-secondary">Adet</span> <span class="badge bg-info text-dark">Kasa</span>'; break;
                                                }
                                            ?>
                                        </td>
                                        <td class="text-end fw-bold text-primary"><?php echo formatMoney($item['price']); ?></td>
                                        <td>
                                            <?php 
                                                $piece_qty = $item['piece_quantity'] ?? 0;
                                                $piece_percent = min(100, ($piece_qty / 100) * 100); // Varsayılan max 100 kabul edelim görsel için
                                                $piece_color = $piece_qty < 10 ? 'bg-danger' : ($piece_qty < 50 ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold me-2" style="width: 30px;"><?php echo $piece_qty; ?></span>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?php echo $piece_color; ?>" role="progressbar" style="width: <?php echo $piece_percent; ?>%" aria-valuenow="<?php echo $piece_qty; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                                $box_qty = $item['box_quantity'] ?? 0;
                                                $box_percent = min(100, ($box_qty / 50) * 100); // Varsayılan max 50 kabul edelim
                                                $box_color = $box_qty < 5 ? 'bg-danger' : ($box_qty < 20 ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold me-2" style="width: 30px;"><?php echo $box_qty; ?></span>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?php echo $box_color; ?>" role="progressbar" style="width: <?php echo $box_percent; ?>%" aria-valuenow="<?php echo $box_qty; ?>" aria-valuemin="0" aria-valuemax="50"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 1): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                                <div class="btn-group">
                                                    <!-- Görüntüle (Ayrı Buton) -->
                                                    <a href="<?php echo BASE_URL; ?>/admin/inventory/view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-info" title="Görüntüle" data-bs-toggle="tooltip">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Diğer İşlemler (Dropdown) -->
                                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span class="visually-hidden">İşlemler</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><h6 class="dropdown-header">İşlemler</h6></li>
                                                        
                                                        <!-- Hızlı Stok Ekle -->
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/inventory/add.php?bread_id=<?php echo $item['id']; ?>">
                                                                <i class="fas fa-plus text-success me-2"></i>Hızlı Stok Ekle
                                                            </a>
                                                        </li>

                                                        <!-- Düzenle -->
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/inventory/edit.php?id=<?php echo $item['id']; ?>">
                                                                <i class="fas fa-edit text-primary me-2"></i>Düzenle
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                        ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $page - 1; ?>">Önceki</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $page + 1; ?>">Sonraki</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-2x text-gray-300 mb-3"></i>
                    <p class="text-gray-500 mb-0">Kayıtlı stok bulunamadı.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Checkbox ve Toplu İşlem Yönetimi ---
    const checkAll = document.getElementById('checkAll');
    const breadCheckboxes = document.querySelectorAll('.bread-checkbox');
    const applyBulkActionBtn = document.getElementById('applyBulkAction');
    const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
    const stockAddContainer = document.getElementById('stockAddContainer');
    const statusSelectContainer = document.getElementById('statusSelectContainer');
    const bulkActionForm = document.getElementById('bulkActionForm');

    // Tümünü Seç/Kaldır
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            breadCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
            updateApplyButtonState();
        });
    }

    // Tekil Checkbox Değişikliği
    breadCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            checkAll.checked = Array.from(breadCheckboxes).every(cb => cb.checked);
            updateApplyButtonState();
        });
    });

    // Toplu İşlem Seçimi Değişikliği
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            stockAddContainer.style.display = (this.value === 'add_stock') ? 'block' : 'none';
            statusSelectContainer.style.display = (this.value === 'set_status') ? 'block' : 'none';
            updateApplyButtonState();
        });
        // Sayfa yüklendiğinde de kontrol et
        stockAddContainer.style.display = (bulkActionSelect.value === 'add_stock') ? 'block' : 'none';
        statusSelectContainer.style.display = (bulkActionSelect.value === 'set_status') ? 'block' : 'none';
    }

    // Uygula Butonu Durumunu Güncelle
    function updateApplyButtonState() {
        const anyChecked = Array.from(breadCheckboxes).some(cb => cb.checked);
        const actionSelected = bulkActionSelect && bulkActionSelect.value !== '';
        if (applyBulkActionBtn) {
            applyBulkActionBtn.disabled = !(anyChecked && actionSelected);
        }
    }
    // İlk yüklemede buton durumunu ayarla
    updateApplyButtonState();

    // Form Gönderme Onayı (Özellikle Silme için)
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            const action = bulkActionSelect.value;
            const anyChecked = Array.from(breadCheckboxes).some(cb => cb.checked);

            if (!anyChecked) {
                e.preventDefault();
                alert('Lütfen en az bir ekmek türü seçin.');
                return;
            }
            if (!action) {
                e.preventDefault();
                alert('Lütfen bir toplu işlem seçin.');
                return;
            }
            
            // Stok ekleme seçildi ama miktar girilmedi kontrolü
            if (action === 'add_stock') {
                const pieceQty = parseInt(document.querySelector('input[name="piece_quantity"]').value) || 0;
                const boxQty = parseInt(document.querySelector('input[name="box_quantity"]').value) || 0;
                
                if (pieceQty <= 0 && boxQty <= 0) {
                    e.preventDefault();
                    alert('Lütfen ekleme için en az bir miktar girin (adet veya kasa).');
                    return;
                }
                
                if (!confirm('Seçili tüm ekmek türlerine aynı miktarda stok eklemek istediğinize emin misiniz?')) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Durum değiştirme onayı
            if (action === 'set_status') {
                const newStatus = document.querySelector('select[name="new_status"]').value;
                const statusText = newStatus == 1 ? 'aktif' : 'pasif';
                
                if (!confirm(`Seçili tüm ekmek türlerinin durumunu '${statusText}' olarak değiştirmek istediğinize emin misiniz?`)) {
                    e.preventDefault();
                }
            }
        });
    }

    // --- Yazdır Butonu ---
    const printBtn = document.getElementById('printInventoryBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            // Yazdırmadan önce filtre/başlık gibi gereksiz alanları gizle, sonra geri aç
            const elementsToHide = '.filter-form, .card-header .btn-group, #bulkActionForm .row, .pagination, #adminSidebar, header.bg-dark, footer.bg-dark';
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = 'none');
            window.print();
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = '');
        });
    }

    // --- DataTables Başlatma (Eğer datatable sınıfı kullanılıyorsa) ---
    if (typeof $ !== 'undefined' && $.fn.dataTable && $('#inventoryTable').length > 0 && !$.fn.dataTable.isDataTable('#inventoryTable')) {
        $('#inventoryTable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json" },
            "responsive": true,
            "order": [[2, 'asc']], // Ekmek adına göre sırala (index 2)
            "columnDefs": [
                { "orderable": false, "targets": [0, 1, 9] } // Checkbox, Görsel ve İşlemler sıralanamaz
            ],
            "paging": false,    // PHP pagination kullanılıyor
            "info": false,      // PHP pagination kullanılıyor
            "searching": false, // PHP filtreleme kullanılıyor
            "autoWidth": false  // Otomatik genişliği devre dışı bırak
        });
    }
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>