<?php
/**
 * Stok Yönetimi İlgili Fonksiyonlar
 */

/**
 * Ekmek bilgilerini ID'ye göre getirir
 * 
 * @param int $bread_id Ekmek ID'si
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array|false Ekmek bilgisi veya false
 */
function getBreadInfo($bread_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bread_types WHERE id = ?");
        $stmt->execute([$bread_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getBreadInfo: " . $e->getMessage());
        return false;
    }
}

/**
 * Ekmek stoğunu getirir
 * 
 * @param int $bread_id Ekmek ID'si
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array|false Stok bilgisi veya false
 */
function getInventory($bread_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE bread_id = ?");
        $stmt->execute([$bread_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'bread_id' => $bread_id,
            'piece_quantity' => 0,
            'box_quantity' => 0,
            'created_at' => null,
            'updated_at' => null
        ];
    } catch (PDOException $e) {
        error_log("Error in getInventory: " . $e->getMessage());
        return false;
    }
}

/**
 * Stok hareketlerini getirir
 * 
 * @param array $filters Filtreler (bread_id, movement_type, date_from, date_to, limit, offset)
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array [movements, total_count]
 */
function getInventoryMovements($filters, $pdo) {
    // Filtre parametrelerini tanımla
    $bread_id = $filters['bread_id'] ?? 0;
    $movement_type = $filters['movement_type'] ?? '';
    $date_from = $filters['date_from'] ?? '';
    $date_to = $filters['date_to'] ?? '';
    $limit = isset($filters['limit']) ? max(1, (int)$filters['limit']) : 50;
    $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
    
    // Temel sorgu
    $base_query = "FROM inventory_movements im 
                   LEFT JOIN bread_types b ON im.bread_id = b.id
                   LEFT JOIN users u ON im.created_by = u.id
                   LEFT JOIN orders o ON im.order_id = o.id
                   WHERE 1=1";
    
    $params = [];
    
    // Filtreleri ekle
    if (!empty($bread_id)) {
        $base_query .= " AND im.bread_id = :bread_id";
        $params[':bread_id'] = $bread_id;
    }
    
    if (!empty($movement_type)) {
        $base_query .= " AND im.movement_type = :movement_type";
        $params[':movement_type'] = $movement_type;
    }
    
    if (!empty($date_from)) {
        $base_query .= " AND DATE(im.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $base_query .= " AND DATE(im.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    // COUNT sorgusu
    $count_query = "SELECT COUNT(*) " . $base_query;
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    // Ana sorgu
    $query = "SELECT im.*, b.name as bread_name, b.sale_type, b.box_capacity, 
                    u.first_name, u.last_name, u.bakery_name,
                    o.order_number " . 
             $base_query . 
             " ORDER BY im.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    // Parametreleri bind et
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'movements' => $movements,
        'total_count' => $total_count
    ];
}

/**
 * Stok hareketi ekler
 * 
 * @param array $data Hareket bilgileri (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by)
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array [success, message, id]
 */
function addInventoryMovement($data, $pdo) {
    $required_fields = ['bread_id', 'movement_type'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return [
                'success' => false,
                'message' => "Zorunlu alan eksik: $field",
                'id' => 0
            ];
        }
    }
    
    // Stok miktarlarını doğrula
    $piece_quantity = isset($data['piece_quantity']) ? max(0, (int)$data['piece_quantity']) : 0;
    $box_quantity = isset($data['box_quantity']) ? max(0, (int)$data['box_quantity']) : 0;
    
    if ($piece_quantity <= 0 && $box_quantity <= 0) {
        return [
            'success' => false,
            'message' => "En az bir miktar (adet veya kasa) girilmelidir.",
            'id' => 0
        ];
    }
    
    // Hareket tipini kontrol et
    $movement_type = strtolower($data['movement_type']);
    if (!in_array($movement_type, ['in', 'out'])) {
        return [
            'success' => false,
            'message' => "Geçersiz hareket tipi: $movement_type. 'in' veya 'out' olmalıdır.",
            'id' => 0
        ];
    }
    
    // Ekmek ID'sini kontrol et
    $bread_id = (int)$data['bread_id'];
    $bread_info = getBreadInfo($bread_id, $pdo);
    if (!$bread_info) {
        return [
            'success' => false,
            'message' => "Ekmek bulunamadı (ID: $bread_id).",
            'id' => 0
        ];
    }
    
    // Eğer çıkış hareketi ise stok yeterliliğini kontrol et
    if ($movement_type === 'out') {
        $inventory = getInventory($bread_id, $pdo);
        if (!$inventory) {
            return [
                'success' => false,
                'message' => "Stok bilgisi alınamadı.",
                'id' => 0
            ];
        }
        
        if ($piece_quantity > ($inventory['piece_quantity'] ?? 0)) {
            return [
                'success' => false,
                'message' => "Yetersiz adet stok. Mevcut: " . ($inventory['piece_quantity'] ?? 0) . ", İstenen: $piece_quantity",
                'id' => 0
            ];
        }
        
        if ($box_quantity > ($inventory['box_quantity'] ?? 0)) {
            return [
                'success' => false,
                'message' => "Yetersiz kasa stok. Mevcut: " . ($inventory['box_quantity'] ?? 0) . ", İstenen: $box_quantity",
                'id' => 0
            ];
        }
    }
    
    // Transaction başlat
    try {
        $pdo->beginTransaction();
        
        // Yeni hareket ekle
        $stmt = $pdo->prepare("
            INSERT INTO inventory_movements 
            (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $params = [
            $bread_id,
            $movement_type,
            $piece_quantity,
            $box_quantity,
            $data['order_id'] ?? null,
            $data['note'] ?? null,
            $data['created_by'] ?? 0
        ];
        
        $stmt->execute($params);
        $movement_id = $pdo->lastInsertId();
        
        // Stok miktarını güncelle
        $inventory = getInventory($bread_id, $pdo);
        $current_piece = $inventory['piece_quantity'] ?? 0;
        $current_box = $inventory['box_quantity'] ?? 0;
        
        if ($movement_type === 'in') {
            // Stok girişi
            $new_piece = $current_piece + $piece_quantity;
            $new_box = $current_box + $box_quantity;
        } else {
            // Stok çıkışı
            $new_piece = $current_piece - $piece_quantity;
            $new_box = $current_box - $box_quantity;
        }
        
        // Yeni stok kaydet
        if ($inventory && isset($inventory['bread_id'])) {
            // Stok güncelle
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET piece_quantity = ?, box_quantity = ?, updated_at = NOW()
                WHERE bread_id = ?
            ");
            $stmt->execute([$new_piece, $new_box, $bread_id]);
        } else {
            // Yeni stok kaydı oluştur
            $stmt = $pdo->prepare("
                INSERT INTO inventory 
                (bread_id, piece_quantity, box_quantity, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$bread_id, $new_piece, $new_box]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Stok hareketi başarıyla kaydedildi.",
            'id' => $movement_id
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in addInventoryMovement: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Veritabanı hatası: " . $e->getMessage(),
            'id' => 0
        ];
    }
}

/**
 * Sipariş için gerekli stok kontrolünü yapar
 * 
 * @param array $order_items Sipariş kalemleri
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array [is_available, message, unavailable_items]
 */
function checkInventoryForOrder($order_items, $pdo) {
    if (empty($order_items)) {
        return [
            'is_available' => true,
            'message' => "Siparişte ürün bulunmuyor.",
            'unavailable_items' => []
        ];
    }
    
    $unavailable_items = [];
    
    foreach ($order_items as $item) {
        $bread_id = $item['bread_id'] ?? 0;
        if (!$bread_id) continue;
        
        $bread_info = getBreadInfo($bread_id, $pdo);
        if (!$bread_info) {
            $unavailable_items[] = [
                'bread_id' => $bread_id,
                'name' => "Bilinmeyen Ekmek (ID: $bread_id)",
                'required' => $item,
                'available' => ['piece_quantity' => 0, 'box_quantity' => 0],
                'reason' => "Ekmek türü bulunamadı."
            ];
            continue;
        }
        
        $inventory = getInventory($bread_id, $pdo);
        $piece_quantity = $inventory['piece_quantity'] ?? 0;
        $box_quantity = $inventory['box_quantity'] ?? 0;
        
        $required_piece = $item['piece_quantity'] ?? 0;
        $required_box = $item['box_quantity'] ?? 0;
        
        if ($required_piece > $piece_quantity || $required_box > $box_quantity) {
            $unavailable_items[] = [
                'bread_id' => $bread_id,
                'name' => $bread_info['name'],
                'required' => [
                    'piece_quantity' => $required_piece,
                    'box_quantity' => $required_box
                ],
                'available' => [
                    'piece_quantity' => $piece_quantity,
                    'box_quantity' => $box_quantity
                ],
                'reason' => "Yetersiz stok."
            ];
        }
    }
    
    if (empty($unavailable_items)) {
        return [
            'is_available' => true,
            'message' => "Tüm ürünler stokta mevcut.",
            'unavailable_items' => []
        ];
    } else {
        return [
            'is_available' => false,
            'message' => count($unavailable_items) . " ürün için stok yetersiz.",
            'unavailable_items' => $unavailable_items
        ];
    }
}

/**
 * Sipariş öğelerini stoktan düşer
 * 
 * @param int $order_id Sipariş ID'si
 * @param array $order_items Sipariş kalemleri
 * @param int $created_by İşlemi yapan kullanıcı ID'si
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array [success, message, details]
 */
function reduceInventoryForOrder($order_id, $order_items, $created_by, $pdo) {
    if (empty($order_items) || !$order_id) {
        return [
            'success' => false,
            'message' => "Geçersiz sipariş veya ürün bilgisi.",
            'details' => []
        ];
    }
    
    // Önce stok kontrolü yap
    $stock_check = checkInventoryForOrder($order_items, $pdo);
    if (!$stock_check['is_available']) {
        return [
            'success' => false,
            'message' => "Yetersiz stok: " . $stock_check['message'],
            'details' => $stock_check['unavailable_items']
        ];
    }
    
    // Transaction başlat
    try {
        $pdo->beginTransaction();
        
        $success_items = [];
        $error_items = [];
        
        foreach ($order_items as $item) {
            $bread_id = $item['bread_id'] ?? 0;
            if (!$bread_id) continue;
            
            $bread_info = getBreadInfo($bread_id, $pdo);
            if (!$bread_info) {
                $error_items[] = [
                    'bread_id' => $bread_id,
                    'message' => "Ekmek türü bulunamadı."
                ];
                continue;
            }
            
            $piece_quantity = (int)($item['piece_quantity'] ?? 0);
            $box_quantity = (int)($item['box_quantity'] ?? 0);
            
            if ($piece_quantity <= 0 && $box_quantity <= 0) {
                continue; // Miktar yoksa işlem yapma
            }
            
            // Not oluştur
            $note = "Sipariş tamamlandı: " . $bread_info['name'];
            if ($piece_quantity > 0) {
                $note .= " - $piece_quantity adet";
            }
            if ($box_quantity > 0) {
                $note .= " - $box_quantity kasa";
            }
            
            // Stok hareketi ekle
            $movement_data = [
                'bread_id' => $bread_id,
                'movement_type' => 'out',
                'piece_quantity' => $piece_quantity,
                'box_quantity' => $box_quantity,
                'order_id' => $order_id,
                'note' => $note,
                'created_by' => $created_by
            ];
            
            $result = addInventoryMovement($movement_data, $pdo);
            
            if ($result['success']) {
                $success_items[] = [
                    'bread_id' => $bread_id,
                    'name' => $bread_info['name'],
                    'piece_quantity' => $piece_quantity,
                    'box_quantity' => $box_quantity,
                    'movement_id' => $result['id']
                ];
            } else {
                $error_items[] = [
                    'bread_id' => $bread_id,
                    'name' => $bread_info['name'],
                    'message' => $result['message']
                ];
                // Hata durumunda işlemi geri al
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "Stok hareketi kaydedilirken hata oluştu: " . $result['message'],
                    'details' => [
                        'success_items' => $success_items,
                        'error_items' => $error_items
                    ]
                ];
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Sipariş için stok düşümü başarıyla tamamlandı.",
            'details' => [
                'success_items' => $success_items,
                'error_items' => $error_items
            ]
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in reduceInventoryForOrder: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Veritabanı hatası: " . $e->getMessage(),
            'details' => []
        ];
    }
}

/**
 * Bir stok hareketi iptal eder
 * 
 * @param int $movement_id Hareket ID'si
 * @param string $reason İptal nedeni
 * @param int $cancelled_by İptal eden kullanıcı ID'si
 * @param PDO $pdo PDO bağlantı nesnesi
 * @return array [success, message]
 */
function cancelInventoryMovement($movement_id, $reason, $cancelled_by, $pdo) {
    if (!$movement_id) {
        return [
            'success' => false,
            'message' => "Geçersiz stok hareketi ID'si."
        ];
    }
    
    try {
        // Önce hareketi bul
        $stmt = $pdo->prepare("SELECT * FROM inventory_movements WHERE id = ?");
        $stmt->execute([$movement_id]);
        $movement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$movement) {
            return [
                'success' => false,
                'message' => "Stok hareketi bulunamadı (ID: $movement_id)."
            ];
        }
        
        // Ekmek bilgilerini al
        $bread_info = getBreadInfo($movement['bread_id'], $pdo);
        if (!$bread_info) {
            return [
                'success' => false,
                'message' => "Ekmek bilgisi bulunamadı (ID: " . $movement['bread_id'] . ")."
            ];
        }
        
        // Hareketin tersini oluştur
        $reverse_type = ($movement['movement_type'] === 'in') ? 'out' : 'in';
        $note = "Stok hareketi iptali (ID: $movement_id): " . ($reason ?: "İptal edildi");
        
        // Transaction başlat
        $pdo->beginTransaction();
        
        // Yeni hareket ekle
        $stmt = $pdo->prepare("
            INSERT INTO inventory_movements 
            (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $params = [
            $movement['bread_id'],
            $reverse_type,
            $movement['piece_quantity'],
            $movement['box_quantity'],
            $movement['order_id'],
            $note,
            $cancelled_by
        ];
        
        $stmt->execute($params);
        $reverse_id = $pdo->lastInsertId();
        
        // Stok miktarını güncelle
        $inventory = getInventory($movement['bread_id'], $pdo);
        $current_piece = $inventory['piece_quantity'] ?? 0;
        $current_box = $inventory['box_quantity'] ?? 0;
        
        if ($reverse_type === 'in') {
            // Stok girişi (önceki çıkışı geri alıyor)
            $new_piece = $current_piece + $movement['piece_quantity'];
            $new_box = $current_box + $movement['box_quantity'];
        } else {
            // Stok çıkışı (önceki girişi geri alıyor)
            $new_piece = $current_piece - $movement['piece_quantity'];
            $new_box = $current_box - $movement['box_quantity'];
            
            // Stok negatif olmamalı
            $new_piece = max(0, $new_piece);
            $new_box = max(0, $new_box);
        }
        
        // Yeni stok kaydet
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET piece_quantity = ?, box_quantity = ?, updated_at = NOW()
            WHERE bread_id = ?
        ");
        $stmt->execute([$new_piece, $new_box, $movement['bread_id']]);
        
        // Orijinal hareketin iptal edildiğini işaretle (opsiyonel, bu alanın veritabanında olduğunu varsayalım)
        // $stmt = $pdo->prepare("UPDATE inventory_movements SET is_cancelled = 1, cancelled_by = ?, cancelled_at = NOW() WHERE id = ?");
        // $stmt->execute([$cancelled_by, $movement_id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Stok hareketi başarıyla iptal edildi.",
            'reverse_id' => $reverse_id
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in cancelInventoryMovement: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Veritabanı hatası: " . $e->getMessage()
        ];
    }
}

/**
 * Stok durumunu kontrol eder ve düşük stok uyarısı verir
 * 
 * @param PDO $pdo PDO bağlantı nesnesi
 * @param int $min_piece_qty Minimum adet stok miktarı
 * @param int $min_box_qty Minimum kasa stok miktarı
 * @return array [low_stock_items, total_count]
 */
function checkLowStockItems($pdo, $min_piece_qty = 10, $min_box_qty = 3) {
    try {
        $query = "
            SELECT b.id, b.name, b.status, b.sale_type, i.piece_quantity, i.box_quantity
            FROM bread_types b
            LEFT JOIN inventory i ON b.id = i.bread_id
            WHERE b.status = 1 AND (
                (b.sale_type IN ('piece', 'both') AND (i.piece_quantity IS NULL OR i.piece_quantity < ?))
                OR
                (b.sale_type IN ('box', 'both') AND (i.box_quantity IS NULL OR i.box_quantity < ?))
            )
            ORDER BY b.name ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$min_piece_qty, $min_box_qty]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'low_stock_items' => $items,
            'total_count' => count($items)
        ];
    } catch (PDOException $e) {
        error_log("Error in checkLowStockItems: " . $e->getMessage());
        return [
            'low_stock_items' => [],
            'total_count' => 0
        ];
    }
}

// Not: formatMoney(), formatDate(), generateCSRFToken(), validateCSRFToken(), redirect() 
// fonksiyonları includes/functions.php içinde tanımlandığı için burada tekrar tanımlanmadı
?>