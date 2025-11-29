<?php
/**
 * Çıkış Sayfası
 */

// Temel yapılandırma dosyasını dahil et
require_once 'init.php';

// Kullanıcının giriş yapmış olduğunu kontrol et
if (isLoggedIn()) {
    // Kullanıcı aktivitesini kaydet
    logActivity($_SESSION['user_id'], 'Sistemden çıkış yapıldı', $pdo);
    
    // Oturumu temizle
    session_unset();
    session_destroy();
}

// Giriş sayfasına yönlendir
redirect('login.php?logout=1');