<?php
/**
 * Büfe Paneli - Destek Talebi Detayı
 * Modern ve profesyonel arayüz
 */

require_once '../../init.php';

if (!isset($_SESSION['user_id'])) { redirect(BASE_URL . 'login.php'); exit; }

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) { redirect('index.php'); exit; }

// --- Talebi Getir ---
try {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['error_message'] = "Talep bulunamadı.";
        redirect('index.php');
        exit;
    }

// --- Mesajları Getir ---
    $stmt_msgs = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name, u.role, u.profile_image
        FROM support_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.ticket_id = ? AND m.is_internal = 0
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
    error_log("Ticket View Error: " . $e->getMessage());
    redirect('index.php');
    exit;
}

// --- İşlemler (Yanıt Ekleme) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect('view.php?id=' . $ticket_id);
        exit;
    }

    $message = trim($_POST['reply_message']);
    $attachment_data = null;

    // Dosya Yükleme
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

            // Mesajı Kaydet
            $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_internal, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$ticket_id, $user_id, $message]);
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
            
            // Talebi 'open' veya 'in_progress' yap (Admin görsün diye)
            if ($ticket['status'] == 'waiting') {
                $pdo->prepare("UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?")->execute([$ticket_id]);
            } else {
                $pdo->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticket_id]);
            }

            // Admin Bildirimi (Opsiyonel)
            // ...

            $pdo->commit();
            $_SESSION['success_message'] = "Yanıtınız gönderildi.";
            redirect("view.php?id=$ticket_id");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Reply Error: " . $e->getMessage());
            $_SESSION['error_message'] = "Mesaj gönderilemedi.";
        }
    }
}

// Helper: Durum Badge
function getStatusBadge($status) {
    $badges = [
        'open' => ['class' => 'bg-warning text-dark', 'text' => 'Yeni'],
        'in_progress' => ['class' => 'bg-info text-white', 'text' => 'İşlemde'],
        'waiting' => ['class' => 'bg-primary', 'text' => 'Yanıt Bekleniyor'],
        'resolved' => ['class' => 'bg-success', 'text' => 'Çözüldü'],
        'closed' => ['class' => 'bg-secondary', 'text' => 'Kapatıldı'],
    ];
    $b = $badges[$status] ?? ['class' => 'bg-light text-dark', 'text' => $status];
    return "<span class='badge {$b['class']}'>{$b['text']}</span>";
}

