<?php
/**
 * Admin Paneli - Sipariş Listesi (Modernize Edilmiş)
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/order_functions.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Sipariş Yönetimi';
$current_page = 'orders';

// --- Filtreleme Değişkenleri ---
$status = $_GET['status'] ?? '';
$user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// --- Sorgu Oluşturma (Filtrelerle) ---
$base_query = "FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];
$count_params = [];

if (!empty($status)) {
    $base_query .= " AND o.status = :status";
    $params[':status'] = $status;
}
if (!empty($user_id)) {
    $base_query .= " AND o.user_id = :user_id";
    $params[':user_id'] = $user_id;
}
if (!empty($date_from)) {
    $base_query .= " AND DATE(o.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $base_query .= " AND DATE(o.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}
if (!empty($search)) {
    $search_like = "%" . $search . "%";
    $base_query .= " AND (o.order_number LIKE :search OR u.bakery_name LIKE :search OR o.note LIKE :search)";
    $params[':search'] = $search_like;
}

$count_params = $params;

// --- Toplam Kayıt Sayısını Hesapla ---
$total_count = 0;
try {
    $count_query = "SELECT COUNT(DISTINCT o.id) " . $base_query;
    $stmt_count = $pdo->prepare($count_query);
    $stmt_count->execute($count_params);
    $total_count = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Order Count Error: " . $e->getMessage());
}

$total_pages = ceil($total_count / $per_page);

// --- Siparişleri Getir ---
$orders = [];
try {
    $query = "SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
                     u.first_name, u.last_name, u.bakery_name "
           . $base_query
           . " ORDER BY o.created_at DESC LIMIT :offset, :per_page";

    $stmt = $pdo->prepare($query);
    $params[':offset'] = $offset;
    $params[':per_page'] = $per_page;
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

    foreach ($count_params as $key => $value) {
         $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Order Fetch Error: " . $e->getMessage());
    $list_error = "Siparişler listelenirken bir hata oluştu.";
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Sipariş Yönetimi</h1>
        <!-- Filtreleme Butonu (Mobil için) -->
        <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
            <i class="fas fa-filter"></i> Filtrele
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($list_error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $list_error; ?>
        </div>
    <?php endif; ?>

    <!-- Filtreleme Alanı -->
    <div class="card shadow mb-4 collapse d-md-block" id="filterCollapse">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-1"></i> Filtrele</h6>
        </div>
        <div class="card-body">
                    <form action="" method="get">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 row-cols-xl-auto g-3 align-items-end">
                            <div class="col">
                                <label for="status" class="form-label small fw-bold text-muted">Durum</label>
                                <select name="status" id="status" class="form-select form-select-sm">
                                    <option value="">Tümü</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                                    <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="date_from" class="form-label small fw-bold text-muted">Başlangıç</label>
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col">
                                <label for="date_to" class="form-label small fw-bold text-muted">Bitiş</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col">
                                <label for="search" class="form-label small fw-bold text-muted">Arama</label>
                                <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Sipariş No, Bayi Adı..." value="<?php echo $search; ?>">
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-filter me-1"></i> Filtrele
                                </button>
                            </div>
                            <?php if (!empty($status) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                            <div class="col">
                                <a href="index.php" class="btn btn-secondary btn-sm w-100">
                                    <i class="fas fa-times me-1"></i> Temizle
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
        </div>
    </div>

    <!-- Orders Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Sipariş Listesi</h6>
            <span class="badge bg-primary"><?php echo $total_count; ?> Kayıt</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="ordersTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>Sipariş No</th>
                            <th>Büfe / Kullanıcı</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($order['bakery_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?php echo number_format($order['total_amount'], 2, ',', '.'); ?> ₺</span>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    $status_icon = '';
                                    switch($order['status']) {
                                        case 'pending': $status_class = 'bg-warning text-dark'; $status_icon = 'fa-clock'; break;
                                        case 'approved': $status_class = 'bg-info text-dark'; $status_icon = 'fa-check'; break;
                                        case 'preparing': $status_class = 'bg-primary'; $status_icon = 'fa-spinner fa-spin'; break;
                                        case 'on_way': $status_class = 'bg-info'; $status_icon = 'fa-truck'; break;
                                        case 'delivered': $status_class = 'bg-success'; $status_icon = 'fa-check-circle'; break;
                                        case 'cancelled': $status_class = 'bg-danger'; $status_icon = 'fa-times-circle'; break;
                                        default: $status_class = 'bg-secondary'; $status_icon = 'fa-question-circle';
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i> <?php echo getOrderStatusText($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-dark"><i class="far fa-calendar-alt me-1"></i> <?php echo date('d.m.Y', strtotime($order['created_at'])); ?></div>
                                    <div class="small text-muted"><i class="far fa-clock me-1"></i> <?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info" title="Görüntüle" data-bs-toggle="tooltip">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Hızlı Durum Güncelleme (Dropdown) -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Durum Değiştir</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><h6 class="dropdown-header">Durum Güncelle</h6></li>
                                            <li><a class="dropdown-item" href="update_status.php?id=<?php echo $order['id']; ?>&status=approved"><i class="fas fa-check text-info me-2"></i>Onayla</a></li>
                                            <li><a class="dropdown-item" href="update_status.php?id=<?php echo $order['id']; ?>&status=preparing"><i class="fas fa-spinner text-primary me-2"></i>Hazırlanıyor</a></li>
                                            <li><a class="dropdown-item" href="update_status.php?id=<?php echo $order['id']; ?>&status=on_way"><i class="fas fa-truck text-info me-2"></i>Yola Çıkar</a></li>
                                            <li><a class="dropdown-item" href="update_status.php?id=<?php echo $order['id']; ?>&status=delivered"><i class="fas fa-check-circle text-success me-2"></i>Teslim Edildi</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="update_status.php?id=<?php echo $order['id']; ?>&status=cancelled" onclick="return confirm('Siparişi iptal etmek istediğinize emin misiniz?');"><i class="fas fa-times-circle text-danger me-2"></i>İptal Et</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Kayıtlı sipariş bulunamadı.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&user_id=<?php echo $user_id; ?>&search=<?php echo $search; ?>">Önceki</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&user_id=<?php echo $user_id; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&user_id=<?php echo $user_id; ?>&search=<?php echo $search; ?>">Sonraki</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
include_once ROOT_PATH . '/admin/footer.php';
?>
    error_log("Order Fetch Error: " . $e->getMessage());
    if (session_status() == PHP_SESSION_ACTIVE) {
         $_SESSION['error_message'] = "Siparişler yüklenirken bir veritabanı hatası oluştu.";
    }
    $orders = []; // Hata durumunda boş dizi
}


// --- Toplu İşlem (Bulk Action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['order_ids'])) {
    // Bu bölüm önceki kodla aynı mantıkta çalışır, sadece mesajları ve yönlendirmeyi kontrol edin.
    // CSRF token, durum güncelleme, silme işlemleri...
    // ... (Önceki yanıttaki bulk action PHP kodu buraya eklenecek) ...
     $bulk_action = $_POST['bulk_action'];
     $order_ids = $_POST['order_ids']; // Bu zaten bir dizi olmalı
     $csrf_token = $_POST['csrf_token'] ?? '';

     // CSRF Doğrulama
     if (!validateCSRFToken($csrf_token)) { // validateCSRFToken fonksiyonu init.php veya includes içinde olmalı
         $_SESSION['error_message'] = "Güvenlik doğrulaması başarısız oldu.";
         redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php?' . http_build_query($_GET));
         exit;
     }

     $success_count = 0;
     $error_count = 0;
     $error_messages = [];

     foreach ($order_ids as $order_id) {
         if (!is_numeric($order_id)) continue; // Geçersiz ID'leri atla
         $order_id = (int)$order_id;

         if ($bulk_action === 'status_update' && !empty($_POST['new_status'])) {
             $new_status = $_POST['new_status'];
             // Durumun geçerli olup olmadığını kontrol et (opsiyonel ama önerilir)
             $valid_statuses = ['pending', 'processing', 'completed', 'cancelled']; // Veya diğer durumlarınız
             if (in_array($new_status, $valid_statuses)) {
                 $note = "Toplu durum güncellemesi: " . ucfirst($new_status);
                 // updateOrderStatus fonksiyonunun son argümanı admin ID'si olmalı
                 $admin_user_id = $_SESSION['user_id'] ?? 0; // Giriş yapan adminin ID'si
                 $result = updateOrderStatus($order_id, $new_status, $note, $admin_user_id, $pdo);
                 if ($result['success']) {
                     $success_count++;
                 } else {
                     $error_count++;
                     $error_messages[] = "ID $order_id: " . ($result['message'] ?? 'Bilinmeyen hata');
                 }
             } else {
                 $error_count++;
                 $error_messages[] = "ID $order_id: Geçersiz durum '$new_status'";
             }
         } elseif ($bulk_action === 'delete') {
             // Silme İşlemi (Transaction içinde)
             try {
                 $pdo->beginTransaction();
                 // İlişkili tabloları sil (Foreign key varsa önce onlar silinmeli)
                 $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
                 $pdo->prepare("DELETE FROM order_status_history WHERE order_id = ?")->execute([$order_id]);
                 $pdo->prepare("DELETE FROM invoices WHERE order_id = ?")->execute([$order_id]);
                 $pdo->prepare("DELETE FROM inventory_movements WHERE order_id = ?")->execute([$order_id]);
                 // Ana siparişi sil
                 $deleted = $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
                 $pdo->commit();
                 if ($deleted) {
                     $success_count++;
                 } else {
                      $error_count++; // Silinemeyen durum (örn: ID yoktu)
                      $error_messages[] = "ID $order_id: Silinemedi veya bulunamadı.";
                 }
             } catch (PDOException $e) {
                 $pdo->rollBack();
                 error_log("Bulk Order Delete Error ID $order_id: " . $e->getMessage());
                 $error_count++;
                 $error_messages[] = "ID $order_id: Veritabanı hatası (" . $e->getCode() . ")";
                  // Eğer foreign key hatası alıyorsanız, buraya log ekleyebilirsiniz.
                 if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
                     $error_messages[] = "ID $order_id: İlişkili veri (örn: stok) nedeniyle silinemedi.";
                 }
             }
         }
     }

     // İşlem sonrası mesajlar
     if ($success_count > 0) {
         $_SESSION['success_message'] = "$success_count sipariş başarıyla işlendi ($bulk_action).";
     }
     if ($error_count > 0) {
          // Hata mesajlarını birleştir
         $error_details = implode("\n", array_map('htmlspecialchars', $error_messages));
         // Pre tag'i session'da kullanmak güvenli olmayabilir, loglamak daha iyi.
         error_log("Bulk action ($bulk_action) errors: \n" . implode("\n", $error_messages));
         $_SESSION['error_message'] = "$error_count sipariş işlenirken hata oluştu ($bulk_action). Detaylar için sistem loglarına bakınız.";

     }

     // Filtreleri koruyarak sayfayı yenile
     redirect(rtrim(BASE_URL, '/') . '/admin/orders/index.php?' . http_build_query($_GET));
     exit;
}


// --- Kullanıcıları Getir (Filtre dropdown için) ---
$users = [];
try {
    // Sadece 'bakery' rolündeki aktif kullanıcıları listeleyelim (veya 'user' mıydı?)
    // Orijinal kodunuz 'user' demişti, ancak context 'bakery' gibi duruyor. 'bakery' varsayalım.
    $stmt_users = $pdo->query("SELECT id, bakery_name FROM users WHERE role = 'bakery' AND status = 1 ORDER BY bakery_name ASC");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users Fetch for Filter Error: " . $e->getMessage());
}

// --- Header'ı Dahil Et ---
// header.php'nin CSS/JS içerdiğini ve $page_title'ı kullandığını varsayıyoruz
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary me-3 mb-2 mb-md-0">
            <i class="fas fa-shopping-basket me-2"></i><?php echo htmlspecialchars($page_title); ?>
        </h6>
        <div class="btn-group btn-group-sm" role="group">
            <?php // Yeni Sipariş Ekle butonu (opsiyonel) ?>
            <a href="<?php echo BASE_URL; ?>/admin/orders/add.php" class="btn btn-primary" title="Yeni Sipariş Oluştur">
                 <i class="fas fa-plus me-1"></i> Yeni Ekle
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/orders/export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success" title="Filtrelenmiş Veriyi CSV Olarak İndir">
                <i class="fas fa-file-excel"></i> <span class="d-none d-sm-inline-block">CSV İndir</span>
            </a>
            <button type="button" class="btn btn-secondary" id="printOrdersBtn" title="Mevcut Listeyi Yazdır"> <?php // Info yerine secondary ?>
                <i class="fas fa-print"></i> <span class="d-none d-sm-inline-block">Yazdır</span>
            </button>
        </div>
    </div>

    <div class="card-body border-bottom bg-light py-2 filter-form"> <?php // Daha az padding, açık arkaplan ?>
        <form action="" method="get" id="filterForm">
            <div class="row g-2 align-items-end"> <?php // align-items-end ile butonlar hizalanır ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label for="status" class="form-label small mb-1">Durum</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <?php
                            $orderStatuses = ['pending' => 'Beklemede', 'processing' => 'İşleniyor', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal Edildi']; // Diğer durumlar...
                            foreach ($orderStatuses as $key => $text): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($status === $key) ? 'selected' : ''; ?>>
                                    <?php echo $text; ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label for="user_id" class="form-label small mb-1">Büfe</label>
                    <select name="user_id" id="user_id" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($user_id == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['bakery_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-4 col-lg-2">
                    <label for="date_from" class="form-label small mb-1">Başlangıç</label>
                    <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-4 col-lg-2">
                    <label for="date_to" class="form-label small mb-1">Bitiş</label>
                    <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-12 col-sm-4 col-md-4 col-lg-2">
                    <label for="search" class="form-label small mb-1">Ara</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Sipariş No, Büfe..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-12 col-lg-2">
                    <div class="d-grid gap-2 d-lg-flex"> <?php // Butonlar için flex/grid ?>
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-filter"></i> Filtrele
                        </button>
                        <a href="<?php echo BASE_URL; ?>/admin/orders/index.php" class="btn btn-secondary btn-sm flex-grow-1" title="Filtreleri Temizle">
                            <i class="fas fa-times"></i> Sıfırla
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body">
        <?php // Session mesajları header'da gösteriliyor varsayalım ?>
        <?php // include ROOT_PATH . '/includes/show_messages.php'; // Veya burada gösterilebilir ?>

        <?php if (!empty($orders)): ?>
            <form action="" method="post" id="bulkActionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="row g-2 align-items-center mb-3">
                    <div class="col-auto">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkAll" title="Tümünü Seç/Kaldır">
                             <label class="form-check-label visually-hidden" for="checkAll">Tümünü Seç</label> <?php // Ekran okuyucular için ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <select name="bulk_action" class="form-select form-select-sm" style="min-width: 150px;">
                            <option value="">Toplu İşlem Seç...</option>
                            <option value="status_update">Durumu Güncelle</option>
                            <option value="delete">Sil</option>
                        </select>
                    </div>
                    <div class="col-auto" id="statusSelectContainer" style="display: none;">
                        <select name="new_status" class="form-select form-select-sm" style="min-width: 150px;">
                             <?php foreach ($orderStatuses as $key => $text): ?>
                                <option value="<?php echo $key; ?>"><?php echo $text; ?></option>
                             <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary btn-sm" id="applyBulkAction" disabled> <?php // Primary yerine secondary ?>
                            <i class="fas fa-check"></i> Uygula
                        </button>
                    </div>
                     <div class="col-sm-auto ms-auto text-sm-end">
                          <small class="text-muted">Toplam <?php echo $total_count; ?> sipariş bulundu.</small> <?php // Daha basit bilgi ?>
                     </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle " id="ordersTable"> <?php // Ekmek listesi stili ?>
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 30px;"></th> <?php // Checkbox ?>
                                <th>Sipariş No</th>
                                <th>Büfe</th>
                                <th>Tarih</th>
                                <th class="text-end">Tutar</th>
                                <th class="text-center">Durum</th>
                                <th class="text-center" style="width: 120px;">İşlemler</th> <?php // Ekmek listesindeki gibi ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center"> <?php // Ortalamak için ?>
                                            <input class="form-check-input order-checkbox" type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" id="order_<?php echo $order['id']; ?>">
                                            <label class="form-check-label visually-hidden" for="order_<?php echo $order['id']; ?>">Sipariş <?php echo $order['id']; ?> seç</label>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/orders/view.php?id=<?php echo $order['id']; ?>" title="Sipariş Detayı">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['bakery_name'] ?? ($order['first_name'] . ' ' . $order['last_name'])); // İsim soyisim fallback ?>
                                    </td>
                                    <td>
                                        <?php echo formatDate($order['created_at'], true); // Tarih ve saat ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo formatMoney($order['total_amount']); ?></td>
                                    <td class="text-center">
                                         <?php // Durum rozeti (Bootstrap 5) ?>
                                         <?php
                                            $status_class = 'secondary'; // Varsayılan
                                            $status_text = getOrderStatusText($order['status'] ?? 'unknown'); // Durum metni
                                            switch ($order['status']) {
                                                case 'pending': $status_class = 'warning text-dark'; break;
                                                case 'processing': $status_class = 'info text-dark'; break;
                                                case 'completed': $status_class = 'success'; break;
                                                case 'cancelled': $status_class = 'danger'; break;
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($status_text); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group"> <?php // Ekmek listesi stili ?>
                                            <a href="<?php echo BASE_URL; ?>/admin/orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-info" title="Görüntüle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/admin/orders/edit.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-warning" title="Düzenle"> <?php // Warning rengi ?>
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/admin/orders/delete.php?id=<?php echo $order['id']; ?>&csrf=<?php echo generateCSRFToken(); ?>"
                                               class="btn btn-outline-danger" title="Sil"
                                               onclick="return confirm('Bu siparişi (ID: <?php echo $order['id']; ?>) ve ilişkili tüm verileri silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php // Pagination HTML'i önceki kodla aynı kalabilir, sadece URL parametrelerinin doğru aktarıldığından emin olun. ?>
             <?php if ($total_pages > 1): ?>
                <nav aria-label="Sipariş sayfaları" class="mt-4 d-flex justify-content-center"> <?php // Ortalama için flex ?>
                    <ul class="pagination pagination-sm"> <?php // justify-content-center kaldırıldı, nav'a eklendi ?>
                        <?php
                            // URL parametrelerini al (page hariç)
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);

                            // Önceki Sayfa
                             echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
                             echo '<a class="page-link" href="?' . $queryString . '&page=' . ($page - 1) . '" aria-label="Önceki">';
                             echo '<span aria-hidden="true">&laquo;</span>';
                             echo '</a></li>';


                            // Sayfa Numaraları (Daha dinamik bir aralık)
                            $links_limit = 5; // Gösterilecek maksimum sayfa linki sayısı
                            $start = max(1, $page - floor($links_limit / 2));
                            $end = min($total_pages, $start + $links_limit - 1);
                            // Eğer sona çok yaklaşıldıysa başlangıcı ayarla
                            $start = max(1, $end - $links_limit + 1);


                            if ($start > 1) { // İlk sayfa ve ...
                                echo '<li class="page-item"><a class="page-link" href="?' . $queryString . '&page=1">1</a></li>';
                                if ($start > 2) {
                                     echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . $queryString . '&page=' . $i . '">' . $i . '</a></li>';
                            }

                             if ($end < $total_pages) { // Son sayfa ve ...
                                 if ($end < $total_pages - 1) {
                                     echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                 }
                                 echo '<li class="page-item"><a class="page-link" href="?' . $queryString . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                             }


                            // Sonraki Sayfa
                            echo '<li class="page-item ' . ($page >= $total_pages ? 'disabled' : '') . '">';
                            echo '<a class="page-link" href="?' . $queryString . '&page=' . ($page + 1) . '" aria-label="Sonraki">';
                            echo '<span aria-hidden="true">&raquo;</span>';
                            echo '</a></li>';
                        ?>
                    </ul>
                </nav>
            <?php endif; ?>


        <?php else: ?>
            <div class="alert alert-warning text-center"> <?php // Warning rengi daha uygun olabilir ?>
                <i class="fas fa-info-circle me-2"></i> Arama kriterlerinize uygun sipariş bulunamadı. Filtreleri sıfırlamayı deneyin.
            </div>
        <?php endif; ?>
    </div> </div> <script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Checkbox ve Toplu İşlem Yönetimi ---
    const checkAll = document.getElementById('checkAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const applyBulkActionBtn = document.getElementById('applyBulkAction');
    const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
    const statusSelectContainer = document.getElementById('statusSelectContainer');
    const bulkActionForm = document.getElementById('bulkActionForm');

    // Tümünü Seç/Kaldır
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            orderCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
            updateApplyButtonState();
        });
    }

    // Tekil Checkbox Değişikliği
    orderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            checkAll.checked = Array.from(orderCheckboxes).every(cb => cb.checked);
            updateApplyButtonState();
        });
    });

    // Toplu İşlem Seçimi Değişikliği
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            statusSelectContainer.style.display = (this.value === 'status_update') ? 'block' : 'none';
            updateApplyButtonState();
        });
         // Sayfa yüklendiğinde de kontrol et (eğer form post sonrası hata ile geri dönmüşse)
         statusSelectContainer.style.display = (bulkActionSelect.value === 'status_update') ? 'block' : 'none';
    }

    // Uygula Butonu Durumunu Güncelle
    function updateApplyButtonState() {
        const anyChecked = Array.from(orderCheckboxes).some(cb => cb.checked);
        const actionSelected = bulkActionSelect && bulkActionSelect.value !== '';
        if (applyBulkActionBtn) {
            applyBulkActionBtn.disabled = !(anyChecked && actionSelected);
        }
    }
     // İlk yüklemede buton durumunu ayarla (eğer seçili checkbox varsa)
    updateApplyButtonState();


    // Form Gönderme Onayı (Özellikle Silme için)
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            const action = bulkActionSelect.value;
            const anyChecked = Array.from(orderCheckboxes).some(cb => cb.checked);

            if (!anyChecked) {
                e.preventDefault();
                alert('Lütfen en az bir sipariş seçin.'); return;
            }
            if (!action) {
                e.preventDefault();
                alert('Lütfen bir toplu işlem seçin.'); return;
            }
            if (action === 'delete') {
                if (!confirm('Seçili siparişleri ve ilişkili tüm verileri kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) {
                    e.preventDefault();
                }
            }
            // Durum güncelleme seçildi ama durum seçilmedi kontrolü (gerekirse)
            if (action === 'status_update' && !document.querySelector('select[name="new_status"]').value) {
                 e.preventDefault();
                 alert('Lütfen güncellenecek durumu seçin.');
            }
        });
    }

    // --- Yazdır Butonu ---
    const printBtn = document.getElementById('printOrdersBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
             // Yazdırmadan önce filtre/başlık gibi gereksiz alanları gizle, sonra geri aç
            const elementsToHide = '.filter-form, .card-header .btn-group, #bulkActionForm .row, .pagination, #adminSidebar, header.bg-dark, footer.bg-dark'; // header/footer/sidebar ID/class'ları farklı olabilir
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = 'none');
            window.print();
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = ''); // Veya orijinal display değeri
        });
    }

    // --- DataTables Başlatma (Eğer datatable sınıfı kullanılıyorsa) ---
    // PHP pagination kullandığımız için DataTables'ın kendi özelliklerini kapattık.
    // İsterseniz PHP limit/offset kaldırılıp DataTables'ın tüm özellikleri açılabilir.
     if (typeof $ !== 'undefined' && $.fn.dataTable && $('#ordersTable').length > 0 && !$.fn.dataTable.isDataTable('#ordersTable')) {
         $('#ordersTable').DataTable({
             "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json" },
             "responsive": true,
             "order": [[3, 'desc']], // Tarihe göre sırala (index 3)
             "columnDefs": [
                 { "orderable": false, "targets": [0, 6] } // Checkbox ve İşlemler sıralanamaz
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