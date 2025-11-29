<?php
/**
 * Yardımcı fonksiyonlar
 * 
 * Bu dosya sadece yardımcı fonksiyonları içerir.
 * Kimlik doğrulama fonksiyonları auth.php dosyasında yer alır.
 */

/**
 * XSS koruması için girişleri temizler
 * 
 * @param string $data Temizlenecek veri
 * @return string Temizlenmiş veri
 */
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Rastgele token oluşturur
 * 
 * @param int $length Token uzunluğu
 * @return string Oluşturulan token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Para birimini formatlar
 * 
 * @param float $amount Tutar
 * @return string Formatlanmış tutar
 */
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return number_format($amount, 2, ',', '.') . ' TL';
    }
}

/**
 * Tarihi formatlar
 * 
 * @param string $date Tarih
 * @param bool $with_time Saat bilgisi eklensin mi?
 * @return string Formatlanmış tarih
 */
if (!function_exists('formatDate')) {
    function formatDate($date, $with_time = true) {
        if (empty($date)) return '-';
        $format = $with_time ? 'd.m.Y H:i' : 'd.m.Y';
        return date($format, strtotime($date));
    }
}

/**
 * Sistem ayarını getirir
 * 
 * @param string $key Ayar anahtarı
 * @param PDO $pdo PDO bağlantısı
 * @return mixed Ayar değeri
 */
function getSetting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : null;
}

/**
 * Kullanıcı aktivitesi kaydeder
 * 
 * @param int $user_id Kullanıcı ID
 * @param string $activity Aktivite
 * @param PDO $pdo PDO bağlantısı
 * @param int|null $related_id İlgili kayıt ID
 * @param string|null $related_type İlgili kayıt tipi
 * @param string|null $details İşlem detayları
 * @return bool İşlem başarılı mı?
 */
