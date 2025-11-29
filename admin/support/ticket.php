<?php
/**
 * Admin - Destek Talebi Detay (Chat Arayüzü)
 */

require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';

$ticket_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id <= 0) {
    $_SESSION['error_message'] = "Geçersiz talep ID.";
    redirect('index.php');
    exit;
}

// --- Veri Çekme ---
try {
    // Talep Detayı
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.bakery_name, u.email, u.phone,
               au.email as assigned_email, au.first_name as assigned_fn, au.last_name as assigned_ln
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users au ON t.assigned_to = au.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['error_message'] = "Talep bulunamadı.";
        redirect('index.php');
        exit;
    }

    // Yöneticileri Çek (Atama için)
    $admins = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('admin', 'moderator') ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Ticket Detail Error: " . $e->getMessage());
    redirect('index.php');
    exit;
}

// --- Mail Şablonlarını Hazırlayan Fonksiyon ---
function prepareEmailTemplate($type, $ticket, $data = []) {
    $ticket_url = BASE_URL . "admin/support/ticket.php?id=" . $ticket['id'];
    $customer_url = BASE_URL . "my/support/view.php?id=" . $ticket['id'];
    $ticket_number = htmlspecialchars($ticket['ticket_number']);
    $ticket_subject = htmlspecialchars($ticket['subject']);
    $customer_name = htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']);

    $title = '';
    $content = '';
    $buttonText = '';
    $buttonUrl = '';

    switch ($type) {
        case 'ticket_assigned':
            $title = 'Destek Talebi Atandı';
            $assigned_by = htmlspecialchars($data['assigned_by'] ?? 'Bir yönetici');
            $admin_name = htmlspecialchars($data['admin_name'] ?? '');
            $note = !empty($data['note']) ? '<div class="info-box"><strong>Not:</strong> ' . htmlspecialchars($data['note']) . '</div>' : '';
            
            $content = "
            <p>Sayın <strong>{$admin_name}</strong>,</p>
            <p>Size bir destek talebi atandı.</p>
            
            <div class='info-box'>
                <p><strong>Destek Talebi Numarası:</strong> #{$ticket_number}</p>
                <p><strong>Konu:</strong> {$ticket_subject}</p>
                <p><strong>Müşteri:</strong> {$customer_name}</p>
                <p><strong>Atayan:</strong> {$assigned_by}</p>
            </div>
            {$note}
            <p>Destek talebini incelemek için aşağıdaki butona tıklayabilirsiniz.</p>";
            
            $buttonText = 'Destek Talebini Görüntüle';
            $buttonUrl = $ticket_url;
            break;

        case 'status_change':
            $title = 'Destek Talebi Durumu Değişti';
            $new_status = $data['new_status'] ?? '';
            $note = !empty($data['note']) ? '<div class="info-box"><strong>Not:</strong> ' . htmlspecialchars($data['note']) . '</div>' : '';
            
            $status_map = [
                'resolved' => 'Çözüldü',
                'closed' => 'Kapatıldı',
                'in_progress' => 'Yeniden Açıldı / İşleme Alındı',
                'waiting' => 'Yanıtınız Bekleniyor'
            ];
            $status_text = $status_map[$new_status] ?? ucfirst($new_status);
            
            $content = "
            <p>Sayın <strong>{$customer_name}</strong>,</p>
            <p>Destek talebinizin durumu değiştirildi.</p>
            
            <div class='info-box'>
                <p><strong>Destek Talebi Numarası:</strong> #{$ticket_number}</p>
                <p><strong>Konu:</strong> {$ticket_subject}</p>
                <p><strong>Yeni Durum:</strong> <strong>{$status_text}</strong></p>
            </div>
            {$note}
            <p>Destek talebinizi görüntülemek için aşağıdaki butona tıklayabilirsiniz.</p>";
            
            $buttonText = 'Destek Talebini Görüntüle';
            $buttonUrl = $customer_url;
            break;

        case 'new_reply_to_customer':
            $title = 'Destek Talebinize Yanıt Verildi';
            $admin_name = htmlspecialchars($data['admin_name'] ?? 'Destek Ekibi');
            $message = nl2br(htmlspecialchars($data['message'] ?? ''));
            
            $content = "
            <p>Sayın <strong>{$customer_name}</strong>,</p>
            <p>Destek talebinize yanıt verildi.</p>
            
            <div class='info-box'>
                <p><strong>Destek Talebi Numarası:</strong> #{$ticket_number}</p>
                <p><strong>Konu:</strong> {$ticket_subject}</p>
                <p><strong>Yanıtlayan:</strong> {$admin_name}</p>
            </div>
            
            <div style='background-color: #f8f9fc; padding: 15px; border-radius: 5px; border-left: 4px solid #4e73df; margin-bottom: 20px;'>
                <p style='margin-top: 0; font-weight: bold; color: #4e73df;'>Mesaj:</p>
                <p style='margin-bottom: 0;'>{$message}</p>
            </div>
            
            <p>Destek talebinizi görüntülemek ve yanıt vermek için aşağıdaki butona tıklayabilirsiniz.</p>";
            
            $buttonText = 'Destek Talebine Yanıt Ver';
            $buttonUrl = $customer_url;
            break;
    }

    if (function_exists('getStandardEmailTemplate')) {
        return getStandardEmailTemplate($title, $content, $buttonText, $buttonUrl);
    } else {
        return "<html><body><h1>{$title}</h1>{$content}<br><a href='{$buttonUrl}'>{$buttonText}</a></body></html>";
    }
}

