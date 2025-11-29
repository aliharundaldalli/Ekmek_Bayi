<?php
/**
 * Müşteri Raporu (Customer Report - orders ve users Tablolarına Uygun)
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
// Helper fonksiyonlar (formatMoney, formatDate, getOrderStatusText vb.) init.php'de olmalı

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Müşteri Raporu';
$current_page = 'reports';
$current_submenu = 'customers';

// --- Rapor Filtreleri ---
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'total_sales';
$sort_order = isset($_GET['sort_order']) && in_array(strtolower($_GET['sort_order']), ['asc', 'desc']) ? strtolower($_GET['sort_order']) : 'desc';
$activity_filter = isset($_GET['activity']) ? trim($_GET['activity']) : '';

// --- Tarih Doğrulama ve Limitler ---
$six_months_ago = date('Y-m-d', strtotime('-6 months'));
if (strtotime($date_from) < strtotime($six_months_ago)) {
    $date_from = $six_months_ago;
}
if (strtotime($date_to) < strtotime($date_from)) {
    $date_to = $date_from;
}

// --- Filtre Dropdown için Müşterileri Getir ---
$customers = [];
try {
    // Disable ONLY_FULL_GROUP_BY for this session to avoid aggregation errors
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    // Sadece 'bakery' rolündeki aktif kullanıcıları alalım (users.role ve users.status)
    $stmt_cust = $pdo->query("
        SELECT id, bakery_name, first_name, last_name
        FROM users
        WHERE role = 'bakery' AND status = 1
        ORDER BY bakery_name ASC
    ");
    $customers = $stmt_cust->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customers query error for filter: " . $e->getMessage());
    $_SESSION['error_message'] = "Müşteri listesi alınamadı.";
}

// --- Ana Müşteri Raporu Verilerini Getir ---
$customer_reports = [];
$total_sales = 0;
$total_orders = 0;
$active_customers_count = 0;
$chart_labels = []; // Genel görünüm için grafik etiketleri
$chart_values = []; // Genel görünüm için grafik değerleri (örn: Top 10 satış)

try {
    // Temel sorgu: users ve orders tablolarını birleştirir. order_items kaldırıldı.
    $query = "
        SELECT
            u.id AS user_id,
            u.bakery_name,
            u.first_name,
            u.last_name,
            u.phone,      -- users tablosundan
            u.email,      -- users tablosundan
            u.created_at AS user_registration_date, -- users tablosundan
            COALESCE(COUNT(DISTINCT o.id), 0) AS order_count, -- orders tablosundan
            COALESCE(SUM(o.total_amount), 0) AS total_sales,   -- orders tablosundan
            COALESCE(AVG(o.total_amount), 0) AS avg_order_value, -- orders tablosundan
            MIN(o.created_at) AS first_order_date, -- orders tablosundan
            MAX(o.created_at) AS last_order_date,   -- orders tablosundan
            COALESCE(COUNT(DISTINCT DATE(o.created_at)), 0) AS active_days, -- orders tablosundan
            CASE WHEN COUNT(o.id) > 0 THEN DATEDIFF(MAX(o.created_at), MIN(o.created_at)) + 1 ELSE 0 END AS date_range_days
        FROM
            users u
        LEFT JOIN
            orders o ON u.id = o.user_id AND o.status != 'cancelled' -- İptal olmayan siparişler (orders.status)
                     AND DATE(o.created_at) BETWEEN :date_from AND :date_to -- Tarih aralığı (orders.created_at)
        WHERE
            u.role = 'bakery' AND u.status = 1 -- Aktif 'bakery' müşterileri (users.role, users.status)
    ";
    $params = [':date_from' => $date_from, ':date_to' => $date_to];

    // Müşteri filtresi
    if ($user_id > 0) {
        $query .= " AND u.id = :user_id";
        $params[':user_id'] = $user_id;
    }

    // Gruplama (users tablosu sütunları)
    $query .= " GROUP BY u.id, u.bakery_name, u.first_name, u.last_name, u.phone, u.email, u.created_at";

    // Aktivite filtresi (HAVING ile)
    if ($activity_filter == 'active') {
        $query .= " HAVING order_count > 0";
    } elseif ($activity_filter == 'inactive') {
        $query .= " HAVING order_count = 0";
    }

    // Sıralama (Hesaplanmış değerlere göre)
    $allowed_sort_columns = [
        'bakery_name' => 'u.bakery_name',
        'total_sales' => 'total_sales',
        'order_count' => 'order_count',
        'avg_order_value' => 'avg_order_value',
        'last_order_date' => 'last_order_date',
        'first_order_date' => 'first_order_date',
        'order_frequency' => 'CASE WHEN date_range_days > 0 THEN (active_days / date_range_days) ELSE 0 END'
    ];
    $sort_column_sql = $allowed_sort_columns[$sort_by] ?? 'total_sales'; // Varsayılan sıralama
    $query .= " ORDER BY " . $sort_column_sql . " " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . ", u.bakery_name ASC";

    // Sorguyu çalıştır
    $stmt = $pdo->prepare($query);
    $stmt->execute($params); // execute() ile parametreleri göndermek daha basit
    $customer_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Genel Toplamları ve Grafik Verilerini Hesapla (Tüm müşteriler görünümündeyken)
    if ($user_id == 0) {
        $top_customers_for_chart = array_slice($customer_reports, 0, 10); // Zaten sıralı geldiği için ilk 10'u al
        foreach ($top_customers_for_chart as $customer) {
            if (($customer['total_sales'] ?? 0) > 0) {
                $chart_labels[] = $customer['bakery_name'];
                $chart_values[] = $customer['total_sales'];
            }
        }
    }

    // Genel toplamları hesaplarken sadece sipariş vermiş olanları dikkate al
    foreach ($customer_reports as $report) {
        if (($report['order_count'] ?? 0) > 0) {
            $total_sales += ($report['total_sales'] ?? 0);
            $total_orders += $report['order_count']; // Zaten sayı, coalesce ile 0 geliyor
            $active_customers_count++;
        }
    }

} catch (PDOException $e) {
    $customer_reports = [];
    error_log("Customer report main query error: " . $e->getMessage());
    $_SESSION['error_message'] = "Müşteri raporu oluşturulurken bir hata oluştu.";
}


// --- Seçili Müşteri Detay Verileri (Eğer $user_id > 0 ise) ---
// Bu kısımlardaki sorgular (müşterinin aldığı ürünler, son siparişleri, aylık grafiği)
// önceki kodla aynı kalabilir, sadece $user_id yerine $user_id kullanılır.
// ... (Önceki yanıttaki $customer_products, $customer_orders, $monthly_orders sorguları ve data hazırlama) ...
$customer_products = []; $customer_orders = []; $monthly_orders = []; $month_labels = []; $month_values = []; $selected_customer_info = null; $selected_customer_report = null;
if ($user_id > 0) {
    // Seçili müşterinin bilgilerini bul
     foreach ($customers as $c) { if ($c['id'] == $user_id) { $selected_customer_info = $c; break; } }
     // Seçili müşterinin rapor verisini bul
     foreach ($customer_reports as $report) { if ($report['user_id'] == $user_id) { $selected_customer_report = $report; break; } }

    // Müşterinin aldığı ürünler sorgusu... (önceki koddan)
    try {
        $query_products = "SELECT bt.id AS product_id, bt.name AS product_name, oi.unit_price, COUNT(DISTINCT o.id) AS order_count, SUM(CASE WHEN oi.sale_type = 'piece' THEN oi.quantity ELSE 0 END) AS piece_sales, SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity ELSE 0 END) AS box_sales, SUM(CASE WHEN oi.sale_type = 'box' AND oi.pieces_per_box > 0 THEN oi.quantity * oi.pieces_per_box WHEN oi.sale_type = 'piece' THEN oi.quantity ELSE 0 END) AS total_quantity, SUM(oi.total_price) AS total_sales, MAX(o.created_at) AS last_order_date FROM bread_types bt JOIN order_items oi ON bt.id = oi.bread_id JOIN orders o ON oi.order_id = o.id WHERE o.status != 'cancelled' AND o.user_id = :user_id AND DATE(o.created_at) BETWEEN :date_from AND :date_to GROUP BY bt.id, bt.name, oi.unit_price ORDER BY total_quantity DESC";
        $stmt_products = $pdo->prepare($query_products);
        $stmt_products->execute([':user_id' => $user_id, ':date_from' => $date_from, ':date_to' => $date_to]);
        $customer_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $customer_products = []; error_log("Customer products query error: " . $e->getMessage()); }

    // Müşterinin son siparişleri sorgusu... (önceki koddan)
     try {
        $query_orders = "SELECT o.id, o.order_number, o.created_at, o.total_amount, COUNT(oi.id) AS item_count, o.status FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = :user_id AND DATE(o.created_at) BETWEEN :date_from AND :date_to GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10";
        $stmt_orders = $pdo->prepare($query_orders);
        $stmt_orders->execute([':user_id' => $user_id, ':date_from' => $date_from, ':date_to' => $date_to]);
        $customer_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $customer_orders = []; error_log("Customer order history query error: " . $e->getMessage()); }

    // Müşterinin aylık satış grafiği verisi... (önceki koddan)
     try {
         $twelve_months_ago = date('Y-m-d', strtotime($date_to . ' -11 months'));
         $query_monthly = "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS month_key, DATE_FORMAT(o.created_at, '%b %Y') AS month_name, COUNT(DISTINCT o.id) AS order_count, SUM(o.total_amount) AS total_sales FROM orders o WHERE o.user_id = :user_id AND o.status != 'cancelled' AND o.created_at >= :start_month AND o.created_at <= :end_date GROUP BY month_key, month_name ORDER BY month_key ASC";
         $stmt_monthly = $pdo->prepare($query_monthly);
         $stmt_monthly->execute([':user_id' => $user_id, ':start_month' => $twelve_months_ago, ':end_date' => $date_to]);
         $monthly_orders = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);
         foreach ($monthly_orders as $month) { $month_labels[] = $month['month_name']; $month_values[] = (float)$month['total_sales']; }
     } catch (PDOException $e) { $monthly_orders = []; $month_labels = []; $month_values = []; error_log("Customer monthly orders query error: " . $e->getMessage()); }
}


// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid"> <?php // Ana container ?>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
        <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
             <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" id="exportExcelBtn">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-outline-danger" id="exportPdfBtn">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-secondary" id="printReportBtn">
                    <i class="fas fa-print me-1"></i> Yazdır
                </button>
            </div>
        </div>
    </div>

     <?php // Session mesajları (varsa) ?>
     <?php include_once ROOT_PATH . '/includes/show_messages.php'; ?>


    <div class="card shadow-sm mb-4 no-print"> <?php // Yazdırırken görünmesin: no-print ?>
        <div class="card-body bg-light p-2 filter-form"> <?php // Daha kompakt: p-2 ?>
            <form action="" method="get" id="customerFilterForm">
                 <div class="row g-2 align-items-end">
                     <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <label for="user_id" class="form-label small mb-1">Müşteri</label>
                        <select name="user_id" id="user_id" class="form-select form-select-sm">
                            <option value="0">-- Tüm Müşteriler --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($user_id == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['bakery_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                        <label for="date_from" class="form-label small mb-1">Başlangıç</label>
                        <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                        <label for="date_to" class="form-label small mb-1">Bitiş</label>
                        <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-12 col-sm-4 col-md-2 col-lg-1">
                         <label for="activity" class="form-label small mb-1">Aktivite</label>
                         <select name="activity" id="activity" class="form-select form-select-sm">
                             <option value="" <?php echo ($activity_filter == '') ? 'selected' : ''; ?>>Tümü</option>
                             <option value="active" <?php echo ($activity_filter == 'active') ? 'selected' : ''; ?>>Aktif</option>
                             <option value="inactive" <?php echo ($activity_filter == 'inactive') ? 'selected' : ''; ?>>Pasif</option>
                         </select>
                     </div>
                     <div class="col-12 col-sm-8 col-md-4 col-lg-2">
                        <label for="sort_by" class="form-label small mb-1">Sırala</label>
                        <div class="input-group input-group-sm">
                            <select name="sort_by" id="sort_by" class="form-select form-select-sm">
                                <option value="total_sales" <?php echo ($sort_by == 'total_sales') ? 'selected' : ''; ?>>Toplam Satış</option>
                                <option value="order_count" <?php echo ($sort_by == 'order_count') ? 'selected' : ''; ?>>Sipariş Sayısı</option>
                                <option value="avg_order_value" <?php echo ($sort_by == 'avg_order_value') ? 'selected' : ''; ?>>Ort. Sipariş</option>
                                <option value="bakery_name" <?php echo ($sort_by == 'bakery_name') ? 'selected' : ''; ?>>Müşteri Adı</option>
                                <?php /* total_items kaldırıldı */ ?>
                                <option value="last_order_date" <?php echo ($sort_by == 'last_order_date') ? 'selected' : ''; ?>>Son Sipariş</option>
                                <option value="first_order_date" <?php echo ($sort_by == 'first_order_date') ? 'selected' : ''; ?>>İlk Sipariş</option>
                                <?php /* Sipariş Sıklığı eklenebilir */ ?>
                            </select>
                            <select name="sort_order" id="sort_order" class="form-select form-select-sm" style="max-width: 80px;">
                                <option value="desc" <?php echo ($sort_order == 'desc') ? 'selected' : ''; ?>>Azalan</option>
                                <option value="asc" <?php echo ($sort_order == 'asc') ? 'selected' : ''; ?>>Artan</option>
                            </select>
                        </div>
                    </div>
                     <div class="col-12 col-lg-2 d-flex align-items-end">
                        <div class="d-grid gap-2 d-lg-flex w-100">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fas fa-filter"></i> Uygula
                            </button>
                             <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-secondary btn-sm flex-grow-1" title="Filtreleri Temizle">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
         <div class="col-xl-3 col-md-6 mb-4">
             <div class="card border-left-primary shadow h-100 py-2">
                 <div class="card-body"> <div class="row g-0 align-items-center"> <div class="col mr-2"> <div class="text-xs fw-bold text-primary text-uppercase mb-1">Toplam Satış</div> <div class="h5 mb-0 fw-bold text-gray-800"><?php echo formatMoney($total_sales); ?></div> </div> <div class="col-auto"> <i class="fas fa-lira-sign fa-2x text-gray-300"></i> </div> </div> </div>
             </div>
         </div>
         <div class="col-xl-3 col-md-6 mb-4">
             <div class="card border-left-success shadow h-100 py-2">
                 <div class="card-body"> <div class="row g-0 align-items-center"> <div class="col mr-2"> <div class="text-xs fw-bold text-success text-uppercase mb-1">Toplam Sipariş</div> <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($total_orders); ?></div> </div> <div class="col-auto"> <i class="fas fa-shopping-cart fa-2x text-gray-300"></i> </div> </div> </div>
             </div>
         </div>
         <div class="col-xl-3 col-md-6 mb-4">
             <div class="card border-left-info shadow h-100 py-2">
                 <div class="card-body"> <div class="row g-0 align-items-center"> <div class="col mr-2"> <div class="text-xs fw-bold text-info text-uppercase mb-1">Ortalama Sipariş</div> <div class="h5 mb-0 fw-bold text-gray-800"><?php $avg_order = $total_orders > 0 ? $total_sales / $total_orders : 0; echo formatMoney($avg_order); ?></div> </div> <div class="col-auto"> <i class="fas fa-chart-line fa-2x text-gray-300"></i> </div> </div> </div>
             </div>
         </div>
          <div class="col-xl-3 col-md-6 mb-4">
             <div class="card border-left-warning shadow h-100 py-2">
                 <div class="card-body"> <div class="row g-0 align-items-center"> <div class="col mr-2"> <div class="text-xs fw-bold text-warning text-uppercase mb-1">Aktif Müşteri (Dönem)</div> <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($active_customers_count); ?></div> </div> <div class="col-auto"> <i class="fas fa-users fa-2x text-gray-300"></i> </div> </div> </div>
             </div>
         </div>
     </div>


    <?php if ($user_id > 0): ?>
        <?php if ($selected_customer_info && $selected_customer_report): ?>
            <div class="row mb-4">
                <div class="col-lg-4 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header py-3 bg-light"> <?php // bg-light eklendi ?>
                             <h6 class="m-0 fw-bold text-primary d-flex justify-content-between align-items-center">
                                 <span><i class="fas fa-user me-2"></i>Müşteri Bilgileri</span>
                                 <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); // Filtreleri temizle ?>" class="btn btn-sm btn-outline-secondary" title="Tüm Müşterilere Dön">
                                     <i class="fas fa-arrow-left"></i> Geri
                                 </a>
                             </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php // Avatar veya logo? Şimdilik baş harf ?>
                                 <div class="avatar avatar-xl bg-primary text-white rounded-circle mb-2 d-inline-flex align-items-center justify-content-center">
                                     <span class="fw-bold"><?php echo mb_strtoupper(mb_substr($selected_customer_info['bakery_name'], 0, 1, 'UTF-8')); ?></span>
                                 </div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($selected_customer_info['bakery_name']); ?></h5>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($selected_customer_info['first_name'] . ' ' . $selected_customer_info['last_name']); ?></p>
                             </div>
                            <hr class="my-2">
                            <div class="customer-details">
                                 <p><i class="fas fa-phone fa-fw me-2 text-muted"></i> <?php echo htmlspecialchars($selected_customer_info['phone'] ?? '-'); ?></p>
                                 <p><i class="fas fa-envelope fa-fw me-2 text-muted"></i> <?php echo htmlspecialchars($selected_customer_info['email'] ?? '-'); ?></p>
                                 <p><i class="fas fa-map-marker-alt fa-fw me-2 text-muted"></i> <?php echo htmlspecialchars($selected_customer_info['address'] ?? '-'); ?></p>
                                 <p><i class="fas fa-calendar-alt fa-fw me-2 text-muted"></i> Kayıt: <?php echo formatDate($selected_customer_info['user_registration_date']); ?></p> <?php // users.created_at ?>
                                <?php if ($selected_customer_report['order_count'] > 0): ?>
                                <hr class="my-2">
                                 <p><i class="fas fa-shopping-cart fa-fw me-2 text-muted"></i> Sipariş Sayısı: <span class="fw-bold"><?php echo number_format($selected_customer_report['order_count']); ?></span></p>
                                 <p><i class="fas fa-lira-sign fa-fw me-2 text-muted"></i> Toplam Satış: <span class="fw-bold"><?php echo formatMoney($selected_customer_report['total_sales']); ?></span></p>
                                 <p><i class="fas fa-chart-line fa-fw me-2 text-muted"></i> Ort. Sipariş: <span class="fw-bold"><?php echo formatMoney($selected_customer_report['avg_order_value']); ?></span></p>
                                 <p><i class="far fa-calendar-alt fa-fw me-2 text-muted"></i> İlk Sipariş: <?php echo formatDate($selected_customer_report['first_order_date']); ?></p>
                                 <p><i class="far fa-calendar-check fa-fw me-2 text-muted"></i> Son Sipariş: <?php echo formatDate($selected_customer_report['last_order_date']); ?></p>
                                <?php endif; ?>
                            </div>
                         </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-4">
                     <div class="card shadow mb-4">
                         <div class="card-header py-3 bg-light">
                             <h6 class="m-0 fw-bold text-primary">Aylık Sipariş Grafiği (Son 12 Ay)</h6>
                         </div>
                         <div class="card-body">
                             <div class="chart-container" style="height: 200px;"> <?php // Daha alçak ?>
                                <?php if (!empty($monthly_orders)): ?>
                                    <canvas id="monthlyOrdersChart"></canvas>
                                <?php else: ?>
                                    <div class="alert alert-warning text-center mb-0 small p-2">Bu müşteri için aylık sipariş verisi bulunamadı.</div>
                                <?php endif; ?>
                            </div>
                         </div>
                     </div>
                    <div class="card shadow">
                         <div class="card-header py-3 bg-light">
                             <h6 class="m-0 fw-bold text-primary">Son Siparişler (En Fazla 10)</h6>
                         </div>
                         <div class="card-body p-0">
                             <?php if (!empty($customer_orders)): ?>
                                <div class="table-responsive">
                                     <table class="table table-sm table-hover mb-0 align-middle">
                                         <thead class="table-light">
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Tarih</th>
                                                <th class="text-center">Ürün Adedi</th> <?php // item_count ?>
                                                <th class="text-end">Tutar</th>
                                                <th class="text-center">Durum</th>
                                                <th class="text-center">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customer_orders as $order): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td><?php echo formatDate($order['created_at'], true); // Tarih+Saat ?></td>
                                                    <td class="text-center"><?php echo number_format($order['item_count']); ?></td>
                                                    <td class="text-end"><?php echo formatMoney($order['total_amount']); ?></td>
                                                    <td class="text-center">
                                                        <?php // Durum Rozeti (Diğer sayfalardaki gibi)
                                                            $s_class = 'secondary'; $s_text = getOrderStatusText($order['status']);
                                                            switch ($order['status']) { case 'pending': $s_class = 'warning text-dark'; break; case 'processing': $s_class = 'info text-dark'; break; case 'completed': $s_class = 'success'; break; case 'cancelled': $s_class = 'danger'; break; }
                                                        ?>
                                                        <span class="badge bg-<?php echo $s_class; ?>"><?php echo htmlspecialchars($s_text); ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                         <a href="<?php echo BASE_URL; ?>/admin/orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-xs btn-outline-info" title="Siparişi Görüntüle">
                                                             <i class="fas fa-eye"></i>
                                                         </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                             <?php else: ?>
                                 <div class="alert alert-warning m-3 mb-0 small p-2 text-center">Bu müşteri için belirtilen tarih aralığında sipariş bulunamadı.</div>
                             <?php endif; ?>
                         </div>
                    </div>
                 </div>
            </div>

            <?php if (!empty($customer_products)): ?>
            <div class="row">
                 <div class="col-12">
                     <div class="card shadow mb-4">
                         <div class="card-header py-3 bg-light">
                             <h6 class="m-0 fw-bold text-primary">Müşterinin Satın Aldığı Ürünler (Tarih Aralığında)</h6>
                         </div>
                          <div class="card-body p-0">
                             <div class="table-responsive">
                                 <table class="table table-sm table-striped table-hover mb-0 align-middle">
                                      <thead class="table-light">
                                        <tr>
                                            <th>Ürün</th>
                                            <th class="text-center">Sipariş Adedi</th> <?php // order_count ?>
                                            <th class="text-center">Toplam Miktar</th> <?php // total_quantity ?>
                                            <th class="text-center">Adet Satışı</th> <?php // piece_sales ?>
                                            <th class="text-center">Kasa Satışı</th> <?php // box_sales ?>
                                            <th class="text-end">Toplam Tutar</th> <?php // total_sales ?>
                                            <th class="text-center">Son Sipariş Tarihi</th> <?php // last_order_date ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php foreach ($customer_products as $product): ?>
                                             <tr>
                                                 <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                 <td class="text-center"><?php echo number_format($product['order_count']); ?></td>
                                                 <td class="text-center fw-bold"><?php echo number_format($product['total_quantity']); ?></td>
                                                 <td class="text-center"><?php echo number_format($product['piece_sales']); ?></td>
                                                 <td class="text-center"><?php echo number_format($product['box_sales']); ?></td>
                                                 <td class="text-end fw-bold"><?php echo formatMoney($product['total_sales']); ?></td>
                                                 <td class="text-center"><?php echo formatDate($product['last_order_date']); ?></td>
                                             </tr>
                                         <?php endforeach; ?>
                                     </tbody>
                                 </table>
                             </div>
                         </div>
                     </div>
                 </div>
            </div>
             <?php endif; ?>

        <?php elseif($user_id > 0): // Müşteri seçili ama bilgi bulunamadı ?>
            <div class="alert alert-danger">Seçilen müşteri (ID: <?php echo $user_id; ?>) için bilgi veya rapor verisi bulunamadı.</div>
        <?php endif; ?>

    <?php else: ?>
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 bg-light">
                        <h6 class="m-0 fw-bold text-primary">En Çok Satış Yapılan Müşteriler (Top 10)</h6>
                    </div>
                    <div class="card-body">
                         <div class="chart-container" style="height: 300px;"> <?php // Grafik alanı ?>
                            <?php if (!empty($chart_labels)): ?>
                                <canvas id="topCustomersChart"></canvas>
                            <?php else: ?>
                                <div class="alert alert-warning text-center mb-0">Grafik için yeterli müşteri verisi bulunamadı.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                 <div class="card shadow h-100">
                    <div class="card-header py-3 bg-light">
                        <h6 class="m-0 fw-bold text-primary">Genel Müşteri İstatistikleri</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center small">
                         <div class="row g-1 align-items-center mb-2">
                            <div class="col-7 text-muted">Toplam Kayıtlı (Aktif):</div>
                            <div class="col-5 text-end fw-bold"><?php echo count($customers); ?></div>
                         </div>
                         <div class="row g-1 align-items-center mb-2">
                             <div class="col-7 text-muted">Sipariş Veren (Dönem İçi):</div>
                             <div class="col-5 text-end fw-bold"><?php echo $active_customers_count; ?></div>
                         </div>
                          <div class="row g-1 align-items-center mb-2">
                             <div class="col-7 text-muted">Sipariş Vermeyen (Dönem İçi):</div>
                             <div class="col-5 text-end fw-bold"><?php echo max(0, count($customer_reports) - $active_customers_count); // Negatif olmasın ?></div>
                         </div>
                         <div class="row g-1 align-items-center mb-2">
                             <div class="col-7 text-muted">Aktif Müşteri Başına Satış:</div>
                              <div class="col-5 text-end fw-bold"><?php $per_customer = $active_customers_count > 0 ? $total_sales / $active_customers_count : 0; echo formatMoney($per_customer); ?></div>
                         </div>
                         <div class="row g-1 align-items-center">
                             <div class="col-7 text-muted">Aktiflik Oranı (Dönem İçi):</div>
                             <div class="col-5 text-end fw-bold"><?php $activity_rate = count($customer_reports) > 0 ? ($active_customers_count / count($customer_reports)) * 100 : 0; echo number_format($activity_rate, 1) . '%'; ?></div>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-light">
                <h6 class="m-0 fw-bold text-primary">Müşteri Raporu Detayları</h6>
            </div>
            <div class="card-body p-0"> <?php // Padding tablo tarafından yönetilsin ?>
                <?php if (!empty($customer_reports)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle" id="customerReportTable"> <?php // Datatable sınıfı kaldırıldı ?>
                             <thead class="table-light">
                                <tr>
                                    <?php // Helper fonksiyonu header.php veya init.php içinde tanımlanmalı ?>
                                    <?php if (!function_exists('sort_link_customer')) { function sort_link_customer($cn, $dt, $cs, $co, $bp) { $o = ($cs == $cn && $co == 'asc') ? 'desc' : 'asc'; $i = ($cs == $cn) ? (($co == 'asc') ? ' <i class="fas fa-sort-up fa-xs"></i>' : ' <i class="fas fa-sort-down fa-xs"></i>') : ''; unset($bp['sort_by'], $bp['sort_order']); $p = http_build_query(array_merge($bp, ['sort_by' => $cn, 'sort_order' => $o])); return '<a href="?' . $p . '">' . htmlspecialchars($dt) . $i . '</a>'; } } ?>
                                    <?php $base_params = ['date_from' => $date_from, 'date_to' => $date_to, 'user_id' => $user_id, 'activity' => $activity_filter]; ?>

                                    <th><?php echo sort_link_customer('bakery_name', 'Müşteri', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-center"><?php echo sort_link_customer('order_count', 'Sipariş', $sort_by, $sort_order, $base_params); ?></th>
                                    <?php /* Ürün Adedi kaldırıldı */ ?>
                                    <th class="text-end"><?php echo sort_link_customer('total_sales', 'Toplam Satış', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-end"><?php echo sort_link_customer('avg_order_value', 'Ort. Sipariş', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-center"><?php echo sort_link_customer('last_order_date', 'Son Sipariş', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-center"><?php echo sort_link_customer('first_order_date', 'İlk Sipariş', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-center" title="Dönem İçindeki Sipariş Verilen Gün Oranı"><?php echo sort_link_customer('order_frequency', 'Sıklık (%)', $sort_by, $sort_order, $base_params); ?></th>
                                    <th class="text-center">İşlemler</th> <?php // İşlem sütunu ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customer_reports as $report): ?>
                                    <tr class="<?php echo ($report['order_count'] == 0) ? 'table-secondary text-muted' : ''; // Sipariş vermeyenleri gri yap ?>">
                                        <td>
                                            <a href="?user_id=<?php echo $report['user_id']; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" title="Müşteri Detay Raporu">
                                                <?php echo htmlspecialchars($report['bakery_name']); ?>
                                            </a>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $report['order_count'] > 0 ? number_format($report['order_count']) : '-'; ?>
                                        </td>
                                        <?php /* Ürün Adedi kaldırıldı */ ?>
                                        <td class="text-end fw-bold">
                                            <?php echo $report['total_sales'] > 0 ? formatMoney($report['total_sales']) : '-'; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $report['avg_order_value'] > 0 ? formatMoney($report['avg_order_value']) : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $report['last_order_date'] ? formatDate($report['last_order_date']) : '-'; ?>
                                        </td>
                                         <td class="text-center">
                                            <?php echo $report['first_order_date'] ? formatDate($report['first_order_date']) : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                             <?php // Sıklık hesaplama
                                                $frequency = 0;
                                                if (($report['date_range_days'] ?? 0) > 0 && ($report['active_days'] ?? 0) > 0) {
                                                    $frequency = ($report['active_days'] / $report['date_range_days']) * 100;
                                                }
                                                echo $frequency > 0 ? number_format($frequency, 1) . '%' : '-';
                                            ?>
                                        </td>
                                         <td class="text-center">
                                            <?php // Müşteri İşlem Butonları ?>
                                             <a href="<?php echo BASE_URL; ?>/admin/users/view.php?id=<?php echo $report['user_id']; ?>" class="btn btn-xs btn-outline-info" title="Müşteri Detayı">
                                                 <i class="fas fa-user"></i>
                                             </a>
                                              <a href="<?php echo BASE_URL; ?>/admin/users/edit.php?id=<?php echo $report['user_id']; ?>" class="btn btn-xs btn-outline-warning" title="Müşteriyi Düzenle">
                                                 <i class="fas fa-edit"></i>
                                             </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-3 text-center">Filtre koşullarına uygun müşteri verisi bulunamadı.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; // Genel/Detay görünüm sonu ?>

</div>

<?php
// --- Footer Dahil Etme ---
include_once ROOT_PATH . '/admin/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- Grafik Başlatma ---
    <?php if ($user_id == 0 && !empty($chart_labels)): ?>
        // Genel görünüm: Top Müşteri Grafiği
        const topCustomersCtx = document.getElementById('topCustomersChart');
        if (topCustomersCtx) {
            new Chart(topCustomersCtx.getContext('2d'), {
                type: 'bar',
                data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Toplam Satış (TL)', data: <?php echo json_encode($chart_values); ?>, backgroundColor: 'rgba(78, 115, 223, 0.8)', maxBarThickness: 30 }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' Toplam Satış: ' + ctx.raw.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' }) } } }, scales: { x: { beginAtZero: true, ticks: { callback: val => val.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits:0 }) } }, y: { ticks: { autoSkip: false } } } }
            });
        }
    <?php elseif ($user_id > 0 && !empty($monthly_orders)): ?>
        // Detay görünüm: Müşterinin Aylık Satış Grafiği
        const monthlyOrdersCtx = document.getElementById('monthlyOrdersChart');
        if (monthlyOrdersCtx) {
            new Chart(monthlyOrdersCtx.getContext('2d'), {
                 type: 'line',
                 data: { labels: <?php echo json_encode($month_labels); ?>, datasets: [{ label: 'Aylık Satış (TL)', data: <?php echo json_encode($month_values); ?>, borderColor: 'rgba(28, 200, 138, 1)', backgroundColor: 'rgba(28, 200, 138, 0.1)', fill: true, tension: 0.1 }] },
                 options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: val => val.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' }) } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.raw.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' }) } } } }
            });
        }
    <?php endif; ?>


    // --- Export ve Print Fonksiyonları ---
    const tableId = '#customerReportTable';
    const reportTitleBase = 'Musteri_Raporu';
    const todayStr = new Date().toISOString().slice(0, 10);
    const reportDateRange = '<?php echo formatDate($date_from, false) . "_" . formatDate($date_to, false); ?>';
    const customerName = '<?php echo $user_id > 0 && $selected_customer_info ? addslashes(preg_replace("/[^a-zA-Z0-9_]/", "", $selected_customer_info['bakery_name'])) : "TumMusteriler"; ?>';
    const fileNameBase = `${reportTitleBase}_${customerName}_${reportDateRange}`;

    // Excel Export
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if(exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            try {
                const table = document.querySelector(tableId);
                if (!table) throw new Error('Tablo bulunamadı.');
                // Son sütunu (İşlemler) klonlamadan önce kaldır
                const clonedTable = table.cloneNode(true);
                Array.from(clonedTable.querySelectorAll('tr')).forEach(row => { if(row.cells.length > 0) row.deleteCell(-1); });

                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.table_to_sheet(clonedTable);
                XLSX.utils.book_append_sheet(wb, ws, "Musteri Raporu");
                XLSX.writeFile(wb, `${fileNameBase}.xlsx`);
            } catch(e) { console.error('Excel export error:', e); alert('Excel aktarma hatası: ' + e.message); }
        });
    }

    // PDF Export - DÜZELTILMIŞ KOD
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    if(exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            try {
                // jsPDF'i doğru şekilde başlat
                if (typeof window.jspdf === 'undefined') {
                    alert('PDF kütüphanesi yüklenemedi. Sayfayı yenileyin veya başka bir tarayıcı deneyin.');
                    return;
                }
                
                // jsPDF'i doğru şekilde çağır
                const { jsPDF } = window.jspdf;
                
                // Yeni bir PDF dokümanı oluştur
                const doc = new jsPDF({
                    orientation: 'landscape', 
                    unit: 'mm', 
                    format: 'a4'
                });
                
                // Başlık ve filtre bilgileri
                doc.setFontSize(14);
                doc.text('Müşteri Raporu', doc.internal.pageSize.getWidth() / 2, 15, { align: 'center' });
                
                doc.setFontSize(10);
                let filterText = `Tarih: <?php echo formatDate($date_from, false); ?> - <?php echo formatDate($date_to, false); ?>`;
                if (<?php echo $user_id; ?> > 0) {
                    filterText += ` | Müşteri: <?php echo addslashes($selected_customer_info['bakery_name'] ?? 'Bilinmiyor'); ?>`;
                }
                if ('<?php echo $activity_filter; ?>') {
                    filterText += ` | Filtre: <?php echo $activity_filter == 'active' ? 'Aktifler' : 'Pasifler'; ?>`;
                }
                doc.text(filterText, 14, 22);

                // Tabloyu PDF'e dönüştür
                doc.autoTable({
                    html: tableId,
                    startY: 28,
                    theme: 'grid',
                    styles: { 
                        fontSize: 8, 
                        cellPadding: 2,
                        overflow: 'linebreak'
                    },
                    headStyles: { 
                        fillColor: [78, 115, 223], 
                        textColor: 255, 
                        fontStyle: 'bold', 
                        halign: 'center' 
                    },
                    columnStyles: {
                        0: { cellWidth: 45 },           // Müşteri
                        1: { halign: 'center', cellWidth: 18 },  // Sipariş
                        2: { halign: 'right', cellWidth: 28 },   // Toplam Satış
                        3: { halign: 'right', cellWidth: 28 },   // Ort. Sipariş
                        4: { halign: 'center', cellWidth: 25 },  // Son Sipariş
                        5: { halign: 'center', cellWidth: 25 },  // İlk Sipariş
                        6: { halign: 'center', cellWidth: 20 },  // Sıklık
                        7: { cellWidth: 0 }            // İşlemler (gizlenecek)
                    },
                    didParseCell: function(data) {
                        // İşlem sütununu gizle
                        if (data.column.index === 7) {
                            data.cell.text = '';
                        }
                        
                        // Para birimi formatlaması
                        if (data.column.index === 2 || data.column.index === 3) {
                            if (data.cell.text && data.cell.text[0] && data.cell.text[0] !== '-') {
                                // Sayısal değer çıkar (TL ve binlik ayıraçlarını kaldır)
                                let text = data.cell.text[0];
                                let cleanValue = text.replace(/[^0-9,.-]/g, '').replace('.', '').replace(',', '.');
                                let numValue = parseFloat(cleanValue);
                                
                                if (!isNaN(numValue)) {
                                    // Formatla ve TL ekle
                                    data.cell.text = numValue.toLocaleString('tr-TR', { 
                                        minimumFractionDigits: 2, 
                                        maximumFractionDigits: 2 
                                    }) + ' TL';
                                }
                            }
                        }
                        
                        // Yüzde formatlaması
                        if (data.column.index === 6 && data.cell.text && data.cell.text[0] !== '-') {
                            let value = data.cell.text[0];
                            if (!value.includes('%')) {
                                data.cell.text = value + '%';
                            }
                        }
                    },
                    didDrawPage: function(data) {
                        // Sayfa numarası
                        doc.setFontSize(8);
                        doc.setTextColor(150);
                        doc.text(
                            'Sayfa ' + doc.internal.getNumberOfPages(), 
                            data.settings.margin.left, 
                            doc.internal.pageSize.getHeight() - 8
                        );
                        
                        // Oluşturulma tarihi
                        doc.text(
                            'Oluşturulma: ' + new Date().toLocaleDateString('tr-TR'),
                            doc.internal.pageSize.getWidth() - data.settings.margin.right,
                            doc.internal.pageSize.getHeight() - 8,
                            { align: 'right' }
                        );
                    }
                });

                // PDF'i kaydet
                doc.save(`${fileNameBase}.pdf`);
                
            } catch(e) { 
                console.error('PDF export error:', e); 
                alert('PDF oluşturulurken bir hata oluştu: ' + e.message); 
            }
        });
    }

    // Print
    const printBtn = document.getElementById('printReportBtn');
    if(printBtn) { 
        printBtn.addEventListener('click', function() {
            // Yazdırırken gizlenecek UI elementleri
            const elementsToHide = document.querySelectorAll('.no-print, #customerReportTable th:last-child, #customerReportTable td:last-child');
            const originalDisplays = [];
            
            // Elementleri gizle
            elementsToHide.forEach(el => {
                originalDisplays.push(el.style.display);
                el.style.display = 'none';
            });
            
            // Yazdır
            window.print();
            
            // Elementleri geri göster
            elementsToHide.forEach((el, i) => {
                el.style.display = originalDisplays[i];
            });
        });
    }
});
</script>