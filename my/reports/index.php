<?php
/**
 * Büfe Paneli - Raporlarım
 * Modern ve profesyonel arayüz
 */

require_once '../../init.php';

if (!isLoggedIn()) { redirect(BASE_URL . 'login.php'); exit; }

$page_title = 'Raporlarım';
$current_page = 'reports';
$user_id = $_SESSION['user_id'];

// Tarih Filtresi
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '30days';
$custom_from = isset($_GET['custom_from']) ? $_GET['custom_from'] : '';
$custom_to = isset($_GET['custom_to']) ? $_GET['custom_to'] : '';

$date_from = date('Y-m-d', strtotime('-30 days'));
$date_to = date('Y-m-d');

switch ($date_range) {
    case '7days': $date_from = date('Y-m-d', strtotime('-7 days')); break;
    case '30days': $date_from = date('Y-m-d', strtotime('-30 days')); break;
    case '90days': $date_from = date('Y-m-d', strtotime('-90 days')); break;
    case 'year': $date_from = date('Y-m-d', strtotime('-1 year')); break;
    case 'custom':
        if (!empty($custom_from)) $date_from = $custom_from;
        if (!empty($custom_to)) $date_to = $custom_to;
        break;
}

// Veri Çekme
try {
    // Özet Veriler
    $stmt_summary = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            COALESCE(SUM(oi.quantity), 0) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? AND o.status != 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stmt_summary->execute([$user_id, $date_from, $date_to]);
    $summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);

    // Grafik Verileri (Günlük Harcama)
    $stmt_chart = $pdo->prepare("
        SELECT DATE(created_at) as date, SUM(total_amount) as total
        FROM orders
        WHERE user_id = ? AND status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt_chart->execute([$user_id, $date_from, $date_to]);
    $chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    foreach ($chart_data as $row) {
        $labels[] = date('d.m', strtotime($row['date']));
        $data[] = $row['total'];
    }

    // En Çok Alınan Ürünler
    $stmt_top = $pdo->prepare("
        SELECT b.name, SUM(oi.quantity) as total_qty, SUM(oi.total_price) as total_price
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN bread_types b ON oi.bread_id = b.id
        WHERE o.user_id = ? AND o.status != 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY b.id
        ORDER BY total_qty DESC
        LIMIT 5
    ");
    $stmt_top->execute([$user_id, $date_from, $date_to]);
    $top_products = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Reports Error: " . $e->getMessage());
    $summary = ['total_orders' => 0, 'total_spent' => 0, 'total_items' => 0];
    $labels = []; $data = []; $top_products = [];
}

include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">

    <!-- Başlık -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Raporlarım</h1>
    </div>

    <!-- Filtre -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Tarih Aralığı</label>
                    <select name="date_range" class="form-select" onchange="toggleCustomDates(this.value)">
                        <option value="7days" <?php echo $date_range == '7days' ? 'selected' : ''; ?>>Son 7 Gün</option>
                        <option value="30days" <?php echo $date_range == '30days' ? 'selected' : ''; ?>>Son 30 Gün</option>
                        <option value="90days" <?php echo $date_range == '90days' ? 'selected' : ''; ?>>Son 90 Gün</option>
                        <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>Son 1 Yıl</option>
                        <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Özel Tarih</option>
                    </select>
                </div>
                <div class="col-md-3 custom-date <?php echo $date_range == 'custom' ? '' : 'd-none'; ?>">
                    <label class="form-label small fw-bold text-uppercase text-muted">Başlangıç</label>
                    <input type="date" name="custom_from" class="form-control" value="<?php echo $custom_from; ?>">
                </div>
                <div class="col-md-3 custom-date <?php echo $date_range == 'custom' ? '' : 'd-none'; ?>">
                    <label class="form-label small fw-bold text-uppercase text-muted">Bitiş</label>
                    <input type="date" name="custom_to" class="form-control" value="<?php echo $custom_to; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Özet Kartlar -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Harcama</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatMoney($summary['total_spent']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Toplam Sipariş</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['total_orders']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-shopping-basket fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Toplam Ürün Adedi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['total_items']); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Grafik -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Harcama Grafiği</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="spendingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- En Çok Alınanlar -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">En Çok Tercih Ettikleriniz</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Ürün</th>
                                    <th class="text-end pe-4">Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                    <tr><td colspan="2" class="text-center py-4 text-muted">Veri bulunamadı.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($top_products as $prod): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($prod['name']); ?></div>
                                            <div class="small text-muted"><?php echo number_format($prod['total_qty']); ?> Adet</div>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-primary">
                                            <?php echo formatMoney($prod['total_price']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleCustomDates(val) {
    const els = document.querySelectorAll('.custom-date');
    els.forEach(el => el.classList.toggle('d-none', val !== 'custom'));
}

const ctx = document.getElementById('spendingChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Harcama (TL)',
            data: <?php echo json_encode($data); ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            pointRadius: 3,
            pointBackgroundColor: '#4e73df',
            pointBorderColor: '#4e73df',
            pointHoverRadius: 3,
            pointHoverBackgroundColor: '#4e73df',
            pointHoverBorderColor: '#4e73df',
            pointHitRadius: 10,
            pointBorderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        maintainAspectRatio: false,
        layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
        scales: {
            x: { grid: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 7 } },
            y: { ticks: { maxTicksLimit: 5, padding: 10, callback: function(value) { return value + ' ₺'; } }, grid: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] } }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: "rgb(255,255,255)",
                bodyColor: "#858796",
                titleMarginBottom: 10,
                titleColor: '#6e707e',
                titleFont: { size: 14 },
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.dataset.label + ': ' + tooltipItem.raw.toLocaleString('tr-TR') + ' ₺';
                    }
                }
            }
        }
    }
});
</script>

<?php include_once ROOT_PATH . '/my/footer.php'; ?>