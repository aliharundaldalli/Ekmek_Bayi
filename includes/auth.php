<?php
/**
 * Kimlik doğrulama ve oturum işlemleri
 * 
 * Bu dosya kimlik doğrulama ile ilgili tüm fonksiyonları içerir.
 */

/**
 * Kullanıcının giriş yapmış olup olmadığını kontrol eder
 * 
 * @return bool
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
    }
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 * 
 * @return bool
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }
}

/**
 * Kullanıcının büfe kullanıcısı olup olmadığını kontrol eder
 * 
 * @return bool
 */
if (!function_exists('isBakery')) {
    function isBakery() {
        return isLoggedIn() && $_SESSION['user_role'] === 'bakery';
    }
}

/**
 * Kullanıcı oturumunu başlatır
 * 
 * @param array $user Kullanıcı bilgileri
 */
if (!function_exists('setUserSession')) {
    function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['bakery_name'] = $user['bakery_name'] ?? '';
        $_SESSION['is_logged_in'] = true;
        
        // Kullanıcı rolüne göre dashboard yönlendirmesi
        if($user['role'] == 'admin') {
            $_SESSION['dashboard'] = 'admin/index.php';
        } else {
            $_SESSION['dashboard'] = 'my/index.php';
        }
    }
}

/**
 * Yönlendirme yapar
 * 
 * @param string $url Yönlendirilecek URL
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

/**
 * Şifreyi hasher
 * 
 * @param string $password Şifre
 * @return string
 */
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

/**
 * Şifre doğrulama
 * 
 * @param string $password Şifre
 * @param string $hash Hash'lenmiş şifre
 * @return bool
 */
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}