function prepareTextEmail($html) {
    return trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('&nbsp;', ' ', html_entity_decode($html)))));
}

// --- İşlemler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect("ticket.php?id=$ticket_id");
        exit;
    }

    
    // 1. Yanıt Ekleme
    if (isset($_POST['reply_message'])) {
        $message = trim($_POST['reply_message']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        $attachment = null;
        $attachment_data = null;

        // Dosya Yükleme Hazırlığı
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_name = uniqid() . '.' . $ext;
                $upload_dir = ROOT_PATH . '/uploads/support_attachments/'; // create.php ile aynı klasör
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $attachment_data = [
                        'file_name' => $filename,
                        'file_path' => '/uploads/support_attachments/' . $new_name,
                        'file_type' => $_FILES['attachment']['type'],
                        'file_size' => $_FILES['attachment']['size']
                    ];
                }
            }
        }

        if (!empty($message) || $attachment_data) {
            try {
                $pdo->beginTransaction();

                // Mesajı Kaydet (attachment kolonu yok, kaldırıldı)
                $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$ticket_id, $_SESSION['user_id'], $message, $is_internal]);
                $message_id = $pdo->lastInsertId();
                
                // Ek varsa kaydet
                if ($attachment_data && $message_id) {
                    $stmt_att = $pdo->prepare("INSERT INTO support_attachments (message_id, file_name, file_path, file_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt_att->execute([
                        $message_id,
                        $attachment_data['file_name'],
                        $attachment_data['file_path'],
                        $attachment_data['file_type'],
                        $attachment_data['file_size']
                    ]);
                }

                if (!$is_internal) {
                    $pdo->prepare("UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?")->execute([$ticket_id]);
                    
                    if (!empty($ticket['email'])) {
                        $email_data = ['admin_name' => $_SESSION['user_name'] ?? 'Destek Ekibi', 'message' => $message];
                        $email_subject = "Destek Talebinize Yanıt Verildi: #{$ticket['ticket_number']}";
                        $email_body = prepareEmailTemplate('new_reply_to_customer', $ticket, $email_data);
                        $email_text = prepareTextEmail($email_body);
                        if (function_exists('sendEmail')) sendEmail($ticket['email'], $email_subject, $email_body, $email_text);
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = "Yanıtınız gönderildi.";

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Reply Error: " . $e->getMessage());
                $_SESSION['error_message'] = "Mesaj gönderilemedi.";
            }
        }
        redirect("ticket.php?id=$ticket_id");
        exit;
    }

    // 2. Durum Güncelleme
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'];
        try {
            $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$new_status, $ticket_id]);
            $_SESSION['success_message'] = "Durum güncellendi.";
            
            if (!empty($ticket['email']) && $new_status != $ticket['status']) {
                $email_data = ['new_status' => $new_status];
                $email_subject = "Destek Talebinizin Durumu Değiştirildi: #{$ticket['ticket_number']}";
                $email_body = prepareEmailTemplate('status_change', $ticket, $email_data);
                $email_text = prepareTextEmail($email_body);
                if (function_exists('sendEmail')) sendEmail($ticket['email'], $email_subject, $email_body, $email_text);
            }

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Durum güncellenemedi.";
        }
        redirect("ticket.php?id=$ticket_id");
        exit;
    }

    // 3. Atama Yapma
    if (isset($_POST['assign_ticket'])) {
        $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        try {
            $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?")->execute([$assigned_to, $ticket_id]);
            $_SESSION['success_message'] = "Atama işlemi başarılı.";
            
            // Atanan kişiye mail at (Eğer varsa)
            // ... (Buraya atama maili eklenebilir)

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Atama yapılamadı.";
        }
        redirect("ticket.php?id=$ticket_id");
        exit;
    }
}

