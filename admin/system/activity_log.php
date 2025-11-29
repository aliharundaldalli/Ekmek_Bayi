<?php
/**
 * Admin Paneli - Kullanıcı Aktivite Logları CSV Dışa Aktarma
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php'; // init.php'nin ROOT_PATH ve BASE_URL tanımladığını varsayıyoruz

require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Admin kontrolü

// --- Filtreleme Değişkenleri ---
$user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$activity_type = $_GET['activity_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// CSV dosyası başlığı
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="aktivite_loglari_' . date('Y-m-d') . '.csv"');

// UTF-8 BOM
echo "\xEF\xBB\xBF";

// CSV çıktı akışını aç
$output = fopen('php://output', 'w');

// CSV başlık satırını belirle
$header = [
    'ID', 
    'Kullanıcı ID', 
    'Ad', 
    'Soyad',
    'Büfe Adı',
    'Rol', 
    'Aktivite Türü', 
    'IP Adresi', 
    'Tarayıcı/Cihaz', 
    'Tarih ve Saat'
];

// BOM karakteri zaten UTF-8 CSV için eklendiğinden,
// Windows için özel bir kodlama yapmamız gerekmez
fputcsv($output, $header);

// --- Sorgu Oluşturma (Filtrelerle) ---
// Temel sorgu yapısı
$base_query = "FROM user_activities a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1";
$params = []; // Ana sorgu için parametreler

// Filtreleri ekle
if (!empty($user_id)) {
    $base_query .= " AND a.user_id = :user_id";
    $params[':user_id'] = $user_id;
}
if (!empty($activity_type)) {
    $base_query .= " AND a.activity_type = :activity_type";
    $params[':activity_type'] = $activity_type;
}
if (!empty($date_from)) {
    $base_query .= " AND DATE(a.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $base_query .= " AND DATE(a.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}
if (!empty($search)) {
    $search_like = "%" . $search . "%";
    $base_query .= " AND (a.ip_address LIKE :search OR a.user_agent LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.bakery_name LIKE :search)";
    $params[':search'] = $search_like;
}

try {
    // Tüm aktivite verilerini al (sayfalama olmadan)
    $query = "SELECT a.id, a.user_id, a.activity_type, a.ip_address, a.user_agent, a.created_at,
                     u.first_name, u.last_name, u.bakery_name, u.role "
           . $base_query
           . " ORDER BY a.created_at DESC";

    $stmt = $pdo->prepare($query);

    // Parametreleri bind et
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    
    // Her satırı CSV'ye ekle
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Kullanıcı adları boş olabilir, bu durumu kontrol et
        $first_name = !empty($row['first_name']) ? $row['first_name'] : '';
        $last_name = !empty($row['last_name']) ? $row['last_name'] : '';
        $bakery_name = !empty($row['bakery_name']) ? $row['bakery_name'] : '';
        
        // Rol için daha anlaşılır metin
        $role = '';
        if (!empty($row['role'])) {
            $role = ($row['role'] === 'admin') ? 'Yönetici' : 'Büfe';
        }
        
        // Aktivite türünü ilk harfi büyük yaparak formatla
        $activity_type = ucfirst($row['activity_type'] ?? '');
        
        // CSV satırı oluştur
        $csv_row = [
            $row['id'],
            $row['user_id'],
            $first_name,
            $last_name,
            $bakery_name,
            $role,
            $activity_type,
            $row['ip_address'],
            $row['user_agent'],
            $row['created_at']
        ];
        
        fputcsv($output, $csv_row);
    }
    
} catch (PDOException $e) {
    // Hata durumunda CSV'ye hata mesajı ekle
    fputcsv($output, ['Hata: ' . $e->getMessage()]);
    error_log("Activity Log Export Error: " . $e->getMessage());
}

// Çıktı tamponunu boşalt ve kapat
fclose($output);
exit;
?>