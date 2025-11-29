<?php
/**
 * Yardımcı fonksiyonlar
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
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' TL';
}

/**
 * Tarihi formatlar
 * 
 * @param string $date Tarih
 * @return string Formatlanmış tarih
 */
function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
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
 */
function logActivity($user_id, $activity, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO user_activities (user_id, activity, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $activity]);
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
 * Şifreyi hasher
 * 
 * @param string $password Şifre
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Şifre doğrulama
 * 
 * @param string $password Şifre
 * @param string $hash Hash'lenmiş şifre
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}