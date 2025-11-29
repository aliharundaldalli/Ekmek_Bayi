<?php
require_once '../../init.php';

// Authentication checks
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

// Redirect admins away from user pages (they have their own order view)
if (isAdmin()) {
    redirect(BASE_URL . '/admin/orders/index.php'); // Redirect admin to their order management
}

// Page setup
$page_title = 'Siparişlerim';
$current_page = 'orders'; // For active menu highlighting
$user_id = $_SESSION['user_id'];
date_default_timezone_set('Europe/Istanbul'); // Set timezone

// Filtering parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => trim($_GET['search'] ?? '') // Trim search input
];

// Pagination
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$per_page = 15; // Increased per page count slightly
$offset = ($page - 1) * $per_page;

// Base query with corrected subquery alias and JOINs
$base_query = "
    SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.created_at,
        o.status,
        COUNT(oi.id) as item_count
        -- Removed subquery for last_status, rely on o.status for simplicity or join history if needed
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = :user_id
";

// Helper functions to determine allowed actions based on status
function canRepeatOrder($order_status) {
    // Define which order statuses allow repeating
    // Example: Only completed or possibly delivered orders can be repeated
    $repeatableStatuses = ['completed', 'delivered']; // Adjusted based on potential statuses
    return in_array($order_status, $repeatableStatuses);
}

function canCancelOrder($order_status) {
    // Define which order statuses can be cancelled by the user
    // Example: Only pending or maybe confirmed orders before processing starts
    $cancellableStatuses = ['pending', 'confirmed']; // Adjusted based on typical workflow
    return in_array($order_status, $cancellableStatuses);
}

// Prepare parameters for binding
$params = [':user_id' => $user_id];

// Apply filters dynamically
$filter_conditions = [];

if (!empty($filters['status'])) {
    $filter_conditions[] = "o.status = :status";
    $params[':status'] = $filters['status'];
}

if (!empty($filters['date_from'])) {
    // Basic validation for date format (optional but good)
    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filters['date_from'])) {
        $filter_conditions[] = "DATE(o.created_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    } else {
        // Handle invalid date format if needed (e.g., show error, ignore filter)
        // For simplicity, we ignore invalid format here
    }
}

if (!empty($filters['date_to'])) {
     if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filters['date_to'])) {
        $filter_conditions[] = "DATE(o.created_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
}

if (!empty($filters['search'])) {
    // Search in order number OR user note OR maybe even product names (requires JOIN)
    $filter_conditions[] = "(o.order_number LIKE :search OR o.note LIKE :search)"; // Simple search
    $params[':search'] = "%" . $filters['search'] . "%";
}

// Combine filter conditions to the base query
if (!empty($filter_conditions)) {
    $base_query .= " AND " . implode(" AND ", $filter_conditions);
}

// Add Grouping and Ordering
$base_query .= " GROUP BY o.id ORDER BY o.created_at DESC";

