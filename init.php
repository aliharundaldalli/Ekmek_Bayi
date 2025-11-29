<?php
/**
 * Temel yapılandırma ve gerekli dosyaları dahil eden başlangıç dosyası
 */

// Oturum daha önce başlatılmamışsa başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kök dizin yolunu tanımla - bu dosya sistemi yoludur (require için kullanılır)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', ROOT_PATH . '/assets');
}

// Web kök URL'sini belirle - bu web tarayıcı yoludur (link ve kaynaklar için kullanılır)
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $root_path = str_replace('\\', '/', ROOT_PATH);
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $sub_dir = str_replace($doc_root, '', $root_path);
    define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $sub_dir . '/');
}

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone ayarı
date_default_timezone_set('Europe/Istanbul');

// Temel fonksiyon ve bağlantı dosyalarını dahil et
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/email_templates.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';

// Composer autoload - PHPMailer için gerekli
require_once ROOT_PATH . '/vendor/autoload.php';

// Eğer site ayarları tanımlı değilse
if (!defined('SITE_TITLE')) {
    // Site ayarları
    define('SITE_TITLE', 'Ekmek Sipariş Sistemi');
    define('SITE_DESCRIPTION', 'Ekmek büfeleri için online sipariş ve yönetim sistemi');
    define('SITE_LOGO', 'assets/images/logo.png');
    define('SITE_FAVICON', 'assets/images/favicon.ico');
    define('SITE_CURRENCY', 'TL');
    define('CONTACT_EMAIL', 'iletisim@ekmek.com');
    define('CONTACT_PHONE', '08505551234');
}

// Sipariş durumları
if (!defined('ORDER_STATUS')) {
    define('ORDER_STATUS', [
        'pending' => 'Beklemede',
        'confirmed' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'ready' => 'Hazır',
        'delivered' => 'Teslim Edildi',
        'cancelled' => 'İptal Edildi'
    ]);
}

// Kullanıcı rolleri
if (!defined('USER_ROLES')) {
    define('USER_ROLES', [
        'admin' => 'Yönetici',
        'bakery' => 'Büfe'
    ]);
}

// Sistem durumu
if (!defined('SYSTEM_STATUS')) {
    define('SYSTEM_STATUS', [
        'open' => 'Açık (Sipariş Alınıyor)',
        'closed' => 'Kapalı (Sipariş Alınmıyor)'
    ]);
}

// Dosya yükleme dizini
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', ROOT_PATH . '/uploads/');
}

// Maksimum dosya boyutu (5MB)
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024);
}

// İzin verilen dosya tipleri
if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf'
    ]);
}

// PHPMailer sınıflarını yükle
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * SMTP Ayarlarını veritabanından getir
 * 
 * @return array|null SMTP ayarları veya başarısız olursa null
 */