function logActivity($user_id, $activity, $pdo, $related_id = null, $related_type = null, $details = null) {
    try {
        // Kullanıcının IP adresini ve user agent bilgisini al
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Alanları kontrol et
        $has_related_fields = false;
        
        // Tabloyu kontrol et
        $stmt_check = $pdo->query("SHOW COLUMNS FROM user_activities");
        $columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('related_id', $columns) && in_array('related_type', $columns) && in_array('details', $columns)) {
            $has_related_fields = true;
        }
        
        if ($has_related_fields) {
            // Genişletilmiş tablo yapısı
            $stmt = $pdo->prepare("
                INSERT INTO user_activities 
                (user_id, activity_type, related_id, related_type, ip_address, user_agent, details, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$user_id, $activity, $related_id, $related_type, $ip_address, $user_agent, $details]);
        } else {
            // Eski tablo yapısı
            $stmt = $pdo->prepare("
                INSERT INTO user_activities 
                (user_id, activity_type, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$user_id, $activity, $ip_address, $user_agent]);
        }
        
        return true;
    } catch (Exception $e) {
        // Hatayı sessizce yönet - log kaydı kritik değil
        error_log('Günlük kaydı hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Hata mesajını gösterir
 * 
 * @param string $message Hata mesajı
 * @return string HTML formatında hata mesajı
 */
function showError($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

/**
 * Başarı mesajını gösterir
 * 
 * @param string $message Başarı mesajı 
 * @return string HTML formatında başarı mesajı
 */
function showSuccess($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

/**
 * Bilgi mesajını gösterir
 * 
 * @param string $message Bilgi mesajı
 * @return string HTML formatında bilgi mesajı
 */
function showInfo($message) {
    return '<div class="alert alert-info">' . $message . '</div>';
}

/**
 * Uyarı mesajını gösterir
 * 
 * @param string $message Uyarı mesajı
 * @return string HTML formatında uyarı mesajı
 */
function showWarning($message) {
    return '<div class="alert alert-warning">' . $message . '</div>';
}


/**
 * Yönlendirme yapar
 * 
 * @param string $url Yönlendirilecek URL
 * @return void
 */


/**
 * Metni güvenli hale getirir
 * 
 * @param string $str Güvenli hale getirilecek metin
 * @return string Güvenli metin
 */
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Sipariş durumunu Türkçe metne çevirir
 * 
 * @param string $status Durum
 * @return string Türkçe durum metni
 */
function getOrderStatusText($status) {
    $status_texts = [
        'pending' => 'Beklemede',
        'processing' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal Edildi'
    ];
    
    return $status_texts[$status] ?? $status;
}

/**
 * Sipariş durumuna göre badge rengini döndürür
 * 
 * @param string $status Durum
 * @return string Bootstrap badge sınıfı
 */
function getOrderStatusBadgeClass($status) {
    $badge_classes = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    return $badge_classes[$status] ?? 'badge-secondary';
}

/**
 * Satış tipini Türkçe metne çevirir
 * 
 * @param string $sale_type Satış tipi
 * @return string Türkçe satış tipi metni
 */
function getSaleTypeText($sale_type) {
    $sale_type_texts = [
        'piece' => 'Adet',
        'box' => 'Kasa'
    ];
    
    return $sale_type_texts[$sale_type] ?? $sale_type;
}

/**
 * Kullanıcı bilgilerini döndürür
 * 
 * @param int $user_id Kullanıcı ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array|bool Kullanıcı bilgileri veya false
 */
function getUserById($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("User Fetch Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ekmek bilgilerini döndürür
 * 
 * @param int $bread_id Ekmek ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array|bool Ekmek bilgileri veya false
 */
function getBreadById($bread_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bread_types WHERE id = ?");
        $stmt->execute([$bread_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Bread Fetch Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sipariş bilgilerini döndürür
 * 
 * @param int $order_id Sipariş ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array|bool Sipariş bilgileri veya false
 */
function getOrderById($order_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.bakery_name, u.phone, u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Order Fetch Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sipariş kalemlerini döndürür
 * 
 * @param int $order_id Sipariş ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array|bool Sipariş kalemleri veya false
 */
function getOrderItems($order_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, bt.name as bread_name, bt.description as bread_description
            FROM order_items oi
            LEFT JOIN bread_types bt ON oi.bread_id = bt.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Order Items Fetch Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Site ayarlarını döndürür
 * 
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Site ayarları
 */
function getSiteSettings($pdo) {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        error_log("Site Settings Fetch Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının siparişi görüntüleme yetkisi olup olmadığını kontrol eder
 * 
 * @param int $order_id Sipariş ID
 * @param int $user_id Kullanıcı ID
 * @param PDO $pdo Veritabanı bağlantısı
 * @return bool Yetki durumu
 */
function canViewOrder($order_id, $user_id, $pdo) {
    // Admin her zaman görüntüleyebilir
    if (isAdmin()) {
        return true;
    }
    
    // Kullanıcı kendi siparişini görüntüleyebilir
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order_user_id = $stmt->fetchColumn();
        
        return $order_user_id === $user_id;
    } catch (PDOException $e) {
        error_log("Order Permission Check Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kritik işlemler için CSRF token kontrolü
 *
 * @param string $token_name Token adı
 * @return string Oluşturulan token
 */
function generateCSRFToken($token_name = 'csrf_token') {
    if (!isset($_SESSION[$token_name])) {
        $_SESSION[$token_name] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$token_name];
}

/**
 * CSRF token doğrulama
 *
 * @param string $token Doğrulanacak token
 * @param string $token_name Token adı
 * @return bool Token doğru mu?
 */
function validateCSRFToken($token, $token_name = 'csrf_token') {
    if (!isset($_SESSION[$token_name]) || $token !== $_SESSION[$token_name]) {
        return false;
    }
    return true;
}

/**
 * Sipariş değişikliği yapılabilir mi kontrolü
 *
 * @param string $status Sipariş durumu
 * @return bool Değişiklik yapılabilir mi?
 */
function isOrderEditable($status) {
    // Sadece beklemede ve işleniyor durumlarında düzenlenebilir
    return in_array($status, ['pending', 'processing']);
}

/**
 * Sipariş iptal edilebilir mi kontrolü
 *
 * @param string $status Sipariş durumu
 * @return bool İptal edilebilir mi?
 */
function isOrderCancellable($status) {
    // Sadece beklemede ve işleniyor durumlarında iptal edilebilir
    return in_array($status, ['pending', 'processing']);
}

/**
 * Fatura durumunu metne çevirir
 * 
 * @param int $is_sent Gönderilme durumu
 * @return string Durum metni
 */
function getInvoiceStatusText($is_sent) {
    return $is_sent ? 'Gönderildi' : 'Gönderilmedi';
}

/**
 * Fatura durumuna göre badge rengini döndürür
 * 
 * @param int $is_sent Gönderilme durumu
 * @return string Bootstrap badge sınıfı
 */
function getInvoiceStatusBadgeClass($is_sent) {
    return $is_sent ? 'badge-success' : 'badge-warning';
}