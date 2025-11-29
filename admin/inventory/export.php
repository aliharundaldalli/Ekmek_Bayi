<?php
/**
 * Admin Paneli - Stok Listesi CSV Dışa Aktarma
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/inventory_functions.php';

// CSV dosyası başlığı
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="stok_listesi_' . date('Y-m-d') . '.csv"');

// UTF-8 BOM
echo "\xEF\xBB\xBF";

// CSV çıktı akışını aç
$output = fopen('php://output', 'w');

// CSV başlık satırını belirle
$header = [
    'ID', 
    'Ekmek Adı', 
    'Açıklama', 
    'Satış Türü', 
    'Kasa Kapasitesi',
    'Fiyat', 
    'Adet Stok', 
    'Kasa Stok', 
    'Toplam Adet',
    'Durum', 
    'Son Güncelleme'
];

// BOM karakteri zaten UTF-8 CSV için eklendiğinden,
// Windows için özel bir kodlama yapmamız gerekmez
fputcsv($output, $header);

// --- Filtreleme Değişkenleri ---
$bread_id = isset($_GET['bread_id']) && is_numeric($_GET['bread_id']) ? (int)$_GET['bread_id'] : 0;
$sale_type = $_GET['sale_type'] ?? '';
$status = isset($_GET['status']) && is_numeric($_GET['status']) ? (int)$_GET['status'] : -1; // -1 = tümü
$search = $_GET['search'] ?? '';
$min_quantity = isset($_GET['min_quantity']) && is_numeric($_GET['min_quantity']) ? (int)$_GET['min_quantity'] : 0;

// --- Sorgu Oluşturma (Filtrelerle) ---
// Temel sorgu yapısı - Ekmek türleri ve envanter bilgilerini birleştir
$base_query = "FROM bread_types b 
               LEFT JOIN inventory i ON b.id = i.bread_id 
               WHERE 1=1";
$params = []; // Ana sorgu için parametreler

// Filtreleri ekle
if (!empty($bread_id)) {
    $base_query .= " AND b.id = :bread_id";
    $params[':bread_id'] = $bread_id;
}
if (!empty($sale_type)) {
    $base_query .= " AND (b.sale_type = :sale_type OR b.sale_type = 'both')";
    $params[':sale_type'] = $sale_type;
}
if ($status != -1) { // -1 = tümü (filtresiz)
    $base_query .= " AND b.status = :status";
    $params[':status'] = $status;
}
if (!empty($search)) {
    $search_like = "%" . $search . "%";
    $base_query .= " AND (b.name LIKE :search OR b.description LIKE :search)";
    $params[':search'] = $search_like;
}
if ($min_quantity > 0) {
    $base_query .= " AND ((i.piece_quantity >= :min_quantity) OR (i.box_quantity >= :min_quantity))";
    $params[':min_quantity'] = $min_quantity;
}

try {
    // Tüm stok verilerini al (sayfalama olmadan)
    $query = "SELECT b.id, b.name, b.description, b.price, b.status, b.sale_type, 
                    b.box_capacity, b.is_packaged, b.package_weight,
                    i.piece_quantity, i.box_quantity, i.updated_at "
           . $base_query
           . " ORDER BY b.name ASC";

    $stmt = $pdo->prepare($query);

    // Parametreleri bind et
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    
    // Her satırı CSV'ye ekle
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Satış türünü metin olarak göster
        $sale_type_text = '';
        switch ($row['sale_type']) {
            case 'piece': $sale_type_text = 'Adet'; break;
            case 'box': $sale_type_text = 'Kasa'; break;
            case 'both': $sale_type_text = 'Adet ve Kasa'; break;
            default: $sale_type_text = 'Belirsiz';
        }
        
        // Durum metni
        $status_text = ($row['status'] == 1) ? 'Aktif' : 'Pasif';
        
        // Adet stok
        $piece_quantity = (int)($row['piece_quantity'] ?? 0);
        
        // Kasa stok
        $box_quantity = (int)($row['box_quantity'] ?? 0);
        
        // Toplam adet (kasa içindeki adetler dahil)
        $total_piece = $piece_quantity;
        if ($box_quantity > 0 && !empty($row['box_capacity'])) {
            $total_piece += $box_quantity * (int)$row['box_capacity'];
        }
        
        // Fiyatı noktayla ayırıp TL sembolünü kaldır (Excel için)
        $price = str_replace(',', '.', str_replace('.', '', str_replace(' ₺', '', formatMoney($row['price']))));
        
        $csv_row = [
            $row['id'],
            $row['name'],
            // Açıklamadan HTML ve yeni satırları temizle
            preg_replace('/\s+/', ' ', strip_tags(str_replace(["\r", "\n"], ' ', $row['description'] ?? ''))),
            $sale_type_text,
            $row['box_capacity'] ?? '',
            $price,
            $piece_quantity,
            $box_quantity,
            $total_piece,
            $status_text,
            $row['updated_at'] ?? ''
        ];
        
        fputcsv($output, $csv_row);
    }
    
} catch (PDOException $e) {
    // Hata durumunda CSV'ye hata mesajı ekle
    fputcsv($output, ['Hata: ' . $e->getMessage()]);
    error_log("Inventory Export Error: " . $e->getMessage());
}

// Çıktı tamponunu boşalt ve kapat
fclose($output);
exit;
?>