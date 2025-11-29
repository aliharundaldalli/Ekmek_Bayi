<?php
/**
 * Admin - Destek Kategorileri Yönetimi
 */
date_default_timezone_set('Europe/Istanbul');
// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Destek Kategorileri';
$current_page = 'support';

// --- Kategori ID Kontrolü (Düzenleme İşlemi İçin) ---
$category_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- Kategori Formu İşlemleri ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        header("Location: " . BASE_URL . "/admin/support/categories.php" . ($action == 'edit' ? "?action=edit&id=$category_id" : "?action=add"));
        exit;
    }

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validasyon
    $errors = [];
    if (empty($name)) {
        $errors[] = "Kategori adı boş olamaz.";
    } elseif (strlen($name) > 50) {
        $errors[] = "Kategori adı 50 karakterden uzun olamaz.";
    }

    // Hata yoksa işleme devam et
    if (empty($errors)) {
        try {
            if ($action == 'edit' && $category_id > 0) {
                // Kategori güncelleme
                $stmt = $pdo->prepare("
                    UPDATE support_categories 
                    SET name = ?, description = ?, is_active = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $is_active, $category_id]);
                
                $_SESSION['success'] = "Kategori başarıyla güncellendi.";
            } else {
                // Yeni kategori ekleme
                $stmt = $pdo->prepare("
                    INSERT INTO support_categories 
                    (name, description, is_active, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $description, $is_active]);
                
                $_SESSION['success'] = "Yeni kategori başarıyla eklendi.";
            }
            
            // Yönlendirme
            header("Location: " . BASE_URL . "/admin/support/categories.php");
            exit;
        } catch (PDOException $e) {
            error_log("Category save error: " . $e->getMessage());
            $_SESSION['error'] = "Kategori kaydedilirken bir hata oluştu.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// --- Kategori Silme İşlemi ---
if ($action == 'delete' && $category_id > 0) {
    // Kategori silinmeden önce kullanımda olup olmadığını kontrol et
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE category = (SELECT name FROM support_categories WHERE id = ?)");
        $stmt->execute([$category_id]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count > 0) {
            $_SESSION['error'] = "Bu kategori $usage_count adet destek talebinde kullanılıyor ve silinemez.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM support_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            $_SESSION['success'] = "Kategori başarıyla silindi.";
        }
        
        header("Location: " . BASE_URL . "/admin/support/categories.php");
        exit;
    } catch (PDOException $e) {
        error_log("Category delete error: " . $e->getMessage());
        $_SESSION['error'] = "Kategori silinirken bir hata oluştu.";
    }
}

// --- Düzenlenecek Kategoriyi Getir ---
$edit_category = null;
if ($action == 'edit' && $category_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM support_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $edit_category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_category) {
            $_SESSION['error'] = "Kategori bulunamadı.";
            header("Location: " . BASE_URL . "/admin/support/categories.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Category fetch error: " . $e->getMessage());
        $_SESSION['error'] = "Kategori bilgileri getirilirken bir hata oluştu.";
    }
}

// --- Tüm Kategorileri Getir ---
try {
    $stmt = $pdo->query("
        SELECT 
            c.*, 
            (SELECT COUNT(*) FROM support_tickets WHERE category = c.name) AS ticket_count 
        FROM 
            support_categories c 
        ORDER BY 
            c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Categories query error: " . $e->getMessage());
    $categories = [];
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php if ($action == 'edit'): ?>
            <i class="fas fa-edit me-2"></i> Kategori Düzenle
        <?php elseif ($action == 'add'): ?>
            <i class="fas fa-plus-circle me-2"></i> Yeni Kategori Ekle
        <?php else: ?>
            <i class="fas fa-tags me-2"></i> Destek Kategorileri
        <?php endif; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($action != 'add' && $action != 'edit'): ?>
            <a href="<?php echo BASE_URL; ?>/admin/support/categories.php?action=add" class="btn btn-sm btn-primary">
                <i class="fas fa-plus-circle"></i> Yeni Kategori
            </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-sm btn-outline-secondary ms-2">
            <i class="fas fa-arrow-left"></i> Destek Taleplerine Dön
        </a>
    </div>
</div>

<?php
// Hata ve başarı mesajları
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}
?>

<?php if ($action == 'add' || $action == 'edit'): ?>
    <!-- Kategori Ekleme/Düzenleme Formu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo $action == 'edit' ? 'Kategori Düzenle' : 'Yeni Kategori Ekle'; ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo BASE_URL; ?>/admin/support/categories.php?action=<?php echo $action; ?><?php echo $category_id ? '&id=' . $category_id : ''; ?>">
                <?php $csrf_token = generateCSRFToken(); ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo isset($edit_category) ? htmlspecialchars($edit_category['name']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($edit_category) ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                           <?php echo (!isset($edit_category) || $edit_category['is_active'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">
                        Aktif
                    </label>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="<?php echo BASE_URL; ?>/admin/support/categories.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> İptal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Kategoriler Tablosu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Kategori Listesi</h6>
        </div>
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> Henüz eklenmiş kategori bulunmuyor.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Kategori Adı</th>
                                <th>Açıklama</th>
                                <th>Durum</th>
                                <th>Talep Sayısı</th>
                                <th>Oluşturulma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td>
                                        <?php if ($category['is_active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($category['ticket_count'] > 0): ?>
                                            <a href="<?php echo BASE_URL; ?>/admin/support/index.php?category_id=<?php echo $category['id']; ?>">
                                                <?php echo $category['ticket_count']; ?> talep
                                            </a>
                                        <?php else: ?>
                                            0 talep
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>/admin/support/categories.php?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($category['ticket_count'] == 0): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/support/categories.php?action=delete&id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-danger" 
                                                   onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-danger" disabled title="Bu kategori kullanımda olduğu için silinemez">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>