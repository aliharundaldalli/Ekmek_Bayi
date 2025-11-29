<?php
/**
 * Raporlar Anasayfası (Yeniden Stillendirilmiş ve Hata Düzeltmeleriyle)
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php'; // Gerekli dosyalar ve tanımlamalar
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Admin kontrolü

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Raporlar';
$current_page = 'reports'; // Sidebar için
$current_submenu = 'dashboard'; // Raporlar alt menüsü için (varsa)

// --- Veri Çekme (Son 30 Gün) ---
try {
    // Disable ONLY_FULL_GROUP_BY for this session to avoid aggregation errors
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    $date_from = date('Y-m-d', strtotime('-30 days'));
    $date_to = date('Y-m-d');

    // Günlük satış verileri (Chart için)
    $query_daily = "
        SELECT
            DATE(o.created_at) AS sale_date,
            DATE_FORMAT(DATE(o.created_at), '%d.%m') AS formatted_date,
            COUNT(DISTINCT o.id) AS order_count,
            SUM(oi.total_price) AS daily_sales
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status != 'cancelled'
            AND DATE(o.created_at) BETWEEN :date_from AND :date_to
        GROUP BY DATE(o.created_at)
        ORDER BY sale_date ASC
    ";
    $stmt_daily = $pdo->prepare($query_daily);
    $stmt_daily->bindParam(':date_from', $date_from);
    $stmt_daily->bindParam(':date_to', $date_to);
    $stmt_daily->execute();
    $daily_sales = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

    // Özet Verileri (Toplamlar)
    // SQL'de COALESCE kullanarak NULL değerler yerine 0 döndürülmesi sağlandı.
    $query_summary = "
        SELECT
            COALESCE(COUNT(DISTINCT o.id), 0) AS total_orders,
            COALESCE(SUM(oi.total_price), 0) AS total_sales,
            COALESCE(SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END), 0) AS total_items,
            COALESCE(COUNT(DISTINCT o.user_id), 0) AS total_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status != 'cancelled'
            AND DATE(o.created_at) BETWEEN :date_from AND :date_to
    ";
    $stmt_summary = $pdo->prepare($query_summary);
    $stmt_summary->bindParam(':date_from', $date_from);
    $stmt_summary->bindParam(':date_to', $date_to);
    $stmt_summary->execute();
    $summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);


    // En çok satılan ürünler (Top 5)
    $query_top_products = "
        SELECT
            b.name AS product_name,
            SUM(CASE WHEN oi.sale_type = 'box' THEN oi.quantity * oi.pieces_per_box ELSE oi.quantity END) AS total_quantity,
            SUM(oi.total_price) AS total_sales
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN bread_types b ON oi.bread_id = b.id
        WHERE o.status != 'cancelled'
            AND DATE(o.created_at) BETWEEN :date_from AND :date_to
        GROUP BY oi.bread_id, b.name
        ORDER BY total_quantity DESC
        LIMIT 5
    ";
    $stmt_top_products = $pdo->prepare($query_top_products);
    $stmt_top_products->bindParam(':date_from', $date_from);
    $stmt_top_products->bindParam(':date_to', $date_to);
    $stmt_top_products->execute();
    $top_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);

    // En çok alım yapan müşteriler (Top 5)
    $query_top_customers = "
        SELECT
            u.bakery_name,
            u.first_name,
            u.last_name,
            COUNT(DISTINCT o.id) AS order_count,
            SUM(oi.total_price) AS total_sales
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status != 'cancelled'
            AND DATE(o.created_at) BETWEEN :date_from AND :date_to
            AND u.role = 'bakery'
        GROUP BY o.user_id, u.bakery_name, u.first_name, u.last_name
        ORDER BY total_sales DESC
        LIMIT 5
    ";
    $stmt_top_customers = $pdo->prepare($query_top_customers);
    $stmt_top_customers->bindParam(':date_from', $date_from);
    $stmt_top_customers->bindParam(':date_to', $date_to);
    $stmt_top_customers->execute();
    $top_customers = $stmt_top_customers->fetchAll(PDO::FETCH_ASSOC);

    // Ürün ve müşteri toplamlarını hesapla
    $total_products_sales = 0;
    foreach ($top_products as $product) {
        $total_products_sales += $product['total_sales'] ?? 0;
    }
    
    $total_customers_sales = 0;
    foreach ($top_customers as $customer) {
        $total_customers_sales += $customer['total_sales'] ?? 0;
    }

    // Grafik verileri
    $chart_labels = [];
    $chart_sales = [];
    $chart_orders = [];

    foreach ($daily_sales as $day) {
        $chart_labels[] = $day['formatted_date'];
        $chart_sales[] = round($day['daily_sales'] ?? 0, 2);
        $chart_orders[] = $day['order_count'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Reports Dashboard query error: " . $e->getMessage());
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['error_message'] = "Rapor verileri alınırken bir hata oluştu: " . $e->getMessage();
    }
    // Hata durumunda varsayılan boş değerler
    $daily_sales = $top_products = $top_customers = $chart_labels = $chart_sales = $chart_orders = [];
    $summary = ['total_orders' => 0, 'total_sales' => 0, 'total_items' => 0, 'total_customers' => 0];
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group btn-group-sm me-2">
            <button type="button" class="btn btn-outline-success" id="exportExcelBtn">
                <i class="fas fa-file-excel me-1"></i> Excel'e Aktar
            </button>
            <button type="button" class="btn btn-outline-danger" id="exportPdfBtn">
                <i class="fas fa-file-pdf me-1"></i> PDF'e Aktar
            </button>
            <button type="button" class="btn btn-outline-secondary" id="printReportBtn">
                <i class="fas fa-print me-1"></i> Yazdır
            </button>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="sales_report.php" class="btn btn-outline-primary">
                <i class="fas fa-chart-line me-1"></i> Satış Raporu
            </a>
            <a href="product_report.php" class="btn btn-outline-success">
                <i class="fas fa-bread-slice me-1"></i> Ürün Raporu
            </a>
            <a href="customer_report.php" class="btn btn-outline-info">
                <i class="fas fa-users me-1"></i> Müşteri Raporu
            </a>
        </div>
    </div>
</div>
<p class="text-muted mb-4">Son 30 günlük verilere genel bakış. (<?php echo date('d.m.Y', strtotime($date_from)); ?> - <?php echo date('d.m.Y', strtotime($date_to)); ?>)</p>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Toplam Satış</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?php echo formatMoney($summary['total_sales'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Toplam Sipariş</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?php echo number_format($summary['total_orders'] ?? 0, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Toplam Ürün (Adet)</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?php echo number_format($summary['total_items'] ?? 0, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Aktif Müşteri (Son 30 Gün)</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?php echo number_format($summary['total_customers'] ?? 0, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-area me-2"></i>Son 30 Gün Satış Grafiği
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($daily_sales)): ?>
                    <div class="chart-container" style="position: relative; height:300px; width:100%">
                        <canvas id="salesChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-2"></i>Son 30 günde grafik için yeterli satış verisi bulunamadı.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-star me-2"></i>En Çok Satılan Ürünler
                </h6>
                <a href="product_report.php" class="btn btn-outline-primary btn-sm">
                    Detaylı Rapor <i class="fas fa-arrow-right fa-xs ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_products)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0" id="topProductsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-center">Miktar</th>
                                    <th class="text-end">Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($product['total_quantity'] ?? 0, 0, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo formatMoney($product['total_sales'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (count($top_products) > 1): ?>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOPLAM</td>
                                    <td class="text-center">
                                        <?php 
                                            $total_quantity = 0;
                                            foreach ($top_products as $product) {
                                                $total_quantity += $product['total_quantity'] ?? 0;
                                            }
                                            echo number_format($total_quantity, 0, ',', '.');
                                        ?>
                                    </td>
                                    <td class="text-end"><?php echo formatMoney($total_products_sales); ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-3 text-center">
                        <i class="fas fa-info-circle me-2"></i>Ürün satış verisi yok.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-crown me-2"></i>En Çok Alım Yapan Müşteriler
                </h6>
                <a href="customer_report.php" class="btn btn-outline-primary btn-sm">
                    Detaylı Rapor <i class="fas fa-arrow-right fa-xs ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_customers)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0" id="topCustomersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Müşteri (Büfe)</th>
                                    <th class="text-center">Sipariş</th>
                                    <th class="text-end">Toplam Alım</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($customer['bakery_name']); ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo number_format($customer['order_count'] ?? 0, 0, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo formatMoney($customer['total_sales'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (count($top_customers) > 1): ?>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOPLAM</td>
                                    <td class="text-center">
                                        <?php 
                                            $total_orders = 0;
                                            foreach ($top_customers as $customer) {
                                                $total_orders += $customer['order_count'] ?? 0;
                                            }
                                            echo number_format($total_orders, 0, ',', '.');
                                        ?>
                                    </td>
                                    <td class="text-end"><?php echo formatMoney($total_customers_sales); ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-3 text-center">
                        <i class="fas fa-info-circle me-2"></i>Müşteri alım verisi yok.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<div class="row mt-4">
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-line me-2"></i>Detaylı Satış Raporu
                </h6>
            </div>
            <div class="card-body d-flex flex-column">
                <p>Günlük, haftalık, aylık ve yıllık satış verilerini tarih aralığı seçerek inceleyin, grafiklerle analiz edin.</p>
                <div class="mt-auto text-end">
                    <a href="sales_report.php" class="btn btn-outline-primary btn-sm">
                        Raporu Görüntüle <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-bread-slice me-2"></i>Detaylı Ürün Raporu
                </h6>
            </div>
            <div class="card-body d-flex flex-column">
                <p>Ürün bazında satış miktarlarını, cirolarını ve popülerliğini tarih aralığı seçerek takip edin.</p>
                <div class="mt-auto text-end">
                    <a href="product_report.php" class="btn btn-outline-success btn-sm">
                        Raporu Görüntüle <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="fas fa-users me-2"></i>Detaylı Müşteri Raporu
                </h6>
            </div>
            <div class="card-body d-flex flex-column">
                <p>Müşterilerinizin sipariş sayılarını, toplam harcamalarını ve satın alma sıklıklarını analiz edin.</p>
                <div class="mt-auto text-end">
                    <a href="customer_report.php" class="btn btn-outline-info btn-sm">
                        Raporu Görüntüle <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- External Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($daily_sales) && !empty($chart_labels)): ?>
    // Sales Chart Initialization
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        const salesChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Satış Tutarı (TL)',
                        data: <?php echo json_encode($chart_sales); ?>,
                        borderColor: 'rgba(78, 115, 223, 1)',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        fill: true,
                        tension: 0.1,
                        yAxisID: 'y',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: 'Sipariş Sayısı',
                        data: <?php echo json_encode($chart_orders); ?>,
                        borderColor: 'rgba(28, 200, 138, 1)',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        fill: false,
                        tension: 0.1,
                        yAxisID: 'y1',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: { // Left Axis (Sales Amount)
                        type: 'linear',
                        position: 'left',
                        grid: {
                            drawBorder: false,
                            color: "rgba(0, 0, 0, 0.05)"
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' });
                            }
                        }
                    },
                    y1: { // Right Axis (Order Count)
                        type: 'linear',
                        position: 'right',
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                if (Number.isInteger(value)) { return value; }
                            },
                            stepSize: 1
                        }
                    },
                    x: { // X Axis (Dates)
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                let value = context.raw;
                                if (context.dataset.yAxisID === 'y') {
                                    label += value.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' });
                                } else {
                                    label += value.toLocaleString('tr-TR');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    } else {
        console.error("Canvas element with ID 'salesChart' not found.");
    }
    <?php endif; ?>

    // Export to Excel
    document.getElementById('exportExcelBtn').addEventListener('click', function() {
        try {
            const wb = XLSX.utils.book_new();
            
            const summaryData = [
                ['Özet Bilgiler', 'Değer'],
                ['Toplam Satış', '<?php echo formatMoney($summary['total_sales'] ?? 0); ?>'],
                ['Toplam Sipariş', '<?php echo number_format($summary['total_orders'] ?? 0, 0, ',', '.'); ?>'],
                ['Toplam Ürün (Adet)', '<?php echo number_format($summary['total_items'] ?? 0, 0, ',', '.'); ?>'],
                ['Aktif Müşteri', '<?php echo number_format($summary['total_customers'] ?? 0, 0, ',', '.'); ?>'],
                ['Tarih Aralığı', '<?php echo date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)); ?>']
            ];
            
            const ws_summary = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, ws_summary, "Özet");
            
            const productsTable = document.getElementById('topProductsTable');
            if (productsTable) {
                const ws_products = XLSX.utils.table_to_sheet(productsTable);
                XLSX.utils.book_append_sheet(wb, ws_products, "En Çok Satılan Ürünler");
            }
            
            const customersTable = document.getElementById('topCustomersTable');
            if (customersTable) {
                const ws_customers = XLSX.utils.table_to_sheet(customersTable);
                XLSX.utils.book_append_sheet(wb, ws_customers, "En Çok Alım Yapan Müşteriler");
            }
            
            const fileName = 'Rapor_Özeti_<?php echo date('d-m-Y'); ?>.xlsx';
            XLSX.writeFile(wb, fileName);
        } catch (e) {
            console.error('Excel export error:', e);
            alert('Excel dışa aktarma sırasında bir hata oluştu: ' + e.message);
        }
    });
    
    // Export to PDF
    document.getElementById('exportPdfBtn').addEventListener('click', function() {
        try {
            if (typeof window.jspdf === 'undefined') {
                alert('PDF kütüphanesi yüklenemedi.');
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            
            // Add a font that supports Turkish characters
            // Note: This requires you to have the font file. For this example, we rely on standard fonts.
            // For full Turkish support, you might need to embed a font like 'Arial'.
            
            doc.setFontSize(16);
            doc.text('Rapor Ozeti', 14, 15); // Using 'Ozeti' for broader compatibility without embedded fonts
            
            doc.setFontSize(10);
            doc.text('Tarih Araligi: <?php echo date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)); ?>', 14, 22);
            
            doc.autoTable({
                startY: 28,
                head: [['Ozet Bilgiler', 'Deger']],
                body: [
                    ['Toplam Satis', '<?php echo formatMoney($summary['total_sales'] ?? 0); ?>'],
                    ['Toplam Siparis', '<?php echo number_format($summary['total_orders'] ?? 0, 0, ',', '.'); ?>'],
                    ['Toplam Urun (Adet)', '<?php echo number_format($summary['total_items'] ?? 0, 0, ',', '.'); ?>'],
                    ['Aktif Musteri', '<?php echo number_format($summary['total_customers'] ?? 0, 0, ',', '.'); ?>']
                ],
                theme: 'grid',
                headStyles: { fillColor: [78, 115, 223] }
            });
            
            let finalY = doc.autoTable.previous.finalY;

            const salesChartCanvas = document.getElementById('salesChart');
            if (salesChartCanvas) {
                html2canvas(salesChartCanvas).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    doc.addPage();
                    doc.setFontSize(12);
                    doc.text('Son 30 Gun Satis Grafigi', 14, 15);
                    doc.addImage(imgData, 'PNG', 14, 22, 180, 80);
                    
                    addTablesToPdf(doc, 110);
                    doc.save('Rapor_Ozeti_<?php echo date('d-m-Y'); ?>.pdf');
                });
            } else {
                 addTablesToPdf(doc, finalY + 10);
                 doc.save('Rapor_Ozeti_<?php echo date('d-m-Y'); ?>.pdf');
            }

        } catch(e) {
            console.error('PDF export error:', e);
            alert('PDF oluşturulurken bir hata oluştu: ' + e.message);
        }
    });

    function addTablesToPdf(doc, startY) {
        let yPos = startY;
        
        if (document.getElementById('topProductsTable')) {
            doc.setFontSize(12);
            doc.text('En Cok Satilan Urunler', 14, yPos);
            doc.autoTable({
                html: '#topProductsTable',
                startY: yPos + 5,
                theme: 'grid',
                headStyles: { fillColor: [78, 115, 223] }
            });
            yPos = doc.autoTable.previous.finalY + 10;
        }

        if (document.getElementById('topCustomersTable')) {
             if (yPos > 250) { // Check if new page is needed
                doc.addPage();
                yPos = 15;
            }
            doc.setFontSize(12);
            doc.text('En Cok Alim Yapan Musteriler', 14, yPos);
            doc.autoTable({
                html: '#topCustomersTable',
                startY: yPos + 5,
                theme: 'grid',
                headStyles: { fillColor: [78, 115, 223] }
            });
        }
    }
    
    // Print function
    document.getElementById('printReportBtn').addEventListener('click', function() {
        window.print();
    });
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>
