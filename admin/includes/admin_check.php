<?php
/**
 * Admin Yetki Kontrolü
 * 
 * Bu dosya admin sayfalarında yetki kontrolü yapmak için kullanılır.
 * Kullanıcı giriş yapmamışsa veya admin değilse, giriş sayfasına yönlendirilir.
 */

// Oturum başlatılmamışsa başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// functions.php dahil değilse dahil et
if (!function_exists('isLoggedIn')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
}

// Kullanıcı giriş yapmamışsa
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Lütfen önce giriş yapın.";
    header("Location: " . rtrim(BASE_URL, '/') . "/login.php");
    exit;
}

// Kullanıcı admin değilse
if (!isAdmin()) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    
    // Büfe kullanıcılarını kendi panellerine yönlendir
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user') {
        header("Location: " . rtrim(BASE_URL, '/') . "/my/index.php");
    } else {
        // Diğer durumlarda login sayfasına yönlendir
        header("Location: " . rtrim(BASE_URL, '/') . "/login.php");
    }
    exit;
}