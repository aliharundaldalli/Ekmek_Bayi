<?php
/**
 * Genel yapılandırma dosyası
 */

// Temel dosyaları dahil et
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone ayarı
date_default_timezone_set('Europe/Istanbul');

// Site ayarları
define('SITE_TITLE', getSetting('site_title', $pdo) ?: 'Ekmek Sipariş Sistemi');
define('SITE_DESCRIPTION', getSetting('site_description', $pdo) ?: 'Ekmek büfeleri için online sipariş ve yönetim sistemi');
define('SITE_LOGO', getSetting('logo', $pdo) ?: 'assets/images/logo.png');
define('SITE_FAVICON', getSetting('favicon', $pdo) ?: 'assets/images/favicon.ico');
define('SITE_CURRENCY', getSetting('currency', $pdo) ?: 'TL');
define('CONTACT_EMAIL', getSetting('contact_email', $pdo) ?: 'iletisim@ekmek.com');
define('CONTACT_PHONE', getSetting('contact_phone', $pdo) ?: '08505551234');

// Sipariş durumları
define('ORDER_STATUS', [
    'pending' => 'Beklemede',
    'confirmed' => 'Onaylandı',
    'preparing' => 'Hazırlanıyor',
    'ready' => 'Hazır',
    'delivered' => 'Teslim Edildi',
    'cancelled' => 'İptal Edildi'
]);

// Kullanıcı rolleri
define('USER_ROLES', [
    'admin' => 'Yönetici',
    'bakery' => 'Büfe'
]);

// Sistem durumu
define('SYSTEM_STATUS', [
    'open' => 'Açık (Sipariş Alınıyor)',
    'closed' => 'Kapalı (Sipariş Alınmıyor)'
]);

// Dosya yükleme dizini
define('UPLOAD_DIR', '../uploads/');

// Maksimum dosya boyutu (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// İzin verilen dosya tipleri
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf'
]);
