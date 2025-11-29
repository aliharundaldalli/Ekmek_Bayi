<?php
/**
 * Admin - Session Message Display Component
 * Bu dosya oturum üzerinden taşınan mesajları göstermek için kullanılır.
 * success_message: Başarı mesajları (yeşil)
 * error_message: Hata mesajları (kırmızı)
 * warning_message: Uyarı mesajları (sarı)
 * info_message: Bilgi mesajları (mavi)
 */

// Başarı mesajı
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-check-circle me-2"></i>' . $_SESSION['success_message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
    echo '</div>';
    unset($_SESSION['success_message']);
}

// Hata mesajı
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-exclamation-triangle me-2"></i>' . $_SESSION['error_message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
    echo '</div>';
    unset($_SESSION['error_message']);
}

// Uyarı mesajı
if (isset($_SESSION['warning_message'])) {
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['warning_message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
    echo '</div>';
    unset($_SESSION['warning_message']);
}

// Bilgi mesajı
if (isset($_SESSION['info_message'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
    echo '<i class="fas fa-info-circle me-2"></i>' . $_SESSION['info_message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
    echo '</div>';
    unset($_SESSION['info_message']);
}
?>