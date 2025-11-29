<?php
/**
 * Admin - Sipariş Dışa Aktarma (CSV)
 * Bu sayfa sipariş verilerini CSV formatında dışa aktarır.
 */

// --- init.php Dahil Etme ---
require_once '../../init.php';

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Parametreleri Al ---
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at';
$order_dir = isset($_GET['order_dir']) && $_GET['order_dir'] === 'asc' ? 'asc' : 'desc';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// --- CSV Dosya Adı Oluştur ---
$timestamp = date('Y-m-d_H-i-s');
$filename = "siparisler_$timestamp.csv";

// --- CSV Başlık Satırı ---
$header_row = [
    'Sipariş ID',
    'Sipariş No',
    'Tarih',
    'Müşteri Adı',
    'Telefon',
    'E-posta',
    'Ürün Sayısı',
    'Toplam Tutar',
    'Durum',
    'Not'
];

// --- Siparişleri Getir ---
try {
    $params = [];
    $where_clauses = [];
    
    // Filtreleme koşullarını oluştur
    if (!empty($status)) {
        $where_clauses[] = "o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(o.order_number LIKE ? OR u.bakery_name LIKE ? OR u.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($date_from)) {
        $where_clauses[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_clauses[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);
    
    // Siparişleri çek
    $sql = "SELECT o.*, u.bakery_name, u.phone, u.email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $where_sql
            ORDER BY $order_by $order_dir";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV çıktı için başlıklar
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // CSV dosyasını oluştur
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM ekle (Excel uyumluluğu için)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Başlık satırını yaz
    fputcsv($output, $header_row);
    
    // Sipariş verilerini yaz
    foreach ($orders as $order) {
        // Durum metnini Türkçeleştir
        $status_text = '';
        switch($order['status']) {
            case 'pending':
                $status_text = 'Beklemede';
                break;
            case 'processing':
                $status_text = 'İşleniyor';
                break;
            case 'completed':
                $status_text = 'Tamamlandı';
                break;
            case 'cancelled':
                $status_text = 'İptal Edildi';
                break;
            default:
                $status_text = $order['status'];
        }
        
        // Tarihi formatlama
        $created_date = new DateTime($order['created_at']);
        $formatted_date = $created_date->format('d.m.Y H:i');
        
        // Veri satırı
        $row = [
            $order['id'],
            $order['order_number'],
            $formatted_date,
            $order['bakery_name'],
            $order['phone'],
            $order['email'],
            $order['item_count'],
            number_format($order['total_amount'], 2, ',', '.') . ' TL',
            $status_text,
            $order['note']
        ];
        
        fputcsv($output, $row);
    }
    
    // Aktivite logu
    logUserActivity($_SESSION['user_id'], 'order_export', "Sipariş verileri CSV olarak dışa aktarıldı");
    
} catch (PDOException $e) {
    error_log("Order Export Error: " . $e->getMessage());
    
    // Hata durumunda JSON döndür
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Veri dışa aktarılırken bir hata oluştu: ' . $e->getMessage()]);
}

exit;
?>