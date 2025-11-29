<?php
/**
 * Admin - Destek Talepleri İstatistikleri
 */
date_default_timezone_set('Europe/Istanbul');
// --- init.php Dahil Etme ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Destek İstatistikleri';
$current_page = 'support';

// --- Tarih Filtreleri ---
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// --- İstatistikler ---

// 1. Genel İstatistikler
try {
    // Toplam destek talebi sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $total_tickets = $stmt->fetchColumn();
    
    // Açık destek talebi sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE status IN ('new', 'in_progress', 'waiting') AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $open_tickets = $stmt->fetchColumn();
    
    // Kapalı destek talebi sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE status IN ('resolved', 'closed') AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $closed_tickets = $stmt->fetchColumn();
    
    // Ortalama çözüm süresi (saat cinsinden)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) 
        FROM support_tickets 
        WHERE status IN ('resolved', 'closed') 
        AND closed_at IS NOT NULL
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $avg_resolution_time = $stmt->fetchColumn();
    
    // Toplam mesaj sayısı
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM support_messages m
        JOIN support_tickets t ON m.ticket_id = t.id
        WHERE t.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $total_messages = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("General stats query error: " . $e->getMessage());
    $total_tickets = $open_tickets = $closed_tickets = $avg_resolution_time = $total_messages = 0;
}

