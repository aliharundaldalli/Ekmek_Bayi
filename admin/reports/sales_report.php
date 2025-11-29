<?php
/**
 * Satış Raporu
 * Bu dosya, günlük, haftalık, aylık ve yıllık satış raporlarını gösterir.
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';

// Sayfa başlığı ve aktif menü
$page_title = 'Satış Raporu';
$current_page = 'reports';
$current_submenu = 'sales';

// Rapor türü ve tarih parametreleri
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// En fazla 3 ay geriye git
$max_date_limit = date('Y-m-d', strtotime('-3 months'));
if (strtotime($date_from) < strtotime($max_date_limit)) {
    $date_from = $max_date_limit;
}

// Rapor tipine göre başlık belirle
$report_titles = [
    'daily' => 'Günlük Satış Raporu',
    'weekly' => 'Haftalık Satış Raporu',
    'monthly' => 'Aylık Satış Raporu',
    'yearly' => 'Yıllık Satış Raporu',
    'customer' => 'Müşteri Bazlı Satış Raporu'
];

$report_title = $report_titles[$report_type] ?? 'Satış Raporu';

// Kullanıcıları getir
try {
    // Disable ONLY_FULL_GROUP_BY for this session to avoid aggregation errors
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    $stmt = $pdo->query("SELECT id, bakery_name, first_name, last_name FROM users WHERE role = 'user' AND status = 1 ORDER BY bakery_name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    error_log("Users query error: " . $e->getMessage());
}

// Seçili kullanıcının adını al (PDF için)
$selected_user_name = '';
if ($user_id > 0 && !empty($users)) {
    foreach ($users as $user) {
        if ($user['id'] == $user_id) {
            $selected_user_name = $user['bakery_name'];
            break;
        }
    }
}

// Rapor verilerini al
$report_data = [];
$total_sales = 0;
$total_orders = 0;
$total_items = 0;
$chart_labels = [];
$chart_values = [];

try {
    switch ($report_type) {
        case 'daily':
            $group_by = "DATE(o.created_at)";
            $format = "%d.%m.%Y"; // gün.ay.yıl
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
            break;
            
        case 'weekly':
            $group_by = "YEARWEEK(o.created_at, 1)";
            $format = "%v. Hafta, %Y"; // hafta numarası ve yıl
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
            break;
            
        case 'monthly':
            $group_by = "DATE_FORMAT(o.created_at, '%Y-%m')";
            $format = "%M %Y"; // ay ve yıl
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
            break;
            
        case 'yearly':
            $group_by = "YEAR(o.created_at)";
            $format = "%Y"; // yıl
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
            break;
            
        case 'customer':
            $group_by = "o.user_id";
            $format = ""; // Müşteri ismi zaten alınacak
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
            break;
            
        default:
            $group_by = "DATE(o.created_at)";
            $format = "%d.%m.%Y";
            $date_query = "DATE(o.created_at) BETWEEN :date_from AND :date_to";
    }
    
    // DÜZELTME: Tutarlı toplam satış hesaplaması için SUM(oi.total_price) yerine SUM(o.total_amount) kullanma
    $query = "
        SELECT 
            {$group_by} AS period,
            DATE_FORMAT(o.created_at, '{$format}') AS formatted_period,
            u.bakery_name,
            u.first_name,
            u.last_name,
            COUNT(DISTINCT o.id) AS total_orders,
            SUM(oi.total_price) AS total_sales,
            SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS total_items
        FROM 
            orders o
        LEFT JOIN 
            users u ON o.user_id = u.id
        LEFT JOIN 
            order_items oi ON o.id = oi.order_id
        WHERE 
            {$date_query}
            AND o.status != 'cancelled'
    ";
    
    // Müşteri filtresi
    if ($user_id > 0) {
        $query .= " AND o.user_id = :user_id";
    }
    
    $query .= " GROUP BY {$group_by}";
    
    if ($report_type == 'customer') {
        $query .= ", o.user_id";
    }
    
    $query .= " ORDER BY ";
    
    if ($report_type == 'customer') {
        $query .= "total_sales DESC";
    } else {
        $query .= "period ASC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':date_from', $date_from);
    $stmt->bindParam(':date_to', $date_to);
    
    if ($user_id > 0) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplamları hesapla
    foreach ($report_data as $row) {
        $total_sales += $row['total_sales'];
        $total_orders += $row['total_orders'];
        $total_items += $row['total_items'];
        
        if ($report_type == 'customer') {
            $chart_labels[] = $row['bakery_name'];
        } else {
            $chart_labels[] = $row['formatted_period'];
        }
        
        $chart_values[] = $row['total_sales'];
    }
    
    // Eğer toplam sıfırsa, doğrudan toplam satışları hesapla (DÜZELTME)
    if ($total_sales == 0) {
        // İkinci bir sorgu ile toplam satışları al
        $total_query = "
            SELECT 
                SUM(oi.total_price) AS total_sales,
                COUNT(DISTINCT o.id) AS total_orders,
                SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS total_items
            FROM 
                orders o
            JOIN 
                order_items oi ON o.id = oi.order_id
            WHERE 
                {$date_query}
                AND o.status != 'cancelled'
        ";
        
        if ($user_id > 0) {
            $total_query .= " AND o.user_id = :user_id";
        }
        
        $stmt_total = $pdo->prepare($total_query);
        $stmt_total->bindParam(':date_from', $date_from);
        $stmt_total->bindParam(':date_to', $date_to);
        
        if ($user_id > 0) {
            $stmt_total->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        
        $stmt_total->execute();
        $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
        
        if ($total_row) {
            $total_sales = $total_row['total_sales'] ?? 0;
            $total_orders = $total_row['total_orders'] ?? 0;
            $total_items = $total_row['total_items'] ?? 0;
        }
    }
    
} catch (PDOException $e) {
    $report_data = [];
    error_log("Report query error: " . $e->getMessage());
    $_SESSION['error_message'] = "Rapor verileri alınırken bir hata oluştu: " . $e->getMessage();
}

// En çok satılan ürünleri getir
try {
    // oi.total_price'ı kullanarak toplam satışı hesapla (DÜZELTME)
    $top_products_query = "
        SELECT 
            b.name AS bread_name,
            SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS total_quantity,
            SUM(oi.total_price) AS total_sales
        FROM 
            order_items oi
        JOIN 
            orders o ON oi.order_id = o.id
        JOIN 
            bread_types b ON oi.bread_id = b.id
        WHERE 
            {$date_query}
            AND o.status != 'cancelled'
    ";
    
    if ($user_id > 0) {
        $top_products_query .= " AND o.user_id = :user_id";
    }
    
    $top_products_query .= "
        GROUP BY 
            oi.bread_id
        ORDER BY 
            total_quantity DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($top_products_query);
    $stmt->bindParam(':date_from', $date_from);
    $stmt->bindParam(':date_to', $date_to);
    
    if ($user_id > 0) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ürünlerin toplam satışını kontrol et (DÜZELTME)
    $products_total = 0;
    foreach ($top_products as $product) {
        $products_total += $product['total_sales'];
    }
    
    // Debug için yorum
    // error_log("Top Products Total: " . $products_total . " vs Total Sales: " . $total_sales);
    
} catch (PDOException $e) {
    $top_products = [];
    error_log("Top products query error: " . $e->getMessage());
}

// Header'ı dahil et
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0"><?php echo $report_title; ?></h5>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <button type="button" class="btn btn-sm btn-success" id="exportExcelBtn">
                                <i class="fas fa-file-excel fa-sm"></i> Excel'e Aktar
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" id="exportPdfBtn">
                                <i class="fas fa-file-pdf fa-sm"></i> PDF'e Aktar
                            </button>
                            <button type="button" class="btn btn-sm btn-info" id="printReportBtn">
                                <i class="fas fa-print fa-sm"></i> Yazdır
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filtre Formu -->
                <div class="card-body border-bottom">
                    <form action="" method="get">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="type" class="form-label small">Rapor Türü</label>
                                <select name="type" id="type" class="form-control form-control-sm">
                                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Günlük Rapor</option>
                                    <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Haftalık Rapor</option>
                                    <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Aylık Rapor</option>
                                    <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yıllık Rapor</option>
                                    <option value="customer" <?php echo $report_type == 'customer' ? 'selected' : ''; ?>>Müşteri Bazlı Rapor</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="date_from" class="form-label small">Başlangıç Tarihi</label>
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="date_to" class="form-label small">Bitiş Tarihi</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="user_id" class="form-label small">Müşteri</label>
                                <select name="user_id" id="user_id" class="form-control form-control-sm">
                                    <option value="0">Tüm Müşteriler</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo $user['bakery_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter fa-sm"></i> Filtrele
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="card-body">
                    <!-- Özet Kartları -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-white mb-0">Toplam Satış</h6>
                                            <h3 class="text-white mb-0"><?php echo number_format($total_sales, 2, ',', '.') . ' TL'; ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-white mb-0">Toplam Sipariş</h6>
                                            <h3 class="text-white mb-0"><?php echo number_format($total_orders, 0, ',', '.'); ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-white mb-0">Toplam Ürün</h6>
                                            <h3 class="text-white mb-0"><?php echo number_format($total_items, 0, ',', '.'); ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-bread-slice fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grafik Alanı -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card shadow-sm">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0">Satış Grafiği</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="salesChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0">En Çok Satılan Ürünler</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($top_products)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm" id="topProductsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Ürün</th>
                                                        <th class="text-center">Miktar</th>
                                                        <th class="text-right">Satış</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_products as $product): ?>
                                                        <tr>
                                                            <td><?php echo $product['bread_name']; ?></td>
                                                            <td class="text-center"><?php echo number_format($product['total_quantity'], 0, ',', '.'); ?></td>
                                                            <td class="text-right"><?php echo number_format($product['total_sales'], 2, ',', '.') . ' TL'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <?php if (count($top_products) > 1): ?>
                                                <tfoot>
                                                    <tr class="font-weight-bold">
                                                        <td>TOPLAM</td>
                                                        <td class="text-center">
                                                            <?php 
                                                                $total_top_quantity = 0;
                                                                foreach ($top_products as $p) {
                                                                    $total_top_quantity += $p['total_quantity'];
                                                                }
                                                                echo number_format($total_top_quantity, 0, ',', '.');
                                                            ?>
                                                        </td>
                                                        <td class="text-right">
                                                            <?php 
                                                                $total_top_sales = 0;
                                                                foreach ($top_products as $p) {
                                                                    $total_top_sales += $p['total_sales'];
                                                                }
                                                                echo number_format($total_top_sales, 2, ',', '.') . ' TL';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            Bu tarih aralığında henüz satış verisi bulunmuyor.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rapor Tablosu -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0">Detaylı Satış Verileri</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($report_data)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="reportTable">
                                        <thead class="bg-light">
                                            <tr>
                                                <?php if ($report_type == 'customer'): ?>
                                                    <th>Müşteri</th>
                                                <?php else: ?>
                                                    <th>Dönem</th>
                                                <?php endif; ?>
                                                <th class="text-center">Sipariş Sayısı</th>
                                                <th class="text-center">Ürün Miktarı</th>
                                                <th class="text-right">Toplam Satış</th>
                                                <th class="text-right">Ortalama Sipariş</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <?php 
                                                    $avg_order = $row['total_orders'] > 0 ? $row['total_sales'] / $row['total_orders'] : 0;
                                                ?>
                                                <tr>
                                                    <?php if ($report_type == 'customer'): ?>
                                                        <td><?php echo $row['bakery_name']; ?></td>
                                                    <?php else: ?>
                                                        <td><?php echo $row['formatted_period']; ?></td>
                                                    <?php endif; ?>
                                                    <td class="text-center"><?php echo number_format($row['total_orders'], 0, ',', '.'); ?></td>
                                                    <td class="text-center"><?php echo number_format($row['total_items'], 0, ',', '.'); ?></td>
                                                    <td class="text-right"><?php echo number_format($row['total_sales'], 2, ',', '.') . ' TL'; ?></td>
                                                    <td class="text-right"><?php echo number_format($avg_order, 2, ',', '.') . ' TL'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-light font-weight-bold">
                                            <tr>
                                                <td>TOPLAM</td>
                                                <td class="text-center"><?php echo number_format($total_orders, 0, ',', '.'); ?></td>
                                                <td class="text-center"><?php echo number_format($total_items, 0, ',', '.'); ?></td>
                                                <td class="text-right"><?php echo number_format($total_sales, 2, ',', '.') . ' TL'; ?></td>
                                                <td class="text-right">
                                                    <?php 
                                                        $overall_avg = $total_orders > 0 ? $total_sales / $total_orders : 0;
                                                        echo number_format($overall_avg, 2, ',', '.') . ' TL'; 
                                                    ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info m-3">
                                    Bu filtre koşullarına göre satış verisi bulunamadı.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js scriptini dahil et -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<!-- SheetJS (Excel export için) -->
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>

<!-- jsPDF (PDF export için) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grafik verisi
    const labels = <?php echo json_encode($chart_labels); ?>;
    const values = <?php echo json_encode($chart_values); ?>;
    
    // Grafik oluştur
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: '<?php echo $report_type == 'customer' ? 'bar' : 'line'; ?>',
        data: {
            labels: labels,
            datasets: [{
                label: 'Satış Tutarı (TL)',
                data: values,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('tr-TR') + ' TL';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString('tr-TR') + ' TL';
                        }
                    }
                }
            }
        }
    });
    
    // Excel'e aktar
    document.getElementById('exportExcelBtn').addEventListener('click', function() {
        try {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('Tablo bulunamadı');
                return;
            }
            
            const wb = XLSX.utils.book_new();
            
            // Ana rapor tablosunu ekle
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, "Satış Raporu");
            
            // Top ürünler tablosunu da ekle
            const topProductsTable = document.getElementById('topProductsTable');
            if (topProductsTable) {
                const ws2 = XLSX.utils.table_to_sheet(topProductsTable);
                XLSX.utils.book_append_sheet(wb, ws2, "En Çok Satılan Ürünler");
            }
            
            // Dosya adı oluştur
            const fileName = '<?php echo str_replace(' ', '_', $report_title); ?>_<?php echo date('Y-m-d'); ?>.xlsx';
            
            // Excel dosyasını indir
            XLSX.writeFile(wb, fileName);
        } catch (e) {
            console.error('Excel export error:', e);
            alert('Excel dışa aktarma sırasında bir hata oluştu: ' + e.message);
        }
    });
    
    // PDF'e aktar
    document.getElementById('exportPdfBtn').addEventListener('click', function() {
        try {
            // jsPDF'i doğru şekilde başlat
            if (typeof window.jspdf === 'undefined') {
                alert('PDF kütüphanesi yüklenemedi. Sayfayı yenileyin veya başka bir tarayıcı deneyin.');
                return;
            }
            
            const { jsPDF } = window.jspdf;
            
            // Yatay (landscape) modunda PDF oluştur
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });
            
            // Başlık
            doc.setFontSize(16);
            doc.text('<?php echo $report_title; ?>', 14, 15);
            
            // Tarih aralığı ve filtre bilgileri
            doc.setFontSize(10);
            let filterText = 'Tarih: <?php echo date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)); ?>';
            
            // Müşteri filtresi varsa ekle
            <?php if (!empty($selected_user_name)): ?>
            filterText += ' | Müşteri: <?php echo addslashes($selected_user_name); ?>';
            <?php endif; ?>
            
            doc.text(filterText, 14, 22);
            
            // Tabloyu PDF'e dönüştür
            doc.autoTable({
                html: '#reportTable',
                startY: 28,
                theme: 'grid',
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    overflow: 'linebreak'
                },
                headStyles: {
                    fillColor: [54, 162, 235], // Mavi
                    textColor: 255, // Beyaz
                    fontStyle: 'bold',
                    halign: 'center'
                },
                footStyles: {
                    fillColor: [240, 240, 240], // Açık gri
                    textColor: 0, // Siyah
                    fontStyle: 'bold'
                },
                columnStyles: {
                    0: { cellWidth: 'auto' },
                    1: { halign: 'center', cellWidth: 30 },  // Sipariş Sayısı
                    2: { halign: 'center', cellWidth: 30 },  // Ürün Miktarı
                    3: { halign: 'right', cellWidth: 35 },   // Toplam Satış
                    4: { halign: 'right', cellWidth: 35 }    // Ortalama Sipariş
                },
                didParseCell: function(data) {
                    // Para birimiyle ilgili sütunlar
                    if (data.column.index === 3 || data.column.index === 4) {
                        // Hücre boş değilse ve geçerli değer varsa
                        if (data.cell.text && data.cell.text.length > 0 && data.cell.text[0] !== '-') {
                            let text = data.cell.text[0];
                            
                            // TL ve diğer karakterleri kaldır, sayısal değeri çıkar
                            let numericValue = text.replace(/[^0-9,.-]/g, '').replace('.', '').replace(',', '.');
                            let number = parseFloat(numericValue);
                            
                            if (!isNaN(number)) {
                                // Türk lirasına formatla
                                data.cell.text = number.toLocaleString('tr-TR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ' TL';
                            }
                        }
                    }
                },
                didDrawPage: function(data) {
                    // Sayfa numarası ekle
                    doc.setFontSize(8);
                    doc.text('Sayfa ' + doc.internal.getNumberOfPages(), 
                           data.settings.margin.left, 
                           doc.internal.pageSize.height - 10);
                    
                    // Oluşturulma tarihi ekle
                    doc.text('Oluşturulma: ' + new Date().toLocaleDateString('tr-TR'), 
                            doc.internal.pageSize.width - data.settings.margin.right, 
                            doc.internal.pageSize.height - 10, 
                            { align: 'right' });
                }
            });
            
            // En çok satılan ürünler tablosunu da ekle (yeni sayfa)
            if (document.getElementById('topProductsTable')) {
                doc.addPage();
                
                // En çok satılan ürünler başlığı
                doc.setFontSize(14);
                doc.text('En Çok Satılan Ürünler', 14, 15);
                
                // Aynı filtre bilgisi
                doc.setFontSize(10);
                doc.text(filterText, 14, 22);
                
                doc.autoTable({
                    html: '#topProductsTable',
                    startY: 28,
                    theme: 'grid',
                    styles: {
                        fontSize: 8,
                        cellPadding: 2
                    },
                    headStyles: {
                        fillColor: [54, 162, 235],
                        textColor: 255,
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    footStyles: {
                        fillColor: [240, 240, 240],
                        textColor: 0,
                        fontStyle: 'bold'
                    },
                    columnStyles: {
                        0: { cellWidth: 'auto' },
                        1: { halign: 'center', cellWidth: 30 },
                        2: { halign: 'right', cellWidth: 35 }
                    },
                    didParseCell: function(data) {
                        // Para birimi formatı (Satış sütunu için)
                        if (data.column.index === 2) {
                            if (data.cell.text && data.cell.text.length > 0 && data.cell.text[0] !== '-') {
                                let text = data.cell.text[0];
                                let numericValue = text.replace(/[^0-9,.-]/g, '').replace('.', '').replace(',', '.');
                                let number = parseFloat(numericValue);
                                
                                if (!isNaN(number)) {
                                    data.cell.text = number.toLocaleString('tr-TR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' TL';
                                }
                            }
                        }
                    }
                });
            }
            
            // Dosya adı oluştur
            const fileName = '<?php echo str_replace(' ', '_', $report_title); ?>_<?php echo date('Y-m-d'); ?>.pdf';
            
            // PDF dosyasını indir
            doc.save(fileName);
            
        } catch(e) {
            console.error('PDF export error:', e);
            alert('PDF oluşturulurken bir hata oluştu: ' + e.message);
        }
    });
    
    // Yazdır fonksiyonu
    document.getElementById('printReportBtn').addEventListener('click', function() {
        window.print();
    });
});
</script>

<?php
// Footer'ı dahil et
include_once ROOT_PATH . '/admin/footer.php';
?>