try {
    // --- Count total records query ---
    // More robust way to create the count query
    $count_query = "SELECT COUNT(DISTINCT o.id) FROM orders o ";
    // We don't need order_items join for count if filter doesn't use it
    // $count_query .= "LEFT JOIN order_items oi ON o.id = oi.order_id "; // Add back if needed by filters
    $count_query .= "WHERE o.user_id = :user_id ";
    if (!empty($filter_conditions)) {
         // Rebuild filter conditions for count query (without aliases if they differ)
        $count_filter_conditions = [];
        if (!empty($filters['status'])) $count_filter_conditions[] = "o.status = :status";
        if (!empty($filters['date_from'])) $count_filter_conditions[] = "DATE(o.created_at) >= :date_from";
        if (!empty($filters['date_to'])) $count_filter_conditions[] = "DATE(o.created_at) <= :date_to";
        if (!empty($filters['search'])) $count_filter_conditions[] = "(o.order_number LIKE :search OR o.note LIKE :search)";

        if (!empty($count_filter_conditions)) {
            $count_query .= " AND " . implode(" AND ", $count_filter_conditions);
        }
    }


    $stmt_count = $pdo->prepare($count_query);
    // Bind only necessary parameters for count
    $count_params = [':user_id' => $user_id];
    if (!empty($filters['status'])) $count_params[':status'] = $filters['status'];
    if (!empty($filters['date_from'])) $count_params[':date_from'] = $filters['date_from'];
    if (!empty($filters['date_to'])) $count_params[':date_to'] = $filters['date_to'];
    if (!empty($filters['search'])) $count_params[':search'] = "%{$filters['search']}%";

    $stmt_count->execute($count_params);
    $total_count = (int) $stmt_count->fetchColumn(); // Cast to int

    // Calculate total pages
    $total_pages = ($per_page > 0) ? max(1, (int) ceil($total_count / $per_page)) : 1;
    // Ensure current page is not out of bounds
    $page = max(1, min($page, $total_pages));
    // Recalculate offset based on potentially corrected page number
    $offset = ($page - 1) * $per_page;

    // --- Fetch orders for the current page ---
    $query = $base_query . " LIMIT :offset, :per_page";

    $stmt_orders = $pdo->prepare($query);
    // Bind all parameters for the main query (including offset and per_page)
    $params[':offset'] = $offset;
    $params[':per_page'] = $per_page;

    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':per_page') {
            $stmt_orders->bindValue($key, (int) $value, PDO::PARAM_INT); // Explicitly bind as INT
        } else {
            $stmt_orders->bindValue($key, $value);
        }
    }
    $stmt_orders->execute();
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Order Fetch Error (User View): " . $e->getMessage());
    // Set safe defaults in case of error
    $orders = [];
    $total_count = 0;
    $total_pages = 1;
    $_SESSION['error'] = "Siparişler yüklenirken bir hata oluştu."; // Inform user
}

// Status mapping for display (Ensure all your possible statuses are here)
$status_map = [
    'pending'    => ['text' => 'Beklemede', 'class' => 'warning'],
    'confirmed'  => ['text' => 'Onaylandı', 'class' => 'primary'], // Added confirmed
    'processing' => ['text' => 'Hazırlanıyor', 'class' => 'info'],
    'ready'      => ['text' => 'Hazır', 'class' => 'info'], // Added ready
    'delivered'  => ['text' => 'Teslim Edildi', 'class' => 'success'], // Added delivered
    'completed'  => ['text' => 'Tamamlandı', 'class' => 'success'],
    'cancelled'  => ['text' => 'İptal Edildi', 'class' => 'danger'],
    'refunded'   => ['text' => 'İade Edildi', 'class' => 'secondary'] // Added refunded example
];

// Include header AFTER data fetching and variable setup
include_once ROOT_PATH . '/my/header.php';
?>