// --- Mesajları Çek ---
try {
    $stmt_msgs = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name, u.role, u.profile_image
        FROM support_messages m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE m.ticket_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt_msgs->execute([$ticket_id]);
    $messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

    // Mesajlara ait ekleri çek
    $message_ids = array_column($messages, 'id');
    $attachments_map = [];
    if (!empty($message_ids)) {
        $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
        $stmt_att = $pdo->prepare("SELECT * FROM support_attachments WHERE message_id IN ($placeholders)");
        $stmt_att->execute($message_ids);
        $all_attachments = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_attachments as $att) {
            $attachments_map[$att['message_id']][] = $att;
        }
    }

} catch (PDOException $e) {
    error_log("Messages Error: " . $e->getMessage());
    $messages = [];
}

$page_title = 'Talep #' . $ticket['ticket_number'];
include_once ROOT_PATH . '/admin/header.php';

$status_badges = [
    'open' => ['bg-danger', 'Açık'],
    'in_progress' => ['bg-primary', 'İşlemde'],
    'waiting' => ['bg-warning text-dark', 'Yanıt Bekliyor'],
    'resolved' => ['bg-success', 'Çözüldü'],
    'closed' => ['bg-secondary', 'Kapatıldı']
];
$current_status = $status_badges[$ticket['status']] ?? ['bg-secondary', $ticket['status']];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <span class="text-primary">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
            <span class="text-muted fs-5 ms-2">Talep Detayı</span>
        </h1>
        <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <div class="row">
        <!-- Sol: Sohbet Alanı -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-comments me-2"></i> Mesaj Geçmişi
                    </h6>
                    <span class="badge <?php echo $current_status[0]; ?>"><?php echo $current_status[1]; ?></span>
                </div>
                <div class="card-body bg-light" style="max-height: 600px; overflow-y: auto;">
                    
                    <!-- Mesajlar Döngüsü -->
                    <?php foreach ($messages as $msg): 
                        $is_admin = ($msg['role'] === 'admin');
                        $is_internal = $msg['is_internal'];
                        
                        if ($is_internal) {
                            $align_class = 'justify-content-center';
                            $bg_class = 'bg-warning bg-opacity-10 border border-warning text-dark';
                            $text_muted_class = 'text-muted';
                        } else {
                            $align_class = $is_admin ? 'flex-row-reverse' : '';
                            $bg_class = $is_admin ? 'bg-primary text-white' : 'bg-white text-dark border';
                            $text_muted_class = $is_admin ? 'text-white-50' : 'text-muted';
                        }
                        
                        $msg_attachments = $attachments_map[$msg['id']] ?? [];
                    ?>
                    <div class="d-flex mb-4 <?php echo $align_class; ?>">
                        <?php if (!$is_internal): ?>
                        <div class="flex-shrink-0">
                            <div class="avatar-circle <?php echo $is_admin ? 'bg-dark' : 'bg-primary'; ?> text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; overflow: hidden;">
                                <?php if (!empty($msg['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . $msg['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php if($is_admin): ?>
                                        <i class="fas fa-user-shield"></i>
                                    <?php else: ?>
                                        <?php echo mb_strtoupper(mb_substr($msg['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="<?php echo $is_internal ? 'w-75' : ($is_admin ? 'me-3 w-75' : 'ms-3 w-75'); ?>">
                            <div class="<?php echo $bg_class; ?> p-3 rounded shadow-sm position-relative">
                                <?php if ($is_internal): ?>
                                    <div class="position-absolute top-0 start-50 translate-middle badge bg-warning text-dark shadow-sm">
                                        <i class="fas fa-lock me-1"></i> Dahili Not
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="<?php echo ($is_admin && !$is_internal) ? 'text-white' : 'text-dark'; ?>">
                                        <?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                        <?php if($is_admin && !$is_internal) echo '<span class="badge bg-light text-dark ms-1" style="font-size: 0.6rem;">Yönetici</span>'; ?>
                                    </strong>
                                    <small class="<?php echo $text_muted_class; ?>"><?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></p>
                                
                                <?php if (!empty($msg_attachments)): ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <?php foreach ($msg_attachments as $att): 
                                            $file_url = BASE_URL . $att['file_path']; // file_path veritabanında /uploads/... şeklinde kayıtlı olmalı
                                            // Eğer create.php'de relative path kaydediliyorsa (örn: uploads/...), başına / eklemek gerekebilir.
                                            // create.php'de: $relative_path = str_replace(ROOT_PATH, '', $upload_dir) . $stored_filename;
                                            // Genelde /uploads/support_attachments/... şeklinde olur.
                                            
                                            $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                            <div class="mb-1">
                                                <?php if ($is_image): ?>
                                                    <a href="<?php echo $file_url; ?>" target="_blank">
                                                        <img src="<?php echo $file_url; ?>" alt="Ek" class="img-fluid rounded shadow-sm mt-1" style="max-height: 150px;">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo $file_url; ?>" target="_blank" class="text-decoration-none small <?php echo ($is_admin && !$is_internal) ? 'text-white' : 'text-primary'; ?>">
                                                        <i class="fas fa-paperclip me-1"></i>
                                                        <?php echo htmlspecialchars($att['file_name']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Yanıt Formu -->
                <div class="card-footer bg-white">
                    <?php if (in_array($ticket['status'], ['closed', 'resolved'])): ?>
                        <div class="alert alert-warning mb-0 text-center">
                            <i class="fas fa-lock me-1"></i> Bu talep kapatılmıştır. Yanıt vermek için durumu değiştirin.
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <?php $csrf_token = generateCSRFToken(); ?>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-gray-800">Yanıtınız</label>
                                <textarea name="reply_message" class="form-control" rows="4" placeholder="Mesajınızı buraya yazın..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Dosya Ekle (İsteğe Bağlı)</label>
                                <input type="file" name="attachment" class="form-control form-control-sm">
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="internalCheck">
                                    <label class="form-check-label text-muted small" for="internalCheck">
                                        <i class="fas fa-lock me-1"></i> Sadece Yöneticiler Görebilir (Dahili Not)
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Yanıtı Gönder
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sağ: Bilgi Paneli -->
        <div class="col-lg-4">
            <!-- Durum Güncelleme -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">İşlemler</h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="update_status" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Durum Değiştir</label>
                            <select name="status" class="form-select">
                                <?php foreach ($status_badges as $key => $val): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $ticket['status'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $val[1]; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-sync-alt me-1"></i> Güncelle
                        </button>
                    </form>

                    <hr>

                    <!-- Atama Formu -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="assign_ticket" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Atama Yap</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">-- Atanmamış --</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>" <?php echo $ticket['assigned_to'] == $admin['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm w-100 text-white">
                            <i class="fas fa-user-tag me-1"></i> Atamayı Kaydet
                        </button>
                    </form>
                </div>
            </div>

            <!-- Müşteri Bilgileri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Müşteri Bilgileri</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-circle bg-info text-white d-flex align-items-center justify-content-center rounded-circle me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                            <?php echo mb_strtoupper(mb_substr($ticket['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0 font-weight-bold text-dark"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($ticket['bakery_name']); ?></small>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">E-posta:</span>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['email']); ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Telefon:</span>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['phone']); ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Kayıt Tarihi:</span>
                            <span class="fw-bold text-dark"><?php echo date('d.m.Y', strtotime($ticket['created_at'])); ?></span>
                        </li>
                    </ul>
                    <div class="mt-3 text-center">
                        <a href="<?php echo BASE_URL; ?>admin/users/view.php?id=<?php echo $ticket['user_id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                            Profili Görüntüle
                        </a>
                    </div>
                </div>
            </div>

            <!-- Talep Detayları -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Talep Detayları</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="d-block text-uppercase text-muted fw-bold mb-1">Kategori</small>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['category']); ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="d-block text-uppercase text-muted fw-bold mb-1">Öncelik</small>
                        <?php if ($ticket['priority'] === 'high'): ?>
                            <span class="badge bg-danger">Yüksek</span>
                        <?php elseif ($ticket['priority'] === 'medium'): ?>
                            <span class="badge bg-warning text-dark">Orta</span>
                        <?php else: ?>
                            <span class="badge bg-success">Düşük</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <small class="d-block text-uppercase text-muted fw-bold mb-1">Oluşturulma</small>
                        <div class="text-dark"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . '/admin/footer.php'; ?>