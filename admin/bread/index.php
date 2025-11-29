<?php
/**
 * Ekmek Çeşitleri Yönetimi Sayfası
 * (Tablo yapısı yeniden düzenlendi)
 */

// --- init.php Dahil Etme ---
require_once '../../init.php'; // init.php'nin ROOT_PATH ve BASE_URL tanımladığını varsayıyoruz

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Page Title ---
$page_title = 'Ekmek Çeşitleri Yönetimi';

// --- Actions (Delete, Toggle) ---
// Silme ve Durum Değiştirme PHP kodları öncekiyle aynı kalacak...
// --- Ekmek Çeşidi Silme (Pasif Yapma) İşlemi ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $bread_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$bread_id_to_delete) {
        $_SESSION['error_message'] = 'Geçersiz Ekmek ID.';
    } else {
        try {
            // 1. Siparişlerde kullanılıyor mu kontrol et (Doğru sütun adı 'bread_id' ile)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE bread_id = ?"); // DÜZELTİLDİ!
            $stmt_check->execute([$bread_id_to_delete]);
            $result = $stmt_check->fetch();

            if ($result && $result['count'] > 0) {
                $_SESSION['error_message'] = 'Bu ekmek çeşidi (ID: ' . $bread_id_to_delete . ') siparişlerde kullanıldığı için silinemez!';
            } else {
                // 2. Soft delete yap (status=0). Sadece zaten aktifse (status != 0) güncelle.
                $stmt_update = $pdo->prepare("UPDATE bread_types SET status = 0 WHERE id = ? AND status != 0");
                $stmt_update->execute([$bread_id_to_delete]);

                if ($stmt_update->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_delete . ') başarıyla pasif hale getirildi.';
                } else {
                    $stmt_status = $pdo->prepare("SELECT status FROM bread_types WHERE id = ?");
                    $stmt_status->execute([$bread_id_to_delete]);
                    $current_status = $stmt_status->fetchColumn();
                    if ($current_status === 0) {
                        $_SESSION['error_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_delete . ') zaten pasif durumda.';
                    } elseif ($current_status === false) {
                         $_SESSION['error_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_delete . ') bulunamadı.';
                    } else {
                        $_SESSION['error_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_delete . ') pasif hale getirilirken bilinmeyen bir sorun oluştu (rowCount=0).';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Bread Delete (Set Inactive) Error for ID " . $bread_id_to_delete . ": " . $e->getMessage());
            $_SESSION['error_message'] = 'İşlem sırasında bir veritabanı hatası oluştu.';
        }
    }
    redirect(rtrim(BASE_URL, '/') . '/admin/bread/index.php');
    exit;
}

// --- Ekmek Çeşidi Durum Değiştirme İşlemi ---
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $bread_id_to_toggle = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

     if (!$bread_id_to_toggle) {
        $_SESSION['error_message'] = 'Geçersiz Ekmek ID.';
    } else {
        try {
            $stmt_fetch = $pdo->prepare("SELECT status FROM bread_types WHERE id = ?");
            $stmt_fetch->execute([$bread_id_to_toggle]);
            $current_status = $stmt_fetch->fetchColumn();

            if ($current_status !== false) {
                $new_status = ($current_status == 1) ? 0 : 1;
                $stmt_update = $pdo->prepare("UPDATE bread_types SET status = ? WHERE id = ?");
                $stmt_update->execute([$new_status, $bread_id_to_toggle]);

                if ($stmt_update->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_toggle . ') durumu başarıyla güncellendi (' . ($new_status ? 'Aktif' : 'Pasif') . ').';
                } else {
                    $_SESSION['error_message'] = 'Ekmek çeşidi (ID: ' . $bread_id_to_toggle . ') durumu güncellenirken bir hata oluştu (rowCount=0).';
                }
            } else {
                $_SESSION['error_message'] = 'Ekmek çeşidi bulunamadı (ID: ' . $bread_id_to_toggle . ').';
            }
        } catch (PDOException $e) {
            error_log("Bread Toggle Status Error for ID " . $bread_id_to_toggle . ": " . $e->getMessage());
             $_SESSION['error_message'] = 'Durum güncellenirken bir veritabanı hatası oluştu.';
        }
    }
    redirect(rtrim(BASE_URL, '/') . '/admin/bread/index.php');
    exit;
}

// --- Veri Çekme ---
$bread_types = [];
$list_error = null;
try {
    $stmt = $pdo->query("SELECT * FROM bread_types ORDER BY status DESC, name ASC");
    $bread_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     error_log("Bread Fetch Error (Index): " . $e->getMessage());
     $list_error = "Ekmek çeşitleri listelenirken bir hata oluştu.";
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php'; // header.php'nin CSS/JS içerdiğini varsayıyoruz
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-bread-slice me-2"></i>Ekmek Çeşitleri Yönetimi
        </h6>
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/bread/add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Yeni Ekmek Çeşidi Ekle
        </a>
    </div>
    <div class="card-body">
        <?php
        // Session mesajları header.php'ye taşındıysa bu bölüm kaldırılabilir.
        // Eğer hala buradaysa:
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['error_message']);
        }
        if (isset($list_error)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($list_error) . '</div>';
        }
        ?>

    <!-- Bread Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Ürün Listesi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable" id="breadTypesTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Görsel</th>
                            <th>Ekmek Adı</th>
                            <th>Birim Fiyat</th>
                            <th>Satış Tipi</th>
                            <th>Paket/Kasa</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bread_types as $bread): ?>
                        <tr>
                            
                            <td>
                                <?php if (!empty($bread['image'])): ?>
                                    <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($bread['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($bread['name']); ?>" 
                                         class="img-thumbnail rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center rounded border" style="width: 60px; height: 60px;">
                                        <i class="fas fa-bread-slice text-muted fa-lg"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($bread['name']); ?></div>
                                <?php if (!empty($bread['description'])): ?>
                                    <small class="text-muted"><?php echo mb_substr(htmlspecialchars($bread['description']), 0, 50) . '...'; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-bold text-primary"><?php echo number_format($bread['price'], 2, ',', '.'); ?> ₺</span>
                            </td>
                            <td>
                                <?php if ($bread['sale_type'] === 'box'): ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-box me-1"></i> Kasa</span>
                                <?php elseif ($bread['sale_type'] === 'unit'): ?>
                                    <span class="badge bg-secondary"><i class="fas fa-shopping-bag me-1"></i> Adet</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="fas fa-sync-alt me-1"></i> Her İkisi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($bread['sale_type'] !== 'unit'): ?>
                                    <div class="small"><i class="fas fa-box-open text-muted me-1"></i> <?php echo $bread['box_capacity']; ?> Adet/Kasa</div>
                                <?php endif; ?>
                                <?php if ($bread['is_packaged']): ?>
                                    <div class="small"><i class="fas fa-weight-hanging text-muted me-1"></i> <?php echo $bread['package_weight']; ?> gr</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="index.php?action=toggle&id=<?php echo $bread['id']; ?>" 
                                   class="text-decoration-none"
                                   onclick="return confirm('Ekmek durumunu <?php echo $bread['status'] == 1 ? 'pasif' : 'aktif'; ?> yapmak istediğinize emin misiniz?');">
                                    <?php if ($bread['status'] == 1): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i> Pasif</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <!-- Görüntüle (Ayrı Buton) -->
                                    <a href="view.php?id=<?php echo $bread['id']; ?>" class="btn btn-sm btn-outline-info" title="Görüntüle" data-bs-toggle="tooltip">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- Diğer İşlemler (Dropdown) -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">İşlemler</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><h6 class="dropdown-header">İşlemler</h6></li>
                                        
                                        <!-- Düzenle -->
                                        <li>
                                            <a class="dropdown-item" href="edit.php?id=<?php echo $bread['id']; ?>">
                                                <i class="fas fa-edit text-primary me-2"></i>Düzenle
                                            </a>
                                        </li>

                                        <!-- Durum Değiştir -->
                                        <li>
                                            <a class="dropdown-item" href="index.php?action=toggle&id=<?php echo $bread['id']; ?>">
                                                <i class="fas <?php echo $bread['status'] == 1 ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary'; ?> me-2"></i>
                                                <?php echo $bread['status'] == 1 ? 'Pasife Al' : 'Aktifleştir'; ?>
                                            </a>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <!-- Sil -->
                                        <li>
                                            <a class="dropdown-item text-danger" href="index.php?action=delete&id=<?php echo $bread['id']; ?>" onclick="return confirm('Bu ekmek çeşidini silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash me-2"></i>Sil
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
        </div>
    </div>

</div>

<?php
// --- Footer'ı Dahil Et ---
// *** GÖRÜNÜM SORUNU İÇİN KONTROL: footer.php dosyasının jQuery, Bootstrap JS ve DataTables JS dosyalarını doğru şekilde içerdiğinden ve DataTables'ı başlattığından (örn. $('#breadTypesTable').DataTable();) emin olun. ***
include_once ROOT_PATH . '/admin/footer.php';
?>