<div class="container mt-4 mb-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Siparişlerim</h5>
            <a href="<?php echo BASE_URL; ?>/my/orders/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Yeni Sipariş Ver
            </a>
        </div>

        <div class="card-body border-bottom">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label for="statusFilter" class="form-label small mb-1">Durum</label>
                    <select name="status" id="statusFilter" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($status_map as $key => $status_info): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status_info['text']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="dateFromFilter" class="form-label small mb-1">Başlangıç Tarihi</label>
                    <input type="date" name="date_from" id="dateFromFilter" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                     <label for="dateToFilter" class="form-label small mb-1">Bitiş Tarihi</label>
                    <input type="date" name="date_to" id="dateToFilter" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-4 col-sm-6">
                     <label for="searchFilter" class="form-label small mb-1">Ara</label>
                    <input type="text" name="search" id="searchFilter" class="form-control form-control-sm"
                           placeholder="Sipariş No veya Not İçeriği"
                           value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <div class="col-md-2 col-sm-12 d-grid">
                    <button type="submit" class="btn btn-secondary btn-sm">
                         <i class="fas fa-filter me-1"></i> Filtrele
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body p-0"> <?php if (isset($_SESSION['success'])): ?>
                 <div class="alert alert-success m-3"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
             <?php if (isset($_SESSION['error'])): ?>
                 <div class="alert alert-danger m-3"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle"> <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Sipariş No</th>
                                <th class="text-nowrap">Tarih</th>
                                <th class="text-center">Ürün Sayısı</th>
                                <th class="text-end">Tutar</th>
                                <th class="text-center">Durum</th>
                                <th class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order):
                                // Get status info, default to pending if unknown
                                $status_info = $status_map[$order['status']] ?? ['text' => ucfirst($order['status']), 'class' => 'secondary'];
                            ?>
                                <tr>
                                    <td data-label="Sipariş No" class="fw-bold"><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td data-label="Tarih" class="text-nowrap"><?= formatDate($order['created_at'], 'd.m.Y H:i') ?></td>
                                    <td data-label="Ürün Sayısı" class="text-center"><?= htmlspecialchars($order['item_count']) ?></td>
                                    <td data-label="Tutar" class="text-end text-nowrap"><?= formatMoney($order['total_amount']) ?></td>
                                    <td data-label="Durum" class="text-center">
                                        <span class="badge bg-<?= htmlspecialchars($status_info['class']) ?>">
                                            <?= htmlspecialchars($status_info['text']) ?>
                                        </span>
                                    </td>
                                    <td data-label="İşlemler" class="text-center">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Sipariş İşlemleri">
                                            <a href="<?php echo BASE_URL; ?>/my/orders/view.php?id=<?= $order['id'] ?>"
                                               class="btn btn-outline-primary"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="Siparişi Görüntüle">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <?php if (canCancelOrder($order['status'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/my/orders/cancel.php?id=<?= $order['id'] ?>"
                                                   class="btn btn-outline-danger"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-placement="top"
                                                   title="Siparişi İptal Et"
                                                   onclick="return confirm('Bu siparişi iptal etmek istediğinize emin misiniz? Bu işlem geri alınamaz.')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>

                                             <?php if (canRepeatOrder($order['status'])): ?>
                                                 <a href="<?php echo BASE_URL; ?>/my/orders/repeat.php?id=<?= $order['id'] ?>"
                                                   class="btn btn-outline-success"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-placement="top"
                                                   title="Bu Siparişi Tekrarla"
                                                    onclick="return confirm('Bu siparişin içeriği ile yeni bir sepet oluşturulacaktır. Onaylıyor musunuz?')">
                                                    <i class="fas fa-redo-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                     <div class="card-footer bg-light d-flex justify-content-center pb-0 pt-3">
                         <?php
                         // Build query string for pagination links, preserving filters
                        $query_params = http_build_query(array_merge(['page' => $page], $filters));
                        // Remove page from it
                        $query_params = preg_replace('/&?page=[^&]*/', '', $query_params);
                         ?>
                        <nav aria-label="Sipariş Sayfaları">
                            <ul class="pagination pagination-sm">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                     <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $query_params ?>" aria-label="Önceki">
                                         <span aria-hidden="true">&laquo;</span>
                                     </a>
                                </li>

                                <?php
                                // Pagination Links Logic (Show limited number of pages)
                                $links_to_show = 5; // Number of page links to display around current page
                                $start_page = max(1, $page - floor($links_to_show / 2));
                                $end_page = min($total_pages, $start_page + $links_to_show - 1);
                                // Adjust start page if end page is maxed out
                                if ($end_page == $total_pages) {
                                    $start_page = max(1, $total_pages - $links_to_show + 1);
                                }

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&' . $query_params . '">1</a></li>';
                                    if ($start_page > 2) {
                                         echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= $query_params ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . $query_params . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                     <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $query_params ?>" aria-label="Sonraki">
                                         <span aria-hidden="true">&raquo;</span>
                                     </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center m-3">
                     <i class="fas fa-info-circle me-1"></i> Filtrelerinize uygun sipariş bulunamadı veya henüz hiç sipariş vermediniz.
                </div>
            <?php endif; ?>
        </div> </div> </div> <?php include_once ROOT_PATH . '/my/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>
</body>
</html>