// 2. Durumlara Göre Dağılım
try {
    $stmt = $pdo->prepare("
        SELECT 
            status, 
            COUNT(*) as count 
        FROM 
            support_tickets 
        WHERE 
            created_at BETWEEN ? AND ?
        GROUP BY 
            status
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON formatına dönüştür (grafik için)
    $status_labels = [];
    $status_data = [];
    $status_colors = [
        'new' => '#ffc107',        // warning
        'in_progress' => '#0d6efd', // primary
        'waiting' => '#0dcaf0',    // info
        'resolved' => '#198754',   // success
        'closed' => '#6c757d'      // secondary
    ];
    $status_bg_colors = [];
    
    foreach ($status_distribution as $item) {
        $status_text = '';
        switch ($item['status']) {
            case 'new':
                $status_text = 'Yeni';
                break;
            case 'in_progress':
                $status_text = 'İşlemde';
                break;
            case 'waiting':
                $status_text = 'Bekliyor';
                break;
            case 'resolved':
                $status_text = 'Çözüldü';
                break;
            case 'closed':
                $status_text = 'Kapatıldı';
                break;
            default:
                $status_text = $item['status'];
        }
        
        $status_labels[] = $status_text;
        $status_data[] = $item['count'];
        $status_bg_colors[] = $status_colors[$item['status']] ?? '#6c757d';
    }
    
    $status_chart_data = [
        'labels' => $status_labels,
        'datasets' => [
            [
                'data' => $status_data,
                'backgroundColor' => $status_bg_colors
            ]
        ]
    ];
    
    $status_chart_json = json_encode($status_chart_data);
} catch (PDOException $e) {
    error_log("Status distribution query error: " . $e->getMessage());
    $status_distribution = [];
    $status_chart_json = json_encode(['labels' => [], 'datasets' => [['data' => [], 'backgroundColor' => []]]]);
}

// 3. Kategorilere Göre Dağılım
try {
    $stmt = $pdo->prepare("
        SELECT 
            category, 
            COUNT(*) as count 
        FROM 
            support_tickets 
        WHERE 
            created_at BETWEEN ? AND ?
        GROUP BY 
            category
        ORDER BY 
            count DESC
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $category_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON formatına dönüştür (grafik için)
    $category_labels = [];
    $category_data = [];
    $category_colors = [];
    
    // Kategoriler için rastgele renkler oluştur
    $base_colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1', '#5a5c69', '#2c9faf', '#3c5fdc', '#6610f2'];
    $color_index = 0;
    
    foreach ($category_distribution as $item) {
        $category_labels[] = $item['category'];
        $category_data[] = $item['count'];
        $category_colors[] = $base_colors[$color_index % count($base_colors)];
        $color_index++;
    }
    
    $category_chart_data = [
        'labels' => $category_labels,
        'datasets' => [
            [
                'data' => $category_data,
                'backgroundColor' => $category_colors
            ]
        ]
    ];
    
    $category_chart_json = json_encode($category_chart_data);
} catch (PDOException $e) {
    error_log("Category distribution query error: " . $e->getMessage());
    $category_distribution = [];
    $category_chart_json = json_encode(['labels' => [], 'datasets' => [['data' => [], 'backgroundColor' => []]]]);
}

// 4. Önceliklere Göre Dağılım
try {
    $stmt = $pdo->prepare("
        SELECT 
            priority, 
            COUNT(*) as count 
        FROM 
            support_tickets 
        WHERE 
            created_at BETWEEN ? AND ?
        GROUP BY 
            priority
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $priority_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON formatına dönüştür (grafik için)
    $priority_labels = [];
    $priority_data = [];
    $priority_colors = [
        'high' => '#e74a3b',    // danger
        'medium' => '#f6c23e',  // warning
        'low' => '#1cc88a'      // success
    ];
    $priority_bg_colors = [];
    
    foreach ($priority_distribution as $item) {
        $priority_text = '';
        switch ($item['priority']) {
            case 'high':
                $priority_text = 'Yüksek';
                break;
            case 'medium':
                $priority_text = 'Orta';
                break;
            case 'low':
                $priority_text = 'Düşük';
                break;
            default:
                $priority_text = $item['priority'];
        }
        
        $priority_labels[] = $priority_text;
        $priority_data[] = $item['count'];
        $priority_bg_colors[] = $priority_colors[$item['priority']] ?? '#6c757d';
    }
    
    $priority_chart_data = [
        'labels' => $priority_labels,
        'datasets' => [
            [
                'data' => $priority_data,
                'backgroundColor' => $priority_bg_colors
            ]
        ]
    ];
    
    $priority_chart_json = json_encode($priority_chart_data);
} catch (PDOException $e) {
    error_log("Priority distribution query error: " . $e->getMessage());
    $priority_distribution = [];
    $priority_chart_json = json_encode(['labels' => [], 'datasets' => [['data' => [], 'backgroundColor' => []]]]);
}

// 5. Zaman İçinde Talep Sayıları (Son 30 gün için)
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count 
        FROM 
            support_tickets 
        WHERE 
            created_at BETWEEN ? AND ?
        GROUP BY 
            DATE(created_at)
        ORDER BY 
            date ASC
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $tickets_over_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tarih aralığındaki her gün için veri oluştur (boşlukları doldur)
    $time_labels = [];
    $time_data = [];
    
    $current_date = new DateTime($date_from);
    $end_date = new DateTime($date_to);
    $end_date->modify('+1 day'); // Son günü dahil etmek için
    
    $date_counts = [];
    foreach ($tickets_over_time as $item) {
        $date_counts[$item['date']] = $item['count'];
    }
    
    while ($current_date < $end_date) {
        $date_str = $current_date->format('Y-m-d');
        $time_labels[] = $current_date->format('d.m.Y');
        $time_data[] = isset($date_counts[$date_str]) ? $date_counts[$date_str] : 0;
        $current_date->modify('+1 day');
    }
    
    $time_chart_data = [
        'labels' => $time_labels,
        'datasets' => [
            [
                'label' => 'Talep Sayısı',
                'data' => $time_data,
                'borderColor' => '#4e73df',
                'backgroundColor' => 'rgba(78, 115, 223, 0.05)',
                'borderWidth' => 2,
                'pointBackgroundColor' => '#4e73df',
                'lineTension' => 0.3,
                'fill' => true
            ]
        ]
    ];
    
    $time_chart_json = json_encode($time_chart_data);
} catch (PDOException $e) {
    error_log("Tickets over time query error: " . $e->getMessage());
    $tickets_over_time = [];
    $time_chart_json = json_encode(['labels' => [], 'datasets' => [[
        'label' => 'Talep Sayısı',
        'data' => [],
        'borderColor' => '#4e73df',
        'backgroundColor' => 'rgba(78, 115, 223, 0.05)',
        'borderWidth' => 2,
        'pointBackgroundColor' => '#4e73df',
        'lineTension' => 0.3,
        'fill' => true
    ]]]);
}

// 6. Kişilere Göre Atanan Talepler
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            COUNT(t.id) as assigned_count,
            SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_count
        FROM 
            users u
        LEFT JOIN 
            support_tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
        WHERE 
            u.role = 'admin' AND u.status = 1
        GROUP BY 
            u.id
        ORDER BY 
            assigned_count DESC
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $assignment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON formatına dönüştür (grafik için)
    $user_labels = [];
    $assigned_data = [];
    $resolved_data = [];
    
    foreach ($assignment_stats as $item) {
        if ($item['assigned_count'] > 0) { // Sadece en az bir talebi olan kişileri göster
            $user_labels[] = $item['first_name'] . ' ' . $item['last_name'];
            $assigned_data[] = $item['assigned_count'];
            $resolved_data[] = $item['resolved_count'];
        }
    }
    
    $assignment_chart_data = [
        'labels' => $user_labels,
        'datasets' => [
            [
                'label' => 'Atanan',
                'data' => $assigned_data,
                'backgroundColor' => '#4e73df'
            ],
            [
                'label' => 'Çözülen',
                'data' => $resolved_data,
                'backgroundColor' => '#1cc88a'
            ]
        ]
    ];
    
    $assignment_chart_json = json_encode($assignment_chart_data);
} catch (PDOException $e) {
    error_log("Assignment stats query error: " . $e->getMessage());
    $assignment_stats = [];
    $assignment_chart_json = json_encode(['labels' => [], 'datasets' => [
        ['label' => 'Atanan', 'data' => [], 'backgroundColor' => '#4e73df'],
        ['label' => 'Çözülen', 'data' => [], 'backgroundColor' => '#1cc88a']
    ]]);
}

// 7. Ortalama İlk Yanıt Süresi ve Çözüm Süresi (Saatlere göre, günün saatleri)
try {
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(t.created_at) as hour,
            AVG(TIMESTAMPDIFF(MINUTE, t.created_at, 
                (SELECT MIN(created_at) FROM support_messages WHERE ticket_id = t.id AND user_id IN (
                    SELECT id FROM users WHERE role = 'admin'
                ))
            )) as avg_first_response_time,
            AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as avg_resolution_time,
            COUNT(*) as ticket_count
        FROM 
            support_tickets t
        WHERE 
            t.created_at BETWEEN ? AND ?
            AND t.status IN ('resolved', 'closed')
        GROUP BY 
            HOUR(t.created_at)
        ORDER BY 
            hour ASC
    ");
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $response_time_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON formatına dönüştür (grafik için)
    $hour_labels = [];
    $response_time_data = [];
    $resolution_time_data = [];
    $ticket_count_by_hour = [];
    
    // Tüm saatler için veri hazırla
    for ($h = 0; $h < 24; $h++) {
        $hour_labels[] = sprintf('%02d:00', $h);
        $response_time_data[$h] = 0;
        $resolution_time_data[$h] = 0;
        $ticket_count_by_hour[$h] = 0;
    }
    
    foreach ($response_time_stats as $item) {
        $hour = intval($item['hour']);
        $response_time_data[$hour] = round($item['avg_first_response_time'] / 60, 1); // Saat cinsinden
        $resolution_time_data[$hour] = round($item['avg_resolution_time'], 1);
        $ticket_count_by_hour[$hour] = $item['ticket_count'];
    }
    
    $response_time_chart_data = [
        'labels' => $hour_labels,
        'datasets' => [
            [
                'label' => 'Ort. İlk Yanıt Süresi (Saat)',
                'data' => array_values($response_time_data),
                'borderColor' => '#e74a3b',
                'backgroundColor' => 'rgba(231, 74, 59, 0.05)',
                'borderWidth' => 2,
                'pointBackgroundColor' => '#e74a3b',
                'lineTension' => 0.3,
                'fill' => true,
                'yAxisID' => 'y'
            ],
            [
                'label' => 'Ort. Çözüm Süresi (Saat)',
                'data' => array_values($resolution_time_data),
                'borderColor' => '#1cc88a',
                'backgroundColor' => 'rgba(28, 200, 138, 0.05)',
                'borderWidth' => 2,
                'pointBackgroundColor' => '#1cc88a',
                'lineTension' => 0.3,
                'fill' => true,
                'yAxisID' => 'y'
            ],
            [
                'label' => 'Talep Sayısı',
                'data' => array_values($ticket_count_by_hour),
                'borderColor' => '#4e73df',
                'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                'borderWidth' => 1,
                'pointBackgroundColor' => '#4e73df',
                'type' => 'bar',
                'yAxisID' => 'y1'
            ]
        ]
    ];
    
    $response_time_chart_json = json_encode($response_time_chart_data);
} catch (PDOException $e) {
    error_log("Response time stats query error: " . $e->getMessage());
    $response_time_stats = [];
    
    // Boş veri hazırla
    $hour_labels = [];
    for ($h = 0; $h < 24; $h++) {
        $hour_labels[] = sprintf('%02d:00', $h);
    }
    
    $response_time_chart_json = json_encode([
        'labels' => $hour_labels,
        'datasets' => [
            [
                'label' => 'Ort. İlk Yanıt Süresi (Saat)',
                'data' => array_fill(0, 24, 0),
                'borderColor' => '#e74a3b',
                'backgroundColor' => 'rgba(231, 74, 59, 0.05)',
                'borderWidth' => 2,
                'pointBackgroundColor' => '#e74a3b',
                'lineTension' => 0.3,
                'fill' => true,
                'yAxisID' => 'y'
            ],
            [
                'label' => 'Ort. Çözüm Süresi (Saat)',
                'data' => array_fill(0, 24, 0),
                'borderColor' => '#1cc88a',
                'backgroundColor' => 'rgba(28, 200, 138, 0.05)',
                'borderWidth' => 2,
                'pointBackgroundColor' => '#1cc88a',
                'lineTension' => 0.3,
                'fill' => true,
                'yAxisID' => 'y'
            ],
            [
                'label' => 'Talep Sayısı',
                'data' => array_fill(0, 24, 0),
                'borderColor' => '#4e73df',
                'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                'borderWidth' => 1,
                'pointBackgroundColor' => '#4e73df',
                'type' => 'bar',
                'yAxisID' => 'y1'
            ]
        ]
    ]);
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i> Destek İstatistikleri
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Destek Taleplerine Dön
        </a>
    </div>
</div>

<!-- Tarih Filtresi -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Tarih Aralığı Seçin</h6>
    </div>
    <div class="card-body">
        <form method="get" action="">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label for="date_to" class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-4 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrele
                    </button>
                    <div class="dropdown ms-2">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar"></i> Hızlı Tarih
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="?date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>">
                                    Son 7 Gün
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>">
                                    Son 30 Gün
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?date_from=<?php echo date('Y-m-d', strtotime('first day of this month')); ?>&date_to=<?php echo date('Y-m-d'); ?>">
                                    Bu Ay
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?date_from=<?php echo date('Y-m-d', strtotime('first day of last month')); ?>&date_to=<?php echo date('Y-m-d', strtotime('last day of last month')); ?>">
                                    Geçen Ay
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?date_from=<?php echo date('Y-m-d', strtotime('first day of January this year')); ?>&date_to=<?php echo date('Y-m-d'); ?>">
                                    Bu Yıl
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Destek Talebi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_tickets; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Açık Talepler</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $open_tickets; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Kapalı Talepler</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $closed_tickets; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Ort. Çözüm Süresi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                                if ($avg_resolution_time) {
                                    echo round($avg_resolution_time, 1) . ' saat';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafikler -->
<div class="row">
    <!-- Zaman İçinde Talep Sayıları -->
    <div class="col-xl-12 col-lg-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Zaman İçinde Talep Sayıları</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="ticketsOverTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Durum ve Öncelik Dağılımı -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Durum Dağılımı</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="statusDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Öncelik Dağılımı</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="priorityDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Kategori Dağılımı -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Kategori Dağılımı</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="categoryDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Yanıt ve Çözüm Süreleri -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Saatlere Göre Yanıt ve Çözüm Süreleri</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Kişilere Göre Talepler -->
    <div class="col-xl-12 col-lg-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Kişilere Göre Atanan Talepler</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="assignmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer'ı Dahil Et -->
<?php include_once ROOT_PATH . '/admin/footer.php'; ?>

<!-- Chart.js Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Grafikleri Oluştur
document.addEventListener('DOMContentLoaded', function() {
    // 1. Zaman İçinde Talep Sayıları
    var timeCtx = document.getElementById('ticketsOverTimeChart').getContext('2d');
    var timeData = <?php echo $time_chart_json; ?>;
    var timeChart = new Chart(timeCtx, {
        type: 'line',
        data: timeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Talep Sayısı'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Tarih'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    // 2. Durum Dağılımı
    var statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
    var statusData = <?php echo $status_chart_json; ?>;
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: statusData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            var percentage = Math.round((value / total) * 100);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // 3. Öncelik Dağılımı
    var priorityCtx = document.getElementById('priorityDistributionChart').getContext('2d');
    var priorityData = <?php echo $priority_chart_json; ?>;
    var priorityChart = new Chart(priorityCtx, {
        type: 'doughnut',
        data: priorityData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            var percentage = Math.round((value / total) * 100);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // 4. Kategori Dağılımı
    var categoryCtx = document.getElementById('categoryDistributionChart').getContext('2d');
    var categoryData = <?php echo $category_chart_json; ?>;
    var categoryChart = new Chart(categoryCtx, {
        type: 'pie',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            var percentage = Math.round((value / total) * 100);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // 5. Yanıt ve Çözüm Süreleri
    var responseCtx = document.getElementById('responseTimeChart').getContext('2d');
    var responseData = <?php echo $response_time_chart_json; ?>;
    var responseChart = new Chart(responseCtx, {
        type: 'line',
        data: responseData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Süre (Saat)'
                    },
                    position: 'left'
                },
                y1: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Talep Sayısı'
                    },
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Saat'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    // 6. Kişilere Göre Atanan Talepler
    var assignmentCtx = document.getElementById('assignmentChart').getContext('2d');
    var assignmentData = <?php echo $assignment_chart_json; ?>;
    var assignmentChart = new Chart(assignmentCtx, {
        type: 'bar',
        data: assignmentData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Talep Sayısı'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Kişi'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
});
</script>