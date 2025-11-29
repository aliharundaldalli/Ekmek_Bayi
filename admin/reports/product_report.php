<?php
/**
 * Ürün Raporu (bread_types Tablosuna Uygun Görünüm)
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php'; // Gerekli dosyalar ve tanımlamalar
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Admin kontrolü

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Ürün Raporu';
$current_page = 'reports';
$current_submenu = 'products'; // Raporlar alt menüsü için

// --- Rapor Filtreleri ---
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$product_id_filter = isset($_GET['product_id']) && is_numeric($_GET['product_id']) ? (int)$_GET['product_id'] : 0; // Filtre için _filter eki
$sort_by = $_GET['sort_by'] ?? 'sales_quantity'; // Sıralama için hala kullanılabilir
$sort_order = $_GET['sort_order'] ?? 'desc';

// Tarih aralığı sınırı (performans için)
$six_months_ago = date('Y-m-d', strtotime('-6 months'));
if (strtotime($date_from) < strtotime($six_months_ago)) {
    $date_from = $six_months_ago;
}
// Bitiş tarihi başlangıçtan önce olamaz
if (strtotime($date_to) < strtotime($date_from)) {
    $date_to = $date_from;
}


// --- Ürünleri Getir (Filtre dropdown için) ---
$products_for_filter = [];
try {
    // Disable ONLY_FULL_GROUP_BY for this session to avoid aggregation errors
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    // Sadece aktif ürünleri filtrede gösterelim
    $stmt_filter = $pdo->query("SELECT id, name FROM bread_types WHERE status = 1 ORDER BY name ASC");
    $products_for_filter = $stmt_filter->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Products for filter query error: " . $e->getMessage());
}

// --- Rapor Verilerini Al (Hem Ürün Detayları Hem Satış Toplamları) ---
$product_reports = [];
$total_sales_amount = 0;
$total_sales_quantity = 0;
// Diğer toplamlar... (Önceki koddan alınabilir)

try {
    // bread_types'dan TÜM sütunları (b.*) ve HESAPLANMIŞ satış verilerini seç
    $query = "
        SELECT
            b.*, -- bread_types tablosundaki tüm sütunlar
            COALESCE(SUM(CASE WHEN oi.sale_type = 'piece' THEN oi.quantity ELSE 0 END), 0) AS piece_sales,
            COALESCE(SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity ELSE 0 END), 0) AS box_sales,
            COALESCE(SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END), 0) AS total_quantity,
            COALESCE(SUM(oi.total_price), 0) AS total_sales, -- order_items.total_price kullanmak daha iyi
            COUNT(DISTINCT o.id) AS order_count,
            COUNT(DISTINCT o.user_id) AS customer_count,
            MAX(o.created_at) AS last_sale_date
        FROM
            bread_types b
        LEFT JOIN
            order_items oi ON b.id = oi.bread_id
        LEFT JOIN
            orders o ON oi.order_id = o.id AND o.status != 'cancelled'
                     AND DATE(o.created_at) BETWEEN :date_from AND :date_to
        WHERE
            1=1 -- Tüm ürünleri al (aktif/pasif), gösterirken filtreleyebiliriz
            -- b.status = 1 -- Sadece aktifleri almak için (isteğe bağlı)
    ";
    $params = [':date_from' => $date_from, ':date_to' => $date_to];

    // Ürün filtresini ekle
    if ($product_id_filter > 0) {
        $query .= " AND b.id = :product_id";
        $params[':product_id'] = $product_id_filter;
    }

    $query .= " GROUP BY b.id"; // Tüm b.* sütunları için sadece b.id yeterli (MySQL < 8 için tüm b sütunları gerekebilir)

    // Sıralama (Hesaplanmış değerlere göre sıralama hala mümkün)
     $order_clause = " ORDER BY ";
     switch ($sort_by) {
        case 'name': $order_clause .= "b.name"; break;
        case 'price': $order_clause .= "b.price"; break;
        case 'status': $order_clause .= "b.status"; break;
        case 'total_sales': $order_clause .= "total_sales"; break;
        case 'total_quantity': $order_clause .= "total_quantity"; break;
        // Diğer sıralama seçenekleri...
        default: $order_clause .= "total_quantity"; // Varsayılan
     }
     $order_clause .= ($sort_order === 'asc' ? ' ASC' : ' DESC');
     $query .= $order_clause;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $product_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Genel toplamları hesapla (eğer filtre yoksa)
     if ($product_id_filter == 0) {
        foreach ($product_reports as $report) {
            $total_sales_amount += $report['total_sales'];
            $total_sales_quantity += $report['total_quantity'];
            // Diğer toplamlar...
        }
     } elseif (count($product_reports) === 1) { // Tek ürün filtrelendiyse onun toplamları
         $report = $product_reports[0];
         $total_sales_amount = $report['total_sales'];
         $total_sales_quantity = $report['total_quantity'];
     }


} catch (PDOException $e) {
    $product_reports = [];
    error_log("Product report query error: " . $e->getMessage());
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['error_message'] = "Ürün raporu verileri alınırken bir hata oluştu.";
    }
}


// --- Seçili Ürün İçin Grafik/Stok/Müşteri Verileri ---
// Bu kısımlar önceki kodla aynı kalabilir, sadece $product_id_filter kullanılır.
// ... ($daily_sales, $top_customers, $current_stock vb. sorguları $product_id_filter > 0 kontrolü ile) ...
$daily_sales = $chart_labels = $chart_values = $top_customers = [];
$current_stock = $min_stock = $max_stock = 0; $stock_status = '';

if ($product_id_filter > 0) {
    // Günlük satış grafiği sorgusu...
    try {
         $query_daily_product = "
            SELECT
                DATE_FORMAT(o.created_at, '%d.%m') AS formatted_date,
                SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS day_quantity
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status != 'cancelled'
                AND DATE(o.created_at) BETWEEN :date_from AND :date_to
                AND oi.bread_id = :product_id
            GROUP BY DATE(o.created_at)
            ORDER BY DATE(o.created_at) ASC
        ";
        $stmt_daily_product = $pdo->prepare($query_daily_product);
        $stmt_daily_product->bindParam(':date_from', $date_from);
        $stmt_daily_product->bindParam(':date_to', $date_to);
        $stmt_daily_product->bindParam(':product_id', $product_id_filter, PDO::PARAM_INT);
        $stmt_daily_product->execute();
        $daily_sales = $stmt_daily_product->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daily_sales as $day) {
            $chart_labels[] = $day['formatted_date'];
            $chart_values[] = (float)$day['day_quantity'];
        }
    } catch (PDOException $e) { error_log("Daily sales for product query error: " . $e->getMessage()); }

    // Stok bilgisi sorgusu...
    try {
        $stmt_stock = $pdo->prepare("SELECT current_stock, min_stock, max_stock FROM inventory WHERE bread_id = :product_id LIMIT 1");
        $stmt_stock->bindParam(':product_id', $product_id_filter, PDO::PARAM_INT);
        $stmt_stock->execute();
        $inventory = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        if ($inventory) {
            $current_stock = $inventory['current_stock'];
            $min_stock = $inventory['min_stock'];
            $max_stock = $inventory['max_stock'];
            if ($current_stock <= $min_stock) $stock_status = 'low';
            elseif ($max_stock > 0 && $current_stock >= $max_stock) $stock_status = 'high'; // max_stock > 0 kontrolü
            else $stock_status = 'normal';
        }
    } catch (PDOException $e) { error_log("Inventory query error: " . $e->getMessage()); }

    // Top müşteriler sorgusu...
    try {
        $query_top_customers_product = "
             SELECT
                u.id AS user_id, u.bakery_name, u.first_name, u.last_name,
                COUNT(DISTINCT o.id) AS order_count,
                SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS total_quantity,
                SUM(oi.total_price) AS total_sales
            FROM users u
            JOIN orders o ON u.id = o.user_id
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status != 'cancelled'
                AND DATE(o.created_at) BETWEEN :date_from AND :date_to
                AND oi.bread_id = :product_id
            GROUP BY u.id, u.bakery_name, u.first_name, u.last_name
            ORDER BY total_quantity DESC
            LIMIT 10
        ";
         $stmt_top_customers_product = $pdo->prepare($query_top_customers_product);
         $stmt_top_customers_product->bindParam(':date_from', $date_from);
         $stmt_top_customers_product->bindParam(':date_to', $date_to);
         $stmt_top_customers_product->bindParam(':product_id', $product_id_filter, PDO::PARAM_INT);
         $stmt_top_customers_product->execute();
         $top_customers = $stmt_top_customers_product->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Top customers for product query error: " . $e->getMessage()); }
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid"> <?php // Ana container ?>
    <div class="row">
        <div class="col-12"> <?php // Tüm içerik tek sütunda ?>
            <div class="card shadow mb-4"> <?php // Ana kart ?>
                <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
                     <h6 class="m-0 font-weight-bold text-success me-3 mb-2 mb-md-0"> <?php // Renk success ?>
                        <i class="fas fa-bread-slice me-2"></i><?php echo htmlspecialchars($page_title); ?>
                     </h6>
                     <div class="btn-group btn-group-sm" role="group">
                         <button type="button" class="btn btn-outline-success" id="exportExcelBtn"> <?php // outline stil ?>
                             <i class="fas fa-file-excel me-1"></i> Excel'e Aktar
                         </button>
                         <button type="button" class="btn btn-outline-danger" id="exportPdfBtn"> <?php // outline stil ?>
                             <i class="fas fa-file-pdf me-1"></i> PDF'e Aktar
                         </button>
                         <button type="button" class="btn btn-outline-secondary" id="printReportBtn"> <?php // outline stil ?>
                             <i class="fas fa-print me-1"></i> Yazdır
                         </button>
                     </div>
                </div>

                <div class="card-body border-bottom bg-light py-2 filter-form">
                     <form action="" method="get" id="filterFormProduct">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                <label for="product_id" class="form-label small mb-1">Ürün</label>
                                <select name="product_id" id="product_id" class="form-select form-select-sm">
                                    <option value="0">Tüm Ürünler</option>
                                    <?php foreach ($products_for_filter as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" <?php echo ($product_id_filter == $product['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['name']); ?>
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
                             <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                <label for="sort_by" class="form-label small mb-1">Sırala</label>
                                <div class="input-group input-group-sm">
                                    <select name="sort_by" id="sort_by" class="form-select form-select-sm">
                                        <option value="total_quantity" <?php echo $sort_by == 'total_quantity' ? 'selected' : ''; ?>>Satış Miktarı</option>
                                        <option value="total_sales" <?php echo $sort_by == 'total_sales' ? 'selected' : ''; ?>>Satış Tutarı</option>
                                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Ürün Adı</option>
                                        <option value="price" <?php echo $sort_by == 'price' ? 'selected' : ''; ?>>Birim Fiyat</option>
                                        <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Durum</option>
                                        <?php /* Diğer sıralama seçenekleri eklenebilir */ ?>
                                     </select>
                                    <select name="sort_order" id="sort_order" class="form-select form-select-sm" style="max-width: 80px;"> <?php // Daha dar ?>
                                        <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>Azalan</option>
                                        <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>Artan</option>
                                    </select>
                                </div>
                            </div>
                             <div class="col-12 col-lg-2">
                                <div class="d-grid gap-2 d-lg-flex">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="fas fa-filter"></i> Uygula
                                    </button>
                                     <a href="<?php echo BASE_URL; ?>/admin/reports/product_report.php" class="btn btn-secondary btn-sm flex-grow-1" title="Filtreleri Temizle">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                     <?php // Session mesajları header'da gösteriliyor varsayalım ?>
                     <?php // include ROOT_PATH . '/includes/show_messages.php'; ?>

                     <div class="row g-2 mb-3"> <?php // g-2 ile daha az boşluk ?>
                         <div class="col-sm-6 col-lg-3">
                             <div class="card bg-light">
                                 <div class="card-body p-2 text-center">
                                     <div class="small text-muted text-uppercase">Toplam Satış Tutarı</div>
                                     <div class="fs-5 fw-bold"><?php echo formatMoney($total_sales_amount); ?></div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-sm-6 col-lg-3">
                              <div class="card bg-light">
                                 <div class="card-body p-2 text-center">
                                     <div class="small text-muted text-uppercase">Toplam Satış Miktarı</div>
                                     <div class="fs-5 fw-bold"><?php echo number_format($total_sales_quantity, 0, ',', '.'); ?></div>
                                 </div>
                             </div>
                         </div>
                         <?php // Diğer özetler buraya eklenebilir ?>
                     </div>


                    <?php if ($product_id_filter > 0 && count($product_reports) === 1): ?>
                        <?php $selected_product = $product_reports[0]; // Seçili ürün verisi ?>
                         <div class="row mb-4">
                             <div class="col-md-6 mb-3 mb-md-0">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 small">Günlük Satış Miktarı: <?php echo htmlspecialchars($selected_product['name']); ?></h6>
                                         <a href="?product_id=0&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-close btn-sm" title="Tüm Ürünlere Dön"></a>
                                    </div>
                                    <div class="card-body">
                                         <?php if (!empty($daily_sales)): ?>
                                            <div class="chart-container" style="position: relative; height:200px; width:100%">
                                                <canvas id="dailySalesChart"></canvas>
                                            </div>
                                         <?php else: ?>
                                            <div class="alert alert-warning mb-0 small p-2 text-center">Bu ürün için seçili tarih aralığında satış verisi yok.</div>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 small">Stok Durumu</h6>
                                    </div>
                                    <div class="card-body d-flex align-items-center justify-content-center">
                                         <?php if ($current_stock !== 0 || $min_stock !== 0 || $max_stock !== 0): ?>
                                            <div class="text-center">
                                                <div class="d-inline-block position-relative" style="width: 100px; height: 100px;">
                                                    <canvas id="stockChart"></canvas>
                                                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                                                        <span class="h4 fw-bold mb-0 d-block"><?php echo number_format($current_stock, 0, ',', '.'); ?></span>
                                                        <small class="text-muted">adet</small>
                                                    </div>
                                                </div>
                                                 <div class="mt-2 small">
                                                     <span class="text-muted">Min: <?php echo $min_stock; ?></span> |
                                                     <span class="text-muted">Max: <?php echo $max_stock; ?></span> |
                                                     <span class="badge <?php
                                                          if ($stock_status == 'low') echo 'bg-danger';
                                                          elseif ($stock_status == 'high') echo 'bg-success';
                                                          else echo 'bg-info';
                                                     ?>">
                                                     <?php
                                                         if ($stock_status == 'low') echo 'Kritik';
                                                         elseif ($stock_status == 'high') echo 'Yüksek';
                                                         else echo 'Normal';
                                                     ?>
                                                     </span>
                                                 </div>
                                            </div>
                                         <?php else: ?>
                                            <div class="alert alert-secondary mb-0 small p-2 text-center">Stok takibi aktif değil.</div>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                         <?php if (!empty($top_customers)): ?>
                         <div class="row mb-4">
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 small">En Çok Bu Ürünü Alan Müşteriler</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                             <table class="table table-sm table-hover table-striped mb-0 align-middle">
                                                 <thead class="table-light">
                                                    <tr>
                                                        <th>Müşteri</th>
                                                        <th class="text-center">Sipariş</th>
                                                        <th class="text-center">Miktar</th>
                                                        <th class="text-end">Toplam Tutar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_customers as $customer): ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($customer['bakery_name']); ?>
                                                                <small class="text-muted d-block"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></small>
                                                            </td>
                                                            <td class="text-center"><?php echo number_format($customer['order_count'], 0, ',', '.'); ?></td>
                                                            <td class="text-center"><?php echo number_format($customer['total_quantity'], 0, ',', '.'); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($customer['total_sales']); ?></td>
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
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle datatable" id="productReportTable"> <?php // Stil sınıfları eklendi ?>
                            <thead class="table-light">
                                <tr>
                                    <?php // Sütun başlıkları bread_types'a uygun ?>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th style="width: 70px;" class="text-center">Resim</th>
                                    <th>Ad</th>
                                    <th class="text-end">Fiyat</th>
                                    <th>Satış Tipi</th>
                                    <th class="text-center">Paket</th>
                                    <th class="text-center">Durum</th>
                                     <?php // Satış verileri opsiyonel olarak eklenebilir veya tooltip'te gösterilebilir ?>
                                     <th class="text-center" title="Seçili Tarih Aralığındaki Toplam Satış Miktarı">Satış Mikt.</th>
                                     <th class="text-end" title="Seçili Tarih Aralığındaki Toplam Satış Tutarı">Satış Tutarı</th>
                                    <th class="text-center" style="width: 120px;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($product_reports)): ?>
                                    <?php foreach ($product_reports as $report): ?>
                                        <tr class="<?php echo ($report['status'] ?? 1) == 0 ? 'table-secondary text-muted' : ''; // Pasifleri gri yap ?>">
                                            <td class="text-center fw-bold"><?php echo $report['id']; ?></td>
                                            <td class="text-center">
                                                <?php // Resim gösterme kodu (Ekmek listesi sayfasından alınabilir) ?>
                                                 <?php
                                                    $image_url = BASE_URL . '/assets/images/no-image.png'; // Varsayılan
                                                    if (!empty($report['image']) && file_exists(ROOT_PATH . '/uploads/' . $report['image'])) {
                                                        $image_url = BASE_URL . '/uploads/' . htmlspecialchars($report['image']);
                                                    }
                                                ?>
                                                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($report['name']); ?>" width="40" height="40" class="rounded img-thumbnail p-0 border">
                                            </td>
                                            <td>
                                                 <a href="?product_id=<?php echo $report['id']; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                                     <?php echo htmlspecialchars($report['name']); ?>
                                                 </a>
                                                 <?php if (!empty($report['description'])): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars(mb_strimwidth($report['description'], 0, 50, "...")); // Açıklamayı kısalt ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?php echo formatMoney($report['price']); ?></td>
                                            <td>
                                                <?php // Satış tipi formatlama
                                                    switch($report['sale_type'] ?? '') {
                                                        case 'piece': echo 'Adet'; break;
                                                        case 'box': echo 'Kasa (' . htmlspecialchars($report['box_capacity'] ?? '?') . ')'; break;
                                                        case 'both': echo 'Adet ve Kasa (' . htmlspecialchars($report['box_capacity'] ?? '?') . ')'; break;
                                                        default: echo '-';
                                                    }
                                                ?>
                                            </td>
                                             <td class="text-center">
                                                 <?php // Paket durumu
                                                    if ($report['is_packaged'] ?? 0) {
                                                        echo '<i class="fas fa-box text-success" title="Paketli (' . htmlspecialchars($report['package_weight'] ?? '?') . ' gr)"></i>';
                                                    } else {
                                                        echo '<i class="fas fa-times text-muted" title="Paketli Değil"></i>';
                                                    }
                                                 ?>
                                             </td>
                                             <td class="text-center">
                                                 <?php // Durum (Aktif/Pasif Butonu) - Ekmek listesindeki gibi ?>
                                                 <?php $isActive = ($report['status'] ?? 0); ?>
                                                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/bread/index.php?action=toggle&id=<?php echo $report['id']; ?>&ref=report" <?php // ref=report ekleyerek rapor sayfasına geri yönlendirme sağlanabilir ?>
                                                    class="btn btn-sm <?php echo $isActive ? 'btn-outline-success' : 'btn-outline-secondary'; ?>"
                                                    title="<?php echo $isActive ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                                    <?php if ($isActive): ?>
                                                        <i class="fas fa-check-circle"></i> Aktif
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle"></i> Pasif
                                                    <?php endif; ?>
                                                </a>
                                             </td>
                                             <?php // Hesaplanan satış verileri (Opsiyonel) ?>
                                             <td class="text-center"><?php echo number_format($report['total_quantity'] ?? 0, 0, ',', '.'); ?></td>
                                             <td class="text-end"><?php echo formatMoney($report['total_sales'] ?? 0); ?></td>
                                            <td class="text-center">
                                                <?php // İşlem Butonları (Ekmek listesi sayfasındaki gibi) ?>
                                                 <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/bread/view.php?id=<?php echo $report['id']; ?>" class="btn btn-outline-info" title="Görüntüle">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                     <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/bread/index.php?action=delete&id=<?php echo $report['id']; ?>&ref=report"
                                                        class="btn btn-outline-danger" title="Sil (Pasif Yap)"
                                                        onclick="return confirm('Bu ekmek çeşidini pasif hale getirmek istediğinize emin misiniz?\nSiparişlerde kullanılmıyorsa pasif yapılacaktır.');">
                                                         <i class="fas fa-trash"></i>
                                                     </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4"> <?php // colspan güncellendi ?>
                                            Raporlanacak ürün bulunamadı.
                                            <?php if (empty($products_for_filter)): ?>
                                                 <br><small>Sistemde hiç aktif ekmek çeşidi tanımlanmamış olabilir.</small>
                                                 <a href="<?php echo BASE_URL; ?>/admin/bread/add.php" class="btn btn-sm btn-primary mt-2">Yeni Ekmek Ekle</a>
                                            <?php endif; ?>
                                         </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Seçili Ürün Grafikleri ---
    <?php if ($product_id_filter > 0): ?>
        // Günlük Satış Grafiği
        const dailyCtx = document.getElementById('dailySalesChart');
        if (dailyCtx && <?php echo !empty($daily_sales) ? 'true' : 'false'; ?>) {
            const dailyLabels = <?php echo json_encode($chart_labels); ?>;
            const dailyValues = <?php echo json_encode($chart_values); ?>;
            new Chart(dailyCtx.getContext('2d'), {
                type: 'line', data: { labels: dailyLabels, datasets: [{ label: 'Satış Miktarı', data: dailyValues, borderColor: 'rgba(75, 192, 192, 1)', backgroundColor: 'rgba(75, 192, 192, 0.1)', fill: true, tension: 0.1 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
            });
        }

        // Stok Grafiği
        const stockCtx = document.getElementById('stockChart');
         if (stockCtx && <?php echo ($current_stock !== 0 || $min_stock !== 0 || $max_stock !== 0) ? 'true' : 'false'; ?>) {
            const stockData = [ <?php echo $current_stock; ?>, <?php echo $max_stock > $current_stock ? max(0, $max_stock - $current_stock) : 0; ?> ]; // Negatif olmasın
            const stockBgColors = [
                '<?php if ($stock_status == 'low') echo 'rgba(220, 53, 69, 0.8)'; elseif ($stock_status == 'high') echo 'rgba(25, 135, 84, 0.8)'; else echo 'rgba(13, 202, 240, 0.8)'; // BS5 renkleri ?>',
                'rgba(233, 236, 239, 0.5)' // bg-light gibi
            ];
            new Chart(stockCtx.getContext('2d'), {
                 type: 'doughnut', data: { labels: ['Mevcut', 'Boş'], datasets: [{ data: stockData, backgroundColor: stockBgColors, borderWidth: 0 }] },
                 options: { cutout: '75%', responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
            });
        }
    <?php endif; ?>

    // --- Export ve Print Butonları ---
     const tableId = '#productReportTable'; // Tablo ID'si
     const reportTitle = '<?php echo "Ürün Raporu - " . date('d.m.Y'); ?>';
     const reportDateRange = '<?php echo date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)); ?>';
     const selectedProductText = '<?php echo $product_id_filter > 0 ? $selected_product['name'] : 'Tüm Ürünler'; ?>';


    // Excel Export
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            try {
                const table = document.querySelector(tableId);
                if (!table) { console.error('Table not found for Excel export'); return; }
                // Görünmeyen sütunları veya butonları klonlamadan önce kaldır (isteğe bağlı)
                const clonedTable = table.cloneNode(true);
                 // Örneğin işlem sütununu kaldır
                 Array.from(clonedTable.querySelectorAll('tr')).forEach(row => row.deleteCell(-1)); // Son sütun

                const wb = XLSX.utils.table_to_book(clonedTable, {sheet: "Ürün Raporu"});
                XLSX.writeFile(wb, reportTitle + '.xlsx');
            } catch(e) { console.error('Excel export error:', e); alert('Excel dışa aktarma sırasında bir hata oluştu.'); }
        });
    }

    // PDF Export - DÜZELTILMIŞ KOD
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    if (exportPdfBtn) {
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

                // Başlık ve bilgiler
                doc.setFontSize(14);
                doc.text(reportTitle, 14, 15);
                
                doc.setFontSize(10);
                doc.text("Tarih Aralığı: " + reportDateRange, 14, 21);
                doc.text("Ürün Filtresi: " + selectedProductText, 14, 27);

                // Tabloyu PDF'e dönüştür
                doc.autoTable({
                    html: tableId,
                    startY: 32,
                    theme: 'grid',
                    styles: { 
                        fontSize: 7, 
                        cellPadding: 1.5, 
                        overflow: 'linebreak' 
                    },
                    headStyles: { 
                        fillColor: [23, 162, 184], 
                        textColor: 255, 
                        fontSize: 7, 
                        fontStyle: 'bold' 
                    },
                    columnStyles: {
                        0: { cellWidth: 10 },   // ID
                        1: { cellWidth: 15 },   // Resim
                        2: { cellWidth: 'auto' }, // Ad
                        3: { halign: 'right', cellWidth: 20 }, // Fiyat
                        4: { cellWidth: 25 },   // Satış Tipi
                        5: { halign: 'center', cellWidth: 15 }, // Paket
                        6: { halign: 'center', cellWidth: 20 }, // Durum
                        7: { halign: 'center', cellWidth: 20 }, // Satış Mikt.
                        8: { halign: 'right', cellWidth: 25 },  // Satış Tutarı
                        9: { cellWidth: 0 }     // İşlemler sütununu gizle
                    },
                    didParseCell: function(data) {
                        // Resim ve İşlem sütunlarını temizle
                        if (data.column.index === 1 || data.column.index === 9) {
                            data.cell.text = '';
                        }
                        // Fiyat ve Tutar sütunlarını formatla
                        if(data.column.index === 3 || data.column.index === 8) {
                            let text = data.cell.text[0];
                            if (text && !isNaN(text.replace(/[^0-9.,-]/g, '').replace(',', '.'))) {
                                data.cell.text = parseFloat(text.replace(/[^0-9.,-]/g, '').replace(',', '.')).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL';
                            }
                        }
                    }
                });

                // PDF'i indir
                doc.save(reportTitle + '.pdf');
                
            } catch(e) { 
                console.error('PDF export error:', e); 
                alert('PDF dışa aktarma sırasında bir hata oluştu: ' + e.message); 
            }
        });
    }

    // Yazdır
    const printBtn = document.getElementById('printReportBtn');
    if(printBtn) {
        printBtn.addEventListener('click', function() {
            // Yazdırmadan önce gizlenecek elementler
             const elementsToHideQuery = '.filter-form, .card-header .btn-group, #adminSidebar, header.bg-dark, footer.bg-dark, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate';
             let hiddenElements = [];
             try {
                 document.querySelectorAll(elementsToHideQuery).forEach(el => {
                     if (window.getComputedStyle(el).display !== 'none') {
                         hiddenElements.push({element: el, originalDisplay: el.style.display});
                         el.style.display = 'none';
                     }
                 });
                 // Tablo işlem sütununu gizle
                  document.querySelectorAll('#productReportTable th:last-child, #productReportTable td:last-child').forEach(el => {
                      hiddenElements.push({element: el, originalDisplay: el.style.display});
                      el.style.display = 'none';
                  });

                  window.print();

             } catch(e) { console.error("Print preparation error:", e); window.print(); } // Hata olsa bile yazdırmayı dene
             finally {
                  // Gizlenen elementleri geri göster
                 hiddenElements.forEach(item => item.element.style.display = item.originalDisplay);
             }
        });
    }

     // DataTables Başlatma (Eğer datatable sınıfı kullanılıyorsa)
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>