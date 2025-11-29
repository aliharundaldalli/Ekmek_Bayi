<?php
// Dosya Yolu: /includes/show_messages.php
// Amaç: Session'da bulunan mesajları Bootstrap 5 alert olarak gösterir ve temizler.

// Oturumun başlatıldığından emin olalım (genellikle init.php'de yapılır)
if (session_status() === PHP_SESSION_NONE) {
    // Eğer init.php zaten session_start() yapıyorsa bu satıra gerek yok,
    // hatta "headers already sent" hatasına neden olabilir.
    // session_start();
}

// Gösterilecek mesaj türleri, session anahtarları, alert sınıfları ve ikonları
$message_types = [
    'success' => [
        'key' => 'success_message',         // Session değişkeninin adı
        'class' => 'alert-success',         // Bootstrap alert sınıfı
        'icon' => 'fas fa-check-circle'     // Font Awesome ikonu
    ],
    'error' => [
        'key' => 'error_message',
        'class' => 'alert-danger',
        'icon' => 'fas fa-exclamation-triangle' // Hata için daha uygun ikon
    ],
    'warning' => [
        'key' => 'warning_message',
        'class' => 'alert-warning',
        'icon' => 'fas fa-exclamation-circle'
    ],
    'info' => [
        'key' => 'info_message',
        'class' => 'alert-info',
        'icon' => 'fas fa-info-circle'
    ]
];

// Her mesaj türünü kontrol et ve varsa göster
foreach ($message_types as $type => $details) {
    $session_key = $details['key'];

    // Session değişkeni tanımlı ve boş değilse
    if (isset($_SESSION[$session_key]) && !empty(trim($_SESSION[$session_key]))) {
        // Mesajı al
        $message = $_SESSION[$session_key];

        // Alert HTML'ini oluştur ve göster
        echo '<div class="alert ' . htmlspecialchars($details['class']) . ' alert-dismissible fade show mb-3" role="alert" id="session-alert-' . htmlspecialchars($type) . '">';
        echo '  <i class="' . htmlspecialchars($details['icon']) . ' me-2"></i>';

        // Mesajın içeriğini güvenli bir şekilde yazdır
        // Eğer mesaj içinde <pre> tag'i varsa, onu koruyarak yazdır (detaylı hata logları için)
        if (strpos($message, '<pre>') !== false && strpos($message, '</pre>') !== false) {
            // <pre> öncesi ve sonrası metni ayır ve escape et
            $before_pre = htmlspecialchars(strstr($message, '<pre>', true), ENT_QUOTES, 'UTF-8');
            $pre_content = strstr($message, '<pre>');
            $after_pre = htmlspecialchars(substr(strstr($pre_content, '</pre>'), strlen('</pre>')), ENT_QUOTES, 'UTF-8');

            // <pre> içeriğini de escape etmek genellikle daha güvenlidir, ama okunabilirliği bozabilir.
            // Güvenliği artırmak için pre içeriğini de escape edebilirsiniz:
            // preg_match('/<pre>(.*?)<\/pre>/s', $pre_content, $matches);
            // $escaped_pre_content = isset($matches[1]) ? htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') : '';
            // echo nl2br($before_pre) . '<pre class="small bg-light p-2 rounded">' . $escaped_pre_content . '</pre>' . nl2br($after_pre);

            // Şimdilik <pre> içeriğini olduğu gibi bırakıyoruz (dikkatli kullanılmalı)
             echo nl2br($before_pre) . $pre_content . nl2br($after_pre);

        } else {
            // Normal mesajlar için nl2br ve htmlspecialchars kullan
            echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        }

        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';

        // ÖNEMLİ: Mesaj gösterildikten sonra session değişkenini temizle ki tekrar görünmesin.
        unset($_SESSION[$session_key]);
    }
}
?>