$page_title = 'Talep #' . $ticket['ticket_number'];
include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">

    <!-- Başlık -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <a href="index.php" class="text-gray-500 hover:text-gray-700 me-2"><i class="fas fa-arrow-left"></i></a>
            Talep #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
        </h1>
        <div>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <a href="close.php?id=<?php echo $ticket_id; ?>" class="btn btn-danger btn-sm me-2">
                <i class="fas fa-times-circle me-1"></i> Talebi Kapat
            </a>
            <?php endif; ?>
            <?php echo getStatusBadge($ticket['status']); ?>
        </div>
    </div>

    <div class="row">
        <!-- Sol Kolon: Mesajlaşma -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Konuşma Geçmişi</h6>
                </div>
                <div class="card-body bg-light" style="max-height: 600px; overflow-y: auto;">
                    
                    <!-- İlk Mesaj (Talep Oluşturma) -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px;">
                                <?php echo strtoupper(mb_substr($_SESSION['user_name'] ?? 'B', 0, 1)); ?>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="bg-white p-3 rounded shadow-sm border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-primary">Siz</strong>
                                    <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($ticket['subject'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($messages as $msg): 
                        $is_me = ($msg['user_id'] == $user_id);
                        $sender_name = $is_me ? 'Siz' : htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']);
                        $avatar_bg = $is_me ? 'bg-primary' : 'bg-success';
                        $align_class = $is_me ? '' : 'flex-row-reverse'; 
                        $container_class = $is_me ? 'justify-content-end' : '';
                        $msg_bg = $is_me ? 'bg-primary text-white' : 'bg-white border';
                        $text_color = $is_me ? 'text-white' : 'text-dark';
                        $meta_color = $is_me ? 'text-white-50' : 'text-muted';
                        
                        $msg_attachments = $attachments_map[$msg['id']] ?? [];
                    ?>
                    <div class="d-flex mb-4 <?php echo $container_class; ?>">
                        <?php if (!$is_me): ?>
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-circle <?php echo $avatar_bg; ?> text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; overflow: hidden;">
                                <?php if (!empty($msg['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . $msg['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo strtoupper(mb_substr($msg['first_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="<?php echo $is_me ? 'ms-3' : ''; ?>" style="max-width: 75%;">
                            <div class="<?php echo $msg_bg; ?> p-3 rounded shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="<?php echo $is_me ? 'text-white' : 'text-success'; ?> me-2"><?php echo $sender_name; ?></strong>
                                    <small class="<?php echo $meta_color; ?>"><?php echo date('d.m H:i', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 <?php echo $text_color; ?>"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                
                                <?php if (!empty($msg_attachments)): ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <?php foreach ($msg_attachments as $att): 
                                            $file_url = BASE_URL . $att['file_path'];
                                            $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                            <div class="mb-1">
                                                <?php if ($is_image): ?>
                                                    <a href="<?php echo $file_url; ?>" target="_blank">
                                                        <img src="<?php echo $file_url; ?>" alt="Ek" class="img-fluid rounded shadow-sm mt-1" style="max-height: 150px;">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo $file_url; ?>" target="_blank" class="text-decoration-none small <?php echo $is_me ? 'text-white' : 'text-primary'; ?>">
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

                        <?php if ($is_me): ?>
                        <div class="flex-shrink-0 ms-3">
                            <div class="avatar-circle <?php echo $avatar_bg; ?> text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; overflow: hidden;">
                                <?php if (!empty($msg['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . $msg['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo strtoupper(mb_substr($_SESSION['user_name'] ?? 'B', 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                </div>
                
                <?php if (!in_array($ticket['status'], ['resolved', 'closed'])): ?>
                <div class="card-footer bg-white">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <?php $csrf_token = generateCSRFToken(); ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-3">
                            <label for="reply_message" class="form-label fw-bold text-gray-700">Yanıtınız</label>
                            <textarea class="form-control" id="reply_message" name="reply_message" rows="4" placeholder="Mesajınızı buraya yazın..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Dosya Ekle (İsteğe Bağlı)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Gönder
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="card-footer bg-light text-center py-3">
                    <span class="text-muted"><i class="fas fa-lock me-1"></i> Bu talep kapatılmıştır, yanıt verilemez.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sağ Kolon: Bilgiler -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Talep Bilgileri</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted ps-0" style="width: 100px;">Kategori:</td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['category']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Öncelik:</td>
                            <td>
                                <?php 
                                    $p_badges = [
                                        'low' => '<span class="badge bg-success">Düşük</span>',
                                        'medium' => '<span class="badge bg-warning text-dark">Orta</span>',
                                        'high' => '<span class="badge bg-danger">Yüksek</span>',
                                        'urgent' => '<span class="badge bg-danger">Acil</span>',
                                    ];
                                    echo $p_badges[$ticket['priority']] ?? $ticket['priority'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Oluşturma:</td>
                            <td class="text-dark"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Son İşlem:</td>
                            <td class="text-dark"><?php echo date('d.m.Y H:i', strtotime($ticket['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Yardım Kutusu -->
            <div class="card shadow mb-4 border-left-info">
                <div class="card-body">
                    <h6 class="fw-bold text-info mb-2"><i class="fas fa-info-circle me-1"></i> Bilgi</h6>
                    <p class="small mb-0 text-muted">
                        Destek talepleriniz en kısa sürede yanıtlanacaktır. Acil durumlarda telefon ile iletişime geçebilirsiniz.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include_once ROOT_PATH . '/my/footer.php'; ?>