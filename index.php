<?php
/**
 * Ana sayfa
 * Login sayfasına yönlendirme
 */

// Temel dosyaları dahil et
require_once 'includes/auth.php';

// Kullanıcı giriş yapmışsa yönlendir
if (isLoggedIn()) {
    redirect($_SESSION['dashboard']);
} else {
    // Giriş yapmamışsa login sayfasına yönlendir
    redirect('login.php');
}