function getMailSettings() {
    global $pdo;
    $smtp_settings = null;
    
    try {
        // Önce smtp_settings tablosundan almayı dene
        $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
        $smtp_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($smtp_data) {
            $smtp_settings = [
                'smtp_status' => $smtp_data['status'],
                'smtp_host' => $smtp_data['host'],
                'smtp_port' => $smtp_data['port'],
                'smtp_username' => $smtp_data['username'],
                'smtp_password' => $smtp_data['password'],
                'smtp_encryption' => $smtp_data['encryption'],
                'smtp_from_email' => $smtp_data['from_email'],
                'smtp_from_name' => $smtp_data['from_name']
            ];
            return $smtp_settings;
        }
        
        // Eğer smtp_settings'den alınamadıysa site_settings'den almayı dene
        $stmt = $pdo->query("
            SELECT setting_value FROM site_settings WHERE setting_key IN (
                'smtp_status', 'smtp_host', 'smtp_port', 'smtp_username', 
                'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
            )
        ");
        $site_smtp = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($site_smtp)) {
            return $site_smtp;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("SMTP ayarları veritabanından alınamadı: " . $e->getMessage());
        return null;
    }
}


/**
 * E-posta gönderimi için PHPMailer kullanarak mail gönderen fonksiyon
 * Hem smtp_settings hem de site_settings uyumlu
 * 
 * @param string|array $to Alıcı e-posta adresi veya adresleri
 * @param string $subject E-posta konusu
 * @param string $htmlBody E-postanın HTML içeriği
 * @param string $altBody Opsiyonel düz metin içerik
 * @param array $settings Uygulama ayarları dizisi
 * @param array $attachments Opsiyonel ekler dizisi [['path' => 'dosya/yolu.pdf', 'name' => 'Dosya Adı.pdf']]
 * @return bool Başarılı ise true, başarısız ise false
 */
function sendEmail($to, $subject, $htmlBody, $altBody = '', $settings = [], $attachments = []) {
    // Boş ayarlar verilirse, veritabanından almayı dene
    if (empty($settings)) {
        $settings = getMailSettings();
        if (!$settings) {
            error_log("E-posta gönderimi başarısız: SMTP ayarları veritabanından alınamadı.");
            return false;
        }
    }
    
    // Her iki tablo formatını destekle
    $smtp_status = $settings['smtp_status'] ?? $settings['status'] ?? 0;
    $smtp_host = $settings['smtp_host'] ?? $settings['host'] ?? '';
    $smtp_port = $settings['smtp_port'] ?? $settings['port'] ?? 587;
    $smtp_username = $settings['smtp_username'] ?? $settings['username'] ?? '';
    $smtp_password = $settings['smtp_password'] ?? $settings['password'] ?? '';
    $smtp_encryption = $settings['smtp_encryption'] ?? $settings['encryption'] ?? 'tls';
    $smtp_from_email = $settings['smtp_from_email'] ?? $settings['from_email'] ?? '';
    $smtp_from_name = $settings['smtp_from_name'] ?? $settings['from_name'] ?? 'Destek Sistemi';
    
    // SMTP etkin ve gerekli ayarlar sağlanmış mı kontrol et
    if (empty($smtp_status) || $smtp_status != 1 || 
        empty($smtp_host) || empty($smtp_username) || 
        empty($smtp_from_email)) {
        
        error_log("E-posta gönderimi başarısız: SMTP devre dışı veya önemli ayarlar eksik.");
        return false;
    }

    $mail = new PHPMailer(true); // İstisnaları etkinleştir

    try {
        // Sunucu ayarları
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Hata ayıklama kapalı
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        
        // Şifreleme yöntemini belirle
        $smtp_encryption = strtolower($smtp_encryption);
        if ($smtp_encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL için sabit kullan
        } elseif ($smtp_encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS için sabit kullan
        } else {
            $mail->SMTPSecure = false; // Şifreleme yok
            $mail->SMTPAutoTLS = false; // Otomatik TLS'i devre dışı bırak
        }
        
        $mail->Port = intval($smtp_port);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Gönderen Adresi
        $mail->setFrom($smtp_from_email, $smtp_from_name);

        // Alıcılar
        if (is_array($to)) {
            foreach ($to as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress(trim($recipient));
                }
            }
        } elseif (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress(trim($to));
        } else {
            throw new Exception('Geçersiz alıcı e-posta adresi sağlandı: ' . $to);
        }

        // En az bir geçerli alıcının eklendiğinden emin ol
        if (empty($mail->getAllRecipientAddresses())) {
            throw new Exception('Hiçbir geçerli alıcı e-posta adresi eklenmedi.');
        }

        // Ekleri işle
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $filename = isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $filename);
                }
            }
        }

        // İçerik
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        
        // Düz metin sürümü sağlanmamışsa oluştur
        $mail->AltBody = !empty($altBody) ? $altBody : html_entity_decode(strip_tags($htmlBody));

        // Cevap adresi ayarla (varsa)
        $reply_to = $settings['smtp_reply_to'] ?? '';
        if (!empty($reply_to)) {
            $mail->addReplyTo($reply_to, $smtp_from_name);
        }

        // E-postayı gönder
        $mail->send();
        
        // Gönderim başarılı ise loglama yap
        $recipient_log = is_array($to) ? implode(', ', $to) : $to;
        $log_message = date('[Y-m-d H:i:s] ') . "SUCCESS: Email sent to [{$recipient_log}], Subject [{$subject}]\n";
        file_put_contents(ROOT_PATH . '/debug_log.txt', $log_message, FILE_APPEND);
        error_log("E-posta başarıyla gönderildi: Alıcı [{$recipient_log}], Konu [{$subject}]");
        
        return true;
    } catch (Exception $e) {
        // Hata detaylarını log dosyasına kaydet
        $recipient_log = is_array($to) ? implode(', ', $to) : $to;
        $error_msg = "ERROR: Email failed to [{$recipient_log}], Subject [{$subject}]. Mailer Error: {$mail->ErrorInfo}";
        $log_message = date('[Y-m-d H:i:s] ') . $error_msg . "\n";
        file_put_contents(ROOT_PATH . '/debug_log.txt', $log_message, FILE_APPEND);
        error_log("E-posta gönderilemedi: Alıcı [{$recipient_log}], Konu [{$subject}]. Mailer Hatası: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Düz metin e-posta içeriği oluştur
 * 
 * @param string $html HTML içeriği
 * @return string Düz metin içeriği
 */
function generatePlainTextFromHtml($html) {
    // HTML içeriğini düz metne çevir
    $text = strip_tags($html);
    $text = str_replace('&nbsp;', ' ', $text);
    $text = html_entity_decode($text);
    
    // Gereksiz boşlukları temizle
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\s*\n\s*/', "\n", $text);
    $text = preg_replace('/\s*\n\n\s*/', "\n\n", $text);
    
    return trim($text);
}

/**
 * Site ayarlarını veritabanından yükle
 */
$settings = []; // Ayarları tutacak dizi
try {
    // $pdo değişkeninin db.php içinde tanımlandığını varsayıyoruz
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Anahtar-değer çifti olarak al

        if ($db_settings) {
            $settings = $db_settings; // Veritabanı ayarlarını $settings dizisine ata
            
            // E-posta şablonları için gerekli varsayılan ayarlar
            if (empty($settings['site_title'])) {
                $settings['site_title'] = SITE_TITLE;
            }
        } else {
            error_log("site_settings tablosundan ayarlar çekilemedi veya tablo boş.");
            // Varsayılan değerleri ayarla
            $settings['site_title'] = SITE_TITLE;
        }
    } else {
        throw new Exception("PDO veritabanı bağlantı nesnesi bulunamadı.");
    }
} catch (PDOException $e) {
    error_log("Veritabanından ayarlar çekilirken PDO hatası: " . $e->getMessage());
    // Kritik hata durumunda varsayılan ayarlar
    $settings['site_title'] = SITE_TITLE;
} catch (Exception $e) {
    error_log("Ayarlar çekilirken genel hata: " . $e->getMessage());
    // Genel hata durumunda varsayılan ayarlar
    $settings['site_title'] = SITE_TITLE;
}

?>