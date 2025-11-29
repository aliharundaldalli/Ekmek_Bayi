<?php
/**
 * Sipariş Yönetimi Yardımcı Fonksiyonları
 */

/**
 * Günlük sipariş limitini kontrol eder
 * 
 * @param int $user_id Kullanıcı ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return bool|int Limit aşılmadıysa günlük sipariş sayısı, aşıldıysa false
 */
function checkDailyOrderLimit($user_id, $pdo) {
    $today = date('Y-m-d');
    
    // Bugün verilen sipariş sayısını kontrol et
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE user_id = ? AND DATE(created_at) = ? AND status != 'cancelled'
    ");
    $stmt->execute([$user_id, $today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $order_count = $result['count'] ?? 0;
    
    // Limit kontrolü (günde 2 sipariş)
    $daily_limit = 2;
    
    if ($order_count >= $daily_limit) {
        return false;
    }
    
    // Yeni siparişin günlük sıra numarası
    return $order_count + 1;
}

/**
 * Sipariş numarası oluşturur
 * 
 * @param string $prefix Sipariş numarası öneki
 * @param int $user_id Kullanıcı ID
 * @param int $daily_count Günlük sipariş sayısı
 * @return string Oluşturulan sipariş numarası
 */
function generateOrderNumber($prefix, $user_id, $daily_count) {
    $date = date('Ymd');
    $unique = uniqid();
    
    return $prefix . $date . '-' . $user_id . '-' . $daily_count . '-' . substr($unique, -4);
}

/**
 * Kasa fiyatını hesaplar
 * 
 * @param float $unit_price Birim fiyat (adet)
 * @param int $box_capacity Kasa kapasitesi
 * @return float Kasa fiyatı
 */
function calculateBoxPrice($unit_price, $box_capacity) {
    // Kasa fiyatı birim fiyat ile kasa kapasitesinin çarpımıdır
    // Ama indirim uygulamak isterseniz başka bir formül kullanabilirsiniz
    return $unit_price * $box_capacity;
}

/**
 * Tamamlanmış sipariş için stok güncelleme
 * 
 * @param int $order_id Sipariş ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function updateInventoryForCompletedOrder($order_id, $pdo) {
    try {
        // Sipariş kalemlerini al
        $stmt_items = $pdo->prepare("
            SELECT oi.*, bt.name as bread_name
            FROM order_items oi
            LEFT JOIN bread_types bt ON oi.bread_id = bt.id
            WHERE oi.order_id = ?
        ");
        $stmt_items->execute([$order_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return false;
        }
        
        // Her kalem için stok çıkışı yap
        foreach ($items as $item) {
            $bread_id = $item['bread_id'];
            $sale_type = $item['sale_type'];
            $quantity = $item['quantity'];
            $pieces_per_box = $item['pieces_per_box'];
            
            // Stok durumunu kontrol et
            $stmt_stock = $pdo->prepare("
                SELECT * FROM inventory WHERE bread_id = ?
            ");
            $stmt_stock->execute([$bread_id]);
            $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
            
            $piece_quantity = 0;
            $box_quantity = 0;
            
            if ($sale_type === 'piece') {
                $piece_quantity = $quantity;
            } else {
                $box_quantity = $quantity;
            }
            
            // Stok hareketi ekle
            $stmt_movement = $pdo->prepare("
                INSERT INTO inventory_movements 
                (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
                VALUES (?, 'out', ?, ?, ?, ?, ?, NOW())
            ");
            
            $note = "Sipariş tamamlandı: " . $item['bread_name'] . " - " . $quantity . " " . ($sale_type === 'piece' ? 'adet' : 'kasa');
            $user_id = $_SESSION['user_id'] ?? 1;
            
            $stmt_movement->execute([
                $bread_id, 
                $piece_quantity, 
                $box_quantity, 
                $order_id, 
                $note, 
                $user_id
            ]);
            
            // Stok tablosunu güncelle
            if ($stock) {
                // Mevcut stok kaydını güncelle
                $new_piece_quantity = max(0, $stock['piece_quantity'] - $piece_quantity);
                $new_box_quantity = max(0, $stock['box_quantity'] - $box_quantity);
                
                $stmt_update = $pdo->prepare("
                    UPDATE inventory 
                    SET piece_quantity = ?, box_quantity = ?, updated_at = NOW()
                    WHERE bread_id = ?
                ");
                $stmt_update->execute([$new_piece_quantity, $new_box_quantity, $bread_id]);
            } else {
                // Yeni stok kaydı oluştur (negatif değerler engellenir)
                $stmt_insert = $pdo->prepare("
                    INSERT INTO inventory 
                    (bread_id, piece_quantity, box_quantity, created_at, updated_at)
                    VALUES (?, 0, 0, NOW(), NOW())
                ");
                $stmt_insert->execute([$bread_id]);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Inventory Update Error: " . $e->getMessage());
        return false;
    }
}

/**
 * İptal edilen sipariş için stok geri alma
 * 
 * @param int $order_id Sipariş ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return bool İşlem başarılı mı?
 */
function rollbackInventoryForCancelledOrder($order_id, $pdo) {
    try {
        // Siparişin durumunu kontrol et
        $stmt_order = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt_order->execute([$order_id]);
        $order_status = $stmt_order->fetchColumn();
        
        // Sadece tamamlanmış siparişlerin stok hareketlerini geri al
        if ($order_status === 'completed') {
            // Siparişin önceki stok hareketlerini kontrol et
            $stmt_check = $pdo->prepare("
                SELECT * FROM inventory_movements
                WHERE order_id = ? AND movement_type = 'out'
            ");
            $stmt_check->execute([$order_id]);
            $existing_movements = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
            
            // Stok çıkışlarını geri al
            if (!empty($existing_movements)) {
                foreach ($existing_movements as $movement) {
                    $bread_id = $movement['bread_id'];
                    $piece_quantity = $movement['piece_quantity'];
                    $box_quantity = $movement['box_quantity'];
                    
                    // Stok durumunu kontrol et
                    $stmt_stock = $pdo->prepare("
                        SELECT * FROM inventory WHERE bread_id = ?
                    ");
                    $stmt_stock->execute([$bread_id]);
                    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                    
                    // Stok hareketi ekle (giriş olarak)
                    $stmt_movement = $pdo->prepare("
                        INSERT INTO inventory_movements 
                        (bread_id, movement_type, piece_quantity, box_quantity, order_id, note, created_by, created_at)
                        VALUES (?, 'in', ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    // Ekmek adını al
                    $stmt_bread = $pdo->prepare("SELECT name FROM bread_types WHERE id = ?");
                    $stmt_bread->execute([$bread_id]);
                    $bread_name = $stmt_bread->fetchColumn() ?: "Ürün #$bread_id";
                    
                    $note = "Sipariş iptal edildi: " . $bread_name;
                    $user_id = $_SESSION['user_id'] ?? 1;
                    
                    $stmt_movement->execute([
                        $bread_id, 
                        $piece_quantity, 
                        $box_quantity, 
                        $order_id, 
                        $note, 
                        $user_id
                    ]);
                    
                    // Stok tablosunu güncelle
                    if ($stock) {
                        // Mevcut stok kaydını güncelle
                        $new_piece_quantity = $stock['piece_quantity'] + $piece_quantity;
                        $new_box_quantity = $stock['box_quantity'] + $box_quantity;
                        
                        $stmt_update = $pdo->prepare("
                            UPDATE inventory 
                            SET piece_quantity = ?, box_quantity = ?, updated_at = NOW()
                            WHERE bread_id = ?
                        ");
                        $stmt_update->execute([$new_piece_quantity, $new_box_quantity, $bread_id]);
                    } else {
                        // Yeni stok kaydı oluştur
                        $stmt_insert = $pdo->prepare("
                            INSERT INTO inventory 
                            (bread_id, piece_quantity, box_quantity, created_at, updated_at)
                            VALUES (?, ?, ?, NOW(), NOW())
                        ");
                        $stmt_insert->execute([$bread_id, $piece_quantity, $box_quantity]);
                    }
                }
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Inventory Rollback Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sipariş için fatura oluştur
 * 
 * @param int $order_id Sipariş ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array|bool Oluşturulan fatura bilgileri veya false
 */
function generateInvoice($order_id, $pdo) {
    try {
        // Sipariş bilgilerini al
        $stmt_order = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.bakery_name, u.address, u.phone, u.email, u.identity_number
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt_order->execute([$order_id]);
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return false;
        }
        
        // Sipariş kalemleri
        $stmt_items = $pdo->prepare("
            SELECT oi.*, bt.name as bread_name, bt.description as bread_description
            FROM order_items oi
            LEFT JOIN bread_types bt ON oi.bread_id = bt.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt_items->execute([$order_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        // Site ayarlarını al
        $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Fatura numarası oluştur
        $invoice_prefix = $settings['invoice_prefix'] ?? 'FTR-';
        $invoice_date = date('Y-m-d');
        $invoice_number = $invoice_prefix . date('Ymd') . '-' . $order_id;
        
        // Fatura PDF'i oluştur
        $invoice_dir = ROOT_PATH . '/uploads/invoices/' . date('Y/m');
        
        // Dizin kontrolü ve oluşturma
        if (!is_dir($invoice_dir)) {
            if (!mkdir($invoice_dir, 0775, true)) {
                error_log("Invoice directory creation failed: $invoice_dir");
                return false;
            }
        }
        
        $invoice_filename = 'fatura_' . $order['order_number'] . '_' . date('YmdHis') . '.pdf';
        $invoice_path = 'uploads/invoices/' . date('Y/m') . '/' . $invoice_filename;
        $invoice_full_path = $invoice_dir . '/' . $invoice_filename;
        
        // TCPDF ile PDF oluştur
        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        
 // PDF nesnesi oluştur
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// PDF meta bilgileri
$pdf->SetCreator('Ekmek Sipariş Sistemi');
$pdf->SetAuthor($settings['site_title'] ?? 'Ekmek Sipariş Sistemi');
$pdf->SetTitle('Fatura: ' . $invoice_number);
$pdf->SetSubject('Sipariş Faturası');

// Varsayılan başlık/altbilgi kaldırma
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Sayfa özellikleri
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Yeni sayfa ekle
$pdf->AddPage();

// Yazı tipi ayarla
$pdf->SetFont('dejavusans', '', 10);

// Logo ve başlık
$logo_path = ROOT_PATH . '/' . ($settings['logo'] ?? '');
if (!empty($settings['logo']) && file_exists($logo_path)) {
    $pdf->Image($logo_path, 15, 15, 40);
}

$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'FATURA', 0, 1, 'R');

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 6, 'Fatura No: ' . $invoice_number, 0, 1, 'R');
$pdf->Cell(0, 6, 'Tarih: ' . date('d.m.Y', strtotime($invoice_date)), 0, 1, 'R');
$pdf->Cell(0, 6, 'Sipariş No: ' . $order['order_number'], 0, 1, 'R');

// Satıcı bilgileri
$pdf->Ln(10);
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'Satıcı:', 0, 1);

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 6, $settings['site_title'] ?? 'Ekmek Sipariş Sistemi', 0, 1);
$pdf->MultiCell(0, 6, $settings['site_description'] ?? '', 0, 'L');
$pdf->Cell(0, 6, 'Tel: ' . ($settings['contact_phone'] ?? ''), 0, 1);
$pdf->Cell(0, 6, 'E-posta: ' . ($settings['contact_email'] ?? ''), 0, 1);

// Müşteri bilgileri
$pdf->Ln(5);
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'Müşteri:', 0, 1);

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 6, $order['bakery_name'], 0, 1);
$pdf->Cell(0, 6, $order['first_name'] . ' ' . $order['last_name'], 0, 1);
$pdf->MultiCell(0, 6, $order['address'], 0, 'L');
$pdf->Cell(0, 6, 'Tel: ' . $order['phone'], 0, 1);
$pdf->Cell(0, 6, 'E-posta: ' . $order['email'], 0, 1);
$pdf->Cell(0, 6, 'TC Kimlik / Vergi No: ' . $order['identity_number'], 0, 1);

// Ürün tablosu
$pdf->Ln(10);

// Tablo başlıkları
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('dejavusans', 'B', 10);

// Tablo sütun genişlikleri
$col_no = 10;
$col_product = 65;
$col_sale_type = 30;
$col_qty = 20;
$col_price = 25;
$col_total = 30;

$pdf->Cell($col_no, 8, 'No', 1, 0, 'C', true);
$pdf->Cell($col_product, 8, 'Ürün', 1, 0, 'L', true);
$pdf->Cell($col_sale_type, 8, 'Satış Tipi', 1, 0, 'C', true);
$pdf->Cell($col_qty, 8, 'Miktar', 1, 0, 'C', true);
$pdf->Cell($col_price, 8, 'Birim Fiyat', 1, 0, 'R', true);
$pdf->Cell($col_total, 8, 'Toplam', 1, 1, 'R', true);

// Ürün satırları
$pdf->SetFont('dejavusans', '', 10);
$counter = 1;
$total_amount = 0;

foreach ($items as $item) {
    $line_height = 8;
    
    // Satış tipi metni
    if ($item['sale_type'] === 'piece') {
        $sale_type_text = 'Adet';
        
        // Adet satışları
        $unit_price = $item['unit_price'];
        $total_price = $unit_price * $item['quantity'];
        $quantity_text = number_format($item['quantity'], 0, ',', '.');
        
        // Bilgileri bastır
        $pdf->Cell($col_no, $line_height, $counter++, 1, 0, 'C');
        $pdf->Cell($col_product, $line_height, $item['bread_name'], 1, 0, 'L');
        $pdf->Cell($col_sale_type, $line_height, $sale_type_text, 1, 0, 'C');
        $pdf->Cell($col_qty, $line_height, $quantity_text, 1, 0, 'C');
        $pdf->Cell($col_price, $line_height, number_format($unit_price, 2, ',', '.') . ' TL', 1, 0, 'R');
        $pdf->Cell($col_total, $line_height, number_format($total_price, 2, ',', '.') . ' TL', 1, 1, 'R');
        
    } elseif ($item['sale_type'] === 'box') {
        // Kasa satışları - daha düzgün bir görünüm için
        $pieces_per_box = $item['pieces_per_box'];
        $box_price = $item['unit_price'] * $pieces_per_box;
        $total_price = $box_price * $item['quantity'];
        
        // Satış tipi olarak daha açıklayıcı metin
        $sale_type_text = 'Kasa (' . number_format($pieces_per_box, 0, ',', '.') . ' adet)';
        
        // Sadece kasa sayısını miktar olarak göster
        $quantity_text = number_format($item['quantity'], 0, ',', '.');
        
        // Bilgileri bastır
        $pdf->Cell($col_no, $line_height, $counter++, 1, 0, 'C');
        $pdf->Cell($col_product, $line_height, $item['bread_name'], 1, 0, 'L');
        $pdf->Cell($col_sale_type, $line_height, $sale_type_text, 1, 0, 'C');
        $pdf->Cell($col_qty, $line_height, $quantity_text, 1, 0, 'C');
        $pdf->Cell($col_price, $line_height, number_format($box_price, 2, ',', '.') . ' TL', 1, 0, 'R');
        $pdf->Cell($col_total, $line_height, number_format($total_price, 2, ',', '.') . ' TL', 1, 1, 'R');
        
        // İsteğe bağlı: Toplam adet bilgisini ayrı bir satır olarak ekleyin
         // $total_pieces = $item['quantity'] * $pieces_per_box;
          //$pdf->SetFont('dejavusans', 'I', 8);
          //$pdf->Cell($col_no, 5, '', 0, 0, 'C');
         // $pdf->Cell($col_product + $col_sale_type + $col_qty + $col_price, 5, 
           //   '    → Toplam ' . number_format($total_pieces, 0, ',', '.') . ' adet ekmek (' . 
           //   number_format($item['quantity'], 0, ',', '.') . ' kasa × ' . 
           //   number_format($pieces_per_box, 0, ',', '.') . ' adet)', 
          //    0, 0, 'L');
         // $pdf->Cell($col_total, 5, '', 0, 1, 'R');
         // $pdf->SetFont('dejavusans', '', 10);
    } else {
        // Diğer satış tipleri
        $sale_type_text = $item['sale_type'];
        $unit_price = $item['unit_price'];
        $total_price = $unit_price * $item['quantity'];
        $quantity_text = number_format($item['quantity'], 0, ',', '.');
        
        // Bilgileri bastır
        $pdf->Cell($col_no, $line_height, $counter++, 1, 0, 'C');
        $pdf->Cell($col_product, $line_height, $item['bread_name'], 1, 0, 'L');
        $pdf->Cell($col_sale_type, $line_height, $sale_type_text, 1, 0, 'C');
        $pdf->Cell($col_qty, $line_height, $quantity_text, 1, 0, 'C');
        $pdf->Cell($col_price, $line_height, number_format($unit_price, 2, ',', '.') . ' TL', 1, 0, 'R');
        $pdf->Cell($col_total, $line_height, number_format($total_price, 2, ',', '.') . ' TL', 1, 1, 'R');
    }
    
    // Toplam tutarı güncelle
    $total_amount += $total_price;
}

// Toplam
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell($col_no + $col_product + $col_sale_type + $col_qty + $col_price, 8, 'TOPLAM:', 1, 0, 'R', true);
$pdf->Cell($col_total, 8, number_format($total_amount, 2, ',', '.') . ' TL', 1, 1, 'R', true);

// Alt bilgi
$pdf->Ln(10);
$pdf->SetFont('dejavusans', '', 10);
$pdf->MultiCell(0, 6, 'Bu fatura ' . date('d.m.Y H:i:s') . ' tarihinde elektronik olarak oluşturulmuştur.', 0, 'L');

//if (!empty($order['note'])) {
   // $pdf->Ln(5);
   // $pdf->SetFont('dejavusans', 'B', 10);
   // $pdf->Cell(0, 6, 'Sipariş Notu:', 0, 1);
    //$pdf->SetFont('dejavusans', '', 10);
    //$pdf->MultiCell(0, 6, $order['note'], 0, 'L');
//}

// PDF dosyasını kaydet
$pdf->Output($invoice_full_path, 'F');
        // Fatura bilgilerini veritabanına kaydet
        $stmt_invoice = $pdo->prepare("
            INSERT INTO invoices 
            (order_id, invoice_number, invoice_date, invoice_path, is_sent, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, NOW(), NOW())
        ");
        
        $stmt_invoice->execute([
            $order_id,
            $invoice_number,
            $invoice_date,
            $invoice_path
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Oluşturulan fatura bilgilerini döndür
        $invoice = [
            'id' => $invoice_id,
            'order_id' => $order_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'invoice_path' => $invoice_path,
            'is_sent' => 0,
            'sent_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $invoice;
        
    } catch (Exception $e) {
        error_log("Invoice Generation Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sipariş durumu değiştiğinde e-posta gönder
 * 
 * @param int $order_id Sipariş ID
 * @param string $old_status Eski durum
 * @param string $new_status Yeni durum
 * @param PDO $pdo Veritabanı bağlantısı
 * @return bool Gönderim başarılı mı?
 */
function sendOrderStatusUpdateEmail($order_id, $old_status, $new_status, $pdo) {
    try {
        // Sipariş bilgilerini al
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email, u.bakery_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order || empty($order['email'])) {
            return false;
        }
        
        // E-posta şablonunu al
        $stmt_template = $pdo->prepare("
            SELECT * FROM email_templates 
            WHERE template_key = 'order_status_update' AND is_active = 1
            LIMIT 1
        ");
        $stmt_template->execute();
        $template = $stmt_template->fetch(PDO::FETCH_ASSOC);
        
        // Durum metinlerini Türkçeleştir
        $status_texts = [
            'pending' => 'Beklemede',
            'processing' => 'İşleniyor',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal Edildi'
        ];
        
        $old_status_text = $status_texts[$old_status] ?? $old_status;
        $new_status_text = $status_texts[$new_status] ?? $new_status;
        
        $subject = 'Sipariş Durumu Güncellendi: #' . $order['order_number'];
        $body_content = '';

        if ($template) {
            // Şablondaki değişkenleri değiştir
            $subject = str_replace(
                ['{order_number}', '{old_status}', '{new_status}'],
                [$order['order_number'], $old_status_text, $new_status_text],
                $template['subject']
            );
            
            $body_content = str_replace(
                [
                    '{first_name}',
                    '{last_name}',
                    '{bakery_name}',
                    '{order_number}',
                    '{old_status}',
                    '{new_status}'
                ],
                [
                    $order['first_name'],
                    $order['last_name'],
                    $order['bakery_name'],
                    $order['order_number'],
                    $old_status_text,
                    $new_status_text
                ],
                $template['body']
            );
        } else {
            // Varsayılan içerik (Şablon yoksa)
            $body_content = '
            <p>Sayın ' . htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) . ',</p>
            <p><strong>#' . htmlspecialchars($order['order_number']) . '</strong> numaralı siparişinizin durumu güncellenmiştir.</p>
            
            <div class="info-box">
                <p style="margin: 5px 0;"><strong>Eski Durum:</strong> ' . htmlspecialchars($old_status_text) . '</p>
                <p style="margin: 5px 0;"><strong>Yeni Durum:</strong> <span style="font-weight: bold; color: #4e73df;">' . htmlspecialchars($new_status_text) . '</span></p>
            </div>
            
            <p>Sipariş detaylarını görüntülemek için aşağıdaki butona tıklayabilirsiniz.</p>';
        }
        
        // Standart şablonu uygula
        $order_url = BASE_URL . '/my/orders/view.php?id=' . $order_id;
        $final_body = getStandardEmailTemplate($subject, $body_content, 'Siparişi Görüntüle', $order_url);
        $plain_text = generatePlainTextFromHtml($final_body);
        
        // E-posta gönder
        if (sendEmail($order['email'], $subject, $final_body, $plain_text)) {
            // E-posta log kaydı (sendEmail içinde loglama yoksa buraya ekleyebiliriz ama sendEmail genellikle loglama yapmaz, dışarıda yapılır)
            // Ancak mevcut yapıda sendEmail loglama yapmıyor gibi görünüyor, bu yüzden loglamayı burada yapalım.
            // NOT: sendEmail fonksiyonu init.php içinde tanımlı ve loglama yapmıyor olabilir.
            // Orijinal kodda loglama vardı.
            
            $stmt_log = $pdo->prepare("
                INSERT INTO email_logs 
                (email_to, subject, body, template_id, related_id, related_type, status, sent_at, created_at)
                VALUES (?, ?, ?, ?, ?, 'order', 'sent', NOW(), NOW())
            ");
            
            $stmt_log->execute([
                $order['email'],
                $subject,
                $final_body,
                $template['id'] ?? null,
                $order_id
            ]);
            
            return true;
        } else {
            // Hata log kaydı
            $stmt_log = $pdo->prepare("
                INSERT INTO email_logs 
                (email_to, subject, body, template_id, related_id, related_type, status, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, 'order', 'failed', 'Mail sending failed', NOW())
            ");
            
            $stmt_log->execute([
                $order['email'],
                $subject,
                $final_body,
                $template['id'] ?? null,
                $order_id
            ]);
            
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Order Status Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sipariş durumunu güncellerken kullanılan durum güncelleme fonksiyonu
 */
function updateOrderStatus($order_id, $new_status, $note, $pdo) {
    try {
        // Önce mevcut siparişi ve durumunu kontrol et
        $order_check = $pdo->prepare("SELECT id, status, user_id FROM orders WHERE id = ?");
        $order_check->execute([$order_id]);
        $order = $order_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return ['success' => false, 'message' => 'Sipariş bulunamadı.'];
        }
        
        $old_status = $order['status'];
        $user_id = $order['user_id'];
        
        // İşlemi başlat
        $pdo->beginTransaction();
        
        // Sipariş durumunu güncelle
        $stmt_update = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt_update->execute([$new_status, $order_id]);
        
        if (!$result) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Sipariş durumu güncellenirken bir hata oluştu.'];
        }
        
        // Durum geçmişine kaydet
        $admin_id = $_SESSION['user_id'] ?? 1;
        $stmt_history = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt_history->execute([$order_id, $new_status, $note, $admin_id]);
        
        if (!$result) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Durum geçmişi kaydedilirken bir hata oluştu.'];
        }
        
        // E-posta bildirimi gönder
        sendOrderStatusUpdateEmail($order_id, $old_status, $new_status, $pdo);
        
        // Eğer durum "tamamlandı" ise, stok hareketlerini güncelle
        if ($new_status === 'completed') {
            updateInventoryForCompletedOrder($order_id, $pdo);
            
            // Otomatik fatura oluştur
            generateInvoice($order_id, $pdo);
        }
        
        // Eğer durum "iptal" ise, stok rezervasyonlarını geri al
        if ($new_status === 'cancelled') {
            rollbackInventoryForCancelledOrder($order_id, $pdo);
        }
        
        // İşlemi tamamla
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Sipariş durumu başarıyla güncellendi: ' . $new_status];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order Status Update Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()];
    }
}