<?php
/**
 * Veritabanı bağlantı ayarları (Örnek)
 * Bu dosyayı 'db.php' olarak kopyalayın ve kendi ayarlarınızı girin.
 */

// Veritabanı bağlantı bilgileri
$db_host = 'localhost';
$db_name = 'ekmek_bayi';
$db_user = 'root'; // Veritabanı kullanıcı adı
$db_pass = '';     // Veritabanı şifresi
$db_port = 3306;   // MySQL portu (MAMP için genellikle 8889, XAMPP için 3306)
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}
